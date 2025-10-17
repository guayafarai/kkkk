<?php
/**
 * SISTEMA DE VENTAS DE PRODUCTOS - Versión 6.0 CENTRALIZADA
 * Migrado al sistema de componentes unificado
 * Moneda: Soles (S/)
 * 
 * CAMBIOS EN ESTA VERSIÓN:
 * - Usa includes/components.php para cargar todo
 * - Usa renderSharedStyles() y renderNavbar()
 * - Componentes reutilizables (renderStatCard, renderAlert, etc.)
 * - Sistema de estilos centralizado con variables CSS
 * - Mantiene toda la lógica de negocio
 * - JavaScript inline optimizado
 */

// ==========================================
// CARGAR DEPENDENCIAS EN ORDEN CORRECTO
// ==========================================

// 1. Cargar .env primero
require_once __DIR__ . '/../includes/dotenv.php';
$dotenv = SimpleDotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// 2. Cargar base de datos
require_once __DIR__ . '/../config/database.php';

// 3. Cargar error handler
require_once __DIR__ . '/../includes/error_handler.php';

// 4. Cargar autenticación
require_once __DIR__ . '/../includes/auth.php';

// 5. Cargar componentes (esto carga automáticamente styles.php y navbar_unified.php)
require_once __DIR__ . '/../includes/components.php';

// ==========================================
// SEGURIDAD Y SESIÓN
// ==========================================
setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit();
}

$db = getDB();

// ==========================================
// PROCESAMIENTO AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'search_products':
                $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
                
                $where_conditions = ['p.activo = 1', 's.cantidad_actual > 0'];
                $params = [];
                
                if (!empty($search)) {
                    $where_conditions[] = "(p.nombre LIKE ? OR p.codigo_producto LIKE ? OR p.marca LIKE ? OR p.modelo_compatible LIKE ? OR c.nombre LIKE ?)";
                    $search_param = "%$search%";
                    $params = array_fill(0, 5, $search_param);
                }
                
                if (!hasPermission('admin')) {
                    $where_conditions[] = "s.tienda_id = ?";
                    $params[] = $user['tienda_id'];
                }
                
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                
                $query = "
                    SELECT p.*, c.nombre as categoria_nombre, s.cantidad_actual, s.tienda_id, t.nombre as tienda_nombre
                    FROM productos p
                    LEFT JOIN categorias_productos c ON p.categoria_id = c.id
                    LEFT JOIN stock_productos s ON p.id = s.producto_id
                    LEFT JOIN tiendas t ON s.tienda_id = t.id
                    $where_clause
                    ORDER BY p.nombre
                    LIMIT 50
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'products' => $products]);
                break;
                
            case 'register_product_sale':
                $producto_id = intval($_POST['producto_id']);
                $tienda_id = intval($_POST['tienda_id']);
                $cantidad = intval($_POST['cantidad']);
                $precio_unitario = floatval($_POST['precio_unitario']);
                $descuento = floatval($_POST['descuento']) ?: 0;
                $cliente_nombre = sanitize($_POST['cliente_nombre']);
                $cliente_telefono = sanitize($_POST['cliente_telefono']);
                $cliente_email = sanitize($_POST['cliente_email']);
                $metodo_pago = $_POST['metodo_pago'];
                $notas = sanitize($_POST['notas']);
                
                if (!hasPermission('admin') && $tienda_id != $user['tienda_id']) {
                    throw new Exception('Sin permisos para vender en esta tienda');
                }
                
                if ($cantidad <= 0) {
                    throw new Exception('La cantidad debe ser mayor a cero');
                }
                
                if ($precio_unitario <= 0) {
                    throw new Exception('El precio debe ser mayor a cero');
                }
                
                $stock_stmt = $db->prepare("
                    SELECT cantidad_actual FROM stock_productos 
                    WHERE producto_id = ? AND tienda_id = ?
                ");
                $stock_stmt->execute([$producto_id, $tienda_id]);
                $stock_data = $stock_stmt->fetch();
                
                if (!$stock_data || $stock_data['cantidad_actual'] < $cantidad) {
                    throw new Exception('Stock insuficiente para realizar la venta');
                }
                
                $precio_total = ($precio_unitario * $cantidad) - $descuento;
                
                if ($precio_total < 0) {
                    throw new Exception('El descuento no puede ser mayor al total');
                }
                
                $db->beginTransaction();
                
                $venta_stmt = $db->prepare("
                    INSERT INTO ventas_productos (producto_id, tienda_id, vendedor_id, cantidad, 
                                                precio_unitario, precio_total, descuento, cliente_nombre, 
                                                cliente_telefono, cliente_email, metodo_pago, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $venta_stmt->execute([
                    $producto_id, $tienda_id, $user['id'], $cantidad,
                    $precio_unitario, $precio_total, $descuento, $cliente_nombre,
                    $cliente_telefono, $cliente_email, $metodo_pago, $notas
                ]);
                
                if (!$result) {
                    throw new Exception('Error al registrar la venta');
                }
                
                $venta_id = $db->lastInsertId();
                
                $db->commit();
                
                logActivity($user['id'], 'product_sale', 
                    "Venta de producto - ID: $producto_id, Cantidad: $cantidad, Total: S/$precio_total");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Venta registrada correctamente',
                    'venta_id' => $venta_id
                ]);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch(Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        logError("Error en venta de productos: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// OBTENER DATOS PARA LA VISTA
// ==========================================
$productos_disponibles = [];
$ventas_recientes = [];
$estadisticas_hoy = ['ventas_hoy' => 0, 'ingresos_hoy' => 0, 'unidades_vendidas_hoy' => 0];

try {
    // Obtener productos disponibles
    if (hasPermission('admin')) {
        $productos_query = "
            SELECT p.*, c.nombre as categoria_nombre, s.cantidad_actual, s.tienda_id, t.nombre as tienda_nombre
            FROM productos p
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id
            LEFT JOIN stock_productos s ON p.id = s.producto_id
            LEFT JOIN tiendas t ON s.tienda_id = t.id
            WHERE p.activo = 1 AND s.cantidad_actual > 0
            ORDER BY p.nombre, t.nombre
            LIMIT 20
        ";
        $productos_stmt = $db->query($productos_query);
    } else {
        $productos_query = "
            SELECT p.*, c.nombre as categoria_nombre, s.cantidad_actual, s.tienda_id, t.nombre as tienda_nombre
            FROM productos p
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id
            LEFT JOIN stock_productos s ON p.id = s.producto_id
            LEFT JOIN tiendas t ON s.tienda_id = t.id
            WHERE p.activo = 1 AND s.cantidad_actual > 0 AND s.tienda_id = ?
            ORDER BY p.nombre
            LIMIT 20
        ";
        $productos_stmt = $db->prepare($productos_query);
        $productos_stmt->execute([$user['tienda_id']]);
    }
    $productos_disponibles = $productos_stmt->fetchAll();
    
    // Obtener ventas recientes
    if (hasPermission('admin')) {
        $ventas_query = "
            SELECT vp.*, p.nombre as producto_nombre, p.codigo_producto, t.nombre as tienda_nombre, 
                   u.nombre as vendedor_nombre
            FROM ventas_productos vp
            LEFT JOIN productos p ON vp.producto_id = p.id
            LEFT JOIN tiendas t ON vp.tienda_id = t.id
            LEFT JOIN usuarios u ON vp.vendedor_id = u.id
            ORDER BY vp.fecha_venta DESC
            LIMIT 20
        ";
        $ventas_stmt = $db->query($ventas_query);
    } else {
        $ventas_query = "
            SELECT vp.*, p.nombre as producto_nombre, p.codigo_producto, t.nombre as tienda_nombre, 
                   u.nombre as vendedor_nombre
            FROM ventas_productos vp
            LEFT JOIN productos p ON vp.producto_id = p.id
            LEFT JOIN tiendas t ON vp.tienda_id = t.id
            LEFT JOIN usuarios u ON vp.vendedor_id = u.id
            WHERE vp.tienda_id = ?
            ORDER BY vp.fecha_venta DESC
            LIMIT 20
        ";
        $ventas_stmt = $db->prepare($ventas_query);
        $ventas_stmt->execute([$user['tienda_id']]);
    }
    $ventas_recientes = $ventas_stmt->fetchAll();
    
    // Estadísticas del día
    $hoy = date('Y-m-d');
    if (hasPermission('admin')) {
        $stats_query = "
            SELECT COUNT(*) as ventas_hoy, COALESCE(SUM(precio_total), 0) as ingresos_hoy,
                   SUM(cantidad) as unidades_vendidas_hoy
            FROM ventas_productos WHERE DATE(fecha_venta) = ?
        ";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$hoy]);
    } else {
        $stats_query = "
            SELECT COUNT(*) as ventas_hoy, COALESCE(SUM(precio_total), 0) as ingresos_hoy,
                   SUM(cantidad) as unidades_vendidas_hoy
            FROM ventas_productos WHERE DATE(fecha_venta) = ? AND tienda_id = ?
        ";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$hoy, $user['tienda_id']]);
    }
    $estadisticas_hoy = $stats_stmt->fetch();
    
} catch(Exception $e) {
    logError("Error al obtener datos de ventas de productos: " . $e->getMessage());
}

// ==========================================
// INICIAR HTML
// ==========================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas de Productos - <?php echo SYSTEM_NAME; ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛍️</text></svg>">
    
    <?php renderSharedStyles(); ?>
    
    <style>
        /* Estilos específicos de ventas de productos - Complementan el sistema centralizado */
        .product-card { 
            transition: all var(--transition-base);
            cursor: pointer;
            border: 2px solid var(--color-gray-200);
            border-radius: var(--radius-lg);
            padding: 1rem;
            background: white;
        }
        
        .product-card:hover { 
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--color-primary);
        }
        
        .product-selected { 
            background: linear-gradient(135deg, #fdf4ff 0%, #f3e8ff 100%);
            border-color: #a855f7 !important;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.1) !important;
        }
        
        .search-box {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-bottom: 2px solid var(--color-gray-200);
        }
        
        .stats-mini-card {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        /* Animación para nuevos productos */
        @keyframes slideInProduct {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .product-card {
            animation: slideInProduct 0.3s ease-out;
        }
        
        /* Badge de stock mejorado */
        .stock-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--color-success-light);
            color: #065f46;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <?php renderNavbar('product_sales'); ?>
    
    <script src="../assets/js/common.js"></script>
    
    <main class="page-content">
        <div class="p-6">
            
            <!-- Header -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">
                            🛍️ Ventas de Productos
                        </h1>
                        <p class="text-gray-600">
                            Accesorios y repuestos para celulares
                        </p>
                    </div>
                    
                    <!-- Stats Card Mini -->
                    <div class="stats-mini-card">
                        <div class="text-center">
                            <p class="text-sm opacity-90">Ventas de Hoy</p>
                            <p class="text-2xl font-bold">
                                <?php echo $estadisticas_hoy['ventas_hoy']; ?> ventas
                            </p>
                            <p class="text-sm opacity-90">
                                S/ <?php echo number_format($estadisticas_hoy['ingresos_hoy'], 2); ?>
                            </p>
                            <p class="text-xs opacity-75">
                                <?php echo $estadisticas_hoy['unidades_vendidas_hoy']; ?> unidades
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Info Alert -->
                <?php 
                renderAlert(
                    '<div>
                        <p class="font-medium mb-1">Venta de productos:</p>
                        <p class="text-sm">1. Busca el producto por nombre, código o categoría → 2. Selecciona el producto → 3. Ajusta cantidad y precio → 4. Completa datos del cliente → 5. Confirma la venta</p>
                    </div>',
                    'info',
                    false
                );
                ?>
            </div>

            <!-- Grid Principal: Productos y Ventas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Panel de Productos Disponibles -->
                <div class="card">
                    <!-- Buscador -->
                    <div class="search-box p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 relative">
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input type="text" 
                                       id="productSearch" 
                                       placeholder="Buscar por nombre, código, marca o categoría..." 
                                       class="form-input pl-10">
                            </div>
                            <button onclick="searchProducts()" class="btn btn-primary">
                                Buscar
                            </button>
                            <button onclick="clearProductSearch()" class="btn btn-secondary">
                                Limpiar
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2" id="searchProductInfo">
                            <?php if ($user['rol'] === 'vendedor'): ?>
                                Buscando solo en <?php echo htmlspecialchars($user['tienda_nombre']); ?>
                            <?php else: ?>
                                Mostrando los últimos 20 productos disponibles
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- Header -->
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Productos Disponibles
                            <?php if ($user['rol'] === 'vendedor'): ?>
                                <span class="text-sm font-normal text-gray-500">- <?php echo htmlspecialchars($user['tienda_nombre']); ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php renderBadge(count($productos_disponibles) . ' disponibles', 'primary'); ?>
                    </div>
                    
                    <!-- Lista de Productos -->
                    <div class="card-body max-h-96 overflow-y-auto" id="productsContainer">
                        <?php renderLoadingSpinner('loadingProductSpinner'); ?>
                        
                        <div id="productsList" class="space-y-3">
                            <?php if (empty($productos_disponibles)): ?>
                                <?php
                                $actionButton = $user['rol'] === 'admin' 
                                    ? '<a href="products.php" class="btn btn-primary mt-4">Ir a Productos</a>'
                                    : '';
                                    
                                renderEmptyState(
                                    'No hay productos con stock',
                                    $user['rol'] === 'admin' 
                                        ? 'Ve a Productos para agregar stock' 
                                        : 'Contacta al administrador para reponer stock',
                                    $actionButton
                                );
                                ?>
                            <?php else: ?>
                                <?php foreach($productos_disponibles as $producto): ?>
                                    <div class="product-card" 
                                         onclick="selectProductForSale(<?php echo htmlspecialchars(json_encode($producto)); ?>)"
                                         data-product-id="<?php echo $producto['id']; ?>-<?php echo $producto['tienda_id']; ?>">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($producto['nombre']); ?></p>
                                                    <span class="stock-badge">
                                                        Stock: <?php echo $producto['cantidad_actual']; ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if ($producto['codigo_producto']): ?>
                                                    <p class="text-xs text-gray-500 font-mono bg-gray-100 inline-block px-2 py-1 rounded mb-1">
                                                        <?php echo htmlspecialchars($producto['codigo_producto']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <div class="flex flex-wrap gap-2 text-xs">
                                                    <?php if ($producto['marca']): ?>
                                                        <?php renderBadge($producto['marca'], 'info'); ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($producto['categoria_nombre']): ?>
                                                        <?php renderBadge($producto['categoria_nombre'], 'secondary'); ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($producto['modelo_compatible']): ?>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        Compatible: <?php echo htmlspecialchars($producto['modelo_compatible']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('admin')): ?>
                                                    <p class="text-xs text-blue-600 mt-1"><?php echo htmlspecialchars($producto['tienda_nombre']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="text-right ml-4">
                                                <p class="font-bold text-lg text-purple-600">S/ <?php echo number_format($producto['precio_venta'], 2); ?></p>
                                                <p class="text-xs text-gray-500">por unidad</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Panel de Ventas Recientes -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-900">Ventas Recientes</h3>
                        <?php renderBadge('Últimas ' . count($ventas_recientes) . ' ventas', 'success'); ?>
                    </div>
                    
                    <div class="card-body max-h-96 overflow-y-auto">
                        <?php if (empty($ventas_recientes)): ?>
                            <?php
                            renderEmptyState(
                                'No hay ventas registradas',
                                '¡Registra tu primera venta de productos!',
                                ''
                            );
                            ?>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($ventas_recientes as $venta): ?>
                                    <div class="border-l-4 border-purple-400 bg-purple-50 p-4 rounded-r-lg">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($venta['producto_nombre']); ?></p>
                                                    <?php renderBadge($venta['cantidad'] . ' ud', 'primary'); ?>
                                                </div>
                                                
                                                <p class="text-sm text-gray-600 mb-1">
                                                    Cliente: <?php echo htmlspecialchars($venta['cliente_nombre']); ?>
                                                </p>
                                                
                                                <div class="flex items-center gap-2 text-xs text-gray-500 flex-wrap">
                                                    <span><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></span>
                                                    <?php if (hasPermission('admin')): ?>
                                                        <span>•</span>
                                                        <span><?php echo htmlspecialchars($venta['tienda_nombre']); ?></span>
                                                    <?php endif; ?>
                                                    <span>•</span>
                                                    <span><?php echo htmlspecialchars($venta['vendedor_nombre']); ?></span>
                                                    <span>•</span>
                                                    <span><?php echo ucfirst($venta['metodo_pago']); ?></span>
                                                </div>
                                                
                                                <?php if ($venta['descuento'] > 0): ?>
                                                    <p class="text-xs text-orange-600 mt-1">
                                                        Descuento aplicado: S/ <?php echo number_format($venta['descuento'], 2); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="text-right ml-4">
                                                <p class="font-bold text-lg text-purple-600">S/ <?php echo number_format($venta['precio_total'], 2); ?></p>
                                                <p class="text-xs text-gray-500">
                                                    S/ <?php echo number_format($venta['precio_unitario'], 2); ?> c/u
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Registrar Venta -->
    <div id="productSaleModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="w-6 h-6 inline-block mr-2" style="color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Registrar Venta de Producto
                </h3>
                <button onclick="closeProductSaleModal()" class="modal-close">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="productSaleForm" class="space-y-4" onsubmit="event.preventDefault(); return false;">
                <input type="hidden" id="selectedProductId">
                <input type="hidden" id="selectedTiendaId">
                <input type="hidden" id="maxStock">
                
                <!-- Info del producto seleccionado -->
                <div id="productInfo" class="hidden p-4 rounded-lg" style="background: linear-gradient(135deg, #fdf4ff 0%, #f3e8ff 100%); border: 2px solid #a855f7;">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: #a855f7;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900" id="productName"></p>
                            <p class="text-sm text-gray-600" id="productDetails"></p>
                            <p class="text-xs text-purple-600" id="productStock"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-purple-600" id="productPrice"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Detalles de la Venta -->
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" style="color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Detalles de la Venta
                    </h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Cantidad <span class="text-red-500">*</span></label>
                            <input type="number" id="cantidad" min="1" required class="form-input">
                            <p class="text-xs text-gray-500 mt-1" id="cantidadInfo"></p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Precio Unitario <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                                <input type="number" id="precio_unitario" step="0.01" required class="form-input pl-10">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Descuento</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                                <input type="number" id="descuento" step="0.01" min="0" value="0" class="form-input pl-10">
                            </div>
                        </div>
                        
                        <?php
                        renderFormField('select', 'metodo_pago', 'Método de Pago', [
                            'options' => [
                                'efectivo' => 'Efectivo',
                                'tarjeta' => 'Tarjeta',
                                'transferencia' => 'Transferencia',
                                'credito' => 'Crédito'
                            ]
                        ]);
                        ?>
                    </div>
                    
                    <div class="mt-4 p-3 rounded-lg" style="background: var(--color-success-light); border: 2px solid var(--color-success);">
                        <div class="flex justify-between items-center">
                            <span class="font-medium" style="color: #065f46;">Total a Pagar:</span>
                            <span id="totalCalculado" class="text-xl font-bold" style="color: #059669;">S/ 0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Información del Cliente -->
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" style="color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Información del Cliente
                    </h4>
                    
                    <div class="space-y-4">
                        <?php
                        renderFormField('text', 'cliente_nombre', 'Nombre del Cliente', [
                            'required' => true,
                            'placeholder' => 'Nombre completo del cliente'
                        ]);
                        ?>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php
                            renderFormField('tel', 'cliente_telefono', 'Teléfono', [
                                'placeholder' => 'Número de contacto'
                            ]);
                            
                            renderFormField('email', 'cliente_email', 'Email', [
                                'placeholder' => 'correo@ejemplo.com'
                            ]);
                            ?>
                        </div>
                        
                        <?php
                        renderFormField('textarea', 'notas', 'Notas', [
                            'placeholder' => 'Observaciones adicionales sobre la venta...',
                            'rows' => 2,
                            'help' => 'Opcional'
                        ]);
                        ?>
                    </div>
                </div>
                
                <!-- Botones de Acción -->
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeProductSaleModal()" class="btn btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" onclick="registerProductSale()" class="btn btn-success">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ==========================================
        // VARIABLES GLOBALES
        // ==========================================
        let selectedProduct = null;
        let productSearchTimeout = null;

        // ==========================================
        // BÚSQUEDA DE PRODUCTOS
        // ==========================================
        function searchProducts() {
            const searchTerm = document.getElementById('productSearch').value.trim();
            
            showLoading('loadingProductSpinner');
            document.getElementById('productsList').style.opacity = '0.5';
            
            const formData = new FormData();
            formData.append('action', 'search_products');
            formData.append('search', searchTerm);
            
            fetch('product_sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderProducts(data.products);
                    
                    if (searchTerm) {
                        document.getElementById('searchProductInfo').textContent = 
                            `Mostrando ${data.products.length} resultados para "${searchTerm}"`;
                    } else {
                        document.getElementById('searchProductInfo').textContent = 
                            '<?php echo $user['rol'] === 'vendedor' ? "Mostrando productos de " . htmlspecialchars($user['tienda_nombre']) : "Mostrando todos los productos disponibles"; ?>';
                    }
                } else {
                    showNotification('Error al buscar productos: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error en la búsqueda', 'danger');
            })
            .finally(() => {
                hideLoading('loadingProductSpinner');
                document.getElementById('productsList').style.opacity = '1';
            });
        }

        function renderProducts(products) {
            const container = document.getElementById('productsList');
            
            if (products.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <p class="text-gray-500 font-medium">No se encontraron productos</p>
                        <p class="text-sm text-gray-400 mt-1">Intenta con otros términos de búsqueda</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            const showTienda = <?php echo hasPermission('admin') ? 'true' : 'false'; ?>;
            
            products.forEach(product => {
                html += `
                    <div class="product-card" 
                         onclick='selectProductForSale(${JSON.stringify(product)})'
                         data-product-id="${product.id}-${product.tienda_id}">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="font-medium text-gray-900">${escapeHtml(product.nombre)}</p>
                                    <span class="stock-badge">Stock: ${product.cantidad_actual}</span>
                                </div>
                                ${product.codigo_producto ? `
                                    <p class="text-xs text-gray-500 font-mono bg-gray-100 inline-block px-2 py-1 rounded mb-1">
                                        ${escapeHtml(product.codigo_producto)}
                                    </p>
                                ` : ''}
                                <div class="flex flex-wrap gap-2 text-xs mt-1">
                                    ${product.marca ? `<span class="badge badge-info">${escapeHtml(product.marca)}</span>` : ''}
                                    ${product.categoria_nombre ? `<span class="badge badge-secondary">${escapeHtml(product.categoria_nombre)}</span>` : ''}
                                </div>
                                ${product.modelo_compatible ? `<p class="text-xs text-gray-500 mt-1">Compatible: ${escapeHtml(product.modelo_compatible)}</p>` : ''}
                                ${showTienda ? `<p class="text-xs text-blue-600 mt-1">${escapeHtml(product.tienda_nombre)}</p>` : ''}
                            </div>
                            <div class="text-right ml-4">
                                <p class="font-bold text-lg text-purple-600">S/ ${formatPrice(product.precio_venta)}</p>
                                <p class="text-xs text-gray-500">por unidad</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function clearProductSearch() {
            document.getElementById('productSearch').value = '';
            searchProducts();
        }

        // ==========================================
        // SELECCIÓN DE PRODUCTO
        // ==========================================
        function selectProductForSale(product) {
            selectedProduct = product;
            
            document.querySelectorAll('.product-card').forEach(el => {
                el.classList.remove('product-selected');
            });
            
            const selectedCard = document.querySelector(`[data-product-id="${product.id}-${product.tienda_id}"]`);
            if (selectedCard) {
                selectedCard.classList.add('product-selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            document.getElementById('selectedProductId').value = product.id;
            document.getElementById('selectedTiendaId').value = product.tienda_id;
            document.getElementById('maxStock').value = product.cantidad_actual;
            
            document.getElementById('productName').textContent = product.nombre;
            document.getElementById('productDetails').textContent = 
                (product.marca || '') + (product.categoria_nombre ? ' - ' + product.categoria_nombre : '');
            document.getElementById('productStock').textContent = `Stock disponible: ${product.cantidad_actual} unidades`;
            document.getElementById('productPrice').textContent = `S/ ${parseFloat(product.precio_venta).toFixed(2)} c/u`;
            document.getElementById('productInfo').classList.remove('hidden');
            
            document.getElementById('cantidad').value = 1;
            document.getElementById('cantidad').max = product.cantidad_actual;
            document.getElementById('precio_unitario').value = product.precio_venta;
            document.getElementById('descuento').value = 0;
            document.getElementById('cantidadInfo').textContent = `Máximo disponible: ${product.cantidad_actual}`;
            
            calculateTotal();
            openModal('productSaleModal');
            
            setTimeout(() => document.getElementById('cantidad').focus(), 100);
        }

        // ==========================================
        // GESTIÓN DEL MODAL
        // ==========================================
        function closeProductSaleModal() {
            closeModal('productSaleModal');
            clearProductSaleForm();
            clearProductSelection();
        }

        function clearProductSelection() {
            selectedProduct = null;
            document.querySelectorAll('.product-card').forEach(el => {
                el.classList.remove('product-selected');
            });
            document.getElementById('productInfo').classList.add('hidden');
        }

        function clearProductSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('cantidad').value = 1;
            document.getElementById('precio_unitario').value = '';
            document.getElementById('descuento').value = 0;
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('notas').value = '';
            document.getElementById('totalCalculado').textContent = 'S/ 0.00';
        }

        // ==========================================
        // CÁLCULO DE TOTALES
        // ==========================================
        function calculateTotal() {
            const cantidad = parseFloat(document.getElementById('cantidad').value) || 0;
            const precioUnitario = parseFloat(document.getElementById('precio_unitario').value) || 0;
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;
            
            const subtotal = cantidad * precioUnitario;
            const total = subtotal - descuento;
            
            document.getElementById('totalCalculado').textContent = `S/ ${total.toFixed(2)}`;
            
            if (descuento > subtotal) {
                document.getElementById('descuento').classList.add('is-invalid');
                document.getElementById('totalCalculado').style.color = 'var(--color-danger)';
            } else {
                document.getElementById('descuento').classList.remove('is-invalid');
                document.getElementById('totalCalculado').style.color = '#059669';
            }
        }

        // ==========================================
        // REGISTRO DE VENTA
        // ==========================================
        function registerProductSale() {
            if (!selectedProduct) {
                showNotification('No se ha seleccionado un producto', 'warning');
                return;
            }
            
            const cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            const cantidad = parseInt(document.getElementById('cantidad').value);
            const precio_unitario = parseFloat(document.getElementById('precio_unitario').value);
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;
            const maxStock = parseInt(document.getElementById('maxStock').value);
            
            if (!cliente_nombre) {
                showNotification('Por favor ingresa el nombre del cliente', 'warning');
                document.getElementById('cliente_nombre').focus();
                return;
            }
            
            if (!cantidad || cantidad <= 0) {
                showNotification('Por favor ingresa una cantidad válida', 'warning');
                document.getElementById('cantidad').focus();
                return;
            }
            
            if (cantidad > maxStock) {
                showNotification(`Stock insuficiente. Máximo disponible: ${maxStock}`, 'danger');
                document.getElementById('cantidad').focus();
                return;
            }
            
            if (!precio_unitario || precio_unitario <= 0) {
                showNotification('Por favor ingresa un precio válido', 'warning');
                document.getElementById('precio_unitario').focus();
                return;
            }
            
            const total = (cantidad * precio_unitario) - descuento;
            if (total < 0) {
                showNotification('El descuento no puede ser mayor al total', 'danger');
                document.getElementById('descuento').focus();
                return;
            }
            
            const confirmMessage = `¿Confirmar venta?\n\nProducto: ${selectedProduct.nombre}\nCantidad: ${cantidad} unidades\nCliente: ${cliente_nombre}\nTotal: S/ ${total.toFixed(2)}`;
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'register_product_sale');
            formData.append('producto_id', selectedProduct.id);
            formData.append('tienda_id', selectedProduct.tienda_id);
            formData.append('cantidad', cantidad);
            formData.append('precio_unitario', precio_unitario);
            formData.append('descuento', descuento);
            formData.append('cliente_nombre', cliente_nombre);
            formData.append('cliente_telefono', document.getElementById('cliente_telefono').value);
            formData.append('cliente_email', document.getElementById('cliente_email').value);
            formData.append('metodo_pago', document.getElementById('metodo_pago').value);
            formData.append('notas', document.getElementById('notas').value);
            
            showLoading();
            
            fetch('product_sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    clearProductSaleForm();
                    closeProductSaleModal();
                    showPrintDialogProduct(data.venta_id);
                } else {
                    showNotification('❌ ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error en la conexión. Por favor intenta nuevamente.', 'danger');
            })
            .finally(() => {
                hideLoading();
            });
        }

        // ==========================================
        // DIÁLOGO DE IMPRESIÓN
        // ==========================================
        function showPrintDialogProduct(ventaId) {
            const modal = document.createElement('div');
            modal.className = 'modal show';
            modal.innerHTML = `
                <div class="modal-content text-center" style="max-width: 500px;">
                    <div class="mb-6">
                        <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Registrada!</h3>
                        <p class="text-gray-600">La venta del producto se ha registrado correctamente.</p>
                    </div>
                    <div class="flex flex-col gap-3">
                        <button onclick="printProductInvoice(${ventaId})" class="btn btn-primary w-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Imprimir Nota
                        </button>
                        <button onclick="closeDialogAndReloadProduct(this)" class="btn btn-secondary w-full">
                            Continuar sin Imprimir
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-4">
                        💡 Puedes imprimir la nota más tarde desde el historial de ventas
                    </p>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function printProductInvoice(ventaId) {
            const printWindow = window.open(
                `print_product_sale_invoice.php?id=${ventaId}`,
                'PrintProductInvoice',
                'width=800,height=600,scrollbars=yes'
            );
            
            if (printWindow) {
                printWindow.onload = () => setTimeout(() => location.reload(), 500);
            } else {
                showNotification('No se pudo abrir la ventana de impresión', 'danger');
                setTimeout(() => location.reload(), 2000);
            }
        }

        function closeDialogAndReloadProduct(button) {
            button.closest('.modal').remove();
            setTimeout(() => location.reload(), 300);
        }

        // ==========================================
        // UTILIDADES
        // ==========================================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatPrice(price) {
            return parseFloat(price).toFixed(2);
        }

        // ==========================================
        // EVENT LISTENERS
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Sistema de Ventas de Productos Centralizado v6.0 Cargado');
            console.log('💰 Moneda: Soles (S/)');
            
            // Búsqueda con delay
            const searchInput = document.getElementById('productSearch');
            searchInput.addEventListener('input', function() {
                clearTimeout(productSearchTimeout);
                productSearchTimeout = setTimeout(() => searchProducts(), 500);
            });
            
            // Enter en búsqueda
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchProducts();
                }
            });
            
            // Event listeners para cálculo automático
            document.getElementById('cantidad').addEventListener('input', function() {
                const maxStock = parseInt(document.getElementById('maxStock').value);
                if (parseInt(this.value) > maxStock) {
                    this.value = maxStock;
                    showNotification(`Stock máximo disponible: ${maxStock}`, 'warning');
                }
                if (this.value < 1) this.value = 1;
                calculateTotal();
            });
            
            document.getElementById('precio_unitario').addEventListener('input', function() {
                if (this.value < 0) this.value = 0;
                calculateTotal();
            });
            
            document.getElementById('descuento').addEventListener('input', function() {
                if (this.value < 0) this.value = 0;
                calculateTotal();
            });
            
            // Sugerencia de email
            document.getElementById('cliente_nombre').addEventListener('blur', function() {
                const nombre = this.value.trim();
                const emailField = document.getElementById('cliente_email');
                
                if (nombre && !emailField.value) {
                    const sugerencia = nombre.toLowerCase().replace(/\s+/g, '.') + '@ejemplo.com';
                    emailField.placeholder = `Ej: ${sugerencia}`;
                }
            });
            
            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeProductSaleModal();
                }
            });
            
            console.log('💡 Atajos: Enter (Buscar) | Esc (Cerrar modal)');
        });
    </script>

</body>
</html>
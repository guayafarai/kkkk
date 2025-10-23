<?php
/**
 * GESTIÓN DE PRODUCTOS Y ACCESORIOS
 * Sistema de Inventario de Celulares
 * VERSIÓN COMPLETA CON CÓDIGO DE BARRAS
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_dir = dirname(__DIR__);

require_once $base_dir . '/config/database.php';
require_once $base_dir . '/includes/auth.php';

$navbar_exists = file_exists($base_dir . '/includes/navbar_unified.php');
if ($navbar_exists) {
    require_once $base_dir . '/includes/navbar_unified.php';
}

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php?error=session_expired');
    exit;
}

$db = getDB();
$csrf_token = generateCsrfToken();

// PROCESAMIENTO DE ACCIONES AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    
    try {
        switch ($_POST['action']) {
case 'add_product':
    if (!hasPermission('admin')) {
        throw new Exception('Sin permisos para agregar productos');
    }
    
    $nombre = trim(sanitize($_POST['nombre'] ?? ''));
    $codigo = trim(sanitize($_POST['codigo_producto'] ?? ''));
    $descripcion = trim(sanitize($_POST['descripcion'] ?? ''));
    $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $tipo = $_POST['tipo'] ?? '';
    $marca = trim(sanitize($_POST['marca'] ?? ''));
    $modelo_compatible = trim(sanitize($_POST['modelo_compatible'] ?? ''));
    $precio_venta = floatval($_POST['precio_venta'] ?? 0);
    $precio_compra = !empty($_POST['precio_compra']) ? floatval($_POST['precio_compra']) : null;
    $minimo_stock = intval($_POST['minimo_stock'] ?? 5);
    
    if (empty($nombre)) throw new Exception('El nombre es obligatorio');
    if (!in_array($tipo, ['accesorio', 'repuesto'])) throw new Exception('Tipo no válido');
    if ($precio_venta <= 0) throw new Exception('El precio debe ser mayor a cero');
    
    // Generar código automáticamente si está vacío
    if (empty($codigo)) {
        $prefix = ($tipo === 'accesorio') ? 'ACC' : 'REP';
        do {
            $codigo = $prefix . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $check = $db->prepare("SELECT id FROM productos WHERE codigo_producto = ?");
            $check->execute([$codigo]);
        } while ($check->fetch());
    } else {
        // Validar que el código no exista
        $check = $db->prepare("SELECT id FROM productos WHERE codigo_producto = ?");
        $check->execute([$codigo]);
        if ($check->fetch()) throw new Exception('El código de barras ya existe');
    }
                
                $stmt = $db->prepare("
                    INSERT INTO productos (codigo_producto, nombre, descripcion, categoria_id, tipo, marca, 
                                          modelo_compatible, precio_venta, precio_compra, minimo_stock, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
if ($stmt->execute([$codigo ?: null, $nombre, $descripcion, $categoria_id, $tipo, $marca, 
                   $modelo_compatible, $precio_venta, $precio_compra, $minimo_stock])) {
    $product_id = $db->lastInsertId();
    logActivity($user['id'], 'add_product', "Producto: $nombre (ID: $product_id, Código: $codigo)");
    $message = empty($_POST['codigo_producto']) 
        ? "Producto agregado correctamente. Código generado: $codigo" 
        : 'Producto agregado correctamente';
    echo json_encode(['success' => true, 'message' => $message, 'codigo' => $codigo]);
} else {
    throw new Exception('Error al guardar');
}
                break;
                
            case 'update_product':
                if (!hasPermission('admin')) {
                    throw new Exception('Sin permisos para modificar productos');
                }
                
                $product_id = intval($_POST['product_id'] ?? 0);
                $nombre = trim(sanitize($_POST['nombre'] ?? ''));
                $codigo = trim(sanitize($_POST['codigo_producto'] ?? ''));
                $descripcion = trim(sanitize($_POST['descripcion'] ?? ''));
                $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
                $tipo = $_POST['tipo'] ?? '';
                $marca = trim(sanitize($_POST['marca'] ?? ''));
                $modelo_compatible = trim(sanitize($_POST['modelo_compatible'] ?? ''));
                $precio_venta = floatval($_POST['precio_venta'] ?? 0);
                $precio_compra = !empty($_POST['precio_compra']) ? floatval($_POST['precio_compra']) : null;
                $minimo_stock = intval($_POST['minimo_stock'] ?? 5);
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if ($product_id <= 0) throw new Exception('ID no válido');
                if (empty($nombre)) throw new Exception('El nombre es obligatorio');
                if (!in_array($tipo, ['accesorio', 'repuesto'])) throw new Exception('Tipo no válido');
                if ($precio_venta <= 0) throw new Exception('El precio debe ser mayor a cero');
                
                if (!empty($codigo)) {
                    $check = $db->prepare("SELECT id FROM productos WHERE codigo_producto = ? AND id != ?");
                    $check->execute([$codigo, $product_id]);
                    if ($check->fetch()) throw new Exception('El código de barras ya existe');
                }
                
                $stmt = $db->prepare("
                    UPDATE productos SET 
                        codigo_producto = ?, nombre = ?, descripcion = ?, categoria_id = ?, tipo = ?, marca = ?,
                        modelo_compatible = ?, precio_venta = ?, precio_compra = ?, 
                        minimo_stock = ?, activo = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$codigo ?: null, $nombre, $descripcion, $categoria_id, $tipo, $marca,
                                   $modelo_compatible, $precio_venta, $precio_compra, 
                                   $minimo_stock, $activo, $product_id])) {
                    logActivity($user['id'], 'update_product', "Producto ID: $product_id");
                    echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente']);
                } else {
                    throw new Exception('Error al actualizar');
                }
                break;
                
            case 'delete_product':
                if (!hasPermission('admin')) {
                    throw new Exception('Sin permisos para eliminar productos');
                }
                
                $product_id = intval($_POST['product_id'] ?? 0);
                if ($product_id <= 0) throw new Exception('ID no válido');
                
                $check = $db->prepare("SELECT SUM(cantidad_actual) as total FROM stock_productos WHERE producto_id = ?");
                $check->execute([$product_id]);
                $stock = $check->fetch();
                
                if ($stock && $stock['total'] > 0) {
                    throw new Exception('No se puede eliminar: tiene stock en tiendas');
                }
                
                $check = $db->prepare("SELECT COUNT(*) as count FROM ventas_productos WHERE producto_id = ?");
                $check->execute([$product_id]);
                $sales = $check->fetch();
                
                if ($sales && $sales['count'] > 0) {
                    $stmt = $db->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $message = 'Producto desactivado (tiene ventas registradas)';
                } else {
                    $stmt = $db->prepare("DELETE FROM productos WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $message = 'Producto eliminado correctamente';
                }
                
                logActivity($user['id'], 'delete_product', "Producto ID: $product_id");
                echo json_encode(['success' => true, 'message' => $message]);
                break;
                
            case 'adjust_stock':
                $producto_id = intval($_POST['producto_id'] ?? 0);
                $tienda_id = intval($_POST['tienda_id'] ?? 0);
                $nueva_cantidad = intval($_POST['nueva_cantidad'] ?? 0);
                $motivo = trim(sanitize($_POST['motivo'] ?? ''));
                
                if (!hasPermission('admin') && $tienda_id != $user['tienda_id']) {
                    throw new Exception('Sin permisos para esta tienda');
                }
                
                if ($producto_id <= 0 || $tienda_id <= 0) throw new Exception('Datos no válidos');
                if ($nueva_cantidad < 0) throw new Exception('La cantidad no puede ser negativa');
                if (empty($motivo)) throw new Exception('El motivo es obligatorio');
                
                $stmt = $db->prepare("
                    SELECT cantidad_actual FROM stock_productos 
                    WHERE producto_id = ? AND tienda_id = ?
                ");
                $stmt->execute([$producto_id, $tienda_id]);
                $stock_actual = $stmt->fetch();
                
                $cantidad_anterior = $stock_actual ? $stock_actual['cantidad_actual'] : 0;
                $diferencia = $nueva_cantidad - $cantidad_anterior;
                
                if ($stock_actual) {
                    $stmt = $db->prepare("
                        UPDATE stock_productos 
                        SET cantidad_actual = ?, fecha_actualizacion = NOW() 
                        WHERE producto_id = ? AND tienda_id = ?
                    ");
                    $stmt->execute([$nueva_cantidad, $producto_id, $tienda_id]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO stock_productos (producto_id, tienda_id, cantidad_actual) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$producto_id, $tienda_id, $nueva_cantidad]);
                }
                
                $tipo_mov = $diferencia > 0 ? 'entrada' : 'salida';
                $stmt = $db->prepare("
                    INSERT INTO movimientos_stock 
                    (producto_id, tienda_id, tipo_movimiento, cantidad, motivo, usuario_id, fecha_movimiento) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$producto_id, $tienda_id, $tipo_mov, abs($diferencia), $motivo, $user['id']]);
                
                logActivity($user['id'], 'adjust_stock', "Producto: $producto_id, Nueva cantidad: $nueva_cantidad");
                echo json_encode(['success' => true, 'message' => 'Stock ajustado correctamente']);
                break;
                
            case 'add_stock':
                $producto_id = intval($_POST['producto_id'] ?? 0);
                $tienda_id = intval($_POST['tienda_id'] ?? 0);
                $cantidad = intval($_POST['cantidad'] ?? 0);
                $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
                $motivo = trim(sanitize($_POST['motivo'] ?? 'Compra a proveedor'));
                
                if (!hasPermission('admin') && $tienda_id != $user['tienda_id']) {
                    throw new Exception('Sin permisos para esta tienda');
                }
                
                if ($producto_id <= 0 || $tienda_id <= 0) throw new Exception('Datos no válidos');
                if ($cantidad <= 0) throw new Exception('La cantidad debe ser mayor a cero');
                
                $stmt = $db->prepare("
                    INSERT INTO stock_productos (producto_id, tienda_id, cantidad_actual) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    cantidad_actual = cantidad_actual + ?, 
                    fecha_actualizacion = NOW()
                ");
                $stmt->execute([$producto_id, $tienda_id, $cantidad, $cantidad]);
                
                $stmt = $db->prepare("
                    INSERT INTO movimientos_stock 
                    (producto_id, tienda_id, tipo_movimiento, cantidad, precio_unitario, motivo, usuario_id, fecha_movimiento) 
                    VALUES (?, ?, 'entrada', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$producto_id, $tienda_id, $cantidad, $precio_unitario, $motivo, $user['id']]);
                
                logActivity($user['id'], 'add_stock', "Producto: $producto_id, Cantidad: $cantidad");
                echo json_encode(['success' => true, 'message' => 'Stock agregado correctamente']);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        logError("Error en products.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// OBTENER DATOS PARA LA VISTA
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$tipo_filter = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
$stock_filter = isset($_GET['stock']) ? sanitize($_GET['stock']) : '';
$categoria_filter = isset($_GET['categoria']) ? intval($_GET['categoria']) : null;

$tienda_filter = null;
if (hasPermission('admin')) {
    $tienda_filter = isset($_GET['tienda']) ? intval($_GET['tienda']) : null;
} else {
    $tienda_filter = $user['tienda_id'];
}

$where_conditions = ['p.activo = 1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nombre LIKE ? OR p.codigo_producto LIKE ? OR p.marca LIKE ? OR p.modelo_compatible LIKE ? OR p.descripcion LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($tipo_filter) && in_array($tipo_filter, ['accesorio', 'repuesto'])) {
    $where_conditions[] = "p.tipo = ?";
    $params[] = $tipo_filter;
}

if ($tienda_filter) {
    $where_conditions[] = "t.id = ?";
    $params[] = $tienda_filter;
}

if ($categoria_filter) {
    $where_conditions[] = "p.categoria_id = ?";
    $params[] = $categoria_filter;
}

if ($stock_filter === 'bajo') {
    $where_conditions[] = "COALESCE(s.cantidad_actual, 0) <= p.minimo_stock AND COALESCE(s.cantidad_actual, 0) > 0";
} elseif ($stock_filter === 'sin_stock') {
    $where_conditions[] = "COALESCE(s.cantidad_actual, 0) = 0";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

try {
    $query = "
        SELECT 
            p.*,
            c.nombre as categoria_nombre,
            t.id as tienda_id,
            t.nombre as tienda_nombre,
            COALESCE(s.cantidad_actual, 0) as stock_actual,
            COALESCE(s.cantidad_reservada, 0) as stock_reservado,
            s.ubicacion,
            CASE 
                WHEN COALESCE(s.cantidad_actual, 0) = 0 THEN 'SIN_STOCK'
                WHEN COALESCE(s.cantidad_actual, 0) <= p.minimo_stock THEN 'BAJO'
                WHEN COALESCE(s.cantidad_actual, 0) <= (p.minimo_stock * 2) THEN 'MEDIO'
                ELSE 'NORMAL'
            END as estado_stock
        FROM productos p
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        INNER JOIN tiendas t ON t.activa = 1
        LEFT JOIN stock_productos s ON p.id = s.producto_id AND t.id = s.tienda_id
        $where_clause
        ORDER BY p.nombre, t.nombre
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $tiendas = [];
    if (hasPermission('admin')) {
        $tiendas_stmt = $db->query("SELECT id, nombre FROM tiendas WHERE activa = 1 ORDER BY nombre");
        $tiendas = $tiendas_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $categorias = [];
    try {
        $cat_stmt = $db->query("
            SELECT id, nombre, tipo, descripcion 
            FROM categorias_productos 
            WHERE activa = 1 
            ORDER BY tipo, nombre
        ");
        $categorias = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logError("Error al obtener categorías: " . $e->getMessage());
    }
    
    $productos_unicos = [];
    $seen = [];
    foreach ($products as $p) {
        if (!isset($seen[$p['id']])) {
            $productos_unicos[] = $p;
            $seen[$p['id']] = true;
        }
    }
    
} catch (Exception $e) {
    logError("Error en query: " . $e->getMessage());
    $products = [];
    $tiendas = [];
    $categorias = [];
    $productos_unicos = [];
}

$productos_count = [];
$stats = ['total' => 0, 'total_stock' => 0, 'bajo_stock' => 0, 'sin_stock' => 0, 'valor_total' => 0];

foreach ($products as $p) {
    if (!isset($productos_count[$p['id']])) {
        $productos_count[$p['id']] = true;
        $stats['total']++;
    }
    $stats['total_stock'] += $p['stock_actual'];
    if ($p['estado_stock'] === 'BAJO') $stats['bajo_stock']++;
    if ($p['estado_stock'] === 'SIN_STOCK') $stats['sin_stock']++;
    $stats['valor_total'] += ($p['precio_venta'] * $p['stock_actual']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:9999}
        .modal.show{display:flex}
        .stock-sin{background:linear-gradient(135deg,#fef2f2 0%,#fee2e2 100%);border-left:4px solid #ef4444}
        .stock-bajo{background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border-left:4px solid #f59e0b}
        .stock-medio{background:linear-gradient(135deg,#f0f9ff 0%,#dbeafe 100%);border-left:4px solid #3b82f6}
        .stock-normal{background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-left:4px solid #22c55e}
        .product-card{transition:all 0.3s ease}
        .product-card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,0.15)}
        .notification{position:fixed;top:1rem;right:1rem;z-index:99999;transform:translateX(120%);transition:transform 0.3s}
        .notification.show{transform:translateX(0)}
    </style>
</head>
<body class="bg-gray-100">

<?php if($navbar_exists): renderNavbar('products'); endif; ?>

<main class="<?php echo $navbar_exists ? 'page-content' : 'container mx-auto'; ?> p-6">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gestión de Productos</h1>
            <p class="text-gray-600">Accesorios y repuestos para celulares</p>
        </div>
<div class="flex gap-3 mt-4 md:mt-0">
    <?php if(hasPermission('admin')): ?>
    <button onclick="openAddProductModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors shadow-md">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Nuevo Producto
    </button>
    <button onclick="openAddStockModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors shadow-md">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Agregar Stock
    </button>
    <?php endif; ?>
</div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total']; ?></p>
            <p class="text-sm text-gray-600">Productos</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_stock']); ?></p>
            <p class="text-sm text-gray-600">En Stock</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['bajo_stock']; ?></p>
            <p class="text-sm text-gray-600">Stock Bajo</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-red-600"><?php echo $stats['sin_stock']; ?></p>
            <p class="text-sm text-gray-600">Sin Stock</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-purple-600">$<?php echo number_format($stats['valor_total'],0); ?></p>
            <p class="text-sm text-gray-600">Valor Total</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
<form method="GET" id="filterForm" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-64">
                <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="🔍 Buscar: nombre, código, marca, modelo compatible..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <select name="tipo" id="tipoFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <option value="">Todos los tipos</option>
                <option value="accesorio" <?php echo $tipo_filter==='accesorio'?'selected':''; ?>>Accesorios</option>
                <option value="repuesto" <?php echo $tipo_filter==='repuesto'?'selected':''; ?>>Repuestos</option>
            </select>
            <select name="categoria" id="categoriaFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <option value="">Todas las categorías</option>
                <?php foreach($categorias as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filter==$cat['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($cat['nombre']); ?> (<?php echo strtoupper($cat['tipo']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <select name="stock" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <option value="">Todos los stocks</option>
                <option value="bajo" <?php echo $stock_filter==='bajo'?'selected':''; ?>>Stock bajo</option>
                <option value="sin_stock" <?php echo $stock_filter==='sin_stock'?'selected':''; ?>>Sin stock</option>
            </select>
            <?php if(hasPermission('admin')&&!empty($tiendas)): ?>
            <select name="tienda" id="tiendaFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <option value="">Todas las tiendas</option>
                <?php foreach($tiendas as $tienda): ?>
                <option value="<?php echo $tienda['id']; ?>" <?php echo $tienda_filter==$tienda['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($tienda['nombre']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">Filtrar</button>
            <?php if($search||$tipo_filter||$stock_filter||$categoria_filter||($tienda_filter&&hasPermission('admin'))): ?>
            <a href="products.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if(empty($products)): ?>
    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron productos</h3>
        <p class="text-gray-600 mb-4">Ajusta los filtros o agrega nuevos productos</p>
        <?php if(hasPermission('admin')): ?>
        <button onclick="openAddProductModal()" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">Agregar Primer Producto</button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($products as $product): ?>
        <div class="product-card bg-white rounded-lg shadow-md overflow-hidden stock-<?php echo strtolower($product['estado_stock']); ?>">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($product['nombre']); ?></h3>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['tipo']==='accesorio'?'bg-blue-100 text-blue-800':'bg-yellow-100 text-yellow-800'; ?>"><?php echo strtoupper($product['tipo']); ?></span>
                            <?php if(!empty($product['categoria_nombre'])): ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">📁 <?php echo htmlspecialchars($product['categoria_nombre']); ?></span>
                            <?php endif; ?>
                        </div>
<?php if(!empty($product['codigo_producto'])): ?>
<p class="text-xs text-gray-500 font-mono bg-gray-100 inline-block px-2 py-1 rounded mt-2">📖 <?php echo htmlspecialchars($product['codigo_producto']); ?></p>
<?php endif; ?>
                    </div>
                    <div class="text-right ml-4">
                        <p class="text-2xl font-bold text-green-600">$<?php echo number_format($product['precio_venta'],2); ?></p>
                        <?php if($product['precio_compra']&&hasPermission('admin')): ?>
                        <p class="text-xs text-gray-500">Costo: $<?php echo number_format($product['precio_compra'],2); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="space-y-2 mb-4">
                    <?php if($product['marca']): ?>
                    <p class="text-sm text-gray-600"><strong>Marca:</strong> <?php echo htmlspecialchars($product['marca']); ?></p>
                    <?php endif; if($product['modelo_compatible']): ?>
                    <p class="text-sm text-gray-600"><strong>Compatible:</strong> <?php echo htmlspecialchars($product['modelo_compatible']); ?></p>
                    <?php endif; if($product['descripcion']): ?>
                    <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($product['descripcion']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="border-t pt-4 mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700"><?php echo hasPermission('admin')?htmlspecialchars($product['tienda_nombre']):'Stock Actual'; ?></span>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold <?php echo $product['estado_stock']==='SIN_STOCK'?'text-red-600':($product['estado_stock']==='BAJO'?'text-yellow-600':($product['estado_stock']==='MEDIO'?'text-blue-600':'text-green-600')); ?>"><?php echo $product['stock_actual']; ?></span>
                            <span class="text-xs text-gray-500">unidades</span>
                        </div>
                    </div>
                    <?php if($product['stock_reservado']>0): ?>
                    <p class="text-xs text-blue-600 mb-1"><?php echo $product['stock_reservado']; ?> reservadas</p>
                    <?php endif; if($product['ubicacion']): ?>
                    <p class="text-xs text-gray-500 mb-2">📍 <?php echo htmlspecialchars($product['ubicacion']); ?></p>
                    <?php endif; ?>
                </div>
                
<div class="flex gap-2">
                    <?php if(hasPermission('admin')): ?>
                    <button onclick='openStockModal(<?php echo $product['id']; ?>,<?php echo $product['tienda_id']; ?>,"<?php echo addslashes($product['nombre']); ?>",<?php echo $product['stock_actual']; ?>)' class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-2 rounded transition-colors">Ajustar Stock</button>
                    <button onclick='openEditProductModal(<?php echo htmlspecialchars(json_encode($product),ENT_QUOTES); ?>)' class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-2 rounded transition-colors" title="Editar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button onclick='deleteProduct(<?php echo $product['id']; ?>,"<?php echo addslashes($product['nombre']); ?>")' class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-2 rounded transition-colors" title="Eliminar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                    <?php else: ?>
                    <div class="flex-1 bg-gray-100 text-gray-500 text-xs px-3 py-2 rounded text-center">
                        Solo vista - Sin permisos de ajuste
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<?php if(hasPermission('admin')): ?>
<div id="productModal" class="modal">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 id="productModalTitle" class="text-lg font-semibold">Agregar Producto</h3>
            <button onclick="closeProductModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="productForm" class="space-y-4">
            <input type="hidden" id="productId">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" id="nombre" required maxlength="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Código de Barras</label>
    <input type="text" id="codigo_producto" maxlength="50" placeholder="Dejar vacío para generar automáticamente" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
    <p class="text-xs text-gray-500 mt-1">💡 Si lo dejas vacío, se generará automáticamente (ACC/REP + Fecha + ID)</p>
</div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <select id="categoria_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Sin categoría</option>
                        <?php $tipo_actual=''; foreach($categorias as $cat): if($cat['tipo']!==$tipo_actual): if($tipo_actual!=='') echo '</optgroup>'; echo '<optgroup label="'.strtoupper($cat['tipo']).'S">'; $tipo_actual=$cat['tipo']; endif; ?>
                        <option value="<?php echo $cat['id']; ?>" data-tipo="<?php echo $cat['tipo']; ?>" title="<?php echo htmlspecialchars($cat['descripcion']); ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; if($tipo_actual!==''): ?></optgroup><?php endif; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1"><a href="categories.php" target="_blank" class="text-purple-600 hover:underline">Gestionar categorías</a></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                    <select id="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Seleccionar...</option>
                        <option value="accesorio">Accesorio</option>
                        <option value="repuesto">Repuesto</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                    <input type="text" id="marca" maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modelo Compatible</label>
                    <input type="text" id="modelo_compatible" maxlength="100" placeholder="Ej: iPhone 15, Galaxy S24" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio de Venta *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input type="number" id="precio_venta" step="0.01" min="0" required class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio de Compra</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input type="number" id="precio_compra" step="0.01" min="0" class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock Mínimo</label>
                    <input type="number" id="minimo_stock" value="5" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea id="descripcion" rows="3" maxlength="500" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
            </div>
            <div id="statusField" class="hidden">
                <label class="flex items-center">
                    <input type="checkbox" id="activo" class="mr-2 rounded">
                    <span class="text-sm font-medium text-gray-700">Producto activo</span>
                </label>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg">Cancelar</button>
                <button type="button" onclick="saveProduct()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="stockModal" class="modal">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Ajustar Stock</h3>
            <button onclick="closeStockModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="stockForm" class="space-y-4">
            <input type="hidden" id="stockProductoId">
            <input type="hidden" id="stockTiendaId">
            <div class="bg-blue-50 p-3 rounded-lg mb-4">
                <p class="font-medium text-blue-900" id="stockProductName"></p>
                <p class="text-sm text-blue-700">Stock actual: <span id="stockCurrent"></span> unidades</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva Cantidad *</label>
                <input type="number" id="nueva_cantidad" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo *</label>
                <select id="motivo_ajuste" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Seleccionar motivo...</option>
                    <option value="Inventario físico">Inventario físico</option>
                    <option value="Producto dañado">Producto dañado</option>
                    <option value="Producto perdido">Producto perdido</option>
                    <option value="Error de registro">Error de registro</option>
                    <option value="Devolución">Devolución</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg">Cancelar</button>
                <button type="button" onclick="adjustStock()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Ajustar</button>
            </div>
        </form>
    </div>
</div>

<div id="addStockModal" class="modal">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Agregar Stock - Entrada</h3>
            <button onclick="closeAddStockModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="addStockForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Producto *</label>
                <select id="add_producto_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">Seleccionar producto...</option>
                    <?php foreach($productos_unicos as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?><?php echo $p['codigo_producto']?' ('.htmlspecialchars($p['codigo_producto']).')':''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if(hasPermission('admin')): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tienda *</label>
                <select id="add_tienda_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">Seleccionar tienda...</option>
                    <?php foreach($tiendas as $tienda): ?>
                    <option value="<?php echo $tienda['id']; ?>"><?php echo htmlspecialchars($tienda['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" id="add_tienda_id" value="<?php echo $user['tienda_id']; ?>">
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
                    <input type="number" id="add_cantidad" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio Unit.</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                        <input type="number" id="add_precio_unitario" step="0.01" min="0" class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <select id="add_motivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="Compra a proveedor">Compra a proveedor</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Devolución">Devolución</option>
                    <option value="Ajuste">Ajuste</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeAddStockModal()" class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg">Cancelar</button>
                <button type="button" onclick="addStock()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">Agregar</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrf_token; ?>';
let isEditMode = false;

function openAddProductModal() {
    isEditMode = false;
    document.getElementById('productModalTitle').textContent = 'Agregar Producto';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('statusField').classList.add('hidden');
    document.getElementById('productModal').classList.add('show');
}

function openEditProductModal(product) {
    isEditMode = true;
    document.getElementById('productModalTitle').textContent = 'Editar Producto';
    document.getElementById('productId').value = product.id;
    document.getElementById('nombre').value = product.nombre || '';
    document.getElementById('codigo_producto').value = product.codigo_producto || '';
    document.getElementById('categoria_id').value = product.categoria_id || '';
    document.getElementById('tipo').value = product.tipo || '';
    document.getElementById('marca').value = product.marca || '';
    document.getElementById('modelo_compatible').value = product.modelo_compatible || '';
    document.getElementById('precio_venta').value = product.precio_venta || '';
    document.getElementById('precio_compra').value = product.precio_compra || '';
    document.getElementById('minimo_stock').value = product.minimo_stock || 5;
    document.getElementById('descripcion').value = product.descripcion || '';
    document.getElementById('activo').checked = product.activo == 1;
    document.getElementById('statusField').classList.remove('hidden');
    document.getElementById('productModal').classList.add('show');
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('show');
}

function saveProduct() {
    const formData = new FormData();
    formData.append('action', isEditMode ? 'update_product' : 'add_product');
    formData.append('csrf_token', CSRF_TOKEN);
    
    if (isEditMode) formData.append('product_id', document.getElementById('productId').value);
    
    formData.append('nombre', document.getElementById('nombre').value);
    formData.append('codigo_producto', document.getElementById('codigo_producto').value);
    formData.append('categoria_id', document.getElementById('categoria_id').value);
    formData.append('tipo', document.getElementById('tipo').value);
    formData.append('marca', document.getElementById('marca').value);
    formData.append('modelo_compatible', document.getElementById('modelo_compatible').value);
    formData.append('precio_venta', document.getElementById('precio_venta').value);
    formData.append('precio_compra', document.getElementById('precio_compra').value);
    formData.append('minimo_stock', document.getElementById('minimo_stock').value);
    formData.append('descripcion', document.getElementById('descripcion').value);
    
    if (isEditMode && document.getElementById('activo').checked) {
        formData.append('activo', '1');
    }
    
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            if (data.codigo) {
                console.log('✅ Código de barras generado:', data.codigo);
            }
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(() => showNotification('Error de conexión', 'error'));
}

function deleteProduct(id, name) {
    if (!confirm(`¿Eliminar "${name}"?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_product');
    formData.append('product_id', id);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function openStockModal(productId, tiendaId, productName, currentStock) {
    document.getElementById('stockProductoId').value = productId;
    document.getElementById('stockTiendaId').value = tiendaId;
    document.getElementById('stockProductName').textContent = productName;
    document.getElementById('stockCurrent').textContent = currentStock;
    document.getElementById('nueva_cantidad').value = currentStock;
    document.getElementById('motivo_ajuste').value = '';
    document.getElementById('stockModal').classList.add('show');
}

function closeStockModal() {
    document.getElementById('stockModal').classList.remove('show');
}

function adjustStock() {
    if (!document.getElementById('motivo_ajuste').value) {
        showNotification('Selecciona un motivo', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'adjust_stock');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('producto_id', document.getElementById('stockProductoId').value);
    formData.append('tienda_id', document.getElementById('stockTiendaId').value);
    formData.append('nueva_cantidad', document.getElementById('nueva_cantidad').value);
    formData.append('motivo', document.getElementById('motivo_ajuste').value);
    
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeStockModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function openAddStockModal() {
    document.getElementById('addStockForm').reset();
    <?php if (!hasPermission('admin')): ?>
    document.getElementById('add_tienda_id').value = <?php echo $user['tienda_id']; ?>;
    <?php endif; ?>
    document.getElementById('addStockModal').classList.add('show');
}

function closeAddStockModal() {
    document.getElementById('addStockModal').classList.remove('show');
}

function addStock() {
    const producto = document.getElementById('add_producto_id').value;
    const tienda = document.getElementById('add_tienda_id').value;
    const cantidad = document.getElementById('add_cantidad').value;
    
    if (!producto || !tienda || !cantidad) {
        showNotification('Completa todos los campos', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_stock');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('producto_id', producto);
    formData.append('tienda_id', tienda);
    formData.append('cantidad', cantidad);
    formData.append('precio_unitario', document.getElementById('add_precio_unitario').value);
    formData.append('motivo', document.getElementById('add_motivo').value);
    
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeAddStockModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function showNotification(message, type = 'info') {
    const colors = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'warning': 'bg-yellow-500',
        'info': 'bg-blue-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `notification ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Filtro de categorías por tipo
document.getElementById('tipo')?.addEventListener('change', function() {
    const categoriaSelect = document.getElementById('categoria_id');
    if (!categoriaSelect) return;
    
    const selectedTipo = this.value;
    const options = categoriaSelect.querySelectorAll('option[data-tipo]');
    
    options.forEach(option => {
        if (!selectedTipo || option.dataset.tipo === selectedTipo) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
    
    const selectedOption = categoriaSelect.options[categoriaSelect.selectedIndex];
    if (selectedOption && selectedOption.dataset.tipo && selectedOption.dataset.tipo !== selectedTipo) {
        categoriaSelect.value = '';
    }
});

// Cerrar modales con ESC
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeProductModal();
        closeStockModal();
        closeAddStockModal();
    }
});

// Búsqueda en tiempo real
let searchTimeout;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 500);
});

// Auto-submit en cambio de filtros
['tipoFilter', 'categoriaFilter', 'stockFilter', 'tiendaFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('change', () => document.getElementById('filterForm').submit());
    }
});

console.log('✅ Sistema de productos con código de barras cargado');
</script>
</body>
</html>
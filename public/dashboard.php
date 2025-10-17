<?php
/**
 * DASHBOARD MODERNO - Sistema de Inventario
 * Versión 3.2 - CORREGIDO - Sin errores de sintaxis
 * Moneda: SOLES (S/)
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

// 5. Cargar componentes
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
// OBTENER ESTADÍSTICAS DEL SISTEMA
// ==========================================
$stats = [
    'celulares' => ['total' => 0, 'disponibles' => 0, 'vendidos' => 0, 'valor' => 0],
    'productos' => ['total' => 0, 'stock' => 0, 'bajo_stock' => 0, 'sin_stock' => 0, 'valor' => 0],
    'ventas_hoy' => ['cantidad' => 0, 'ingresos' => 0, 'unidades' => 0],
    'ventas_mes' => ['cantidad' => 0, 'ingresos' => 0],
    'top_productos' => [],
    'top_celulares' => [],
    'ventas_semana' => []
];

try {
    // Estadísticas de celulares
    if ($user['rol'] === 'admin') {
        $cel_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
                SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos,
                SUM(CASE WHEN estado = 'disponible' THEN precio ELSE 0 END) as valor
            FROM celulares
        ";
        $stats['celulares'] = $db->query($cel_query)->fetch(PDO::FETCH_ASSOC);
    } else {
        $cel_stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
                SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos,
                SUM(CASE WHEN estado = 'disponible' THEN precio ELSE 0 END) as valor
            FROM celulares WHERE tienda_id = ?
        ");
        $cel_stmt->execute([$user['tienda_id']]);
        $stats['celulares'] = $cel_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Estadísticas de productos
    if ($user['rol'] === 'admin') {
        $prod_query = "
            SELECT 
                COUNT(DISTINCT p.id) as total,
                COALESCE(SUM(s.cantidad_actual), 0) as stock,
                SUM(CASE WHEN s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0 THEN 1 ELSE 0 END) as bajo_stock,
                SUM(CASE WHEN COALESCE(s.cantidad_actual, 0) = 0 THEN 1 ELSE 0 END) as sin_stock,
                COALESCE(SUM(p.precio_venta * s.cantidad_actual), 0) as valor
            FROM productos p
            LEFT JOIN stock_productos s ON p.id = s.producto_id
            WHERE p.activo = 1
        ";
        $stats['productos'] = $db->query($prod_query)->fetch(PDO::FETCH_ASSOC);
    } else {
        $prod_stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT p.id) as total,
                COALESCE(SUM(s.cantidad_actual), 0) as stock,
                SUM(CASE WHEN s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0 THEN 1 ELSE 0 END) as bajo_stock,
                SUM(CASE WHEN COALESCE(s.cantidad_actual, 0) = 0 THEN 1 ELSE 0 END) as sin_stock,
                COALESCE(SUM(p.precio_venta * s.cantidad_actual), 0) as valor
            FROM productos p
            LEFT JOIN stock_productos s ON p.id = s.producto_id
            WHERE p.activo = 1 AND s.tienda_id = ?
        ");
        $prod_stmt->execute([$user['tienda_id']]);
        $stats['productos'] = $prod_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Ventas de hoy
    $hoy = date('Y-m-d');
    if ($user['rol'] === 'admin') {
        $ventas_hoy_stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = ?) +
                (SELECT COUNT(*) FROM ventas_productos WHERE DATE(fecha_venta) = ?) as cantidad,
                (SELECT COALESCE(SUM(precio_venta), 0) FROM ventas WHERE DATE(fecha_venta) = ?) +
                (SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM ventas_productos WHERE DATE(fecha_venta) = ?) as ingresos,
                (SELECT COALESCE(SUM(cantidad), 0) FROM ventas_productos WHERE DATE(fecha_venta) = ?) as unidades
        ");
        $ventas_hoy_stmt->execute([$hoy, $hoy, $hoy, $hoy, $hoy]);
    } else {
        $ventas_hoy_stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?) +
                (SELECT COUNT(*) FROM ventas_productos WHERE DATE(fecha_venta) = ? AND tienda_id = ?) as cantidad,
                (SELECT COALESCE(SUM(precio_venta), 0) FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?) +
                (SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM ventas_productos WHERE DATE(fecha_venta) = ? AND tienda_id = ?) as ingresos,
                (SELECT COALESCE(SUM(cantidad), 0) FROM ventas_productos WHERE DATE(fecha_venta) = ? AND tienda_id = ?) as unidades
        ");
        $tid = $user['tienda_id'];
        $ventas_hoy_stmt->execute([$hoy, $tid, $hoy, $tid, $hoy, $tid, $hoy, $tid, $hoy, $tid]);
    }
    $stats['ventas_hoy'] = $ventas_hoy_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ventas del mes actual
    $mes_actual = date('Y-m');
    if ($user['rol'] === 'admin') {
        $ventas_mes_stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?) +
                (SELECT COUNT(*) FROM ventas_productos WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?) as cantidad,
                (SELECT COALESCE(SUM(precio_venta), 0) FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?) +
                (SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM ventas_productos WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?) as ingresos
        ");
        $ventas_mes_stmt->execute([$mes_actual, $mes_actual, $mes_actual, $mes_actual]);
    } else {
        $ventas_mes_stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?) +
                (SELECT COUNT(*) FROM ventas_productos WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?) as cantidad,
                (SELECT COALESCE(SUM(precio_venta), 0) FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?) +
                (SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM ventas_productos WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?) as ingresos
        ");
        $tid = $user['tienda_id'];
        $ventas_mes_stmt->execute([$mes_actual, $tid, $mes_actual, $tid, $mes_actual, $tid, $mes_actual, $tid]);
    }
    $stats['ventas_mes'] = $ventas_mes_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Últimos 5 productos vendidos
    if ($user['rol'] === 'admin') {
        $top_prod_stmt = $db->query("
            SELECT p.nombre, p.tipo, vp.cantidad, 
                   (vp.precio_unitario * vp.cantidad) as total_venta,
                   vp.fecha_venta, vp.cliente_nombre
            FROM ventas_productos vp
            JOIN productos p ON vp.producto_id = p.id
            ORDER BY vp.fecha_venta DESC
            LIMIT 5
        ");
    } else {
        $top_prod_stmt = $db->prepare("
            SELECT p.nombre, p.tipo, vp.cantidad, 
                   (vp.precio_unitario * vp.cantidad) as total_venta,
                   vp.fecha_venta, vp.cliente_nombre
            FROM ventas_productos vp
            JOIN productos p ON vp.producto_id = p.id
            WHERE vp.tienda_id = ?
            ORDER BY vp.fecha_venta DESC
            LIMIT 5
        ");
        $top_prod_stmt->execute([$user['tienda_id']]);
    }
    $stats['top_productos'] = $top_prod_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Últimos 5 celulares vendidos
    if ($user['rol'] === 'admin') {
        $top_cel_stmt = $db->query("
            SELECT c.marca, c.modelo, v.precio_venta as total_venta,
                   v.fecha_venta, v.cliente_nombre
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            ORDER BY v.fecha_venta DESC
            LIMIT 5
        ");
    } else {
        $top_cel_stmt = $db->prepare("
            SELECT c.marca, c.modelo, v.precio_venta as total_venta,
                   v.fecha_venta, v.cliente_nombre
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            WHERE v.tienda_id = ?
            ORDER BY v.fecha_venta DESC
            LIMIT 5
        ");
        $top_cel_stmt->execute([$user['tienda_id']]);
    }
    $stats['top_celulares'] = $top_cel_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ventas de los últimos 7 días
    if ($user['rol'] === 'admin') {
        $ventas_semana_stmt = $db->query("
            SELECT 
                DATE(fecha_venta) as fecha,
                COUNT(*) as ventas,
                SUM(precio_venta) as total
            FROM (
                SELECT fecha_venta, precio_venta FROM ventas WHERE DATE(fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                UNION ALL
                SELECT fecha_venta, precio_unitario * cantidad as precio_venta FROM ventas_productos WHERE DATE(fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ) as all_sales
            GROUP BY DATE(fecha_venta)
            ORDER BY fecha ASC
        ");
    } else {
        $ventas_semana_stmt = $db->prepare("
            SELECT 
                DATE(fecha_venta) as fecha,
                COUNT(*) as ventas,
                SUM(precio_venta) as total
            FROM (
                SELECT fecha_venta, precio_venta FROM ventas WHERE tienda_id = ? AND DATE(fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                UNION ALL
                SELECT fecha_venta, precio_unitario * cantidad as precio_venta FROM ventas_productos WHERE tienda_id = ? AND DATE(fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ) as all_sales
            GROUP BY DATE(fecha_venta)
            ORDER BY fecha ASC
        ");
        $ventas_semana_stmt->execute([$user['tienda_id'], $user['tienda_id']]);
    }
    $stats['ventas_semana'] = $ventas_semana_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
}

$valor_total_inventario = $stats['celulares']['valor'] + $stats['productos']['valor'];

// ==========================================
// PREPARAR CONTENIDO HTML PARA CARDS
// ==========================================

// Ventas de la Semana
$ventasSemanContent = '';
if (!empty($stats['ventas_semana'])) {
    $max_ventas = max(array_column($stats['ventas_semana'], 'total'));
    $ventasSemanContent .= '<div class="space-y-3">';
    foreach($stats['ventas_semana'] as $day) {
        $percentage = $max_ventas > 0 ? ($day['total'] / $max_ventas) * 100 : 0;
        $fecha = new DateTime($day['fecha']);
        
        $ventasSemanContent .= '<div>';
        $ventasSemanContent .= '<div class="flex justify-between text-sm mb-1">';
        $ventasSemanContent .= '<span class="font-medium text-gray-700">' . $fecha->format('D d/m') . '</span>';
        $ventasSemanContent .= '<span class="text-gray-900">';
        $ventasSemanContent .= '<strong>' . $day['ventas'] . '</strong> ventas - ';
        $ventasSemanContent .= '<strong class="text-green-600">S/ ' . number_format($day['total'], 0) . '</strong>';
        $ventasSemanContent .= '</span></div>';
        $ventasSemanContent .= '<div class="w-full bg-gray-200 rounded-full h-2">';
        $ventasSemanContent .= '<div class="h-2 rounded-full transition-all duration-500" style="width: ' . $percentage . '%; background: linear-gradient(90deg, #10b981 0%, #059669 100%);"></div>';
        $ventasSemanContent .= '</div></div>';
    }
    $ventasSemanContent .= '</div>';
}

// Últimos Celulares Vendidos
$celularesContent = '';
if (empty($stats['top_celulares'])) {
    ob_start();
    renderEmptyState('Sin ventas recientes', 'No se han registrado ventas de celulares recientemente', '');
    $celularesContent = ob_get_clean();
} else {
    $celularesContent .= '<div class="space-y-3">';
    foreach($stats['top_celulares'] as $index => $cel) {
        $tiempo_transcurrido = time() - strtotime($cel['fecha_venta']);
        if ($tiempo_transcurrido < 3600) {
            $tiempo = floor($tiempo_transcurrido / 60) . ' min';
        } elseif ($tiempo_transcurrido < 86400) {
            $tiempo = floor($tiempo_transcurrido / 3600) . ' h';
        } else {
            $tiempo = floor($tiempo_transcurrido / 86400) . ' días';
        }
        
        $celularesContent .= '<div class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg hover:shadow-md transition-all">';
        $celularesContent .= '<div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xs" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">' . ($index + 1) . '</div>';
        $celularesContent .= '<div class="flex-1 min-w-0">';
        $celularesContent .= '<div class="font-medium text-gray-900 truncate">' . htmlspecialchars($cel['marca'] ?? 'N/A') . '</div>';
        $celularesContent .= '<div class="text-sm text-gray-600 truncate">' . htmlspecialchars($cel['modelo']) . '</div>';
        $celularesContent .= '<div class="text-xs text-gray-500 mt-1">👤 ' . htmlspecialchars($cel['cliente_nombre']) . '</div>';
        $celularesContent .= '</div>';
        $celularesContent .= '<div class="text-right">';
        $celularesContent .= '<div class="font-bold text-green-600">S/ ' . number_format($cel['total_venta'], 0) . '</div>';
        $celularesContent .= '<div class="text-xs text-gray-500">Hace ' . $tiempo . '</div>';
        $celularesContent .= '</div></div>';
    }
    $celularesContent .= '</div>';
}

// Últimos Productos Vendidos
$productosContent = '';
if (empty($stats['top_productos'])) {
    ob_start();
    renderEmptyState('Sin ventas recientes', 'No se han registrado ventas de productos recientemente', '');
    $productosContent = ob_get_clean();
} else {
    $productosContent .= '<div class="space-y-3">';
    foreach($stats['top_productos'] as $index => $prod) {
        $tiempo_transcurrido = time() - strtotime($prod['fecha_venta']);
        if ($tiempo_transcurrido < 3600) {
            $tiempo = floor($tiempo_transcurrido / 60) . ' min';
        } elseif ($tiempo_transcurrido < 86400) {
            $tiempo = floor($tiempo_transcurrido / 3600) . ' h';
        } else {
            $tiempo = floor($tiempo_transcurrido / 86400) . ' días';
        }
        
        $productosContent .= '<div class="flex items-center gap-3 p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg hover:shadow-md transition-all">';
        $productosContent .= '<div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xs" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">' . ($index + 1) . '</div>';
        $productosContent .= '<div class="flex-1 min-w-0">';
        $productosContent .= '<div class="font-medium text-gray-900 text-sm truncate">' . htmlspecialchars($prod['nombre']) . '</div>';
        $productosContent .= '<div class="flex items-center gap-2 mt-1">';
        ob_start();
        renderBadge(strtoupper($prod['tipo']), $prod['tipo'] === 'accesorio' ? 'info' : 'warning');
        $productosContent .= ob_get_clean();
        $productosContent .= '<span class="text-xs text-gray-600">' . $prod['cantidad'] . ' uds</span>';
        $productosContent .= '</div>';
        $productosContent .= '<div class="text-xs text-gray-500 mt-1">👤 ' . htmlspecialchars($prod['cliente_nombre']) . '</div>';
        $productosContent .= '</div>';
        $productosContent .= '<div class="text-right">';
        $productosContent .= '<div class="font-bold text-green-600">S/ ' . number_format($prod['total_venta'], 0) . '</div>';
        $productosContent .= '<div class="text-xs text-gray-500">Hace ' . $tiempo . '</div>';
        $productosContent .= '</div></div>';
    }
    $productosContent .= '</div>';
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
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    
    <?php renderSharedStyles(); ?>
</head>
<body class="bg-gray-50">

<?php renderNavbar('dashboard'); ?>

<script src="../assets/js/common.js"></script>

<main class="page-content">
    <div class="p-6">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                Hola, <?php echo htmlspecialchars($user['nombre']); ?> 👋
            </h1>
            <p class="text-gray-600">
                Bienvenido de nuevo. Aquí está el resumen de tu negocio.
            </p>
        </div>

        <!-- Stats Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <?php
            renderStatCard(
                'S/ ' . number_format($stats['ventas_hoy']['ingresos'], 2),
                'Ventas de Hoy',
                [
                    'color' => 'orange',
                    'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                ]
            );
            
            renderStatCard(
                'S/ ' . number_format($stats['ventas_mes']['ingresos'], 0),
                'Ventas del Mes',
                [
                    'color' => 'purple',
                    'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>'
                ]
            );
            
            renderStatCard(
                number_format($stats['celulares']['disponibles']),
                'Celulares Disponibles',
                [
                    'color' => 'green',
                    'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>'
                ]
            );
            
            renderStatCard(
                'S/ ' . number_format($valor_total_inventario / 1000, 1) . 'K',
                'Valor Inventario',
                [
                    'color' => 'blue',
                    'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'
                ]
            );
            ?>

        </div>

        <!-- Segunda fila de stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg p-4 text-center shadow-sm hover:shadow-md transition-shadow">
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['celulares']['total']; ?></p>
                <p class="text-sm text-gray-600">Total Celulares</p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center shadow-sm hover:shadow-md transition-shadow">
                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['productos']['stock']); ?></p>
                <p class="text-sm text-gray-600">Stock Productos</p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center shadow-sm hover:shadow-md transition-shadow">
                <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['productos']['bajo_stock']; ?></p>
                <p class="text-sm text-gray-600">Stock Bajo</p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center shadow-sm hover:shadow-md transition-shadow">
                <p class="text-2xl font-bold text-red-600"><?php echo $stats['celulares']['vendidos']; ?></p>
                <p class="text-sm text-gray-600">Vendidos Mes</p>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($stats['productos']['bajo_stock'] > 0 || $stats['productos']['sin_stock'] > 0): ?>
            <?php 
            $alertMessage = '<div class="space-y-2">';
            $alertMessage .= '<h3 class="text-lg font-semibold text-gray-900 mb-2">⚠️ Alertas de Inventario</h3>';
            if ($stats['productos']['sin_stock'] > 0) {
                $alertMessage .= '<p class="text-sm">• <strong>' . $stats['productos']['sin_stock'] . '</strong> productos sin stock</p>';
            }
            if ($stats['productos']['bajo_stock'] > 0) {
                $alertMessage .= '<p class="text-sm">• <strong>' . $stats['productos']['bajo_stock'] . '</strong> productos con stock bajo</p>';
            }
            $alertMessage .= '<a href="products.php?stock=bajo" class="inline-block mt-3 text-sm font-medium hover:underline">Ver productos →</a>';
            $alertMessage .= '</div>';
            
            renderAlert($alertMessage, 'warning', true);
            ?>
        <?php endif; ?>

        <!-- Gráficos y tablas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <?php if (!empty($stats['ventas_semana'])): 
                renderCard('📈 Ventas Últimos 7 Días', $ventasSemanContent);
            endif; ?>

            <?php renderCard('📱 Últimos Celulares Vendidos', $celularesContent); ?>
        </div>

        <!-- Top Productos y Accesos Rápidos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <?php renderCard('🛍️ Últimos Productos Vendidos', $productosContent); ?>

            <!-- Accesos Rápidos -->
            <?php
            $accesosHTML = '<div class="grid grid-cols-2 gap-3">';
            
            $accesos = [
                ['url' => 'sales.php', 'label' => 'Vender Celular', 'color' => '#3b82f6', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'],
                ['url' => 'product_sales.php', 'label' => 'Vender Producto', 'color' => '#10b981', 'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
                ['url' => 'inventory.php', 'label' => 'Inventario', 'color' => '#8b5cf6', 'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['url' => 'products.php', 'label' => 'Productos', 'color' => '#f59e0b', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['url' => 'reports.php', 'label' => 'Reportes', 'color' => '#ef4444', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['url' => '../index.php', 'label' => 'Ver Catálogo', 'color' => '#ec4899', 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z']
            ];
            
            if ($user['rol'] === 'admin') {
                $accesos[] = ['url' => 'users.php', 'label' => 'Usuarios', 'color' => '#6366f1', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'];
                $accesos[] = ['url' => 'stores.php', 'label' => 'Tiendas', 'color' => '#14b8a6', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'];
            }
            
            foreach ($accesos as $acceso) {
                $target = ($acceso['url'] === '../index.php') ? 'target="_blank"' : '';
                $accesosHTML .= '<a href="' . $acceso['url'] . '" ' . $target . ' class="group bg-white hover:bg-opacity-80 p-4 rounded-lg text-center transition-all hover:shadow-md">';
                $accesosHTML .= '<div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, ' . $acceso['color'] . ' 0%, ' . $acceso['color'] . ' 100%);">';
                $accesosHTML .= '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                $accesosHTML .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . $acceso['icon'] . '"></path>';
                $accesosHTML .= '</svg></div>';
                $accesosHTML .= '<p class="text-sm font-medium text-gray-900">' . $acceso['label'] . '</p>';
                $accesosHTML .= '</a>';
            }
            
            $accesosHTML .= '</div>';
            
            renderCard('⚡ Accesos Rápidos', $accesosHTML, ['class' => 'bg-gradient-to-br from-blue-50 to-indigo-50']);
            ?>

        </div>

        <!-- Footer Info -->
        <div class="mt-8 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-sm">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-gray-600">
                    Sistema activo • <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Última actualización: <?php echo date('d/m/Y H:i'); ?>
            </p>
        </div>

    </div>
</main>

<?php renderLoadingSpinner(); ?>
<?php renderCommonScripts(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animar valores en stat cards
    const statValues = document.querySelectorAll('.stats-card-value');
    
    statValues.forEach(element => {
        const text = element.textContent;
        const hasNumber = text.match(/[\d,\.]+/);
        
        if (hasNumber) {
            const number = parseFloat(hasNumber[0].replace(/,/g, ''));
            if (!isNaN(number) && number > 0) {
                element.textContent = text.replace(/[\d,\.]+/, '0');
                
                const hasSoles = text.includes('S/');
                const hasK = text.includes('K');
                
                animateValue(element, 0, number, 1500, {
                    prefix: hasSoles ? 'S/ ' : '',
                    suffix: hasK ? 'K' : '',
                    decimals: hasK ? 1 : 0
                });
            }
        }
    });
    
    console.log('✅ Dashboard cargado - Sistema centralizado v3.2');
    console.log('💰 Moneda: Soles (S/)');
});

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    if (e.altKey) {
        switch(e.key) {
            case 'v': e.preventDefault(); window.location.href = 'sales.php'; break;
            case 'i': e.preventDefault(); window.location.href = 'inventory.php'; break;
            case 'p': e.preventDefault(); window.location.href = 'products.php'; break;
            case 'r': e.preventDefault(); window.location.href = 'reports.php'; break;
        }
    }
});

<?php if (isset($_GET['welcome'])): ?>
setTimeout(() => {
    showNotification('¡Bienvenido de nuevo, <?php echo htmlspecialchars($user['nombre']); ?>! 👋', 'success', 3000);
}, 500);
<?php endif; ?>

console.log('💡 Atajos: Alt+V (Ventas) | Alt+I (Inventario) | Alt+P (Productos) | Alt+R (Reportes)');
</script>

</body>
</html>
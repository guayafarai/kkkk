<?php
/**
 * DASHBOARD PROFESIONAL COMPLETO
 * Sistema de Inventario de Celulares
 * Versión 3.0 - Optimizado con estilos centralizados
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/styles.php';
require_once '../includes/navbar_unified.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// ==========================================
// OBTENER ESTADÍSTICAS
// ==========================================
$cel_stats = ['total_celulares' => 0, 'cel_disponibles' => 0, 'cel_vendidos' => 0, 'valor_celulares' => 0];
$prod_stats = ['total_productos' => 0, 'stock_productos' => 0, 'productos_bajo_stock' => 0, 'productos_sin_stock' => 0, 'valor_productos' => 0];
$today_stats = ['ventas' => 0, 'ingresos' => 0];
$week_sales = [];
$top_celulares = [];
$top_productos = [];

try {
    // Stats de celulares
    if ($user['rol'] === 'admin') {
        $cel_stats = $db->query("
            SELECT 
                COUNT(*) as total_celulares,
                SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as cel_disponibles,
                SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as cel_vendidos,
                SUM(CASE WHEN estado = 'disponible' THEN precio ELSE 0 END) as valor_celulares
            FROM celulares
        ")->fetch();
    } else {
        $cel_stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_celulares,
                SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as cel_disponibles,
                SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as cel_vendidos,
                SUM(CASE WHEN estado = 'disponible' THEN precio ELSE 0 END) as valor_celulares
            FROM celulares WHERE tienda_id = ?
        ");
        $cel_stmt->execute([$user['tienda_id']]);
        $cel_stats = $cel_stmt->fetch();
    }
    
    // Stats de productos
    if ($user['rol'] === 'admin') {
        $prod_stats = $db->query("
            SELECT 
                COUNT(DISTINCT p.id) as total_productos,
                COALESCE(SUM(s.cantidad_actual), 0) as stock_productos,
                SUM(CASE WHEN s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0 THEN 1 ELSE 0 END) as productos_bajo_stock,
                SUM(CASE WHEN COALESCE(s.cantidad_actual, 0) = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
                COALESCE(SUM(p.precio_venta * s.cantidad_actual), 0) as valor_productos
            FROM productos p
            LEFT JOIN stock_productos s ON p.id = s.producto_id
            WHERE p.activo = 1
        ")->fetch();
    } else {
        $prod_stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT p.id) as total_productos,
                COALESCE(SUM(s.cantidad_actual), 0) as stock_productos,
                SUM(CASE WHEN s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0 THEN 1 ELSE 0 END) as productos_bajo_stock,
                SUM(CASE WHEN COALESCE(s.cantidad_actual, 0) = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
                COALESCE(SUM(p.precio_venta * s.cantidad_actual), 0) as valor_productos
            FROM productos p
            LEFT JOIN stock_productos s ON p.id = s.producto_id
            WHERE p.activo = 1 AND s.tienda_id = ?
        ");
        $prod_stmt->execute([$user['tienda_id']]);
        $prod_stats = $prod_stmt->fetch();
    }
    
    // Ventas de hoy
    $today = date('Y-m-d');
    if ($user['rol'] === 'admin') {
        $today_stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = ?) +
                (SELECT COUNT(*) FROM ventas_productos WHERE DATE(fecha_venta) = ?) as ventas,
                (SELECT COALESCE(SUM(precio_venta), 0) FROM ventas WHERE DATE(fecha_venta) = ?) +
                (SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM ventas_productos WHERE DATE(fecha_venta) = ?) as ingresos
        ");
        $today_stmt->execute([$today, $today, $today, $today]);
    } else {
        $today_stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?) +
                (SELECT COUNT(*) FROM ventas_productos WHERE DATE(fecha_venta) = ? AND tienda_id = ?) as ventas,
                (SELECT COALESCE(SUM(precio_venta), 0) FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?) +
                (SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM ventas_productos WHERE DATE(fecha_venta) = ? AND tienda_id = ?) as ingresos
        ");
        $tid = $user['tienda_id'];
        $today_stmt->execute([$today, $tid, $today, $tid, $today, $tid, $today, $tid]);
    }
    $today_stats = $today_stmt->fetch();
    
    // Top 5 celulares vendidos
    if ($user['rol'] === 'admin') {
        $top_celulares = $db->query("
            SELECT c.marca, c.modelo, COUNT(*) as cantidad, SUM(v.precio_venta) as total_ventas
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            WHERE DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY c.marca, c.modelo
            ORDER BY cantidad DESC 
            LIMIT 5
        ")->fetchAll();
    } else {
        $top_cel = $db->prepare("
            SELECT c.marca, c.modelo, COUNT(*) as cantidad, SUM(v.precio_venta) as total_ventas
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            WHERE v.tienda_id = ? AND DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY c.marca, c.modelo
            ORDER BY cantidad DESC 
            LIMIT 5
        ");
        $top_cel->execute([$user['tienda_id']]);
        $top_celulares = $top_cel->fetchAll();
    }
    
    // Top 5 productos vendidos
    if ($user['rol'] === 'admin') {
        $top_productos = $db->query("
            SELECT p.nombre, p.tipo, SUM(vp.cantidad) as cantidad, SUM(vp.precio_unitario * vp.cantidad) as total_ventas
            FROM ventas_productos vp
            JOIN productos p ON vp.producto_id = p.id
            WHERE DATE(vp.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY p.id, p.nombre, p.tipo
            ORDER BY cantidad DESC 
            LIMIT 5
        ")->fetchAll();
    } else {
        $top_prod = $db->prepare("
            SELECT p.nombre, p.tipo, SUM(vp.cantidad) as cantidad, SUM(vp.precio_unitario * vp.cantidad) as total_ventas
            FROM ventas_productos vp
            JOIN productos p ON vp.producto_id = p.id
            WHERE vp.tienda_id = ? AND DATE(vp.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY p.id, p.nombre, p.tipo
            ORDER BY cantidad DESC 
            LIMIT 5
        ");
        $top_prod->execute([$user['tienda_id']]);
        $top_productos = $top_prod->fetchAll();
    }
    
    // Actividad reciente (últimas 10 acciones)
    if ($user['rol'] === 'admin') {
        $recent_activity = $db->query("
            SELECT al.*, u.nombre as usuario_nombre
            FROM activity_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ")->fetchAll();
    } else {
        $recent_stmt = $db->prepare("
            SELECT al.*, u.nombre as usuario_nombre
            FROM activity_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            WHERE al.user_id = ?
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $recent_stmt->execute([$user['id']]);
        $recent_activity = $recent_stmt->fetchAll();
    }
    
    // Ventas de la última semana (para gráfico)
    if ($user['rol'] === 'admin') {
        $week_sales_stmt = $db->query("
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
        $week_sales = $week_sales_stmt->fetchAll();
    } else {
        $week_sales_stmt = $db->prepare("
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
        $week_sales_stmt->execute([$user['tienda_id'], $user['tienda_id']]);
        $week_sales = $week_sales_stmt->fetchAll();
    }
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
}

// Calcular totales
$total_items = $cel_stats['total_celulares'] + $prod_stats['total_productos'];
$valor_total_inventario = $cel_stats['valor_celulares'] + $prod_stats['valor_productos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <?php renderSharedStyles(); ?>
</head>
<body>
    
    <?php renderNavbar('dashboard'); ?>
    
    <main class="page-content">
        <div class="p-6">
            
            <!-- Header con bienvenida -->
            <div class="mb-8 slide-up">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    👋 Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?>
                </h1>
                <p class="text-gray-600">
                    Aquí tienes un resumen de tu negocio
                    <?php if ($user['tienda_nombre']): ?>
                        en <strong><?php echo htmlspecialchars($user['tienda_nombre']); ?></strong>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Stats Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
                
                <!-- Celulares Disponibles -->
                <div class="stats-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="stats-card-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="stats-card-value"><?php echo number_format($cel_stats['cel_disponibles']); ?></div>
                    <div class="stats-card-label">Celulares Disponibles</div>
                </div>

                <!-- Total Celulares -->
                <div class="stats-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <div class="stats-card-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="stats-card-value"><?php echo number_format($cel_stats['total_celulares']); ?></div>
                    <div class="stats-card-label">Total Celulares</div>
                    <div class="text-xs opacity-90 mt-1"><?php echo $cel_stats['cel_vendidos']; ?> vendidos</div>
                </div>

                <!-- Stock Productos -->
                <div class="stats-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <div class="stats-card-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="stats-card-value"><?php echo number_format($prod_stats['stock_productos']); ?></div>
                    <div class="stats-card-label">Stock de Productos</div>
                </div>

                <!-- Catálogo -->
                <div class="stats-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                    <div class="stats-card-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                    </div>
                    <div class="stats-card-value"><?php echo number_format($prod_stats['total_productos']); ?></div>
                    <div class="stats-card-label">Productos Únicos</div>
                </div>

                <!-- Ventas Hoy -->
                <div class="stats-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="stats-card-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stats-card-value"><?php echo $today_stats['ventas']; ?></div>
                    <div class="stats-card-label">Ventas Hoy</div>
                    <div class="text-xs opacity-90 mt-1">$<?php echo number_format($today_stats['ingresos'], 2); ?></div>
                </div>

                <!-- Valor Total -->
                <div class="stats-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <div class="stats-card-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stats-card-value">$<?php echo number_format($valor_total_inventario / 1000, 1); ?>K</div>
                    <div class="stats-card-label">Valor Inventario</div>
                </div>

            </div>

            <!-- Alertas de Stock -->
            <?php if ($prod_stats['productos_bajo_stock'] > 0 || $prod_stats['productos_sin_stock'] > 0): ?>
            <div class="mb-8 fade-in">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-yellow-800">
                                ⚠️ Alerta de Inventario
                            </p>
                            <div class="mt-2 text-sm text-yellow-700">
                                <?php if ($prod_stats['productos_sin_stock'] > 0): ?>
                                    <p>• <strong><?php echo $prod_stats['productos_sin_stock']; ?></strong> productos sin stock</p>
                                <?php endif; ?>
                                <?php if ($prod_stats['productos_bajo_stock'] > 0): ?>
                                    <p>• <strong><?php echo $prod_stats['productos_bajo_stock']; ?></strong> productos con stock bajo</p>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <a href="products.php?stock=bajo" class="text-sm font-medium text-yellow-800 hover:text-yellow-900 underline">
                                    Ver productos →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Grid de Contenido Principal -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Top 5 Celulares -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            Top 5 Celulares (Último Mes)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_celulares)): ?>
                            <div class="empty-state">
                                <p class="text-gray-500">Sin datos de ventas en los últimos 30 días</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($top_celulares as $index => $cel): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover-lift">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($cel['marca'] ?? 'N/A'); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($cel['modelo']); ?></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900"><?php echo $cel['cantidad']; ?> ventas</div>
                                            <div class="text-sm text-green-600">$<?php echo number_format($cel['total_ventas'], 0); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top 5 Productos -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            Top 5 Productos (Último Mes)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_productos)): ?>
                            <div class="empty-state">
                                <p class="text-gray-500">Sin datos de ventas en los últimos 30 días</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($top_productos as $index => $prod): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover-lift">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="badge badge-<?php echo $prod['tipo'] === 'accesorio' ? 'info' : 'warning'; ?>">
                                                        <?php echo strtoupper($prod['tipo']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900"><?php echo $prod['cantidad']; ?> uds</div>
                                            <div class="text-sm text-green-600">$<?php echo number_format($prod['total_ventas'], 0); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Actividad Reciente -->
            <?php if (!empty($recent_activity)): ?>
            <div class="card mb-8 fade-in">
                <div class="card-header">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Actividad Reciente
                    </h3>
                </div>
                <div class="card-body">
                    <div class="space-y-2">
                        <?php foreach($recent_activity as $activity): ?>
                            <div class="flex items-start gap-3 p-2 hover:bg-gray-50 rounded transition-colors">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        <span class="font-medium"><?php echo htmlspecialchars($activity['usuario_nombre'] ?? 'Sistema'); ?></span>
                                        <span class="text-gray-600"> - <?php echo htmlspecialchars($activity['action']); ?></span>
                                    </p>
                                    <?php if ($activity['description']): ?>
                                        <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400">
                                        <?php 
                                            $time_ago = time() - strtotime($activity['created_at']);
                                            if ($time_ago < 60) {
                                                echo 'Hace un momento';
                                            } elseif ($time_ago < 3600) {
                                                echo 'Hace ' . floor($time_ago / 60) . ' minutos';
                                            } elseif ($time_ago < 86400) {
                                                echo 'Hace ' . floor($time_ago / 3600) . ' horas';
                                            } else {
                                                echo date('d/m/Y H:i', strtotime($activity['created_at']));
                                            }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($user['rol'] === 'admin'): ?>
                <div class="card-footer">
                    <a href="activity_logs.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                        Ver todos los registros →
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Ventas de la Semana (Gráfico Simple) -->
            <?php if (!empty($week_sales)): ?>
            <div class="card mb-8 fade-in">
                <div class="card-header">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                        Ventas de los Últimos 7 Días
                    </h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        <?php 
                        $max_ventas = max(array_column($week_sales, 'total'));
                        foreach($week_sales as $day): 
                            $percentage = $max_ventas > 0 ? ($day['total'] / $max_ventas) * 100 : 0;
                        ?>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium text-gray-700">
                                        <?php 
                                            $fecha = new DateTime($day['fecha']);
                                            echo $fecha->format('D d/m');
                                        ?>
                                    </span>
                                    <span class="text-gray-900">
                                        <strong><?php echo $day['ventas']; ?></strong> ventas - 
                                        <strong class="text-green-600">$<?php echo number_format($day['total'], 2); ?></strong>
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Accesos Rápidos -->
            <div class="card fade-in" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
                <div class="card-header" style="background: transparent; border: none;">
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Accesos Rápidos
                    </h2>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        
                        <!-- Vender Celular -->
                        <a href="sales.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-all" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Vender Celular</p>
                        </a>

                        <!-- Vender Producto -->
                        <a href="product_sales.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-all" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Vender Producto</p>
                        </a>

                        <!-- Inventario -->
                        <a href="inventory.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-all" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Inventario</p>
                        </a>

                        <!-- Productos -->
                        <a href="products.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-all" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Productos</p>
                        </a>

                        <!-- Reportes -->
                        <a href="reports.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-all" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Reportes</p>
                        </a>

                        <!-- Catálogo Público -->
                        <a href="../index.php" target="_blank" class="interactive-card text-center group hover:scale-105">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-all" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Ver Catálogo</p>
                        </a>

                    </div>

                    <?php if (hasPermission('admin')): ?>
                    <div class="divider-text mt-6">
                        <span>ADMINISTRACIÓN</span>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                        
                        <!-- Usuarios -->
                        <a href="users.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center bg-gradient-to-br from-green-500 to-teal-500">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-gray-900">Usuarios</p>
                        </a>

                        <!-- Tiendas -->
                        <a href="stores.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center bg-gradient-to-br from-blue-500 to-indigo-500">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-gray-900">Tiendas</p>
                        </a>

                        <!-- Categorías -->
                        <a href="categories.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center bg-gradient-to-br from-yellow-500 to-orange-500">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-gray-900">Categorías</p>
                        </a>

                        <!-- Config. Catálogo -->
                        <a href="catalog_settings.php" class="interactive-card text-center group hover:scale-105">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center bg-gradient-to-br from-purple-500 to-pink-500">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-gray-900">Config. Catálogo</p>
                        </a>

                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información del Sistema (Footer) -->
            <div class="mt-8 text-center text-sm text-gray-500 fade-in">
                <p>
                    <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?> | 
                    <?php echo $user['rol'] === 'admin' ? 'Administrador' : 'Vendedor'; ?>
                    <?php if ($user['tienda_nombre']): ?>
                        - <?php echo htmlspecialchars($user['tienda_nombre']); ?>
                    <?php endif; ?>
                </p>
                <p class="text-xs mt-1">
                    Última actualización: <?php echo date('d/m/Y H:i'); ?>
                </p>
            </div>

        </div>
    </main>

    <script>
        // Log del sistema
        console.log('✅ Dashboard cargado correctamente');
        console.log('📊 Estadísticas del sistema:');
        console.log('   - Celulares disponibles: <?php echo $cel_stats['cel_disponibles']; ?>');
        console.log('   - Total celulares: <?php echo $cel_stats['total_celulares']; ?>');
        console.log('   - Stock productos: <?php echo $prod_stats['stock_productos']; ?>');
        console.log('   - Ventas hoy: <?php echo $today_stats['ventas']; ?>');
        console.log('   - Ingresos hoy: $<?php echo number_format($today_stats['ingresos'], 2); ?>');
        console.log('   - Valor inventario: $<?php echo number_format($valor_total_inventario, 2); ?>');

        // Animación de entrada para las cards
        document.addEventListener('DOMContentLoaded', function() {
            // Aplicar animaciones escalonadas a las stats cards
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Efecto de conteo animado para números grandes
            function animateValue(element, start, end, duration) {
                const range = end - start;
                const increment = range / (duration / 16);
                let current = start;
                
                const timer = setInterval(() => {
                    current += increment;
                    if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                        clearInterval(timer);
                        current = end;
                    }
                    element.textContent = Math.floor(current).toLocaleString();
                }, 16);
            }

            // Animar valores de las stats cards
            const statsValues = document.querySelectorAll('.stats-card-value');
            statsValues.forEach(element => {
                const finalValue = parseInt(element.textContent.replace(/[^0-9]/g, ''));
                if (!isNaN(finalValue) && finalValue > 0) {
                    element.textContent = '0';
                    setTimeout(() => {
                        animateValue(element, 0, finalValue, 1000);
                    }, 200);
                }
            });

            // Efecto hover mejorado para accesos rápidos
            const quickAccessCards = document.querySelectorAll('.interactive-card');
            quickAccessCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px) scale(1.05)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Mostrar notificación de bienvenida (solo primera vez en sesión)
            if (!sessionStorage.getItem('welcomeShown')) {
                setTimeout(() => {
                    showWelcomeNotification();
                    sessionStorage.setItem('welcomeShown', 'true');
                }, 500);
            }
        });

        // Función para mostrar notificación de bienvenida
        function showWelcomeNotification() {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 0.75rem;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                z-index: 10000;
                animation: slideInRight 0.3s ease-out;
            `;
            
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="font-semibold">¡Bienvenido de nuevo!</p>
                        <p class="text-sm opacity-90">Sistema listo para trabajar</p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Alt + V = Ir a Ventas
            if (e.altKey && e.key === 'v') {
                e.preventDefault();
                window.location.href = 'sales.php';
            }
            // Alt + I = Ir a Inventario
            if (e.altKey && e.key === 'i') {
                e.preventDefault();
                window.location.href = 'inventory.php';
            }
            // Alt + P = Ir a Productos
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'products.php';
            }
            // Alt + R = Ir a Reportes
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'reports.php';
            }
        });

        // Auto-refresh cada 5 minutos para mantener datos actualizados
        let autoRefreshTimer = setTimeout(() => {
            if (confirm('¿Actualizar dashboard para ver datos más recientes?')) {
                location.reload();
            }
        }, 300000); // 5 minutos

        // Cancelar auto-refresh si el usuario está interactuando
        ['click', 'scroll', 'keypress'].forEach(event => {
            document.addEventListener(event, () => {
                clearTimeout(autoRefreshTimer);
            }, { once: true });
        });

        console.log('💡 Atajos de teclado disponibles:');
        console.log('   - Alt + V: Ir a Ventas');
        console.log('   - Alt + I: Ir a Inventario');
        console.log('   - Alt + P: Ir a Productos');
        console.log('   - Alt + R: Ir a Reportes');
    </script>

</body>
</html>

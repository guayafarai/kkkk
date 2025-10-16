<?php
/**
 * DASHBOARD MODERNO - Sistema de Inventario
 * Versión Premium con diseño profesional - MONEDA: SOLES (S/)
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
    
    // Ventas de los últimos 7 días (para gráfico)
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <?php renderSharedStyles(); ?>
    <style>
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-secondary) 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .stat-change.positive {
            background: #dcfce7;
            color: #166534;
        }
        
        .stat-change.negative {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring-circle {
            transition: stroke-dashoffset 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <?php renderNavbar('dashboard'); ?>
    
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
                
                <!-- Ventas Hoy -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #f59e0b;">
                        S/ <?php echo number_format($stats['ventas_hoy']['ingresos'], 2); ?>
                    </div>
                    <div class="stat-label">Ventas de Hoy</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <?php echo $stats['ventas_hoy']['cantidad']; ?> transacciones
                    </div>
                </div>

                <!-- Ventas del Mes -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #8b5cf6;">
                        S/ <?php echo number_format($stats['ventas_mes']['ingresos'], 0); ?>
                    </div>
                    <div class="stat-label">Ventas del Mes</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <?php echo $stats['ventas_mes']['cantidad']; ?> transacciones
                    </div>
                </div>

                <!-- Celulares Disponibles -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #10b981;">
                        <?php echo number_format($stats['celulares']['disponibles']); ?>
                    </div>
                    <div class="stat-label">Celulares Disponibles</div>
                    <div class="text-sm text-gray-500 mt-2">
                        de <?php echo $stats['celulares']['total']; ?> totales
                    </div>
                </div>

                <!-- Valor Inventario -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #3b82f6;">
                        S/ <?php echo number_format($valor_total_inventario / 1000, 1); ?>K
                    </div>
                    <div class="stat-label">Valor Inventario</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <?php echo number_format($stats['productos']['stock']); ?> productos
                    </div>
                </div>

            </div>

            <!-- Segunda fila de stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-blue-600"><?php echo $stats['celulares']['total']; ?></p>
                    <p class="text-sm text-gray-600">Total Celulares</p>
                </div>
                <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['productos']['stock']); ?></p>
                    <p class="text-sm text-gray-600">Stock Productos</p>
                </div>
                <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['productos']['bajo_stock']; ?></p>
                    <p class="text-sm text-gray-600">Stock Bajo</p>
                </div>
                <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['celulares']['vendidos']; ?></p>
                    <p class="text-sm text-gray-600">Vendidos Mes</p>
                </div>
            </div>

            <!-- Alertas -->
            <?php if ($stats['productos']['bajo_stock'] > 0 || $stats['productos']['sin_stock'] > 0): ?>
            <div class="mb-8">
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow-sm">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 text-yellow-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-yellow-900 mb-2">⚠️ Alertas de Inventario</h3>
                            <div class="space-y-1">
                                <?php if ($stats['productos']['sin_stock'] > 0): ?>
                                    <p class="text-sm text-yellow-800">• <strong><?php echo $stats['productos']['sin_stock']; ?></strong> productos sin stock</p>
                                <?php endif; ?>
                                <?php if ($stats['productos']['bajo_stock'] > 0): ?>
                                    <p class="text-sm text-yellow-800">• <strong><?php echo $stats['productos']['bajo_stock']; ?></strong> productos con stock bajo</p>
                                <?php endif; ?>
                            </div>
                            <a href="products.php?stock=bajo" class="inline-block mt-3 text-sm font-medium text-yellow-900 hover:text-yellow-700 underline">
                                Ver productos →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gráficos y tablas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Ventas de la Semana -->
                <?php if (!empty($stats['ventas_semana'])): ?>
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                        Ventas Últimos 7 Días
                    </h3>
                    <div class="space-y-3">
                        <?php 
                        $max_ventas = max(array_column($stats['ventas_semana'], 'total'));
                        foreach($stats['ventas_semana'] as $day): 
                            $percentage = $max_ventas > 0 ? ($day['total'] / $max_ventas) * 100 : 0;
                            $fecha = new DateTime($day['fecha']);
                        ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">
                                        <?php echo $fecha->format('D d/m'); ?>
                                    </span>
                                    <span class="text-gray-900">
                                        <strong><?php echo $day['ventas']; ?></strong> ventas - 
                                        <strong class="text-green-600">S/ <?php echo number_format($day['total'], 0); ?></strong>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-500" 
                                         style="width: <?php echo $percentage; ?>%; background: linear-gradient(90deg, #10b981 0%, #059669 100%);"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Últimos Celulares Vendidos -->
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Últimos Celulares Vendidos
                    </h3>
                    <?php if (empty($stats['top_celulares'])): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">Sin ventas recientes</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach($stats['top_celulares'] as $index => $cel): 
                                $tiempo_transcurrido = time() - strtotime($cel['fecha_venta']);
                                if ($tiempo_transcurrido < 3600) {
                                    $tiempo = floor($tiempo_transcurrido / 60) . ' min';
                                } elseif ($tiempo_transcurrido < 86400) {
                                    $tiempo = floor($tiempo_transcurrido / 3600) . ' h';
                                } else {
                                    $tiempo = floor($tiempo_transcurrido / 86400) . ' días';
                                }
                            ?>
                                <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg hover:shadow-md transition-all">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                                         style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($cel['marca'] ?? 'N/A'); ?></div>
                                        <div class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($cel['modelo']); ?></div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <span class="inline-flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                <?php echo htmlspecialchars($cel['cliente_nombre']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-green-600">S/ <?php echo number_format($cel['total_venta'], 0); ?></div>
                                        <div class="text-xs text-gray-500">Hace <?php echo $tiempo; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Productos y Accesos Rápidos -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Últimos Productos Vendidos -->
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        Últimos Productos Vendidos
                    </h3>
                    <?php if (empty($stats['top_productos'])): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">Sin ventas recientes</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach($stats['top_productos'] as $index => $prod): 
                                $tiempo_transcurrido = time() - strtotime($prod['fecha_venta']);
                                if ($tiempo_transcurrido < 3600) {
                                    $tiempo = floor($tiempo_transcurrido / 60) . ' min';
                                } elseif ($tiempo_transcurrido < 86400) {
                                    $tiempo = floor($tiempo_transcurrido / 3600) . ' h';
                                } else {
                                    $tiempo = floor($tiempo_transcurrido / 86400) . ' días';
                                }
                            ?>
                                <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg hover:shadow-md transition-all">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xs" 
                                         style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 text-sm truncate"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs <?php echo $prod['tipo'] === 'accesorio' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo strtoupper($prod['tipo']); ?>
                                            </span>
                                            <span class="text-xs text-gray-600"><?php echo $prod['cantidad']; ?> uds</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <span class="inline-flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                <?php echo htmlspecialchars($prod['cliente_nombre']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-green-600">S/ <?php echo number_format($prod['total_venta'], 0); ?></div>
                                        <div class="text-xs text-gray-500">Hace <?php echo $tiempo; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Accesos Rápidos -->
                <div class="chart-container" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Accesos Rápidos
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        
                        <a href="sales.php" class="group bg-white hover:bg-blue-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600">Vender Celular</p>
                        </a>

                        <a href="product_sales.php" class="group bg-white hover:bg-green-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-green-600">Vender Producto</p>
                        </a>

                        <a href="inventory.php" class="group bg-white hover:bg-purple-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-purple-600">Inventario</p>
                        </a>

                        <a href="products.php" class="group bg-white hover:bg-yellow-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-yellow-600">Productos</p>
                        </a>

                        <a href="reports.php" class="group bg-white hover:bg-red-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-red-600">Reportes</p>
                        </a>

                        <a href="../index.php" target="_blank" class="group bg-white hover:bg-pink-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-pink-600">Ver Catálogo</p>
                        </a>

                        <?php if (hasPermission('admin')): ?>
                        <a href="users.php" class="group bg-white hover:bg-indigo-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-600">Usuarios</p>
                        </a>

                        <a href="stores.php" class="group bg-white hover:bg-teal-50 p-4 rounded-lg text-center transition-all hover:shadow-md">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center" 
                                 style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900 group-hover:text-teal-600">Tiendas</p>
                        </a>
                        <?php endif; ?>

                    </div>
                </div>

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

    <script>
        // Animación de números al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const statValues = document.querySelectorAll('.stat-value');
            
            statValues.forEach(element => {
                const text = element.textContent;
                const hasNumber = text.match(/[\d,\.]+/);
                
                if (hasNumber) {
                    const number = parseFloat(hasNumber[0].replace(/,/g, ''));
                    if (!isNaN(number) && number > 0) {
                        element.textContent = text.replace(/[\d,\.]+/, '0');
                        animateValue(element, 0, number, 1000, text);
                    }
                }
            });
        });

        function animateValue(element, start, end, duration, template) {
            const startTime = performance.now();
            const hasSoles = template.includes('S/');
            const hasK = template.includes('K');
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = start + (end - start) * easeOutQuart;
                
                let displayValue = Math.floor(current);
                
                if (hasK) {
                    displayValue = (current).toFixed(1);
                    element.textContent = hasSoles ? `S/ ${displayValue}K` : `${displayValue}K`;
                } else {
                    element.textContent = hasSoles ? `S/ ${displayValue.toLocaleString()}` : displayValue.toLocaleString();
                }
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }
            
            requestAnimationFrame(update);
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case 'v':
                        e.preventDefault();
                        window.location.href = 'sales.php';
                        break;
                    case 'i':
                        e.preventDefault();
                        window.location.href = 'inventory.php';
                        break;
                    case 'p':
                        e.preventDefault();
                        window.location.href = 'products.php';
                        break;
                    case 'r':
                        e.preventDefault();
                        window.location.href = 'reports.php';
                        break;
                }
            }
        });

        // Auto-refresh opcional cada 5 minutos
        setTimeout(() => {
            if (confirm('¿Actualizar dashboard con datos más recientes?')) {
                location.reload();
            }
        }, 300000);

        console.log('✅ Dashboard cargado - Moneda: SOLES (S/)');
        console.log('💡 Atajos: Alt+V (Ventas) | Alt+I (Inventario) | Alt+P (Productos) | Alt+R (Reportes)');
    </script>

</body>
</html>
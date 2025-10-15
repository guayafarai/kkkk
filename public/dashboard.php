<?php
/**
 * DASHBOARD PROFESIONAL COMPLETO - SIN ERRORES
 * Sistema de Inventario - Versión Ultimate 4.1 FIXED
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Obtener estadísticas (código simplificado para evitar errores)
$cel_stats = ['total_celulares' => 0, 'cel_disponibles' => 0, 'cel_vendidos' => 0, 'valor_celulares' => 0];
$prod_stats = ['total_productos' => 0, 'stock_productos' => 0, 'productos_bajo_stock' => 0, 'productos_sin_stock' => 0, 'valor_productos' => 0];
$today_stats = ['ventas' => 0, 'ingresos' => 0];
$week_sales = [];
$top_celulares = [];
$top_productos = [];
$low_stock_products = [];
$inventory_distribution = [];
$tiendas_stats = [];

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
    
    // Top celulares
    if ($user['rol'] === 'admin') {
        $top_celulares = $db->query("
            SELECT c.marca, c.modelo, COUNT(*) as cantidad
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            GROUP BY c.marca, c.modelo
            ORDER BY cantidad DESC LIMIT 5
        ")->fetchAll();
    } else {
        $top_cel = $db->prepare("
            SELECT c.marca, c.modelo, COUNT(*) as cantidad
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            WHERE v.tienda_id = ?
            GROUP BY c.marca, c.modelo
            ORDER BY cantidad DESC LIMIT 5
        ");
        $top_cel->execute([$user['tienda_id']]);
        $top_celulares = $top_cel->fetchAll();
    }
    
    // Top productos
    if ($user['rol'] === 'admin') {
        $top_productos = $db->query("
            SELECT p.nombre, SUM(vp.cantidad) as cantidad
            FROM ventas_productos vp
            JOIN productos p ON vp.producto_id = p.id
            GROUP BY p.nombre
            ORDER BY cantidad DESC LIMIT 5
        ")->fetchAll();
    } else {
        $top_prod = $db->prepare("
            SELECT p.nombre, SUM(vp.cantidad) as cantidad
            FROM ventas_productos vp
            JOIN productos p ON vp.producto_id = p.id
            WHERE vp.tienda_id = ?
            GROUP BY p.nombre
            ORDER BY cantidad DESC LIMIT 5
        ");
        $top_prod->execute([$user['tienda_id']]);
        $top_productos = $top_prod->fetchAll();
    }
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
}

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        body { background: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.12); }
        .stat-card.gradient-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .stat-card.gradient-blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .stat-card.gradient-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
        .stat-card.gradient-pink { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: white; }
        .stat-card.gradient-orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .stat-card.gradient-red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .stat-icon { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); opacity: 0.2; font-size: 4rem; }
        .stat-value { font-size: 2.5rem; font-weight: 700; line-height: 1; margin-bottom: 0.5rem; }
        .stat-label { font-size: 0.875rem; opacity: 0.9; font-weight: 500; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .table-header { padding: 1rem 1.5rem; border-bottom: 2px solid #e5e7eb; font-weight: 600; color: #1f2937; }
        .table-row { padding: 1rem 1.5rem; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .table-row:hover { background: #f9fafb; }
        @media (max-width: 768px) { .stat-value { font-size: 2rem; } }
    </style>
</head>
<body>
    
    <?php renderNavbar('dashboard'); ?>
    
    <main class="page-content">
        <div class="p-6">
            
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard Integral</h1>
                <p class="text-gray-600">Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?></p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
                
                <div class="stat-card gradient-green">
                    <div class="stat-icon">📱</div>
                    <div class="stat-label">Celulares</div>
                    <div class="stat-value"><?php echo number_format($cel_stats['cel_disponibles']); ?></div>
                    <div class="text-sm opacity-90">Disponibles</div>
                </div>

                <div class="stat-card gradient-blue">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Total Celulares</div>
                    <div class="stat-value"><?php echo number_format($cel_stats['total_celulares']); ?></div>
                    <div class="text-sm opacity-90"><?php echo $cel_stats['cel_vendidos']; ?> vendidos</div>
                </div>

                <div class="stat-card gradient-purple">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Productos</div>
                    <div class="stat-value"><?php echo number_format($prod_stats['stock_productos']); ?></div>
                    <div class="text-sm opacity-90">En stock</div>
                </div>

                <div class="stat-card gradient-pink">
                    <div class="stat-icon">🏷️</div>
                    <div class="stat-label">Catálogo</div>
                    <div class="stat-value"><?php echo number_format($prod_stats['total_productos']); ?></div>
                    <div class="text-sm opacity-90">Productos únicos</div>
                </div>

                <div class="stat-card gradient-orange">
                    <div class="stat-icon">💰</div>
                    <div class="stat-label">Ventas Hoy</div>
                    <div class="stat-value"><?php echo $today_stats['ventas']; ?></div>
                    <div class="text-sm opacity-90">$<?php echo number_format($today_stats['ingresos'], 2); ?></div>
                </div>

                <div class="stat-card gradient-red">
                    <div class="stat-icon">💎</div>
                    <div class="stat-label">Valor Total</div>
                    <div class="stat-value">$<?php echo number_format(($cel_stats['valor_celulares'] + $prod_stats['valor_productos']) / 1000, 1); ?>K</div>
                    <div class="text-sm opacity-90">Inventario</div>
                </div>

            </div>

            <!-- Tables Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Top Celulares -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="text-lg font-semibold">📱 Top 5 Celulares</h3>
                    </div>
                    <?php if (empty($top_celulares)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <p>Sin datos de ventas</p>
                        </div>
                    <?php else: ?>
                        <?php $index = 0; foreach($top_celulares as $cel): $index++; ?>
                            <div class="table-row">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-teal-500 flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo $index; ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($cel['marca']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($cel['modelo']); ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900"><?php echo $cel['cantidad']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Top Productos -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="text-lg font-semibold">📦 Top 5 Productos</h3>
                    </div>
                    <?php if (empty($top_productos)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <p>Sin datos de ventas</p>
                        </div>
                    <?php else: ?>
                        <?php $index = 0; foreach($top_productos as $prod): $index++; ?>
                            <div class="table-row">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo $index; ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $prod['cantidad']; ?> unidades</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Accesos Rápidos -->
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">🚀 Accesos Rápidos</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    
                    <a href="sales.php" class="bg-white hover:bg-blue-50 rounded-lg p-4 text-center transition-all hover:shadow-lg">
                        <div class="w-12 h-12 mx-auto mb-2 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Vender Celular</p>
                    </a>

                    <a href="inventory.php" class="bg-white hover:bg-purple-50 rounded-lg p-4 text-center transition-all hover:shadow-lg">
                        <div class="w-12 h-12 mx-auto mb-2 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Inventario</p>
                    </a>

                    <a href="products.php" class="bg-white hover:bg-yellow-50 rounded-lg p-4 text-center transition-all hover:shadow-lg">
                        <div class="w-12 h-12 mx-auto mb-2 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Productos</p>
                    </a>

                    <a href="reports.php" class="bg-white hover:bg-red-50 rounded-lg p-4 text-center transition-all hover:shadow-lg">
                        <div class="w-12 h-12 mx-auto mb-2 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Reportes</p>
                    </a>

                    <?php if (hasPermission('admin')): ?>
                    <a href="users.php" class="bg-white hover:bg-green-50 rounded-lg p-4 text-center transition-all hover:shadow-lg">
                        <div class="w-12 h-12 mx-auto mb-2 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Usuarios</p>
                    </a>
                    <?php endif; ?>

                    <a href="../index.php" target="_blank" class="bg-white hover:bg-pink-50 rounded-lg p-4 text-center transition-all hover:shadow-lg">
                        <div class="w-12 h-12 mx-auto mb-2 bg-pink-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Catálogo</p>
                    </a>

                </div>
            </div>

        </div>
    </main>

    <script>
        console.log('✅ Dashboard cargado correctamente');
    </script>

</body>
</html>
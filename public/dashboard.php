<?php
/**
 * DASHBOARD PROFESIONAL - Sistema de Inventario
 * Versión 3.0 - Diseño Moderno y Elegante
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Obtener estadísticas
try {
    if ($user['rol'] === 'admin') {
        $stats_query = "SELECT * FROM vista_estadisticas_tienda ORDER BY tienda_nombre";
        $stats_stmt = $db->query($stats_query);
        $all_stats = $stats_stmt->fetchAll();
        
        $total_dispositivos = array_sum(array_column($all_stats, 'total_dispositivos'));
        $total_disponibles = array_sum(array_column($all_stats, 'disponibles'));
        $total_valor = array_sum(array_column($all_stats, 'valor_inventario'));
        $total_ventas = array_sum(array_column($all_stats, 'total_ventas'));
    } else {
        $stats_query = "SELECT * FROM vista_estadisticas_tienda WHERE tienda_id = ?";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$user['tienda_id']]);
        $tienda_stats = $stats_stmt->fetch();
        
        $total_dispositivos = $tienda_stats['total_dispositivos'] ?? 0;
        $total_disponibles = $tienda_stats['disponibles'] ?? 0;
        $total_valor = $tienda_stats['valor_inventario'] ?? 0;
        $total_ventas = $tienda_stats['total_ventas'] ?? 0;
        $all_stats = [];
    }
    
    // Ventas del día
    $today = date('Y-m-d');
    if ($user['rol'] === 'admin') {
        $today_query = "SELECT COUNT(*) as ventas_hoy, COALESCE(SUM(precio_venta), 0) as ingresos_hoy FROM ventas WHERE DATE(fecha_venta) = ?";
        $today_stmt = $db->prepare($today_query);
        $today_stmt->execute([$today]);
    } else {
        $today_query = "SELECT COUNT(*) as ventas_hoy, COALESCE(SUM(precio_venta), 0) as ingresos_hoy FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?";
        $today_stmt = $db->prepare($today_query);
        $today_stmt->execute([$today, $user['tienda_id']]);
    }
    $today_data = $today_stmt->fetch();
    
    // Ventas de la semana
    $week_start = date('Y-m-d', strtotime('monday this week'));
    if ($user['rol'] === 'admin') {
        $week_stmt = $db->prepare("SELECT DATE(fecha_venta) as fecha, COUNT(*) as ventas, SUM(precio_venta) as total FROM ventas WHERE fecha_venta >= ? GROUP BY DATE(fecha_venta) ORDER BY fecha");
        $week_stmt->execute([$week_start]);
    } else {
        $week_stmt = $db->prepare("SELECT DATE(fecha_venta) as fecha, COUNT(*) as ventas, SUM(precio_venta) as total FROM ventas WHERE fecha_venta >= ? AND tienda_id = ? GROUP BY DATE(fecha_venta) ORDER BY fecha");
        $week_stmt->execute([$week_start, $user['tienda_id']]);
    }
    $week_sales = $week_stmt->fetchAll();
    
    // Top productos vendidos
    if ($user['rol'] === 'admin') {
        $top_products = $db->query("SELECT c.marca, c.modelo, COUNT(*) as cantidad FROM ventas v JOIN celulares c ON v.celular_id = c.id GROUP BY c.marca, c.modelo ORDER BY cantidad DESC LIMIT 5")->fetchAll();
    } else {
        $top_stmt = $db->prepare("SELECT c.marca, c.modelo, COUNT(*) as cantidad FROM ventas v JOIN celulares c ON v.celular_id = c.id WHERE v.tienda_id = ? GROUP BY c.marca, c.modelo ORDER BY cantidad DESC LIMIT 5");
        $top_stmt->execute([$user['tienda_id']]);
        $top_products = $top_stmt->fetchAll();
    }
    
    // Distribución por condición
    if ($user['rol'] === 'admin') {
        $condition_stats = $db->query("SELECT condicion, COUNT(*) as cantidad FROM celulares WHERE estado = 'disponible' GROUP BY condicion")->fetchAll();
    } else {
        $condition_stmt = $db->prepare("SELECT condicion, COUNT(*) as cantidad FROM celulares WHERE estado = 'disponible' AND tienda_id = ? GROUP BY condicion");
        $condition_stmt->execute([$user['tienda_id']]);
        $condition_stats = $condition_stmt->fetchAll();
    }
    
} catch(Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
    $total_dispositivos = $total_disponibles = $total_valor = $total_ventas = 0;
    $today_data = ['ventas_hoy' => 0, 'ingresos_hoy' => 0];
    $week_sales = [];
    $top_products = [];
    $condition_stats = [];
    $all_stats = [];
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        body {
            background: #f9fafb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }
        
        .stat-card.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .stat-card.blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .stat-card.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .stat-card.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .stat-card.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
        
        .stat-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.2;
            font-size: 4rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1rem 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #1f2937;
        }
        
        .table-row {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .table-row:hover {
            background: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .progress-bar {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
            transition: width 1s ease;
        }
        
        @media (max-width: 768px) {
            .stat-value { font-size: 2rem; }
        }
    </style>
</head>
<body>
    
    <?php renderNavbar('dashboard'); ?>
    
    <main class="page-content">
        <div class="p-6">
            
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard</h1>
                <p class="text-gray-600">Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?> 
                    <?php if ($user['rol'] === 'admin'): ?>
                        <span class="badge badge-info">Administrador</span>
                    <?php else: ?>
                        <span class="badge badge-success"><?php echo htmlspecialchars($user['tienda_nombre']); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Stats Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total Dispositivos -->
                <div class="stat-card green">
                    <div class="stat-icon">📱</div>
                    <div class="stat-label">Total Dispositivos</div>
                    <div class="stat-value"><?php echo number_format($total_dispositivos); ?></div>
                    <div class="text-sm opacity-90">En inventario</div>
                </div>

                <!-- Disponibles -->
                <div class="stat-card blue">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Disponibles</div>
                    <div class="stat-value"><?php echo number_format($total_disponibles); ?></div>
                    <div class="text-sm opacity-90">
                        <?php 
                        $disponible_percent = $total_dispositivos > 0 ? round(($total_disponibles / $total_dispositivos) * 100, 1) : 0;
                        echo $disponible_percent . '%'; 
                        ?>
                    </div>
                </div>

                <!-- Valor Inventario -->
                <div class="stat-card orange">
                    <div class="stat-icon">💰</div>
                    <div class="stat-label">Valor Inventario</div>
                    <div class="stat-value">$<?php echo number_format($total_valor / 1000, 1); ?>K</div>
                    <div class="text-sm opacity-90">Total en stock</div>
                </div>

                <!-- Ventas Hoy -->
                <div class="stat-card red">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Ventas Hoy</div>
                    <div class="stat-value"><?php echo $today_data['ventas_hoy']; ?></div>
                    <div class="text-sm opacity-90">$<?php echo number_format($today_data['ingresos_hoy'], 2); ?></div>
                </div>

            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Ventas de la Semana -->
                <div class="chart-container">
                    <h3 class="chart-title">Ventas de la Semana</h3>
                    <canvas id="weekSalesChart" height="250"></canvas>
                </div>

                <!-- Distribución por Condición -->
                <div class="chart-container">
                    <h3 class="chart-title">Inventario por Condición</h3>
                    <canvas id="conditionChart" height="250"></canvas>
                </div>

            </div>

            <!-- Tables Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Top Productos -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="text-lg font-semibold">Top 5 Productos Más Vendidos</h3>
                    </div>
                    <?php if (empty($top_products)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <p class="font-medium">No hay datos de ventas</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($top_products as $index => $product): ?>
                            <div class="table-row">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($product['marca']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($product['modelo']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900"><?php echo $product['cantidad']; ?></div>
                                    <div class="text-xs text-gray-500">ventas</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Estadísticas por Tienda (Admin) -->
                <?php if ($user['rol'] === 'admin' && !empty($all_stats)): ?>
                    <div class="table-container">
                        <div class="table-header">
                            <h3 class="text-lg font-semibold">Rendimiento por Tienda</h3>
                        </div>
                        <?php foreach($all_stats as $stat): ?>
                            <div class="table-row flex-col items-start gap-2">
                                <div class="w-full flex justify-between items-center">
                                    <div class="font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($stat['tienda_nombre']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <?php echo $stat['total_ventas']; ?> ventas
                                    </div>
                                </div>
                                <div class="w-full grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <div class="text-gray-500 text-xs">Stock</div>
                                        <div class="font-medium"><?php echo $stat['disponibles']; ?>/<?php echo $stat['total_dispositivos']; ?></div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 text-xs">Valor</div>
                                        <div class="font-medium">$<?php echo number_format($stat['valor_inventario'], 0); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 text-xs">Disponibilidad</div>
                                        <div class="font-medium">
                                            <?php 
                                            $avail = $stat['total_dispositivos'] > 0 ? round(($stat['disponibles'] / $stat['total_dispositivos']) * 100) : 0;
                                            echo $avail . '%'; 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $avail; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Accesos Rápidos para Vendedor -->
                    <div class="table-container">
                        <div class="table-header">
                            <h3 class="text-lg font-semibold">Accesos Rápidos</h3>
                        </div>
                        <a href="sales.php" class="table-row hover:bg-blue-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Nueva Venta</div>
                                    <div class="text-sm text-gray-500">Registrar venta de celular</div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        
                        <a href="inventory.php" class="table-row hover:bg-green-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Ver Inventario</div>
                                    <div class="text-sm text-gray-500">Dispositivos disponibles</div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        
                        <a href="reports.php" class="table-row hover:bg-purple-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Ver Reportes</div>
                                    <div class="text-sm text-gray-500">Estadísticas detalladas</div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        // Datos para gráficos
        const weekSalesData = <?php echo json_encode($week_sales); ?>;
        const conditionData = <?php echo json_encode($condition_stats); ?>;

        // Gráfico de Ventas Semanales
        const weekLabels = weekSalesData.map(d => {
            const date = new Date(d.fecha);
            return date.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric' });
        });
        const weekValues = weekSalesData.map(d => parseFloat(d.total));

        new Chart(document.getElementById('weekSalesChart'), {
            type: 'bar',
            data: {
                labels: weekLabels.length > 0 ? weekLabels : ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Ingresos',
                    data: weekValues.length > 0 ? weekValues : [0, 0, 0, 0, 0, 0, 0],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Condición
        const conditionLabels = conditionData.map(d => d.condicion.charAt(0).toUpperCase() + d.condicion.slice(1));
        const conditionValues = conditionData.map(d => parseInt(d.cantidad));
        const conditionColors = ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6'];

        new Chart(document.getElementById('conditionChart'), {
            type: 'doughnut',
            data: {
                labels: conditionLabels.length > 0 ? conditionLabels : ['Sin datos'],
                datasets: [{
                    data: conditionValues.length > 0 ? conditionValues : [1],
                    backgroundColor: conditionLabels.length > 0 ? conditionColors : ['#e5e7eb'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        // Animación de contadores
        document.addEventListener('DOMContentLoaded', function() {
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100);
            });
        });
    </script>

</body>
</html>
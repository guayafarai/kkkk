<?php
/**
 * DASHBOARD
 * Sistema de Inventario con AdminLTE 4
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/components.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Obtener estadísticas
$stats = [
    'ventas_hoy' => ['cantidad' => 0, 'ingresos' => 0],
    'ventas_mes' => ['cantidad' => 0, 'ingresos' => 0],
    'celulares_disponibles' => 0,
    'productos_stock' => 0,
    'productos_bajo_stock' => 0
];

try {
    $hoy = date('Y-m-d');
    $mes_actual = date('Y-m');
    
    // Ventas de hoy
    if ($user['rol'] === 'admin') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ?
        ");
        $stmt->execute([$hoy]);
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ? AND tienda_id = ?
        ");
        $stmt->execute([$hoy, $user['tienda_id']]);
    }
    $stats['ventas_hoy'] = $stmt->fetch();
    
    // Ventas del mes
    if ($user['rol'] === 'admin') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?
        ");
        $stmt->execute([$mes_actual]);
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?
        ");
        $stmt->execute([$mes_actual, $user['tienda_id']]);
    }
    $stats['ventas_mes'] = $stmt->fetch();
    
    // Celulares disponibles
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("SELECT COUNT(*) as total FROM celulares WHERE estado = 'disponible'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM celulares WHERE estado = 'disponible' AND tienda_id = ?");
        $stmt->execute([$user['tienda_id']]);
    }
    $stats['celulares_disponibles'] = $stmt->fetchColumn();
    
    // Stock de productos
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("SELECT COALESCE(SUM(cantidad_actual), 0) as total FROM stock_productos");
    } else {
        $stmt = $db->prepare("SELECT COALESCE(SUM(cantidad_actual), 0) as total FROM stock_productos WHERE tienda_id = ?");
        $stmt->execute([$user['tienda_id']]);
    }
    $stats['productos_stock'] = $stmt->fetchColumn();
    
    // Productos con stock bajo
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("
            SELECT COUNT(DISTINCT s.producto_id) as total 
            FROM stock_productos s
            JOIN productos p ON s.producto_id = p.id
            WHERE s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0
        ");
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.producto_id) as total 
            FROM stock_productos s
            JOIN productos p ON s.producto_id = p.id
            WHERE s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0 AND s.tienda_id = ?
        ");
        $stmt->execute([$user['tienda_id']]);
    }
    $stats['productos_bajo_stock'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <?php renderSharedStyles(); ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php renderNavbar('dashboard', $user); ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Inicio</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Bienvenida -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-hand-wave"></i> ¡Hola, <?php echo htmlspecialchars($user['nombre']); ?>!</h5>
                            Bienvenido de nuevo al sistema. Aquí está el resumen de tu negocio.
                        </div>
                    </div>
                </div>

                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <!-- Ventas Hoy -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-gradient-warning">
                            <div class="inner">
                                <h3>S/ <?php echo number_format($stats['ventas_hoy']['ingresos'], 2); ?></h3>
                                <p>Ventas de Hoy</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            <a href="sales.php" class="small-box-footer">
                                Ver más <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Ventas Mes -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-gradient-primary">
                            <div class="inner">
                                <h3>S/ <?php echo number_format($stats['ventas_mes']['ingresos'], 0); ?></h3>
                                <p>Ventas del Mes</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <a href="reports.php" class="small-box-footer">
                                Ver más <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Celulares -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-gradient-success">
                            <div class="inner">
                                <h3><?php echo number_format($stats['celulares_disponibles']); ?></h3>
                                <p>Celulares Disponibles</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <a href="inventory.php" class="small-box-footer">
                                Ver más <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Stock Productos -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-gradient-info">
                            <div class="inner">
                                <h3><?php echo number_format($stats['productos_stock']); ?></h3>
                                <p>Productos en Stock</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <a href="products.php" class="small-box-footer">
                                Ver más <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Info boxes -->
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info elevation-1">
                                <i class="fas fa-shopping-cart"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Ventas Hoy</span>
                                <span class="info-box-number">
                                    <?php echo $stats['ventas_hoy']['cantidad']; ?>
                                    <small>transacciones</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-success elevation-1">
                                <i class="fas fa-calendar-check"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Ventas Mes</span>
                                <span class="info-box-number">
                                    <?php echo $stats['ventas_mes']['cantidad']; ?>
                                    <small>transacciones</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning elevation-1">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Stock Bajo</span>
                                <span class="info-box-number">
                                    <?php echo $stats['productos_bajo_stock']; ?>
                                    <small>productos</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger elevation-1">
                                <i class="fas fa-chart-pie"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Promedio Venta</span>
                                <span class="info-box-number">
                                    S/ <?php 
                                    $promedio = $stats['ventas_mes']['cantidad'] > 0 
                                        ? $stats['ventas_mes']['ingresos'] / $stats['ventas_mes']['cantidad'] 
                                        : 0;
                                    echo number_format($promedio, 0); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if ($stats['productos_bajo_stock'] > 0): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-warning alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Alerta de Stock!</h5>
                            Tienes <strong><?php echo $stats['productos_bajo_stock']; ?></strong> producto(s) con stock bajo.
                            <a href="products.php?stock=bajo" class="alert-link">Ver ahora</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cards principales -->
                <div class="row">
                    <!-- Accesos Rápidos -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <i class="fas fa-bolt"></i> Accesos Rápidos
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <a href="sales.php" class="btn btn-app w-100">
                                            <i class="fas fa-cash-register"></i> Vender Celular
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="product_sales.php" class="btn btn-app w-100">
                                            <i class="fas fa-shopping-cart"></i> Vender Producto
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="inventory.php" class="btn btn-app w-100">
                                            <i class="fas fa-mobile-alt"></i> Inventario
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="reports.php" class="btn btn-app w-100">
                                            <i class="fas fa-chart-bar"></i> Reportes
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actividad Reciente -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <i class="fas fa-history"></i> Actividad Reciente
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php
                                    try {
                                        if ($user['rol'] === 'admin') {
                                            $stmt = $db->query("
                                                SELECT c.modelo, v.precio_venta, v.fecha_venta, v.cliente_nombre
                                                FROM ventas v
                                                JOIN celulares c ON v.celular_id = c.id
                                                ORDER BY v.fecha_venta DESC
                                                LIMIT 5
                                            ");
                                        } else {
                                            $stmt = $db->prepare("
                                                SELECT c.modelo, v.precio_venta, v.fecha_venta, v.cliente_nombre
                                                FROM ventas v
                                                JOIN celulares c ON v.celular_id = c.id
                                                WHERE v.tienda_id = ?
                                                ORDER BY v.fecha_venta DESC
                                                LIMIT 5
                                            ");
                                            $stmt->execute([$user['tienda_id']]);
                                        }
                                        $actividades = $stmt->fetchAll();
                                        
                                        if (empty($actividades)) {
                                            echo '<li class="item"><span class="text-muted p-3 d-block">Sin actividad reciente</span></li>';
                                        } else {
                                            foreach ($actividades as $act) {
                                                $tiempo = time() - strtotime($act['fecha_venta']);
                                                if ($tiempo < 3600) {
                                                    $tiempo_texto = floor($tiempo / 60) . ' min';
                                                } elseif ($tiempo < 86400) {
                                                    $tiempo_texto = floor($tiempo / 3600) . ' h';
                                                } else {
                                                    $tiempo_texto = floor($tiempo / 86400) . ' días';
                                                }
                                                
                                                echo '<li class="item">';
                                                echo '<div class="product-img">';
                                                echo '<i class="fas fa-mobile-alt fa-2x text-primary"></i>';
                                                echo '</div>';
                                                echo '<div class="product-info">';
                                                echo '<span class="product-title">' . htmlspecialchars($act['modelo']) . '</span>';
                                                echo '<span class="product-description">';
                                                echo '<i class="fas fa-user"></i> ' . htmlspecialchars($act['cliente_nombre']);
                                                echo '</span>';
                                                echo '</div>';
                                                echo '<div class="product-info text-right">';
                                                echo '<span class="badge badge-success">S/ ' . number_format($act['precio_venta'], 0) . '</span><br>';
                                                echo '<small class="text-muted">Hace ' . $tiempo_texto . '</small>';
                                                echo '</div>';
                                                echo '</li>';
                                            }
                                        }
                                    } catch (Exception $e) {
                                        echo '<li class="item"><span class="text-danger p-3 d-block">Error al cargar actividad</span></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <a href="reports.php" class="uppercase">Ver Todos los Reportes</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Sistema -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline card-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-circle text-success"></i>
                                        <strong>Sistema Activo</strong> | 
                                        <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>
                                    </div>
                                    <div class="text-muted">
                                        <small>Última actualización: <?php echo date('d/m/Y H:i'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#"><?php echo SYSTEM_NAME; ?></a>.</strong>
        Todos los derechos reservados.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> <?php echo SYSTEM_VERSION; ?>
        </div>
    </footer>
</div>

<?php renderCommonScripts(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Dashboard cargado con AdminLTE 4');
    console.log('💰 Moneda: Soles (S/)');
    
    // Animar valores
    const statValues = document.querySelectorAll('.small-box h3');
    statValues.forEach(element => {
        const text = element.textContent;
        const hasNumber = text.match(/[\d,\.]+/);
        
        if (hasNumber && window.animateValue) {
            const number = parseFloat(hasNumber[0].replace(/,/g, ''));
            if (!isNaN(number) && number > 0) {
                element.textContent = text.replace(/[\d,\.]+/, '0');
                
                const hasSoles = text.includes('S/');
                window.animateValue(element, 0, number, 1000, {
                    prefix: hasSoles ? 'S/ ' : '',
                    decimals: hasSoles ? 2 : 0
                });
            }
        }
    });
});

<?php if (isset($_GET['welcome'])): ?>
setTimeout(() => {
    $(document).Toasts('create', {
        class: 'bg-success',
        title: 'Bienvenido',
        subtitle: 'Sistema',
        body: '¡Hola <?php echo htmlspecialchars($user['nombre']); ?>! 👋',
        autohide: true,
        delay: 3000
    });
}, 500);
<?php endif; ?>
</script>

</body>
</html>
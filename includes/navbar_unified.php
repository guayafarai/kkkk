<?php
/**
 * NAVBAR/SIDEBAR UNIFICADO
 * Sistema de Inventario con AdminLTE 4
 */

if (!function_exists('renderNavbar')) {
    function renderNavbar($current_page = '', $user = null) {
        if (!$user) {
            $user = getCurrentUser();
        }
        
        if (!$user) return;
        
        $is_admin = $user['rol'] === 'admin';
        ?>
        
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <!-- Notificaciones -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-bell"></i>
                        <span class="badge badge-warning navbar-badge">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <span class="dropdown-item dropdown-header">Sin notificaciones</span>
                    </div>
                </li>

                <!-- Usuario -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-user"></i>
                        <span class="d-none d-md-inline ml-1"><?php echo htmlspecialchars($user['nombre']); ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <div class="dropdown-item">
                            <strong><?php echo htmlspecialchars($user['nombre']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo $is_admin ? 'Administrador' : 'Vendedor'; ?>
                                <?php if (!$is_admin && $user['tienda_nombre']): ?>
                                    <br><?php echo htmlspecialchars($user['tienda_nombre']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Mi Perfil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                        </a>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Main Sidebar -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <!-- Brand Logo -->
            <a href="dashboard.php" class="brand-link">
                <i class="fas fa-mobile-alt brand-image"></i>
                <span class="brand-text font-weight-light"><?php echo SYSTEM_NAME; ?></span>
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image">
                        <div class="img-circle elevation-2 d-flex align-items-center justify-center bg-white" style="width: 34px; height: 34px;">
                            <span class="text-primary font-weight-bold">
                                <?php echo strtoupper(substr($user['nombre'], 0, 2)); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info">
                        <a href="profile.php" class="d-block"><?php echo htmlspecialchars($user['nombre']); ?></a>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        <!-- VENTAS -->
                        <li class="nav-header">VENTAS</li>
                        
                        <li class="nav-item">
                            <a href="sales.php" class="nav-link <?php echo $current_page === 'sales' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-cash-register"></i>
                                <p>Vender Celulares</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="product_sales.php" class="nav-link <?php echo $current_page === 'product_sales' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-shopping-cart"></i>
                                <p>Vender Productos</p>
                            </a>
                        </li>

                        <!-- INVENTARIO -->
                        <li class="nav-header">INVENTARIO</li>
                        
                        <li class="nav-item">
                            <a href="inventory.php" class="nav-link <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-mobile-alt"></i>
                                <p>Celulares</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="products.php" class="nav-link <?php echo $current_page === 'products' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-box"></i>
                                <p>Productos y Stock</p>
                            </a>
                        </li>

                        <!-- REPORTES -->
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Reportes</p>
                            </a>
                        </li>

                        <?php if ($is_admin): ?>
                        <!-- ADMINISTRACIÓN -->
                        <li class="nav-header">ADMINISTRACIÓN</li>

                        <li class="nav-item">
                            <a href="users.php" class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Usuarios</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="stores.php" class="nav-link <?php echo $current_page === 'stores' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-store"></i>
                                <p>Tiendas</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="categories.php" class="nav-link <?php echo $current_page === 'categories' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Categorías</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="catalog_settings.php" class="nav-link <?php echo $current_page === 'catalog_settings' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-cog"></i>
                                <p>Config. Catálogo</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="activity_logs.php" class="nav-link <?php echo $current_page === 'activity_logs' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-history"></i>
                                <p>Logs</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- CATÁLOGO -->
                        <li class="nav-header">PÚBLICO</li>
                        
                        <li class="nav-item">
                            <a href="../index.php" target="_blank" class="nav-link">
                                <i class="nav-icon fas fa-eye"></i>
                                <p>
                                    Ver Catálogo
                                    <i class="fas fa-external-link-alt ml-auto"></i>
                                </p>
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>
        </aside>
        <?php
    }
}

<?php
/**
 * Navbar/Sidebar Unificado
 * Sistema adaptativo según el rol del usuario
 * VERSIÓN CORREGIDA - Sin duplicados
 */

if (!function_exists('renderNavbar')) {
    function renderNavbar($current_page = '') {
        $user = getCurrentUser();
        if (!$user) return;
        
        $is_admin = $user['rol'] === 'admin';
        
        // Definir elementos del menú según el rol
        $menu_items = [];
        
        // Items comunes para todos
        $menu_items[] = [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'url' => 'dashboard.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        // VENTAS
        $menu_items[] = [
            'id' => 'sales',
            'label' => 'Ventas de Celulares',
            'url' => 'sales.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        $menu_items[] = [
            'id' => 'product_sales',
            'label' => 'Ventas de Productos',
            'url' => 'product_sales.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        // INVENTARIO
        $menu_items[] = [
            'id' => 'inventory',
            'label' => 'Inventario Celulares',
            'url' => 'inventory.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        $menu_items[] = [
            'id' => 'products',
            'label' => 'Productos y Stock',
            'url' => 'products.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        // REPORTES
        $menu_items[] = [
            'id' => 'reports',
            'label' => 'Reportes',
            'url' => 'reports.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        // ✅ CORREGIDO: CONFIGURACIÓN - Solo una vez
        if ($is_admin) {
            $menu_items[] = [
                'id' => 'divider_config',
                'type' => 'divider',
                'label' => 'CONFIGURACIÓN',
                'roles' => ['admin']
            ];
            
            $menu_items[] = [
                'id' => 'users',
                'label' => 'Usuarios',
                'url' => 'users.php',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>',
                'roles' => ['admin']
            ];
            
            $menu_items[] = [
                'id' => 'stores',
                'label' => 'Tiendas',
                'url' => 'stores.php',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>',
                'roles' => ['admin']
            ];
            
            $menu_items[] = [
                'id' => 'categories',
                'label' => 'Categorías',
                'url' => 'categories.php',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>',
                'roles' => ['admin']
            ];
            
            $menu_items[] = [
                'id' => 'catalog_settings',
                'label' => 'Config. Catálogo',
                'url' => 'catalog_settings.php',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
                'roles' => ['admin']
            ];
            
            $menu_items[] = [
                'id' => 'company_config',
                'label' => 'Config. Empresa',
                'url' => 'company_config.php',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>',
                'roles' => ['admin']
            ];
            
            $menu_items[] = [
                'id' => 'activity_logs',
                'label' => 'Logs de Actividad',
                'url' => 'activity_logs.php',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
                'roles' => ['admin']
            ];
        }
        
        // CATÁLOGO PÚBLICO
        $menu_items[] = [
            'id' => 'divider_catalogo',
            'type' => 'divider',
            'label' => 'CATÁLOGO PÚBLICO',
            'roles' => ['admin', 'vendedor']
        ];
        
        $menu_items[] = [
            'id' => 'catalog',
            'label' => 'Ver Catálogo',
            'url' => '../index.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>',
            'target' => '_blank',
            'roles' => ['admin', 'vendedor']
        ];
        
        // PERFIL Y LOGOUT
        $menu_items[] = [
            'id' => 'divider_cuenta',
            'type' => 'divider',
            'label' => 'MI CUENTA',
            'roles' => ['admin', 'vendedor']
        ];
        
        $menu_items[] = [
            'id' => 'profile',
            'label' => 'Mi Perfil',
            'url' => 'profile.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        $menu_items[] = [
            'id' => 'logout',
            'label' => 'Cerrar Sesión',
            'url' => 'logout.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>',
            'roles' => ['admin', 'vendedor']
        ];
        
        ?>
        <!-- Navbar/Sidebar -->
        <style>
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 260px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                z-index: 1000;
                overflow-y: auto;
                transition: transform 0.3s ease;
            }
            
            .sidebar::-webkit-scrollbar {
                width: 6px;
            }
            
            .sidebar::-webkit-scrollbar-track {
                background: rgba(255, 255, 255, 0.1);
            }
            
            .sidebar::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.3);
                border-radius: 3px;
            }
            
            .page-content {
                margin-left: 260px;
                min-height: 100vh;
            }
            
            .nav-item {
                transition: all 0.2s ease;
            }
            
            .nav-item:hover {
                background: rgba(255, 255, 255, 0.1);
                transform: translateX(5px);
            }
            
            .nav-item.active {
                background: rgba(255, 255, 255, 0.2);
                border-left: 4px solid #fff;
            }
            
            .nav-divider {
                font-size: 0.7rem;
                font-weight: 700;
                color: rgba(255, 255, 255, 0.6);
                padding: 1rem 1.5rem 0.5rem;
                letter-spacing: 0.1em;
            }
            
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                }
                
                .sidebar.mobile-open {
                    transform: translateX(0);
                }
                
                .page-content {
                    margin-left: 0;
                }
                
                .mobile-overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 999;
                }
                
                .mobile-overlay.show {
                    display: block;
                }
            }
        </style>
        
        <div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>
        
        <button onclick="toggleSidebar()" class="fixed top-4 left-4 z-50 md:hidden bg-purple-600 text-white p-3 rounded-lg shadow-lg">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        
        <aside class="sidebar" id="sidebar">
            <div class="p-6 border-b border-white border-opacity-20">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-white"><?php echo SYSTEM_NAME; ?></h1>
                        <p class="text-xs text-purple-200">v<?php echo SYSTEM_VERSION; ?></p>
                    </div>
                    <button onclick="closeSidebar()" class="md:hidden text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-4 border-b border-white border-opacity-20">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center mr-3">
                        <span class="text-sm font-bold text-white">
                            <?php echo strtoupper(substr($user['nombre'], 0, 2)); ?>
                        </span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($user['nombre']); ?></p>
                        <p class="text-xs text-purple-200">
                            <?php echo $is_admin ? 'Administrador' : 'Vendedor'; ?>
                            <?php if (!$is_admin && $user['tienda_nombre']): ?>
                                <br><?php echo htmlspecialchars($user['tienda_nombre']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <nav class="py-4">
                <?php foreach ($menu_items as $item): ?>
                    <?php if (!in_array($user['rol'], $item['roles'])): continue; endif; ?>
                    
                    <?php if (isset($item['type']) && $item['type'] === 'divider'): ?>
                        <div class="nav-divider"><?php echo $item['label']; ?></div>
                    <?php else: ?>
                        <a href="<?php echo $item['url']; ?>" 
                           <?php echo isset($item['target']) ? 'target="' . $item['target'] . '"' : ''; ?>
                           class="nav-item flex items-center px-6 py-3 text-white text-sm <?php echo $current_page === $item['id'] ? 'active' : ''; ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?php echo $item['icon']; ?>
                            </svg>
                            <?php echo $item['label']; ?>
                            <?php if (isset($item['target']) && $item['target'] === '_blank'): ?>
                                <svg class="w-4 h-4 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            
            <div class="p-4 border-t border-white border-opacity-20 mt-auto">
                <p class="text-xs text-purple-200 text-center">
                    © <?php echo date('Y'); ?> ChamoTV<br>
                    Todos los derechos reservados
                </p>
            </div>
        </aside>
        
        <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobileOverlay');
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('show');
            }
            
            function closeSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobileOverlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
            }
            
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 768) {
                        closeSidebar();
                    }
                });
            });
        </script>
        <?php
    }
}
?>
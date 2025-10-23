<?php
/**
 * ESTILOS DEL SISTEMA - SOLO RECURSOS LOCALES
 * Sistema de Inventario de Celulares con AdminLTE 4 RC5
 * 
 * INSTRUCCIONES:
 * 1. Descarga Font Awesome 6.5.1 de: https://fontawesome.com/download
 * 2. Descarga AdminLTE 4 RC5 de: https://github.com/ColorlibHQ/AdminLTE/releases
 * 3. Colócalos en: /public/assets/fontawesome/ y /public/assets/adminlte/
 */

if (!function_exists('renderSharedStyles')) {
    function renderSharedStyles() {
        // Determinar si usar CDN o archivos locales
        $useLocal = true; // Cambiar a false si los CDN funcionan
        
        if ($useLocal) {
            // Rutas locales
            $fontAwesomePath = '../assets/fontawesome/css/all.min.css';
            $adminLTEPath = '../assets/adminlte/adminlte.min.css';
            
            // Verificar si existen los archivos locales
            $rootPath = dirname(dirname(__FILE__));
            $faExists = file_exists($rootPath . '/public/assets/fontawesome/css/all.min.css');
            $alExists = file_exists($rootPath . '/public/assets/adminlte/adminlte.min.css');
            
            ?>
            <?php if ($faExists): ?>
            <!-- Font Awesome 6 Local -->
            <link rel="stylesheet" href="<?php echo $fontAwesomePath; ?>">
            <?php else: ?>
            <!-- Font Awesome 6 desde CDN (fallback) -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
            <?php endif; ?>
            
            <?php if ($alExists): ?>
            <!-- AdminLTE 4 Local -->
            <link rel="stylesheet" href="<?php echo $adminLTEPath; ?>">
            <?php else: ?>
            <!-- AdminLTE 4 desde CDN (fallback) -->
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc.5/dist/css/adminlte.min.css" integrity="sha256-example" crossorigin="anonymous">
            <?php endif; ?>
            <?php
        } else {
            ?>
            <!-- Font Awesome 6 desde CDN -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
            
            <!-- AdminLTE 4 desde CDN -->
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc.5/dist/css/adminlte.min.css" crossorigin="anonymous">
            <?php
        }
        ?>
        
        <!-- Estilos personalizados INLINE (evita problemas de CSP) -->
        <style nonce="<?php echo base64_encode(random_bytes(16)); ?>">
            :root {
                --primary-color: #667eea;
                --secondary-color: #764ba2;
                --success-color: #10b981;
                --danger-color: #ef4444;
                --warning-color: #f59e0b;
                --info-color: #3b82f6;
            }

            /* Degradados personalizados */
            .bg-gradient-primary {
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
            }

            .bg-gradient-success {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            }

            .bg-gradient-danger {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            }

            .bg-gradient-warning {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            }

            .bg-gradient-info {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            }

            /* Sidebar personalizado */
            .main-sidebar {
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            }

            .nav-sidebar .nav-link {
                color: rgba(255, 255, 255, 0.9);
            }

            .nav-sidebar .nav-link:hover {
                background-color: rgba(255, 255, 255, 0.1);
                color: white;
            }

            .nav-sidebar .nav-link.active {
                background-color: rgba(255, 255, 255, 0.2);
                color: white;
            }

            .brand-link {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            /* Cards mejoradas */
            .card {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border: none;
                border-radius: 0.5rem;
                transition: all 0.3s ease;
            }

            .card:hover {
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                transform: translateY(-2px);
            }

            .card-header {
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                background-color: #f8f9fa;
            }

            /* Info Box mejorada */
            .info-box {
                border-radius: 0.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .info-box-icon {
                border-radius: 0.5rem 0 0 0.5rem;
            }

            /* Small Box mejorada */
            .small-box {
                border-radius: 0.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
            }

            .small-box:hover {
                transform: translateY(-4px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            }

            .small-box .icon {
                font-size: 70px;
                top: 15px;
                right: 15px;
            }

            /* Botones personalizados */
            .btn {
                border-radius: 0.375rem;
                padding: 0.5rem 1rem;
                transition: all 0.2s ease;
            }

            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            }

            /* Tarjetas de producto/dispositivo */
            .product-card {
                border: 2px solid #e5e7eb;
                border-radius: 0.75rem;
                padding: 1rem;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white;
            }

            .product-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
                border-color: var(--primary-color);
            }

            .product-card.selected {
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                border-color: var(--success-color);
                box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            }

            /* Badges mejorados */
            .badge {
                padding: 0.35em 0.65em;
                border-radius: 0.375rem;
                font-weight: 600;
            }

            /* Animaciones */
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .animate-in {
                animation: fadeIn 0.5s ease-out;
            }

            /* Estados de stock */
            .stock-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                font-weight: 600;
            }

            .stock-badge.sin-stock {
                background: #fee2e2;
                color: #991b1b;
            }

            .stock-badge.bajo-stock {
                background: #fef3c7;
                color: #92400e;
            }

            .stock-badge.stock-normal {
                background: #dcfce7;
                color: #065f46;
            }

            /* Tabla responsiva */
            .table-responsive {
                border-radius: 0.5rem;
                overflow: hidden;
            }

            .table thead th {
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                color: white;
                font-weight: 600;
                border: none;
            }

            .table tbody tr:hover {
                background-color: #f8f9fa;
            }

            /* Loading overlay */
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.95);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                backdrop-filter: blur(4px);
            }

            /* Empty state */
            .empty-state {
                text-align: center;
                padding: 3rem 1rem;
            }

            .empty-state i {
                font-size: 4rem;
                color: #d1d5db;
                margin-bottom: 1rem;
            }

            .empty-state h3 {
                color: #6b7280;
                font-size: 1.25rem;
                margin-bottom: 0.5rem;
            }

            .empty-state p {
                color: #9ca3af;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .small-box .icon {
                    font-size: 50px;
                }

                .content-wrapper {
                    padding: 1rem;
                }
            }

            .shadow-sm { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
            .shadow { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
            .shadow-lg { box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); }

            .rounded { border-radius: 0.375rem; }
            .rounded-lg { border-radius: 0.5rem; }
            .rounded-xl { border-radius: 0.75rem; }
        </style>
        
        <?php if (!$faExists || !$alExists): ?>
        <!-- Aviso de recursos faltantes -->
        <script nonce="<?php echo base64_encode(random_bytes(16)); ?>">
        console.warn('⚠️ Recursos locales no encontrados. Usando CDN como fallback.');
        console.info('📥 Descarga los recursos y colócalos en:');
        console.info('   - /public/assets/fontawesome/css/all.min.css');
        console.info('   - /public/assets/adminlte/adminlte.min.css');
        </script>
        <?php endif; ?>
        <?php
    }
}

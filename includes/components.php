<?php
/**
 * COMPONENTES REUTILIZABLES
 * Sistema de Inventario con AdminLTE 4
 */

// Cargar dependencias solo si no están cargadas
if (!function_exists('renderSharedStyles')) {
    require_once __DIR__ . '/styles.php';
}

if (!function_exists('renderNavbar')) {
    require_once __DIR__ . '/navbar_unified.php';
}

// ============================================================================
// SMALL BOXES (Stats Cards)
// ============================================================================

function renderSmallBox($title, $value, $icon, $color = 'primary', $link = '#', $linkText = 'Más información') {
    ?>
    <div class="small-box bg-<?php echo $color; ?>">
        <div class="inner">
            <h3><?php echo htmlspecialchars($value); ?></h3>
            <p><?php echo htmlspecialchars($title); ?></p>
        </div>
        <div class="icon">
            <i class="<?php echo $icon; ?>"></i>
        </div>
        <a href="<?php echo $link; ?>" class="small-box-footer">
            <?php echo htmlspecialchars($linkText); ?> <i class="fas fa-arrow-circle-right"></i>
        </a>
    </div>
    <?php
}

// ============================================================================
// INFO BOXES
// ============================================================================

function renderInfoBox($title, $value, $icon, $color = 'info', $subtitle = '') {
    ?>
    <div class="info-box">
        <span class="info-box-icon bg-<?php echo $color; ?> elevation-1">
            <i class="<?php echo $icon; ?>"></i>
        </span>
        <div class="info-box-content">
            <span class="info-box-text"><?php echo htmlspecialchars($title); ?></span>
            <span class="info-box-number">
                <?php echo htmlspecialchars($value); ?>
                <?php if ($subtitle): ?>
                    <small><?php echo htmlspecialchars($subtitle); ?></small>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php
}

// ============================================================================
// CARDS
// ============================================================================

function renderCard($title, $content, $options = []) {
    $headerClass = $options['headerClass'] ?? '';
    $footerContent = $options['footer'] ?? '';
    $collapsible = $options['collapsible'] ?? false;
    $tools = $options['tools'] ?? '';
    ?>
    <div class="card <?php echo $options['cardClass'] ?? ''; ?>">
        <?php if ($title): ?>
        <div class="card-header <?php echo $headerClass; ?>">
            <h3 class="card-title"><?php echo $title; ?></h3>
            <?php if ($collapsible || $tools): ?>
            <div class="card-tools">
                <?php echo $tools; ?>
                <?php if ($collapsible): ?>
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="card-body">
            <?php echo $content; ?>
        </div>
        <?php if ($footerContent): ?>
        <div class="card-footer">
            <?php echo $footerContent; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// ALERTS
// ============================================================================

function renderAlert($message, $type = 'info', $dismissible = true, $icon = '') {
    $icons = [
        'success' => 'fas fa-check-circle',
        'danger' => 'fas fa-exclamation-triangle',
        'warning' => 'fas fa-exclamation-circle',
        'info' => 'fas fa-info-circle'
    ];
    
    $iconClass = $icon ?: ($icons[$type] ?? 'fas fa-info-circle');
    ?>
    <div class="alert alert-<?php echo $type; ?> <?php echo $dismissible ? 'alert-dismissible' : ''; ?>">
        <?php if ($dismissible): ?>
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <?php endif; ?>
        <h5><i class="icon <?php echo $iconClass; ?>"></i> Atención!</h5>
        <?php echo $message; ?>
    </div>
    <?php
}

// ============================================================================
// BADGES
// ============================================================================

function renderBadge($text, $color = 'primary') {
    echo '<span class="badge badge-' . $color . '">' . htmlspecialchars($text) . '</span>';
}

function renderStockBadge($quantity, $minStock = 10) {
    if ($quantity == 0) {
        echo '<span class="stock-badge sin-stock"><i class="fas fa-times-circle"></i> Sin Stock</span>';
    } elseif ($quantity <= $minStock) {
        echo '<span class="stock-badge bajo-stock"><i class="fas fa-exclamation-circle"></i> Stock Bajo (' . $quantity . ')</span>';
    } else {
        echo '<span class="stock-badge stock-normal"><i class="fas fa-check-circle"></i> Stock Normal (' . $quantity . ')</span>';
    }
}

// ============================================================================
// MODALES
// ============================================================================

function renderModal($id, $title, $content, $options = []) {
    $size = $options['size'] ?? 'lg';
    $footerButtons = $options['footer'] ?? '';
    ?>
    <div class="modal fade" id="<?php echo $id; ?>" tabindex="-1">
        <div class="modal-dialog modal-<?php echo $size; ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><?php echo $title; ?></h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <?php echo $content; ?>
                </div>
                <?php if ($footerButtons): ?>
                <div class="modal-footer justify-content-between">
                    <?php echo $footerButtons; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================================
// TABLAS
// ============================================================================

function renderDataTable($columns, $data, $options = []) {
    $tableId = $options['id'] ?? 'dataTable_' . uniqid();
    $striped = $options['striped'] ?? true;
    $bordered = $options['bordered'] ?? true;
    $hover = $options['hover'] ?? true;
    $responsive = $options['responsive'] ?? true;
    
    $tableClasses = ['table'];
    if ($striped) $tableClasses[] = 'table-striped';
    if ($bordered) $tableClasses[] = 'table-bordered';
    if ($hover) $tableClasses[] = 'table-hover';
    
    $tableClass = implode(' ', $tableClasses);
    ?>
    <?php if ($responsive): ?>
    <div class="table-responsive">
    <?php endif; ?>
    
    <table id="<?php echo $tableId; ?>" class="<?php echo $tableClass; ?>">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                <th><?php echo htmlspecialchars($col['label']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
            <tr>
                <td colspan="<?php echo count($columns); ?>" class="text-center text-muted">
                    No hay datos disponibles
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <td>
                        <?php 
                        $value = $row[$col['field']] ?? '';
                        if (isset($col['render'])) {
                            echo $col['render']($value, $row);
                        } else {
                            echo htmlspecialchars($value);
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($responsive): ?>
    </div>
    <?php endif; ?>
    <?php
}

// ============================================================================
// FORMULARIOS
// ============================================================================

function renderFormField($type, $name, $label, $options = []) {
    $value = $options['value'] ?? '';
    $placeholder = $options['placeholder'] ?? '';
    $required = $options['required'] ?? false;
    $help = $options['help'] ?? '';
    $class = $options['class'] ?? '';
    ?>
    <div class="form-group <?php echo $class; ?>">
        <label for="<?php echo $name; ?>">
            <?php echo htmlspecialchars($label); ?>
            <?php if ($required): ?>
                <span class="text-danger">*</span>
            <?php endif; ?>
        </label>
        
        <?php if ($type === 'textarea'): ?>
            <textarea 
                id="<?php echo $name; ?>" 
                name="<?php echo $name; ?>"
                class="form-control"
                placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                <?php echo $required ? 'required' : ''; ?>
                rows="<?php echo $options['rows'] ?? 3; ?>"
            ><?php echo htmlspecialchars($value); ?></textarea>
            
        <?php elseif ($type === 'select'): ?>
            <select 
                id="<?php echo $name; ?>" 
                name="<?php echo $name; ?>"
                class="form-control"
                <?php echo $required ? 'required' : ''; ?>
            >
                <?php if (!empty($options['options'])): ?>
                    <?php foreach ($options['options'] as $optValue => $optLabel): ?>
                        <option value="<?php echo htmlspecialchars($optValue); ?>" 
                                <?php echo ($value == $optValue) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($optLabel); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            
        <?php else: ?>
            <input 
                type="<?php echo $type; ?>"
                id="<?php echo $name; ?>" 
                name="<?php echo $name; ?>"
                value="<?php echo htmlspecialchars($value); ?>"
                placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                class="form-control"
                <?php echo $required ? 'required' : ''; ?>
                <?php if (!empty($options['min'])): ?>min="<?php echo $options['min']; ?>"<?php endif; ?>
                <?php if (!empty($options['max'])): ?>max="<?php echo $options['max']; ?>"<?php endif; ?>
                <?php if (!empty($options['step'])): ?>step="<?php echo $options['step']; ?>"<?php endif; ?>
            />
        <?php endif; ?>
        
        <?php if ($help): ?>
            <small class="form-text text-muted"><?php echo htmlspecialchars($help); ?></small>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// BÚSQUEDA
// ============================================================================

function renderSearchBox($id, $placeholder = 'Buscar...', $onSearch = 'handleSearch') {
    ?>
    <div class="input-group">
        <input 
            type="text" 
            id="<?php echo $id; ?>"
            class="form-control"
            placeholder="<?php echo htmlspecialchars($placeholder); ?>"
            oninput="<?php echo $onSearch; ?>(this.value)"
        />
        <div class="input-group-append">
            <button 
                type="button" 
                id="<?php echo $id; ?>_clear"
                class="btn btn-default"
                onclick="clearSearch('<?php echo $id; ?>')"
                style="display: none;"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <script>
    document.getElementById('<?php echo $id; ?>').addEventListener('input', function() {
        const clearBtn = document.getElementById('<?php echo $id; ?>_clear');
        clearBtn.style.display = this.value ? 'block' : 'none';
    });
    
    function clearSearch(id) {
        document.getElementById(id).value = '';
        document.getElementById(id + '_clear').style.display = 'none';
        <?php echo $onSearch; ?>('');
    }
    </script>
    <?php
}

// ============================================================================
// EMPTY STATE
// ============================================================================

function renderEmptyState($title, $description, $icon = 'fas fa-inbox', $actionButton = '') {
    ?>
    <div class="empty-state">
        <i class="<?php echo $icon; ?>"></i>
        <h3><?php echo htmlspecialchars($title); ?></h3>
        <p><?php echo htmlspecialchars($description); ?></p>
        <?php if ($actionButton): ?>
            <?php echo $actionButton; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// LOADING SPINNER
// ============================================================================

function renderLoadingSpinner($id = 'loadingSpinner') {
    ?>
    <div id="<?php echo $id; ?>" class="loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="sr-only">Cargando...</span>
        </div>
    </div>
    <?php
}

// ============================================================================
// BREADCRUMBS
// ============================================================================

function renderBreadcrumbs($items) {
    ?>
    <ol class="breadcrumb float-sm-right">
        <?php foreach ($items as $item): ?>
            <?php if (isset($item['url'])): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo $item['url']; ?>"><?php echo htmlspecialchars($item['label']); ?></a>
                </li>
            <?php else: ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($item['label']); ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
    <?php
}

// ============================================================================
// CALLOUTS
// ============================================================================

function renderCallout($title, $content, $type = 'info') {
    ?>
    <div class="callout callout-<?php echo $type; ?>">
        <h5><?php echo htmlspecialchars($title); ?></h5>
        <p><?php echo $content; ?></p>
    </div>
    <?php
}

// ============================================================================
// TIMELINE
// ============================================================================

function renderTimeline($items) {
    ?>
    <div class="timeline">
        <?php foreach ($items as $item): ?>
        <div class="time-label">
            <span class="bg-<?php echo $item['color'] ?? 'primary'; ?>">
                <?php echo htmlspecialchars($item['date'] ?? ''); ?>
            </span>
        </div>
        <div>
            <i class="<?php echo $item['icon'] ?? 'fas fa-clock'; ?> bg-<?php echo $item['color'] ?? 'primary'; ?>"></i>
            <div class="timeline-item">
                <?php if (isset($item['time'])): ?>
                <span class="time"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($item['time']); ?></span>
                <?php endif; ?>
                <h3 class="timeline-header"><?php echo $item['title']; ?></h3>
                <?php if (isset($item['body'])): ?>
                <div class="timeline-body">
                    <?php echo $item['body']; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div>
            <i class="fas fa-clock bg-gray"></i>
        </div>
    </div>
    <?php
}

// ============================================================================
// STATS CARD (Alternativa a Small Box)
// ============================================================================

function renderStatCard($value, $label, $options = []) {
    $icon = $options['icon'] ?? '';
    $color = $options['color'] ?? 'blue';
    $change = $options['change'] ?? null;
    $changeType = $options['changeType'] ?? 'positive';
    
    $bgClass = 'bg-gradient-' . $color;
    ?>
    <div class="small-box <?php echo $bgClass; ?>">
        <div class="inner">
            <h3><?php echo htmlspecialchars($value); ?></h3>
            <p><?php echo htmlspecialchars($label); ?></p>
            <?php if ($change !== null): ?>
            <small class="text-white">
                <?php if ($changeType === 'positive'): ?>
                    <i class="fas fa-arrow-up"></i>
                <?php else: ?>
                    <i class="fas fa-arrow-down"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($change); ?>
            </small>
            <?php endif; ?>
        </div>
        <?php if ($icon): ?>
        <div class="icon">
            <?php echo $icon; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// PAGINACIÓN
// ============================================================================

function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return;
    
    ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($currentPage > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $baseUrl; ?>?page=<?php echo $currentPage - 1; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $currentPage - 2);
            $end = min($totalPages, $currentPage + 2);
            
            if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?php echo $baseUrl; ?>?page=1">1</a></li>
                <?php if ($start > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo $baseUrl; ?>?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?php echo $baseUrl; ?>?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
            <?php endif; ?>
            
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $baseUrl; ?>?page=<?php echo $currentPage + 1; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php
}

// ============================================================================
// UTILIDADES DE FORMATO
// ============================================================================

function formatPricePHP($price) {
    return 'S/ ' . number_format($price, 2);
}

function formatDatePHP($date, $format = 'short') {
    $timestamp = strtotime($date);
    
    switch ($format) {
        case 'short':
            return date('d/m/Y', $timestamp);
        case 'long':
            return strftime('%d de %B de %Y', $timestamp);
        case 'full':
            return date('d/m/Y H:i', $timestamp);
        default:
            return date('d/m/Y', $timestamp);
    }
}

function timeAgoPHP($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Hace un momento';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' minutos';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' horas';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';
    if ($diff < 2592000) return 'Hace ' . floor($diff / 604800) . ' semanas';
    if ($diff < 31536000) return 'Hace ' . floor($diff / 2592000) . ' meses';
    return 'Hace ' . floor($diff / 31536000) . ' años';
}

// ============================================================================
// SCRIPTS COMUNES
// ============================================================================

function renderCommonScripts() {
    // Determinar si usar CDN o archivos locales
    $useLocal = true;
    $rootPath = dirname(dirname(__FILE__));
    
    $jqueryExists = file_exists($rootPath . '/public/assets/js/jquery.min.js');
    $bootstrapExists = file_exists($rootPath . '/public/assets/js/bootstrap.bundle.min.js');
    $adminlteExists = file_exists($rootPath . '/public/assets/adminlte/adminlte.min.js');
    
    ?>
    <?php if ($useLocal && $jqueryExists): ?>
    <!-- jQuery Local -->
    <script src="../assets/js/jquery.min.js"></script>
    <?php else: ?>
    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <?php endif; ?>
    
    <?php if ($useLocal && $bootstrapExists): ?>
    <!-- Bootstrap 5 Local -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <?php else: ?>
    <!-- Bootstrap 5 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <?php endif; ?>
    
    <?php if ($useLocal && $adminlteExists): ?>
    <!-- AdminLTE 4 Local -->
    <script src="../assets/adminlte/adminlte.min.js"></script>
    <?php else: ?>
    <!-- AdminLTE 4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc.5/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
    <?php endif; ?>
    
    <!-- Common.js del sistema -->
    <script src="../assets/js/common.js"></script>
    
    <?php if (!$jqueryExists || !$bootstrapExists || !$adminlteExists): ?>
    <script>
    console.warn('⚠️ Algunos recursos JS no están locales. Usando CDN como fallback.');
    console.info('📥 Para mejor rendimiento, descarga:');
    console.info('   - jQuery: https://code.jquery.com/jquery-3.6.0.min.js');
    console.info('   - Bootstrap: https://getbootstrap.com/docs/5.3/getting-started/download/');
    console.info('   - AdminLTE: https://github.com/ColorlibHQ/AdminLTE/releases');
    </script>
    <?php endif; ?>
    <?php
}

// ============================================================================
// LAYOUT COMPLETO
// ============================================================================

function initPage($title, $currentPage = '', $user = null) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - <?php echo SYSTEM_NAME; ?></title>
        <?php renderSharedStyles(); ?>
    </head>
    <body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <?php renderNavbar($currentPage, $user); ?>
    <?php
}

function endPage() {
    ?>
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
    </body>
    </html>
    <?php
}

// Fin del archivo
        
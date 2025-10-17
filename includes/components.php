<?php
/**
 * SISTEMA DE COMPONENTES CENTRALIZADOS
 * Componentes reutilizables para todo el sistema
 * Incluye: Modales, Notificaciones, Formularios, Tablas, Loading, etc.
 * 
 * USO: require_once '../includes/components.php';
 */

<?php
/**
 * SISTEMA DE COMPONENTES CENTRALIZADOS
 * Componentes reutilizables para todo el sistema
 * Incluye: Modales, Notificaciones, Formularios, Tablas, Loading, etc.
 * 
 * USO: require_once '../includes/components.php';
 */

// ============================================================================
// CARGAR DEPENDENCIAS NECESARIAS - CRÍTICO: DEBE ESTAR AL INICIO
// ============================================================================

// Obtener directorio actual
$current_dir = __DIR__;

// Cargar estilos SI NO ESTÁ ya cargado
if (!function_exists('renderSharedStyles')) {
    $styles_file = $current_dir . '/styles.php';
    if (file_exists($styles_file)) {
        require_once $styles_file;
    } else {
        // Intentar ruta alternativa
        require_once dirname(__FILE__) . '/styles.php';
    }
}

// Cargar navbar SI NO ESTÁ ya cargado
if (!function_exists('renderNavbar')) {
    $navbar_file = $current_dir . '/navbar_unified.php';
    if (file_exists($navbar_file)) {
        require_once $navbar_file;
    } else {
        // Intentar ruta alternativa
        require_once dirname(__FILE__) . '/navbar_unified.php';
    }
}

// Verificar que se cargaron correctamente
if (!function_exists('renderSharedStyles')) {
    die('ERROR CRÍTICO: No se pudo cargar styles.php. Verifica que el archivo existe en includes/styles.php');
}

if (!function_exists('renderNavbar')) {
    die('ERROR CRÍTICO: No se pudo cargar navbar_unified.php. Verifica que el archivo existe en includes/navbar_unified.php');
}

// ============================================================================
// AHORA SÍ, CONTINUAR CON EL RESTO DE COMPONENTES
// ============================================================================

/**
 * Modal genérico reutilizable
 * @param string $id ID único del modal
 * @param string $title Título del modal
 * @param string $content Contenido HTML del modal
 * @param array $options Opciones adicionales
 */
function renderModal($id, $title, $content, $options = []) {
    // ... resto del código de renderModal
}

// ... RESTO DE TU ARCHIVO components.php SIN CAMBIOS

// ============================================================================
// COMPONENTES UI - MODALES
// ============================================================================

/**
 * Modal genérico reutilizable
 * @param string $id ID único del modal
 * @param string $title Título del modal
 * @param string $content Contenido HTML del modal
 * @param array $options Opciones adicionales
 */
function renderModal($id, $title, $content, $options = []) {
    $size = $options['size'] ?? 'medium'; // small, medium, large, full
    $show = $options['show'] ?? false;
    $backdrop = $options['backdrop'] ?? 'static'; // static, true, false
    
    $sizeClasses = [
        'small' => 'max-w-md',
        'medium' => 'max-w-2xl',
        'large' => 'max-w-4xl',
        'full' => 'max-w-6xl'
    ];
    
    $modalClass = $show ? 'modal show' : 'modal';
    $maxWidth = $sizeClasses[$size] ?? $sizeClasses['medium'];
    
    ?>
    <div id="<?php echo $id; ?>" class="<?php echo $modalClass; ?>" data-backdrop="<?php echo $backdrop; ?>">
        <div class="modal-content <?php echo $maxWidth; ?>">
            <div class="modal-header">
                <h3 class="modal-title"><?php echo htmlspecialchars($title); ?></h3>
                <button type="button" class="modal-close" onclick="closeModal('<?php echo $id; ?>')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <?php echo $content; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Modal de confirmación
 */
function renderConfirmModal($id, $title, $message, $onConfirm, $options = []) {
    $confirmText = $options['confirmText'] ?? 'Confirmar';
    $cancelText = $options['cancelText'] ?? 'Cancelar';
    $type = $options['type'] ?? 'danger'; // success, danger, warning, info
    
    $content = "
        <div class='text-center py-6'>
            <div class='mb-4'>
                <svg class='w-16 h-16 mx-auto text-{$type}-500' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'></path>
                </svg>
            </div>
            <p class='text-lg text-gray-700 mb-6'>" . htmlspecialchars($message) . "</p>
            <div class='flex gap-3 justify-center'>
                <button onclick='closeModal(\"{$id}\")' class='btn btn-secondary'>
                    {$cancelText}
                </button>
                <button onclick='{$onConfirm}; closeModal(\"{$id}\")' class='btn btn-{$type}'>
                    {$confirmText}
                </button>
            </div>
        </div>
    ";
    
    renderModal($id, $title, $content, ['size' => 'small']);
}

// ============================================================================
// COMPONENTES UI - FORMULARIOS
// ============================================================================

/**
 * Campo de formulario genérico
 */
function renderFormField($type, $name, $label, $options = []) {
    $value = $options['value'] ?? '';
    $placeholder = $options['placeholder'] ?? '';
    $required = $options['required'] ?? false;
    $disabled = $options['disabled'] ?? false;
    $help = $options['help'] ?? '';
    $error = $options['error'] ?? '';
    $class = $options['class'] ?? '';
    
    $requiredAttr = $required ? 'required' : '';
    $disabledAttr = $disabled ? 'disabled' : '';
    $errorClass = $error ? 'is-invalid' : '';
    
    ?>
    <div class="form-group <?php echo $class; ?>">
        <label for="<?php echo $name; ?>" class="form-label">
            <?php echo htmlspecialchars($label); ?>
            <?php if ($required): ?>
                <span class="text-red-500">*</span>
            <?php endif; ?>
        </label>
        
        <?php if ($type === 'textarea'): ?>
            <textarea 
                id="<?php echo $name; ?>" 
                name="<?php echo $name; ?>"
                class="form-textarea <?php echo $errorClass; ?>"
                placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                <?php echo $requiredAttr; ?>
                <?php echo $disabledAttr; ?>
                rows="<?php echo $options['rows'] ?? 4; ?>"
            ><?php echo htmlspecialchars($value); ?></textarea>
            
        <?php elseif ($type === 'select'): ?>
            <select 
                id="<?php echo $name; ?>" 
                name="<?php echo $name; ?>"
                class="form-select <?php echo $errorClass; ?>"
                <?php echo $requiredAttr; ?>
                <?php echo $disabledAttr; ?>
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
                class="form-input <?php echo $errorClass; ?>"
                <?php echo $requiredAttr; ?>
                <?php echo $disabledAttr; ?>
                <?php if (!empty($options['min'])): ?>min="<?php echo $options['min']; ?>"<?php endif; ?>
                <?php if (!empty($options['max'])): ?>max="<?php echo $options['max']; ?>"<?php endif; ?>
                <?php if (!empty($options['step'])): ?>step="<?php echo $options['step']; ?>"<?php endif; ?>
                <?php if (!empty($options['pattern'])): ?>pattern="<?php echo $options['pattern']; ?>"<?php endif; ?>
            />
        <?php endif; ?>
        
        <?php if ($help): ?>
            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($help); ?></p>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <p class="text-sm text-red-600 mt-1"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Búsqueda con autocompletado
 */
function renderSearchBox($id, $placeholder = 'Buscar...', $options = []) {
    $icon = $options['icon'] ?? true;
    $clearButton = $options['clearButton'] ?? true;
    $onSearch = $options['onSearch'] ?? 'handleSearch';
    
    ?>
    <div class="relative">
        <?php if ($icon): ?>
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <?php endif; ?>
        
        <input 
            type="text" 
            id="<?php echo $id; ?>"
            placeholder="<?php echo htmlspecialchars($placeholder); ?>"
            class="form-input <?php echo $icon ? 'pl-10' : ''; ?> <?php echo $clearButton ? 'pr-10' : ''; ?>"
            oninput="<?php echo $onSearch; ?>(this.value)"
        />
        
        <?php if ($clearButton): ?>
        <button 
            type="button" 
            id="<?php echo $id; ?>_clear"
            class="absolute inset-y-0 right-0 pr-3 flex items-center hidden"
            onclick="clearSearch('<?php echo $id; ?>')"
        >
            <svg class="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <?php endif; ?>
    </div>
    
    <script>
    function clearSearch(id) {
        document.getElementById(id).value = '';
        document.getElementById(id + '_clear').classList.add('hidden');
        <?php echo $onSearch; ?>('');
    }
    
    document.getElementById('<?php echo $id; ?>').addEventListener('input', function() {
        const clearBtn = document.getElementById('<?php echo $id; ?>_clear');
        if (this.value) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }
    });
    </script>
    <?php
}

// ============================================================================
// COMPONENTES UI - ALERTAS Y NOTIFICACIONES
// ============================================================================

/**
 * Alerta estática
 */
function renderAlert($message, $type = 'info', $dismissible = false) {
    $icons = [
        'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        'danger' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
        'info' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
    ];
    
    $icon = $icons[$type] ?? $icons['info'];
    
    ?>
    <div class="alert alert-<?php echo $type; ?> <?php echo $dismissible ? 'dismissible' : ''; ?>">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php echo $icon; ?>
        </svg>
        <div class="flex-1">
            <?php echo $message; ?>
        </div>
        <?php if ($dismissible): ?>
        <button type="button" class="ml-auto" onclick="this.parentElement.remove()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// COMPONENTES UI - TARJETAS Y CONTENEDORES
// ============================================================================

/**
 * Card genérica
 */
function renderCard($title, $content, $options = []) {
    $headerActions = $options['headerActions'] ?? '';
    $footer = $options['footer'] ?? '';
    $class = $options['class'] ?? '';
    
    ?>
    <div class="card <?php echo $class; ?>">
        <?php if ($title || $headerActions): ?>
        <div class="card-header">
            <?php if ($title): ?>
                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($title); ?></h3>
            <?php endif; ?>
            <?php if ($headerActions): ?>
                <div class="flex gap-2">
                    <?php echo $headerActions; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="card-body">
            <?php echo $content; ?>
        </div>
        
        <?php if ($footer): ?>
        <div class="card-footer">
            <?php echo $footer; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Stat Card (Métrica)
 */
function renderStatCard($value, $label, $options = []) {
    $icon = $options['icon'] ?? '';
    $color = $options['color'] ?? 'blue';
    $change = $options['change'] ?? null;
    $changeType = $options['changeType'] ?? 'positive';
    
    $gradients = [
        'blue' => 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        'green' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'purple' => 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)',
        'orange' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'red' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
        'pink' => 'linear-gradient(135deg, #ec4899 0%, #db2777 100%)'
    ];
    
    $gradient = $gradients[$color] ?? $gradients['blue'];
    
    ?>
    <div class="stats-card" style="background: <?php echo $gradient; ?>;">
        <?php if ($icon): ?>
        <div class="stats-card-icon">
            <?php echo $icon; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-card-value"><?php echo htmlspecialchars($value); ?></div>
        <div class="stats-card-label"><?php echo htmlspecialchars($label); ?></div>
        
        <?php if ($change !== null): ?>
        <div class="stat-change <?php echo $changeType; ?>">
            <?php if ($changeType === 'positive'): ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            <?php else: ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                </svg>
            <?php endif; ?>
            <?php echo htmlspecialchars($change); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// COMPONENTES UI - TABLAS
// ============================================================================

/**
 * Tabla con datos
 */
function renderDataTable($columns, $data, $options = []) {
    $id = $options['id'] ?? 'dataTable_' . uniqid();
    $striped = $options['striped'] ?? true;
    $hover = $options['hover'] ?? true;
    $actions = $options['actions'] ?? null;
    $emptyMessage = $options['emptyMessage'] ?? 'No hay datos disponibles';
    
    ?>
    <div class="table-container">
        <table id="<?php echo $id; ?>" class="table <?php echo $striped ? 'table-striped' : ''; ?> <?php echo $hover ? 'table-hover' : ''; ?>">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?php echo htmlspecialchars($column['label']); ?></th>
                    <?php endforeach; ?>
                    <?php if ($actions): ?>
                        <th class="text-center">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?php echo count($columns) + ($actions ? 1 : 0); ?>" class="text-center py-8 text-gray-500">
                            <?php echo htmlspecialchars($emptyMessage); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <td>
                                    <?php 
                                    $value = $row[$column['field']] ?? '';
                                    if (isset($column['render'])) {
                                        echo $column['render']($value, $row);
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if ($actions): ?>
                                <td class="text-center">
                                    <?php echo $actions($row); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ============================================================================
// COMPONENTES UI - LOADING Y SPINNERS
// ============================================================================

/**
 * Loading spinner
 */
function renderLoadingSpinner($id = 'loadingSpinner', $text = 'Cargando...') {
    ?>
    <div id="<?php echo $id; ?>" class="loading-overlay hidden">
        <div class="text-center">
            <div class="loading-spinner mx-auto mb-4"></div>
            <p class="text-gray-600 font-medium"><?php echo htmlspecialchars($text); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Skeleton loader para tarjetas
 */
function renderSkeletonCard($count = 3) {
    for ($i = 0; $i < $count; $i++) {
        ?>
        <div class="card animate-pulse">
            <div class="skeleton h-8 w-3/4 mb-4"></div>
            <div class="skeleton h-4 w-full mb-2"></div>
            <div class="skeleton h-4 w-5/6 mb-2"></div>
            <div class="skeleton h-4 w-4/6"></div>
        </div>
        <?php
    }
}

// ============================================================================
// COMPONENTES UI - BADGES Y ETIQUETAS
// ============================================================================

/**
 * Badge
 */
function renderBadge($text, $type = 'primary') {
    ?>
    <span class="badge badge-<?php echo $type; ?>">
        <?php echo htmlspecialchars($text); ?>
    </span>
    <?php
}

/**
 * Badge de estado de stock
 */
function renderStockBadge($quantity, $minStock = 10) {
    if ($quantity == 0) {
        $type = 'danger';
        $text = 'Sin stock';
    } elseif ($quantity <= $minStock) {
        $type = 'warning';
        $text = 'Stock bajo';
    } elseif ($quantity <= $minStock * 2) {
        $type = 'info';
        $text = 'Stock medio';
    } else {
        $type = 'success';
        $text = 'Stock normal';
    }
    
    renderBadge($text . " ({$quantity})", $type);
}

// ============================================================================
// COMPONENTES UI - PAGINACIÓN
// ============================================================================

/**
 * Paginación
 */
function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return;
    
    ?>
    <div class="flex items-center justify-center gap-2 mt-6">
        <?php if ($currentPage > 1): ?>
            <a href="<?php echo $baseUrl; ?>?page=<?php echo $currentPage - 1; ?>" 
               class="btn btn-secondary">
                ← Anterior
            </a>
        <?php endif; ?>
        
        <div class="flex gap-1">
            <?php 
            $start = max(1, $currentPage - 2);
            $end = min($totalPages, $currentPage + 2);
            
            if ($start > 1) {
                echo "<a href='{$baseUrl}?page=1' class='px-3 py-2 rounded border'>1</a>";
                if ($start > 2) echo "<span class='px-2'>...</span>";
            }
            
            for ($i = $start; $i <= $end; $i++) {
                $activeClass = ($i == $currentPage) ? 'bg-purple-600 text-white' : 'bg-white';
                echo "<a href='{$baseUrl}?page={$i}' class='px-3 py-2 rounded border {$activeClass}'>{$i}</a>";
            }
            
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo "<span class='px-2'>...</span>";
                echo "<a href='{$baseUrl}?page={$totalPages}' class='px-3 py-2 rounded border'>{$totalPages}</a>";
            }
            ?>
        </div>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="<?php echo $baseUrl; ?>?page=<?php echo $currentPage + 1; ?>" 
               class="btn btn-secondary">
                Siguiente →
            </a>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// COMPONENTES UI - BREADCRUMBS
// ============================================================================

/**
 * Breadcrumbs
 */
function renderBreadcrumbs($items) {
    ?>
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <?php foreach ($items as $index => $item): ?>
                <li class="inline-flex items-center">
                    <?php if ($index > 0): ?>
                        <svg class="w-6 h-6 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    <?php endif; ?>
                    
                    <?php if (isset($item['url']) && $index < count($items) - 1): ?>
                        <a href="<?php echo $item['url']; ?>" 
                           class="text-gray-700 hover:text-purple-600 font-medium">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                    <?php else: ?>
                        <span class="text-gray-500 font-medium">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
}

// ============================================================================
// COMPONENTES UI - EMPTY STATES
// ============================================================================

/**
 * Empty State
 */
function renderEmptyState($title, $description, $actionButton = null) {
    ?>
    <div class="empty-state">
        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
        </svg>
        <h3 class="empty-state-title"><?php echo htmlspecialchars($title); ?></h3>
        <p class="empty-state-description"><?php echo htmlspecialchars($description); ?></p>
        <?php if ($actionButton): ?>
            <?php echo $actionButton; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// FUNCIONES JAVASCRIPT HELPERS
// ============================================================================

/**
 * Renderizar scripts JS comunes
 */
function renderCommonScripts() {
    ?>
    <script>
    // ==========================================
    // GESTIÓN DE MODALES
    // ==========================================
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
    
    // Cerrar modal al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            const backdrop = e.target.getAttribute('data-backdrop');
            if (backdrop !== 'static') {
                closeModal(e.target.id);
            }
        }
    });
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const backdrop = modal.getAttribute('data-backdrop');
                if (backdrop !== 'static') {
                    closeModal(modal.id);
                }
            });
        }
    });
    
    // ==========================================
    // SISTEMA DE NOTIFICACIONES
    // ==========================================
    function showNotification(message, type = 'info', duration = 4000) {
        const colors = {
            'success': 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            'danger': 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
            'warning': 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            'info': 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
        };
        
        const icons = {
            'success': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'danger': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'warning': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
            'info': '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        };
        
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 text-white flex items-center gap-3';
        notification.style.background = colors[type] || colors['info'];
        notification.innerHTML = icons[type] + '<span>' + message + '</span>';
        
        document.body.appendChild(notification);
        
        // Animación de entrada
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-cerrar
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, duration);
        
        // Cerrar al hacer clic
        notification.addEventListener('click', () => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        });
    }
    
    // ==========================================
    // LOADING OVERLAY
    // ==========================================
    function showLoading(spinnerId = 'loadingSpinner') {
        const spinner = document.getElementById(spinnerId);
        if (spinner) {
            spinner.classList.remove('hidden');
        }
    }
    
    function hideLoading(spinnerId = 'loadingSpinner') {
        const spinner = document.getElementById(spinnerId);
        if (spinner) {
            spinner.classList.add('hidden');
        }
    }
    
    // ==========================================
    // VALIDACIÓN DE FORMULARIOS
    // ==========================================
    function validateForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    // Limpiar validación al escribir
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('is-invalid')) {
            if (e.target.value.trim()) {
                e.target.classList.remove('is-invalid');
            }
        }
    });
    
    // ==========================================
    // CONFIRMACIÓN DE ACCIONES
    // ==========================================
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
    
    // ==========================================
    // FORMATEO DE NÚMEROS Y MONEDA
    // ==========================================
    function formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }
    
    function formatCurrency(amount, currency = 'S/') {
        return currency + ' ' + parseFloat(amount).toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function formatNumber(number) {
        return parseInt(number).toLocaleString('es-PE');
    }
    
    // ==========================================
    // SANITIZACIÓN DE HTML
    // ==========================================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ==========================================
    // DEBOUNCE PARA BÚSQUEDAS
    // ==========================================
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // ==========================================
    // COPIAR AL PORTAPAPELES
    // ==========================================
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('✅ Copiado al portapapeles', 'success', 2000);
            }).catch(() => {
                showNotification('❌ Error al copiar', 'danger', 2000);
            });
        } else {
            // Fallback para navegadores antiguos
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showNotification('✅ Copiado al portapapeles', 'success', 2000);
            } catch (err) {
                showNotification('❌ Error al copiar', 'danger', 2000);
            }
            document.body.removeChild(textarea);
        }
    }
    
    // ==========================================
    // FORMATEO DE FECHAS
    // ==========================================
    function formatDate(dateString, format = 'short') {
        const date = new Date(dateString);
        const options = {
            'short': { year: 'numeric', month: '2-digit', day: '2-digit' },
            'long': { year: 'numeric', month: 'long', day: 'numeric' },
            'full': { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }
        };
        
        return date.toLocaleDateString('es-PE', options[format] || options['short']);
    }
    
    function formatDateTime(dateString) {
        return formatDate(dateString, 'full');
    }
    
    function timeAgo(dateString) {
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " años";
        
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " meses";
        
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " días";
        
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " horas";
        
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutos";
        
        return "Hace un momento";
    }
    
    // ==========================================
    // AJAX HELPERS
    // ==========================================
    async function fetchData(url, options = {}) {
        try {
            showLoading();
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            hideLoading();
            return data;
        } catch (error) {
            hideLoading();
            showNotification('Error en la conexión: ' + error.message, 'danger');
            throw error;
        }
    }
    
    async function postData(url, data) {
        return fetchData(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async function submitForm(formId, url) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        if (!validateForm(formId)) {
            showNotification('Por favor completa todos los campos requeridos', 'warning');
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await postData(url, data);
            if (response.success) {
                showNotification(response.message || 'Operación exitosa', 'success');
                return response;
            } else {
                showNotification(response.message || 'Error en la operación', 'danger');
                return null;
            }
        } catch (error) {
            showNotification('Error al enviar el formulario', 'danger');
            return null;
        }
    }
    
    // ==========================================
    // ANIMACIONES
    // ==========================================
    function animateValue(element, start, end, duration, prefix = '', suffix = '') {
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = start + (end - start) * easeOutQuart;
            
            element.textContent = prefix + Math.floor(current).toLocaleString('es-PE') + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }
    
    // ==========================================
    // DETECCIÓN DE CAMBIOS NO GUARDADOS
    // ==========================================
    let formChanged = false;
    
    function trackFormChanges(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        form.addEventListener('input', () => {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
        
        form.addEventListener('submit', () => {
            formChanged = false;
        });
    }
    
    // ==========================================
    // SCROLL SUAVE
    // ==========================================
    function smoothScrollTo(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    // ==========================================
    // TOGGLE DE ELEMENTOS
    // ==========================================
    function toggleElement(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.toggle('hidden');
        }
    }
    
    function showElement(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.remove('hidden');
        }
    }
    
    function hideElement(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.add('hidden');
        }
    }
    
    // ==========================================
    // BÚSQUEDA EN TABLA
    // ==========================================
    function searchTable(tableId, searchValue) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const searchLower = searchValue.toLowerCase();
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchLower)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        return visibleCount;
    }
    
    // ==========================================
    // PRINT
    // ==========================================
    function printElement(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Imprimir</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f2f2f2; }');
        printWindow.document.write('@media print { .no-print { display: none; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(element.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    // ==========================================
    // EXPORTAR A CSV
    // ==========================================
    function exportTableToCSV(tableId, filename = 'export.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = Array.from(cols).map(col => {
                let text = col.textContent.trim();
                // Escapar comillas
                text = text.replace(/"/g, '""');
                return `"${text}"`;
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // ==========================================
    // INICIALIZACIÓN
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Sistema de componentes cargado');
        
        // Auto-cerrar alertas dismissibles después de 5 segundos
        document.querySelectorAll('.alert.dismissible').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        // Agregar tooltips automáticos
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('data-tooltip');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
                tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
            });
            
            element.addEventListener('mouseleave', function() {
                document.querySelectorAll('.tooltip').forEach(t => t.remove());
            });
        });
    });
    </script>
    <?php
}

// ============================================================================
// LAYOUT HELPERS
// ============================================================================

/**
 * Inicializar página con todos los componentes necesarios
 */
function initPage($title, $currentPage = '', $options = []) {
    $includeNavbar = $options['navbar'] ?? true;
    $includeStyles = $options['styles'] ?? true;
    $includeScripts = $options['scripts'] ?? true;
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - <?php echo SYSTEM_NAME; ?></title>
        <?php if ($includeStyles): ?>
            <?php renderSharedStyles(); ?>
        <?php endif; ?>
    </head>
    <body class="bg-gray-50">
        <?php if ($includeNavbar): ?>
            <?php renderNavbar($currentPage); ?>
        <?php endif; ?>
    <?php
}

/**
 * Finalizar página
 */
function endPage($includeScripts = true) {
    if ($includeScripts) {
        renderCommonScripts();
    }
    ?>
    </body>
    </html>
    <?php
}

// ============================================================================
// COMPONENTES DE DATOS
// ============================================================================

/**
 * Lista de productos/items con búsqueda
 */
function renderItemsList($items, $options = []) {
    $searchable = $options['searchable'] ?? true;
    $itemRenderer = $options['itemRenderer'] ?? null;
    $emptyMessage = $options['emptyMessage'] ?? 'No hay elementos para mostrar';
    $gridCols = $options['gridCols'] ?? 4;
    
    ?>
    <div class="items-list">
        <?php if ($searchable): ?>
            <?php renderSearchBox('itemsSearch', 'Buscar...', [
                'onSearch' => 'searchItems'
            ]); ?>
            <div id="searchInfo" class="text-sm text-gray-600 mt-2 mb-4"></div>
        <?php endif; ?>
        
        <div id="itemsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-<?php echo $gridCols; ?> gap-6">
            <?php if (empty($items)): ?>
                <?php renderEmptyState('Sin elementos', $emptyMessage); ?>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php if ($itemRenderer): ?>
                        <?php echo $itemRenderer($item); ?>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <pre><?php echo htmlspecialchars(print_r($item, true)); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($searchable): ?>
    <script>
    function searchItems(query) {
        const items = document.querySelectorAll('#itemsContainer > *');
        const searchLower = query.toLowerCase();
        let visibleCount = 0;
        
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchLower) || !query) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        const searchInfo = document.getElementById('searchInfo');
        if (query) {
            searchInfo.textContent = `Mostrando ${visibleCount} de ${items.length} elementos`;
        } else {
            searchInfo.textContent = '';
        }
    }
    </script>
    <?php endif; ?>
    <?php
}

// ============================================================================
// HELPERS DE FORMATO
// ============================================================================

/**
 * Formatear precio en soles
 */
function formatPricePHP($price) {
    return 'S/ ' . number_format($price, 2);
}

/**
 * Formatear fecha en español
 */
function formatDatePHP($date, $format = 'short') {
    $timestamp = strtotime($date);
    
    switch ($format) {
        case 'short':
            return date('d/m/Y', $timestamp);
        case 'long':
            return strftime('%d de %B de %Y', $timestamp);
        case 'full':
            return strftime('%d de %B de %Y, %H:%M', $timestamp);
        default:
            return date('d/m/Y', $timestamp);
    }
}

/**
 * Tiempo transcurrido
 */
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
// COMPONENTE DE TABS
// ============================================================================

/**
 * Sistema de tabs/pestañas
 */
function renderTabs($tabs, $defaultTab = 0) {
    $tabId = 'tabs_' . uniqid();
    ?>
    <div class="tabs-container">
        <div class="tabs" role="tablist">
            <?php foreach ($tabs as $index => $tab): ?>
                <button 
                    type="button"
                    class="tab <?php echo $index === $defaultTab ? 'active' : ''; ?>"
                    onclick="switchTab('<?php echo $tabId; ?>', <?php echo $index; ?>)"
                    role="tab"
                    aria-selected="<?php echo $index === $defaultTab ? 'true' : 'false'; ?>">
                    <?php if (!empty($tab['icon'])): ?>
                        <?php echo $tab['icon']; ?>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($tab['label']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="tab-content">
            <?php foreach ($tabs as $index => $tab): ?>
                <div 
                    id="<?php echo $tabId; ?>_<?php echo $index; ?>"
                    class="tab-pane <?php echo $index === $defaultTab ? 'active' : ''; ?>"
                    role="tabpanel">
                    <?php echo $tab['content']; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .tabs-container { margin-bottom: 2rem; }
    .tabs { display: flex; border-bottom: 2px solid #e5e7eb; gap: 0.5rem; }
    .tab {
        padding: 0.75rem 1.25rem;
        border: none;
        background: none;
        color: #6b7280;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .tab:hover { color: #667eea; background-color: #f9fafb; }
    .tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
    }
    .tab-content { margin-top: 1.5rem; }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; animation: fadeIn 0.3s; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    </style>
    
    <script>
    function switchTab(tabId, index) {
        // Desactivar todos los tabs y panes
        const container = document.querySelector(`[id^="${tabId}"]`).closest('.tabs-container');
        container.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
        });
        container.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        
        // Activar el tab y pane seleccionado
        const tabs = container.querySelectorAll('.tab');
        tabs[index].classList.add('active');
        tabs[index].setAttribute('aria-selected', 'true');
        
        const pane = document.getElementById(`${tabId}_${index}`);
        if (pane) {
            pane.classList.add('active');
        }
    }
    </script>
    <?php
}

// ============================================================================
// FIN DEL ARCHIVO
// ============================================================================

/**
 * Log de componentes disponibles
 */
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_log("✅ Sistema de componentes cargado - " . basename(__FILE__));
}

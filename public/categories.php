<?php
/**
 * GESTIÓN DE CATEGORÍAS DE PRODUCTOS
 * Sistema de Inventario de Celulares
 * VERSIÓN COMPLETA CON CRUD
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

// Solo administradores pueden gestionar categorías
if (!hasPermission('admin')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$user = getCurrentUser();
$db = getDB();
$csrf_token = generateCsrfToken();

// ============================================================================
// PROCESAMIENTO DE ACCIONES
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    
    try {
        switch ($_POST['action']) {
            // ----------------------------------------------------------------
            // AGREGAR CATEGORÍA
            // ----------------------------------------------------------------
            case 'add_category':
                $nombre = trim(sanitize($_POST['nombre'] ?? ''));
                $descripcion = trim(sanitize($_POST['descripcion'] ?? ''));
                $tipo = $_POST['tipo'] ?? '';
                
                if (empty($nombre)) {
                    throw new Exception('El nombre es obligatorio');
                }
                
                if (!in_array($tipo, ['accesorio', 'repuesto'])) {
                    throw new Exception('Tipo no válido');
                }
                
                // Verificar duplicados
                $check = $db->prepare("SELECT id FROM categorias_productos WHERE nombre = ? AND tipo = ?");
                $check->execute([$nombre, $tipo]);
                if ($check->fetch()) {
                    throw new Exception('Ya existe una categoría con ese nombre y tipo');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO categorias_productos (nombre, descripcion, tipo, activa, fecha_creacion) 
                    VALUES (?, ?, ?, 1, NOW())
                ");
                
                if ($stmt->execute([$nombre, $descripcion, $tipo])) {
                    $category_id = $db->lastInsertId();
                    logActivity($user['id'], 'add_category', "Categoría: $nombre (ID: $category_id)");
                    echo json_encode(['success' => true, 'message' => 'Categoría agregada correctamente']);
                } else {
                    throw new Exception('Error al guardar la categoría');
                }
                break;
                
            // ----------------------------------------------------------------
            // ACTUALIZAR CATEGORÍA
            // ----------------------------------------------------------------
            case 'update_category':
                $category_id = intval($_POST['category_id'] ?? 0);
                $nombre = trim(sanitize($_POST['nombre'] ?? ''));
                $descripcion = trim(sanitize($_POST['descripcion'] ?? ''));
                $tipo = $_POST['tipo'] ?? '';
                $activa = isset($_POST['activa']) ? 1 : 0;
                
                if ($category_id <= 0) {
                    throw new Exception('ID no válido');
                }
                
                if (empty($nombre)) {
                    throw new Exception('El nombre es obligatorio');
                }
                
                if (!in_array($tipo, ['accesorio', 'repuesto'])) {
                    throw new Exception('Tipo no válido');
                }
                
                // Verificar duplicados (excepto el mismo registro)
                $check = $db->prepare("SELECT id FROM categorias_productos WHERE nombre = ? AND tipo = ? AND id != ?");
                $check->execute([$nombre, $tipo, $category_id]);
                if ($check->fetch()) {
                    throw new Exception('Ya existe otra categoría con ese nombre y tipo');
                }
                
                $stmt = $db->prepare("
                    UPDATE categorias_productos 
                    SET nombre = ?, descripcion = ?, tipo = ?, activa = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$nombre, $descripcion, $tipo, $activa, $category_id])) {
                    logActivity($user['id'], 'update_category', "Categoría ID: $category_id");
                    echo json_encode(['success' => true, 'message' => 'Categoría actualizada correctamente']);
                } else {
                    throw new Exception('Error al actualizar la categoría');
                }
                break;
                
            // ----------------------------------------------------------------
            // ELIMINAR CATEGORÍA
            // ----------------------------------------------------------------
            case 'delete_category':
                $category_id = intval($_POST['category_id'] ?? 0);
                
                if ($category_id <= 0) {
                    throw new Exception('ID no válido');
                }
                
                // Verificar si tiene productos asociados
                $check = $db->prepare("SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?");
                $check->execute([$category_id]);
                $result = $check->fetch();
                
                if ($result && $result['count'] > 0) {
                    // Si tiene productos, solo desactivar
                    $stmt = $db->prepare("UPDATE categorias_productos SET activa = 0 WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $message = 'Categoría desactivada (tiene productos asociados)';
                } else {
                    // Si no tiene productos, eliminar
                    $stmt = $db->prepare("DELETE FROM categorias_productos WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $message = 'Categoría eliminada correctamente';
                }
                
                logActivity($user['id'], 'delete_category', "Categoría ID: $category_id");
                echo json_encode(['success' => true, 'message' => $message]);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        logError("Error en categories.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// OBTENER CATEGORÍAS
// ============================================================================

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$tipo_filter = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
$estado_filter = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nombre LIKE ? OR descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($tipo_filter) && in_array($tipo_filter, ['accesorio', 'repuesto'])) {
    $where_conditions[] = "tipo = ?";
    $params[] = $tipo_filter;
}

if ($estado_filter === 'activa') {
    $where_conditions[] = "activa = 1";
} elseif ($estado_filter === 'inactiva') {
    $where_conditions[] = "activa = 0";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $query = "
        SELECT 
            c.*,
            COUNT(DISTINCT p.id) as total_productos,
            COUNT(DISTINCT CASE WHEN p.activo = 1 THEN p.id END) as productos_activos
        FROM categorias_productos c
        LEFT JOIN productos p ON c.id = p.categoria_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.tipo, c.nombre
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $stats = [
        'total' => 0,
        'activas' => 0,
        'inactivas' => 0,
        'accesorios' => 0,
        'repuestos' => 0
    ];
    
    foreach ($categories as $cat) {
        $stats['total']++;
        if ($cat['activa']) $stats['activas']++;
        else $stats['inactivas']++;
        if ($cat['tipo'] === 'accesorio') $stats['accesorios']++;
        else $stats['repuestos']++;
    }
    
} catch (Exception $e) {
    logError("Error al obtener categorías: " . $e->getMessage());
    $categories = [];
    $stats = ['total' => 0, 'activas' => 0, 'inactivas' => 0, 'accesorios' => 0, 'repuestos' => 0];
}

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999; }
        .modal.show { display: flex; }
        .category-card { transition: all 0.3s ease; }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.15); }
        .notification { position: fixed; top: 1rem; right: 1rem; z-index: 99999; transform: translateX(120%); transition: transform 0.3s; }
        .notification.show { transform: translateX(0); }
        .badge-accesorio { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        .badge-repuesto { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    </style>
</head>
<body class="bg-gray-100">

<?php renderNavbar('categories'); ?>

<main class="page-content p-6">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gestión de Categorías</h1>
            <p class="text-gray-600">Organiza tus productos en categorías</p>
        </div>
        <button onclick="openAddModal()" 
                class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg flex items-center transition-colors shadow-md mt-4 md:mt-0">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Nueva Categoría
        </button>
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total']; ?></p>
            <p class="text-sm text-gray-600">Total</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?php echo $stats['activas']; ?></p>
            <p class="text-sm text-gray-600">Activas</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-red-600"><?php echo $stats['inactivas']; ?></p>
            <p class="text-sm text-gray-600">Inactivas</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['accesorios']; ?></p>
            <p class="text-sm text-gray-600">Accesorios</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['repuestos']; ?></p>
            <p class="text-sm text-gray-600">Repuestos</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-64">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Buscar categoría..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <select name="tipo" class="px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">Todos los tipos</option>
                <option value="accesorio" <?php echo $tipo_filter === 'accesorio' ? 'selected' : ''; ?>>Accesorios</option>
                <option value="repuesto" <?php echo $tipo_filter === 'repuesto' ? 'selected' : ''; ?>>Repuestos</option>
            </select>
            <select name="estado" class="px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">Todos los estados</option>
                <option value="activa" <?php echo $estado_filter === 'activa' ? 'selected' : ''; ?>>Activas</option>
                <option value="inactiva" <?php echo $estado_filter === 'inactiva' ? 'selected' : ''; ?>>Inactivas</option>
            </select>
            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                Filtrar
            </button>
            <?php if ($search || $tipo_filter || $estado_filter): ?>
            <a href="categories.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">
                Limpiar
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Lista de Categorías -->
    <?php if (empty($categories)): ?>
    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No hay categorías</h3>
        <p class="text-gray-600 mb-4">Crea la primera categoría para organizar tus productos</p>
        <button onclick="openAddModal()" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Crear Primera Categoría
        </button>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($categories as $category): ?>
        <div class="category-card bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($category['nombre']); ?></h3>
                            <?php if (!$category['activa']): ?>
                            <span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">
                                Inactiva
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-white rounded-full badge-<?php echo $category['tipo']; ?>">
                            <?php echo strtoupper($category['tipo']); ?>
                        </span>
                    </div>
                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                </div>
                
                <?php if ($category['descripcion']): ?>
                <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($category['descripcion']); ?></p>
                <?php endif; ?>
                
                <div class="border-t pt-4 mb-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Productos:</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-gray-900"><?php echo $category['total_productos']; ?></span>
                            <?php if ($category['productos_activos'] > 0): ?>
                            <span class="text-green-600">(<?php echo $category['productos_activos']; ?> activos)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($category), ENT_QUOTES); ?>)' 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-2 rounded transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Editar
                    </button>
                    <button onclick='deleteCategory(<?php echo $category['id']; ?>, "<?php echo addslashes($category['nombre']); ?>", <?php echo $category['total_productos']; ?>)' 
                            class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-2 rounded transition-colors"
                            title="Eliminar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<!-- Modal Agregar/Editar -->
<div id="categoryModal" class="modal">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-lg font-semibold">Agregar Categoría</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="categoryForm" class="space-y-4">
            <input type="hidden" id="categoryId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" id="nombre" required maxlength="100"
                       placeholder="Ej: Fundas y Protectores"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                <select id="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">Seleccionar...</option>
                    <option value="accesorio">Accesorio</option>
                    <option value="repuesto">Repuesto</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea id="descripcion" rows="3" maxlength="500"
                          placeholder="Describe el tipo de productos de esta categoría..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
            </div>
            
            <div id="statusField" class="hidden">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="activa" class="mr-2 rounded">
                    <span class="text-sm font-medium text-gray-700">Categoría activa</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">Si se desactiva, no aparecerá en los selectores</p>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal()" 
                        class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button type="button" onclick="saveCategory()" 
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrf_token; ?>';
let isEditMode = false;

// ============================================================================
// GESTIÓN DE MODALES
// ============================================================================

function openAddModal() {
    isEditMode = false;
    document.getElementById('modalTitle').textContent = 'Agregar Categoría';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('statusField').classList.add('hidden');
    document.getElementById('categoryModal').classList.add('show');
}

function openEditModal(category) {
    isEditMode = true;
    document.getElementById('modalTitle').textContent = 'Editar Categoría';
    document.getElementById('categoryId').value = category.id;
    document.getElementById('nombre').value = category.nombre || '';
    document.getElementById('tipo').value = category.tipo || '';
    document.getElementById('descripcion').value = category.descripcion || '';
    document.getElementById('activa').checked = category.activa == 1;
    document.getElementById('statusField').classList.remove('hidden');
    document.getElementById('categoryModal').classList.add('show');
}

function closeModal() {
    document.getElementById('categoryModal').classList.remove('show');
}

// ============================================================================
// CRUD DE CATEGORÍAS
// ============================================================================

function saveCategory() {
    const nombre = document.getElementById('nombre').value.trim();
    const tipo = document.getElementById('tipo').value;
    
    if (!nombre || !tipo) {
        showNotification('Completa todos los campos obligatorios', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', isEditMode ? 'update_category' : 'add_category');
    formData.append('csrf_token', CSRF_TOKEN);
    
    if (isEditMode) {
        formData.append('category_id', document.getElementById('categoryId').value);
        if (document.getElementById('activa').checked) {
            formData.append('activa', '1');
        }
    }
    
    formData.append('nombre', nombre);
    formData.append('tipo', tipo);
    formData.append('descripcion', document.getElementById('descripcion').value.trim());
    
    fetch('categories.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                closeModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(() => showNotification('Error de conexión', 'error'));
}

function deleteCategory(id, name, productCount) {
    let confirmMessage = `¿Eliminar la categoría "${name}"?`;
    
    if (productCount > 0) {
        confirmMessage += `\n\nATENCIÓN: Esta categoría tiene ${productCount} producto(s) asociado(s).\nSe desactivará en lugar de eliminarse.`;
    }
    
    if (!confirm(confirmMessage)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_category');
    formData.append('category_id', id);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('categories.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(() => showNotification('Error de conexión', 'error'));
}

// ============================================================================
// NOTIFICACIONES
// ============================================================================

function showNotification(message, type = 'info') {
    const colors = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'warning': 'bg-yellow-500',
        'info': 'bg-blue-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `notification ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Cerrar modal con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

console.log('✅ Sistema de categorías cargado');
console.log('Total categorías:', <?php echo count($categories); ?>);

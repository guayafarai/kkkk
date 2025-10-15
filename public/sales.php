<?php
/**
 * SISTEMA DE VENTAS - Versión 3.2 Optimizada
 * Usa estilos centralizados y código modularizado
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/styles.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// ==========================================
// PROCESAMIENTO AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'search_devices':
                $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
                
                $where_conditions = ["c.estado = 'disponible'"];
                $params = [];
                
                if (!empty($search)) {
                    $where_conditions[] = "(c.modelo LIKE ? OR c.marca LIKE ? OR c.imei1 LIKE ? OR c.imei2 LIKE ? OR c.color LIKE ? OR c.capacidad LIKE ?)";
                    $search_param = "%$search%";
                    $params = array_fill(0, 6, $search_param);
                }
                
                if (!hasPermission('admin')) {
                    $where_conditions[] = "c.tienda_id = ?";
                    $params[] = $user['tienda_id'];
                }
                
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                
                $query = "
                    SELECT c.*, t.nombre as tienda_nombre 
                    FROM celulares c 
                    LEFT JOIN tiendas t ON c.tienda_id = t.id 
                    $where_clause 
                    ORDER BY c.fecha_registro DESC
                    LIMIT 50
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $devices = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'devices' => $devices]);
                break;
                
            case 'register_sale':
                $celular_id = intval($_POST['celular_id']);
                $cliente_nombre = sanitize($_POST['cliente_nombre']);
                $cliente_telefono = sanitize($_POST['cliente_telefono']);
                $cliente_email = sanitize($_POST['cliente_email']);
                $precio_venta = floatval($_POST['precio_venta']);
                $metodo_pago = $_POST['metodo_pago'];
                $notas = sanitize($_POST['notas']);
                
                if (empty($cliente_nombre)) {
                    throw new Exception('El nombre del cliente es obligatorio');
                }
                
                if ($precio_venta <= 0) {
                    throw new Exception('El precio de venta debe ser mayor a cero');
                }
                
                $check_stmt = $db->prepare("
                    SELECT c.*, t.nombre as tienda_nombre 
                    FROM celulares c 
                    LEFT JOIN tiendas t ON c.tienda_id = t.id 
                    WHERE c.id = ? AND c.estado = 'disponible'
                ");
                $check_stmt->execute([$celular_id]);
                $device = $check_stmt->fetch();
                
                if (!$device) {
                    throw new Exception('Dispositivo no disponible para venta');
                }
                
                if (!hasPermission('admin') && $device['tienda_id'] != $user['tienda_id']) {
                    throw new Exception('Sin permisos para vender este dispositivo');
                }
                
                $db->beginTransaction();
                
                $sale_stmt = $db->prepare("
                    INSERT INTO ventas (celular_id, tienda_id, vendedor_id, cliente_nombre, 
                                      cliente_telefono, cliente_email, precio_venta, metodo_pago, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $sale_stmt->execute([
                    $celular_id,
                    $device['tienda_id'],
                    $user['id'],
                    $cliente_nombre,
                    $cliente_telefono,
                    $cliente_email,
                    $precio_venta,
                    $metodo_pago,
                    $notas
                ]);
                
                $venta_id = $db->lastInsertId();
                
                $update_stmt = $db->prepare("UPDATE celulares SET estado = 'vendido' WHERE id = ?");
                $update_stmt->execute([$celular_id]);
                
                $db->commit();
                
                logActivity($user['id'], 'register_sale', 
                    "Venta #$venta_id - {$device['modelo']} - Cliente: $cliente_nombre - $$precio_venta");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Venta registrada exitosamente',
                    'venta_id' => $venta_id
                ]);
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch(Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        logError("Error en ventas: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// OBTENER DATOS PARA LA VISTA
// ==========================================
$available_devices = [];
$recent_sales = [];
$today_stats = ['ventas' => 0, 'ingresos' => 0];

try {
    // Dispositivos disponibles
    if (hasPermission('admin')) {
        $devices_query = "SELECT c.*, t.nombre as tienda_nombre FROM celulares c LEFT JOIN tiendas t ON c.tienda_id = t.id WHERE c.estado = 'disponible' ORDER BY c.fecha_registro DESC LIMIT 20";
        $devices_stmt = $db->query($devices_query);
    } else {
        $devices_query = "SELECT c.*, t.nombre as tienda_nombre FROM celulares c LEFT JOIN tiendas t ON c.tienda_id = t.id WHERE c.estado = 'disponible' AND c.tienda_id = ? ORDER BY c.fecha_registro DESC LIMIT 20";
        $devices_stmt = $db->prepare($devices_query);
        $devices_stmt->execute([$user['tienda_id']]);
    }
    $available_devices = $devices_stmt->fetchAll();
    
    // Ventas recientes
    if (hasPermission('admin')) {
        $sales_query = "SELECT v.*, c.modelo, c.marca, c.capacidad, t.nombre as tienda_nombre, u.nombre as vendedor_nombre FROM ventas v LEFT JOIN celulares c ON v.celular_id = c.id LEFT JOIN tiendas t ON v.tienda_id = t.id LEFT JOIN usuarios u ON v.vendedor_id = u.id ORDER BY v.fecha_venta DESC LIMIT 20";
        $sales_stmt = $db->query($sales_query);
    } else {
        $sales_query = "SELECT v.*, c.modelo, c.marca, c.capacidad, t.nombre as tienda_nombre, u.nombre as vendedor_nombre FROM ventas v LEFT JOIN celulares c ON v.celular_id = c.id LEFT JOIN tiendas t ON v.tienda_id = t.id LEFT JOIN usuarios u ON v.vendedor_id = u.id WHERE v.tienda_id = ? ORDER BY v.fecha_venta DESC LIMIT 20";
        $sales_stmt = $db->prepare($sales_query);
        $sales_stmt->execute([$user['tienda_id']]);
    }
    $recent_sales = $sales_stmt->fetchAll();
    
    // Estadísticas del día
    $today = date('Y-m-d');
    if (hasPermission('admin')) {
        $stats_stmt = $db->prepare("SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos FROM ventas WHERE DATE(fecha_venta) = ?");
        $stats_stmt->execute([$today]);
    } else {
        $stats_stmt = $db->prepare("SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?");
        $stats_stmt->execute([$today, $user['tienda_id']]);
    }
    $today_stats = $stats_stmt->fetch();
    
} catch(Exception $e) {
    logError("Error obteniendo datos: " . $e->getMessage());
}

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - <?php echo SYSTEM_NAME; ?></title>
    <?php renderSharedStyles(); ?>
</head>
<body>
    
    <?php renderNavbar('sales'); ?>
    
    <main class="page-content">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">Ventas de Celulares</h2>
                        <p class="text-gray-600">
                            <?php echo $user['rol'] === 'admin' ? 'Gestión global - Todas las tiendas' : 'Tienda: ' . htmlspecialchars($user['tienda_nombre']); ?>
                        </p>
                    </div>
                    
                    <div class="stats-card text-white p-4 rounded-lg mt-4 md:mt-0">
                        <div class="text-center">
                            <p class="text-sm opacity-90">Ventas de Hoy</p>
                            <p class="text-2xl font-bold"><?php echo $today_stats['ventas']; ?> ventas</p>
                            <p class="text-sm opacity-90">$<?php echo number_format($today_stats['ingresos'], 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="font-medium">Proceso de venta rápido:</p>
                        <p class="text-sm">1. Busca el dispositivo → 2. Selecciona → 3. Completa datos del cliente → 4. Confirma la venta</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Panel de Dispositivos -->
                <div class="card">
                    <!-- Búsqueda -->
                    <div class="card-header">
                        <div class="w-full space-y-3">
                            <div class="flex gap-3">
                                <div class="flex-1 form-input-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    <input type="text" id="deviceSearch" placeholder="Buscar por modelo, marca, IMEI..." class="form-input">
                                </div>
                                <button onclick="searchDevices()" class="btn btn-primary">Buscar</button>
                                <button onclick="clearDeviceSearch()" class="btn btn-secondary">Limpiar</button>
                            </div>
                            <p class="text-xs text-gray-500" id="searchInfo">
                                <?php echo $user['rol'] === 'vendedor' ? 'Buscando solo en ' . htmlspecialchars($user['tienda_nombre']) : 'Mostrando los últimos 20 dispositivos disponibles'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Lista de dispositivos -->
                    <div class="p-6 border-b flex justify-between items-center">
                        <h3 class="text-lg font-semibold">Dispositivos Disponibles</h3>
                        <span class="badge badge-primary" id="deviceCount"><?php echo count($available_devices); ?> disponibles</span>
                    </div>
                    
                    <div class="card-body max-h-96 overflow-y-auto">
                        <div id="loadingSpinner" class="hidden flex justify-center items-center py-8">
                            <div class="loading-spinner"></div>
                        </div>
                        
                        <div id="devicesList" class="space-y-3">
                            <?php if (empty($available_devices)): ?>
                                <div class="text-center py-8">
                                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-gray-500 font-medium">No hay dispositivos disponibles</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($available_devices as $device): ?>
                                    <div class="interactive-card" 
                                         data-device-id="<?php echo $device['id']; ?>"
                                         data-device='<?php echo htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($device['modelo']); ?></p>
                                                    <span class="badge badge-success">Disponible</span>
                                                </div>
                                                <p class="text-sm text-gray-600 mb-1">
                                                    <?php echo htmlspecialchars($device['marca']); ?> - <?php echo htmlspecialchars($device['capacidad']); ?>
                                                    <?php if ($device['color']): ?> - <?php echo htmlspecialchars($device['color']); ?><?php endif; ?>
                                                </p>
                                                <?php if ($device['imei1']): ?>
                                                    <p class="text-xs text-gray-500 font-mono bg-gray-100 inline-block px-2 py-1 rounded mb-1">
                                                        IMEI: <?php echo htmlspecialchars($device['imei1']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (hasPermission('admin')): ?>
                                                    <p class="text-xs text-primary mt-1"><?php echo htmlspecialchars($device['tienda_nombre']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-right ml-4">
                                                <p class="font-bold text-lg text-primary">$<?php echo number_format($device['precio'], 2); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Panel de Ventas Recientes -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold">Ventas Recientes</h3>
                        <span class="badge badge-success">Últimas <?php echo count($recent_sales); ?> ventas</span>
                    </div>
                    <div class="card-body max-h-96 overflow-y-auto">
                        <?php if (empty($recent_sales)): ?>
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">No hay ventas registradas</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($recent_sales as $sale): ?>
                                    <div class="alert alert-success">
                                        <div class="flex justify-between items-start flex-1">
                                            <div>
                                                <p class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($sale['modelo']); ?></p>
                                                <p class="text-sm mb-1">Cliente: <?php echo htmlspecialchars($sale['cliente_nombre']); ?></p>
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <span><?php echo date('d/m/Y H:i', strtotime($sale['fecha_venta'])); ?></span>
                                                    <?php if (hasPermission('admin')): ?>
                                                        <span>•</span>
                                                        <span><?php echo htmlspecialchars($sale['tienda_nombre']); ?></span>
                                                    <?php endif; ?>
                                                    <span>•</span>
                                                    <span><?php echo ucfirst($sale['metodo_pago']); ?></span>
                                                </div>
                                            </div>
                                            <p class="font-bold text-lg text-success">$<?php echo number_format($sale['precio_venta'], 2); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Venta -->
    <div id="saleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Venta</h3>
                <button onclick="closeSaleModal()" class="modal-close">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="saleForm">
                <input type="hidden" id="selectedDeviceId">
                
                <!-- Info del dispositivo -->
                <div id="deviceInfo" class="alert alert-info mb-4 hidden">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="flex-1">
                            <p class="font-semibold" id="deviceName"></p>
                            <p class="text-sm" id="deviceDetails"></p>
                        </div>
                        <p class="text-lg font-bold text-primary" id="devicePrice"></p>
                    </div>
                </div>
                
                <!-- Datos del cliente -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900">Información del Cliente</h4>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre del Cliente *</label>
                        <input type="text" id="cliente_nombre" required placeholder="Nombre completo" class="form-input">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" id="cliente_telefono" placeholder="Número" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" id="cliente_email" placeholder="correo@ejemplo.com" class="form-input">
                        </div>
                    </div>
                </div>
                
                <!-- Detalles de venta -->
                <div class="space-y-4 mt-6">
                    <h4 class="font-medium text-gray-900">Detalles de la Venta</h4>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Precio de Venta *</label>
                            <div class="form-input-icon">
                                <span class="absolute left-3 top-2 text-gray-500">$</span>
                                <input type="number" id="precio_venta" step="0.01" min="0" required class="form-input" style="padding-left: 2rem;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Método de Pago</label>
                            <select id="metodo_pago" class="form-select">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="credito">Crédito</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notas <span class="text-gray-400">(opcional)</span></label>
                        <textarea id="notas" rows="2" placeholder="Observaciones..." class="form-textarea"></textarea>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <button type="button" onclick="closeSaleModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="button" onclick="registerSale()" class="btn btn-success">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/sales.js"></script>
</body>
</html>
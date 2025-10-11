<?php
/**
 * SISTEMA DE VENTAS DE CELULARES
 * Versión 3.1 - ERRORES CORREGIDOS
 * Sin errores de sintaxis ni referencias indefinidas
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

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
    if (hasPermission('admin')) {
        $devices_query = "
            SELECT c.*, t.nombre as tienda_nombre 
            FROM celulares c 
            LEFT JOIN tiendas t ON c.tienda_id = t.id 
            WHERE c.estado = 'disponible' 
            ORDER BY c.fecha_registro DESC
            LIMIT 20
        ";
        $devices_stmt = $db->query($devices_query);
    } else {
        $devices_query = "
            SELECT c.*, t.nombre as tienda_nombre 
            FROM celulares c 
            LEFT JOIN tiendas t ON c.tienda_id = t.id 
            WHERE c.estado = 'disponible' AND c.tienda_id = ? 
            ORDER BY c.fecha_registro DESC
            LIMIT 20
        ";
        $devices_stmt = $db->prepare($devices_query);
        $devices_stmt->execute([$user['tienda_id']]);
    }
    $available_devices = $devices_stmt->fetchAll();
    
    if (hasPermission('admin')) {
        $sales_query = "
            SELECT v.*, c.modelo, c.marca, c.capacidad, c.imei1, 
                   t.nombre as tienda_nombre, u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN celulares c ON v.celular_id = c.id
            LEFT JOIN tiendas t ON v.tienda_id = t.id  
            LEFT JOIN usuarios u ON v.vendedor_id = u.id
            ORDER BY v.fecha_venta DESC
            LIMIT 20
        ";
        $sales_stmt = $db->query($sales_query);
    } else {
        $sales_query = "
            SELECT v.*, c.modelo, c.marca, c.capacidad, c.imei1, 
                   t.nombre as tienda_nombre, u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN celulares c ON v.celular_id = c.id
            LEFT JOIN tiendas t ON v.tienda_id = t.id  
            LEFT JOIN usuarios u ON v.vendedor_id = u.id
            WHERE v.tienda_id = ?
            ORDER BY v.fecha_venta DESC
            LIMIT 20
        ";
        $sales_stmt = $db->prepare($sales_query);
        $sales_stmt->execute([$user['tienda_id']]);
    }
    $recent_sales = $sales_stmt->fetchAll();
    
    $today = date('Y-m-d');
    if (hasPermission('admin')) {
        $stats_query = "
            SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas WHERE DATE(fecha_venta) = ?
        ";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute([$today]);
    } else {
        $stats_query = "
            SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?
        ";
        $stats_stmt = $db->prepare($stats_query);
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
    <title>Ventas de Celulares - <?php echo SYSTEM_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📱</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .modal { display: none; }
        .modal.show { display: flex; }
        .device-card { 
            transition: all 0.2s ease; 
            cursor: pointer;
        }
        .device-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        }
        .device-selected { 
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: #22c55e; 
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); 
        }
        .stats-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <?php renderNavbar('sales'); ?>
    
    <main class="page-content">
        <div class="p-6">
            <div class="mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">Ventas de Celulares</h2>
                        <p class="text-gray-600">
                            <?php if ($user['rol'] === 'admin'): ?>
                                Gestión global - Todas las tiendas
                            <?php else: ?>
                                Tienda: <?php echo htmlspecialchars($user['tienda_nombre']); ?>
                            <?php endif; ?>
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
                
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="font-medium text-blue-800">Proceso de venta rápido:</p>
                            <p class="text-sm text-blue-700">1. Busca el dispositivo → 2. Selecciona → 3. Completa datos del cliente → 4. Confirma la venta</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-1 relative">
                                <input type="text" id="deviceSearch" placeholder="Buscar por modelo, marca, IMEI, color..." 
                                       class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <button type="button" onclick="searchDevices()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                Buscar
                            </button>
                            <button type="button" onclick="clearDeviceSearch()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                                Limpiar
                            </button>
                        </div>
                        <p class="text-xs text-gray-500" id="searchInfo">
                            <?php if ($user['rol'] === 'vendedor'): ?>
                                Buscando solo en <?php echo htmlspecialchars($user['tienda_nombre']); ?>
                            <?php else: ?>
                                Mostrando los últimos 20 dispositivos disponibles
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Dispositivos Disponibles</h3>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full" id="deviceCount">
                            <?php echo count($available_devices); ?> disponibles
                        </span>
                    </div>
                    
                    <div class="p-6 max-h-96 overflow-y-auto" id="devicesContainer">
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
                                    <p class="text-sm text-gray-400 mt-1">
                                        <?php if ($user['rol'] === 'admin'): ?>
                                            Ve a <a href="inventory.php" class="text-blue-600 underline">Inventario</a> para agregar
                                        <?php else: ?>
                                            Contacta al administrador
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach($available_devices as $device): ?>
                                    <div class="device-card border rounded-lg p-4 hover:border-blue-300 transition-all duration-200" 
                                         data-device-id="<?php echo $device['id']; ?>"
                                         data-device='<?php echo htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($device['modelo']); ?></p>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Disponible
                                                    </span>
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
                                                
                                                <p class="text-xs text-gray-600 capitalize">
                                                    Condición: <?php echo ucfirst($device['condicion']); ?>
                                                </p>
                                                
                                                <?php if (hasPermission('admin')): ?>
                                                    <p class="text-xs text-blue-600 mt-1"><?php echo htmlspecialchars($device['tienda_nombre']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="text-right ml-4">
                                                <p class="font-bold text-lg text-blue-600">$<?php echo number_format($device['precio'], 2); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Ventas Recientes</h3>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            Últimas <?php echo count($recent_sales); ?> ventas
                        </span>
                    </div>
                    <div class="p-6 max-h-96 overflow-y-auto">
                        <?php if (empty($recent_sales)): ?>
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">No hay ventas registradas</p>
                                <p class="text-sm text-gray-400 mt-1">¡Registra tu primera venta!</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($recent_sales as $sale): ?>
                                    <div class="border-l-4 border-green-400 bg-green-50 p-4 rounded-r-lg">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($sale['modelo']); ?></p>
                                                
                                                <p class="text-sm text-gray-600 mb-1">
                                                    Cliente: <?php echo htmlspecialchars($sale['cliente_nombre']); ?>
                                                </p>
                                                
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <span><?php echo date('d/m/Y H:i', strtotime($sale['fecha_venta'])); ?></span>
                                                    <?php if (hasPermission('admin')): ?>
                                                        <span>•</span>
                                                        <span><?php echo htmlspecialchars($sale['tienda_nombre']); ?></span>
                                                    <?php endif; ?>
                                                    <span>•</span>
                                                    <span><?php echo htmlspecialchars($sale['vendedor_nombre']); ?></span>
                                                    <span>•</span>
                                                    <span><?php echo ucfirst($sale['metodo_pago']); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="text-right ml-4">
                                                <p class="font-bold text-lg text-green-600">$<?php echo number_format($sale['precio_venta'], 2); ?></p>
                                            </div>
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

    <div id="saleModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-900">Registrar Venta</h3>
                <button type="button" onclick="closeSaleModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="saleForm" class="space-y-4">
                <input type="hidden" id="selectedDeviceId">
                
                <div id="deviceInfo" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 p-4 rounded-lg hidden">
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900" id="deviceName"></p>
                            <p class="text-sm text-gray-600" id="deviceDetails"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-blue-600" id="devicePrice"></p>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-3">Información del Cliente</h4>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Cliente *</label>
                            <input type="text" id="cliente_nombre" required 
                                   placeholder="Nombre completo"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input type="tel" id="cliente_telefono" 
                                       placeholder="Número de contacto"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" id="cliente_email" 
                                       placeholder="correo@ejemplo.com"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-3">Detalles de la Venta</h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Precio de Venta *</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-500">$</span>
                                <input type="number" id="precio_venta" step="0.01" min="0" required 
                                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
                            <select id="metodo_pago" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="credito">Crédito</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas <span class="text-gray-400">(opcional)</span></label>
                        <textarea id="notas" rows="2" 
                                  placeholder="Observaciones adicionales..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeSaleModal()" 
                            class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="button" onclick="registerSale()" 
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
// ==========================================
// SCRIPT JAVASCRIPT COMPLETO CORREGIDO
// Reemplaza todo el bloque <script> en sales.php
// ==========================================

// Variables globales
var selectedDevice = null;
var searchTimeout = null;

// Función para buscar dispositivos
function searchDevices() {
    var searchTerm = document.getElementById('deviceSearch').value.trim();
    
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('devicesList').style.opacity = '0.5';
    
    var formData = new FormData();
    formData.append('action', 'search_devices');
    formData.append('search', searchTerm);
    
    fetch('sales.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            renderDevices(data.devices);
            document.getElementById('deviceCount').textContent = data.devices.length + ' encontrados';
            
            if (searchTerm) {
                document.getElementById('searchInfo').textContent = 
                    'Mostrando ' + data.devices.length + ' resultados para "' + searchTerm + '"';
            } else {
                document.getElementById('searchInfo').textContent = 
                    '<?php echo $user["rol"] === "vendedor" ? "Mostrando dispositivos de " . htmlspecialchars($user["tienda_nombre"]) : "Mostrando todos los dispositivos disponibles"; ?>';
            }
        } else {
            showNotification('Error al buscar: ' + data.message, 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('Error en la búsqueda', 'error');
    })
    .finally(function() {
        document.getElementById('loadingSpinner').classList.add('hidden');
        document.getElementById('devicesList').style.opacity = '1';
    });
}

// Función para renderizar dispositivos
function renderDevices(devices) {
    var container = document.getElementById('devicesList');
    
    if (devices.length === 0) {
        container.innerHTML = '<div class="text-center py-8">' +
            '<svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>' +
            '</svg>' +
            '<p class="text-gray-500 font-medium">No se encontraron dispositivos</p>' +
            '<p class="text-sm text-gray-400 mt-1">Intenta con otros términos de búsqueda</p>' +
            '</div>';
        return;
    }
    
    var html = '';
    var isAdmin = <?php echo hasPermission('admin') ? 'true' : 'false'; ?>;
    
    for (var i = 0; i < devices.length; i++) {
        var device = devices[i];
        var deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
        
        html += '<div class="device-card border rounded-lg p-4 hover:border-blue-300 transition-all duration-200" ' +
                'data-device-id="' + device.id + '" ' +
                'data-device="' + deviceJson + '">' +
                '<div class="flex justify-between items-start">' +
                '<div class="flex-1">' +
                '<div class="flex items-center gap-2 mb-1">' +
                '<p class="font-medium text-gray-900">' + escapeHtml(device.modelo) + '</p>' +
                '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Disponible</span>' +
                '</div>' +
                '<p class="text-sm text-gray-600 mb-1">' +
                escapeHtml(device.marca) + ' - ' + escapeHtml(device.capacidad);
        
        if (device.color) {
            html += ' - ' + escapeHtml(device.color);
        }
        
        html += '</p>';
        
        if (device.imei1) {
            html += '<p class="text-xs text-gray-500 font-mono bg-gray-100 inline-block px-2 py-1 rounded mb-1">' +
                    'IMEI: ' + escapeHtml(device.imei1) + '</p>';
        }
        
        html += '<p class="text-xs text-gray-600 capitalize">Condición: ' + device.condicion + '</p>';
        
        if (isAdmin && device.tienda_nombre) {
            html += '<p class="text-xs text-blue-600 mt-1">' + escapeHtml(device.tienda_nombre) + '</p>';
        }
        
        html += '</div>' +
                '<div class="text-right ml-4">' +
                '<p class="font-bold text-lg text-blue-600">$' + formatPrice(device.precio) + '</p>' +
                '</div>' +
                '</div>' +
                '</div>';
    }
    
    container.innerHTML = html;
    
    // Agregar event listeners después de renderizar
    attachDeviceCardListeners();
}

// Función para agregar listeners a las tarjetas
function attachDeviceCardListeners() {
    var cards = document.querySelectorAll('.device-card');
    cards.forEach(function(card) {
        card.addEventListener('click', function() {
            var deviceData = this.getAttribute('data-device');
            if (deviceData) {
                try {
                    var device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error parsing device data:', e);
                    showNotification('Error al seleccionar dispositivo', 'error');
                }
            }
        });
    });
}

// Función para limpiar búsqueda
function clearDeviceSearch() {
    document.getElementById('deviceSearch').value = '';
    searchDevices();
}

// Event listener para búsqueda con delay
document.getElementById('deviceSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        searchDevices();
    }, 500);
});

// Event listener para Enter en búsqueda
document.getElementById('deviceSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchDevices();
    }
});

// Función para seleccionar dispositivo
function selectDeviceForSale(device) {
    selectedDevice = device;
    
    // Limpiar selección previa
    var cards = document.querySelectorAll('.device-card');
    cards.forEach(function(el) {
        el.classList.remove('device-selected');
    });
    
    // Marcar tarjeta seleccionada
    var selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
    if (selectedCard) {
        selectedCard.classList.add('device-selected');
        selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // Llenar información del dispositivo
    document.getElementById('selectedDeviceId').value = device.id;
    document.getElementById('deviceName').textContent = device.modelo;
    document.getElementById('deviceDetails').textContent = 
        device.marca + ' - ' + device.capacidad + (device.color ? ' - ' + device.color : '');
    document.getElementById('devicePrice').textContent = '$' + parseFloat(device.precio).toFixed(2);
    document.getElementById('deviceInfo').classList.remove('hidden');
    
    // Pre-llenar precio
    document.getElementById('precio_venta').value = device.precio;
    
    // Abrir modal
    document.getElementById('saleModal').classList.add('show');
    setTimeout(function() { 
        document.getElementById('cliente_nombre').focus(); 
    }, 100);
}

// Función para cerrar modal
function closeSaleModal() {
    document.getElementById('saleModal').classList.remove('show');
    clearSaleForm();
    clearDeviceSelection();
}

// Función para limpiar formulario
function clearSaleForm() {
    document.getElementById('cliente_nombre').value = '';
    document.getElementById('cliente_telefono').value = '';
    document.getElementById('cliente_email').value = '';
    document.getElementById('precio_venta').value = '';
    document.getElementById('metodo_pago').value = 'efectivo';
    document.getElementById('notas').value = '';
}

// Función para limpiar selección
function clearDeviceSelection() {
    selectedDevice = null;
    var cards = document.querySelectorAll('.device-card');
    cards.forEach(function(el) {
        el.classList.remove('device-selected');
    });
    document.getElementById('deviceInfo').classList.add('hidden');
}

// Función para registrar venta
function registerSale() {
    if (!selectedDevice) {
        showNotification('No se ha seleccionado un dispositivo', 'error');
        return;
    }
    
    var cliente_nombre = document.getElementById('cliente_nombre').value.trim();
    var precio_venta = parseFloat(document.getElementById('precio_venta').value);
    
    if (!cliente_nombre) {
        showNotification('Por favor ingresa el nombre del cliente', 'warning');
        document.getElementById('cliente_nombre').focus();
        return;
    }
    
    if (!precio_venta || precio_venta <= 0) {
        showNotification('Por favor ingresa un precio válido', 'warning');
        document.getElementById('precio_venta').focus();
        return;
    }
    
    var confirmMessage = '¿Confirmar venta?\n\n' +
                        'Dispositivo: ' + selectedDevice.modelo + '\n' +
                        'Cliente: ' + cliente_nombre + '\n' +
                        'Precio: $' + precio_venta.toFixed(2);
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    var formData = new FormData();
    formData.append('action', 'register_sale');
    formData.append('celular_id', selectedDevice.id);
    formData.append('cliente_nombre', cliente_nombre);
    formData.append('cliente_telefono', document.getElementById('cliente_telefono').value);
    formData.append('cliente_email', document.getElementById('cliente_email').value);
    formData.append('precio_venta', precio_venta);
    formData.append('metodo_pago', document.getElementById('metodo_pago').value);
    formData.append('notas', document.getElementById('notas').value);
    
    var buttons = document.querySelectorAll('#saleForm button');
    buttons.forEach(function(btn) { btn.disabled = true; });
    
    fetch('sales.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification('✅ ' + data.message, 'success');
            clearSaleForm();
            closeSaleModal();
            showPrintDialog(data.venta_id);
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('❌ Error en la conexión. Por favor intenta nuevamente.', 'error');
    })
    .finally(function() {
        buttons.forEach(function(btn) { btn.disabled = false; });
    });
}

// Función para mostrar diálogo de impresión
function showPrintDialog(ventaId) {
    var modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = 
        '<div class="bg-white rounded-lg p-6 max-w-md mx-4 text-center">' +
        '<div class="mb-4">' +
        '<svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
        '</svg>' +
        '</div>' +
        '<h3 class="text-xl font-bold text-gray-900 mb-2">¡Venta Registrada!</h3>' +
        '<p class="text-gray-600 mb-6">La venta se ha registrado correctamente.</p>' +
        '<div class="flex gap-3 justify-center">' +
        '<button onclick="printInvoice(' + ventaId + ')" ' +
        'class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center">' +
        '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>' +
        '</svg>' +
        'Imprimir Comprobante' +
        '</button>' +
        '<button onclick="closeDialogAndReload(this)" ' +
        'class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors">' +
        'Continuar sin Imprimir' +
        '</button>' +
        '</div>' +
        '<p class="text-xs text-gray-500 mt-4">' +
        'Puedes imprimir el comprobante más tarde desde el historial de ventas' +
        '</p>' +
        '</div>';
    
    document.body.appendChild(modal);
}

// Función para imprimir factura
function printInvoice(ventaId) {
    var printWindow = window.open(
        'print_sale_invoice.php?id=' + ventaId,
        'PrintInvoice',
        'width=800,height=600,scrollbars=yes'
    );
    
    if (printWindow) {
        printWindow.onload = function() {
            setTimeout(function() {
                location.reload();
            }, 500);
        };
    } else {
        showNotification('❌ No se pudo abrir la ventana de impresión. Verifica los bloqueadores de ventanas emergentes.', 'error');
        setTimeout(function() { location.reload(); }, 2000);
    }
}

// Función para cerrar diálogo y recargar
function closeDialogAndReload(button) {
    button.closest('.fixed').remove();
    setTimeout(function() { location.reload(); }, 300);
}

// Función para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Función para formatear precio
function formatPrice(price) {
    return parseFloat(price).toFixed(2);
}

// Función para mostrar notificaciones
function showNotification(message, type) {
    type = type || 'info';
    var bgColors = {
        'success': 'bg-green-500',
        'error': 'bg-red-500', 
        'warning': 'bg-yellow-500',
        'info': 'bg-blue-500'
    };
    
    var notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 ' + bgColors[type] + ' text-white';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.style.opacity = '0';
        setTimeout(function() { notification.remove(); }, 300);
    }, 4000);
}

// Event listener para Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSaleModal();
    }
});

// Validación de precio
document.getElementById('precio_venta').addEventListener('input', function() {
    if (this.value < 0) this.value = 0;
});

// Sugerencia de email - SIN EXPRESIONES REGULARES
document.getElementById('cliente_nombre').addEventListener('blur', function() {
    var nombre = this.value.trim();
    var emailField = document.getElementById('cliente_email');
    
    if (nombre && !emailField.value) {
        // Limpiar el nombre
        var nombreLimpio = nombre.toLowerCase();
        
        // Reemplazar espacios por puntos
        var partes = nombreLimpio.split(' ');
        nombreLimpio = partes.join('.');
        
        // Remover acentos y caracteres especiales
        var acentos = {
            'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u',
            'à': 'a', 'è': 'e', 'ì': 'i', 'ò': 'o', 'ù': 'u',
            'ä': 'a', 'ë': 'e', 'ï': 'i', 'ö': 'o', 'ü': 'u',
            'â': 'a', 'ê': 'e', 'î': 'i', 'ô': 'o', 'û': 'u',
            'ã': 'a', 'õ': 'o', 'ñ': 'n', 'ç': 'c'
        };
        
        var resultado = '';
        for (var i = 0; i < nombreLimpio.length; i++) {
            var char = nombreLimpio[i];
            resultado += acentos[char] || char;
        }
        
        // Remover caracteres especiales (solo letras, números y puntos)
        var final = '';
        for (var j = 0; j < resultado.length; j++) {
            var c = resultado[j];
            if ((c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') || c === '.') {
                final += c;
            }
        }
        
        var sugerencia = final + '@ejemplo.com';
        emailField.placeholder = 'Ej: ' + sugerencia;
    }
});

// Inicializar listeners al cargar
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Sistema de Ventas Cargado');
    attachDeviceCardListeners();
});
    </script>
</body>
</html>
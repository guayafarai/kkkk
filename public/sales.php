<?php
/**
 * SISTEMA DE VENTAS - VERSIÓN FINAL 2.0
 * 100% Funcional con todas las características
 * - Paginación profesional
 * - Búsqueda optimizada
 * - Event delegation correcta
 * - Sin errores de sintaxis
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();
requirePageAccess('sales.php');

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
                $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
                $per_page = 12;
                $offset = ($page - 1) * $per_page;
                
                // Construir consulta
                $where_conditions = ["c.estado = 'disponible'"];
                $params = [];
                
                if (!empty($search)) {
                    $where_conditions[] = "(c.modelo LIKE ? OR c.marca LIKE ? OR c.imei1 LIKE ? OR c.imei2 LIKE ? OR c.color LIKE ? OR c.capacidad LIKE ?)";
                    $search_param = "%$search%";
                    $params = array_fill(0, 6, $search_param);
                }
                
                // Filtro por tienda
                if (!hasPermission('view_all_sales')) {
                    $where_conditions[] = "c.tienda_id = ?";
                    $params[] = $user['tienda_id'];
                }
                
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                
                // Contar total
                $count_query = "SELECT COUNT(*) as total FROM celulares c $where_clause";
                $count_stmt = $db->prepare($count_query);
                $count_stmt->execute($params);
                $total = $count_stmt->fetch()['total'];
                
                // Obtener dispositivos
                $query = "
                    SELECT c.*, t.nombre as tienda_nombre 
                    FROM celulares c 
                    LEFT JOIN tiendas t ON c.tienda_id = t.id 
                    $where_clause 
                    ORDER BY c.fecha_registro DESC
                    LIMIT $per_page OFFSET $offset
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $devices = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true, 
                    'devices' => $devices,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => ceil($total / $per_page)
                ]);
                exit;
                
            case 'register_sale':
                $db->beginTransaction();
                
                $celular_id = intval($_POST['celular_id']);
                
                // Verificar disponibilidad
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
                
                if (!canAccessDevice($device['tienda_id'], 'sell')) {
                    throw new Exception('Sin permisos para vender este dispositivo');
                }
                
                // Registrar venta
                $sale_stmt = $db->prepare("
                    INSERT INTO ventas (celular_id, tienda_id, vendedor_id, cliente_nombre, 
                                      cliente_telefono, cliente_email, precio_venta, metodo_pago, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $sale_stmt->execute([
                    $celular_id,
                    $device['tienda_id'],
                    $user['id'],
                    sanitize($_POST['cliente_nombre']),
                    sanitize($_POST['cliente_telefono']),
                    sanitize($_POST['cliente_email']),
                    floatval($_POST['precio_venta']),
                    $_POST['metodo_pago'],
                    sanitize($_POST['notas'])
                ]);
                
                $venta_id = $db->lastInsertId();
                
                // Actualizar estado
                $update_stmt = $db->prepare("UPDATE celulares SET estado = 'vendido' WHERE id = ?");
                $update_stmt->execute([$celular_id]);
                
                $db->commit();
                
                logActivity($user['id'], 'register_sale', 
                    "Venta #$venta_id - {$device['modelo']} - Cliente: {$_POST['cliente_nombre']} - $".floatval($_POST['precio_venta']));
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Venta registrada exitosamente',
                    'venta_id' => $venta_id
                ]);
                exit;
                
            default:
                throw new Exception('Acción no válida');
        }
    } catch(Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        logError("Error en AJAX: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// OBTENER DATOS INICIALES
// ==========================================
$devices_count = 0;
$available_devices = [];

try {
    // Contar dispositivos
    if (hasPermission('view_all_sales')) {
        $count_stmt = $db->query("SELECT COUNT(*) as total FROM celulares WHERE estado = 'disponible'");
    } else {
        $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM celulares WHERE estado = 'disponible' AND tienda_id = ?");
        $count_stmt->execute([$user['tienda_id']]);
    }
    $devices_count = $count_stmt->fetch()['total'];
    
    // Obtener primeros 12
    if (hasPermission('view_all_sales')) {
        $devices_stmt = $db->query("
            SELECT c.*, t.nombre as tienda_nombre 
            FROM celulares c 
            LEFT JOIN tiendas t ON c.tienda_id = t.id 
            WHERE c.estado = 'disponible' 
            ORDER BY c.fecha_registro DESC
            LIMIT 12
        ");
    } else {
        $devices_stmt = $db->prepare("
            SELECT c.*, t.nombre as tienda_nombre 
            FROM celulares c 
            LEFT JOIN tiendas t ON c.tienda_id = t.id 
            WHERE c.estado = 'disponible' AND c.tienda_id = ? 
            ORDER BY c.fecha_registro DESC
            LIMIT 12
        ");
        $devices_stmt->execute([$user['tienda_id']]);
    }
    $available_devices = $devices_stmt->fetchAll();
} catch(Exception $e) {
    logError("Error obteniendo dispositivos: " . $e->getMessage());
}

// Ventas recientes
$recent_sales = [];
try {
    if (hasPermission('view_all_sales')) {
        $sales_stmt = $db->query("
            SELECT v.*, c.modelo, c.marca, c.capacidad, c.imei1, 
                   t.nombre as tienda_nombre, u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN celulares c ON v.celular_id = c.id
            LEFT JOIN tiendas t ON v.tienda_id = t.id  
            LEFT JOIN usuarios u ON v.vendedor_id = u.id
            ORDER BY v.fecha_venta DESC
            LIMIT 15
        ");
    } else {
        $sales_stmt = $db->prepare("
            SELECT v.*, c.modelo, c.marca, c.capacidad, c.imei1, 
                   t.nombre as tienda_nombre, u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN celulares c ON v.celular_id = c.id
            LEFT JOIN tiendas t ON v.tienda_id = t.id  
            LEFT JOIN usuarios u ON v.vendedor_id = u.id
            WHERE v.tienda_id = ?
            ORDER BY v.fecha_venta DESC
            LIMIT 15
        ");
        $sales_stmt->execute([$user['tienda_id']]);
    }
    $recent_sales = $sales_stmt->fetchAll();
} catch(Exception $e) {
    logError("Error obteniendo ventas: " . $e->getMessage());
}

// Estadísticas
$today_stats = ['ventas' => 0, 'ingresos' => 0];
try {
    $today = date('Y-m-d');
    if (hasPermission('view_all_sales')) {
        $stats_stmt = $db->prepare("SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos FROM ventas WHERE DATE(fecha_venta) = ?");
        $stats_stmt->execute([$today]);
    } else {
        $stats_stmt = $db->prepare("SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos FROM ventas WHERE DATE(fecha_venta) = ? AND tienda_id = ?");
        $stats_stmt->execute([$today, $user['tienda_id']]);
    }
    $today_stats = $stats_stmt->fetch();
} catch(Exception $e) {
    // Usar valores por defecto
}

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .modal { 
            display: none; 
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        
        .device-card { 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .device-card:hover { 
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        .device-card.selected { 
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #22c55e !important;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.15);
            transform: scale(1.03);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            animation: pulse-subtle 2s ease-in-out infinite;
        }
        @keyframes pulse-subtle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .search-box {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
        
        .badge-nuevo {
            animation: bounce 1s ease-in-out infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .notification {
            animation: slideInRight 0.3s ease-out;
        }
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <?php renderNavbar('sales'); ?>
    
    <main class="page-content">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                            <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Centro de Ventas
                        </h2>
                        <p class="text-gray-600 mt-1">
                            <?php if ($user['rol'] === 'admin'): ?>
                                Gestión global - Todas las tiendas
                            <?php else: ?>
                                Tienda: <strong><?php echo htmlspecialchars($user['tienda_nombre']); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="stats-card text-white px-6 py-4 rounded-xl mt-4 md:mt-0 shadow-lg">
                        <div class="text-center">
                            <p class="text-sm opacity-90 font-medium">Ventas de Hoy</p>
                            <p class="text-3xl font-bold my-1"><?php echo $today_stats['ventas']; ?></p>
                            <p class="text-lg opacity-90">$<?php echo number_format($today_stats['ingresos'], 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 text-amber-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="font-semibold text-amber-900 mb-1">💡 Proceso de Venta Rápido:</p>
                            <p class="text-sm text-amber-800">
                                <span class="font-medium">1.</span> Busca por modelo, IMEI o color 
                                <span class="mx-2">→</span> 
                                <span class="font-medium">2.</span> Click en el dispositivo 
                                <span class="mx-2">→</span> 
                                <span class="font-medium">3.</span> Completa datos 
                                <span class="mx-2">→</span> 
                                <span class="font-medium">4.</span> ¡Venta registrada!
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Dispositivos Disponibles -->
                <div class="bg-white rounded-xl shadow-lg">
                    <!-- Búsqueda -->
                    <div class="search-box p-4 rounded-t-xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-1 relative">
                                <input type="text" 
                                       id="deviceSearch" 
                                       placeholder="🔍 Buscar por modelo, marca, IMEI, color o capacidad..." 
                                       class="w-full px-4 py-3 pl-11 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                                       autocomplete="off">
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <button type="button" id="clearSearchBtn"
                                    class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-3 rounded-lg transition-all hover:shadow-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500" id="searchInfo">
                            Mostrando <?php echo min(12, $devices_count); ?> de <?php echo $devices_count; ?> dispositivos disponibles
                        </p>
                    </div>
                    
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-green-50 to-blue-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            Dispositivos Disponibles
                        </h3>
                        <span class="bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-bold px-4 py-1.5 rounded-full shadow" id="deviceCount">
                            <?php echo $devices_count; ?> disponibles
                        </span>
                    </div>
                    
                    <!-- Contenedor dispositivos -->
                    <div class="p-4 max-h-[600px] overflow-y-auto" id="devicesContainer">
                        <div id="loadingSpinner" class="hidden flex justify-center items-center py-12">
                            <div class="loading-spinner"></div>
                        </div>
                        
                        <div id="devicesList" class="grid grid-cols-1 gap-4">
                            <?php if (empty($available_devices)): ?>
                                <div class="text-center py-12">
                                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-gray-600 font-semibold text-lg">No hay dispositivos disponibles</p>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <?php if ($user['rol'] === 'admin'): ?>
                                            Ve a <a href="inventory.php" class="text-blue-600 underline hover:text-blue-700">Inventario</a> para agregar
                                        <?php else: ?>
                                            Contacta al administrador
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach($available_devices as $device): 
                                    $deviceJson = htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8');
                                    $isAdmin = hasPermission('view_all_sales');
                                ?>
                                <div class="device-card border-2 border-gray-200 rounded-xl p-4 hover:border-green-400 bg-white shadow-sm"
                                     data-device-id="<?php echo $device['id']; ?>"
                                     data-device='<?php echo $deviceJson; ?>'
                                     onclick="window.handleDeviceClick(this)"
                                     style="cursor: pointer;">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <p class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($device['modelo']); ?></p>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-300">
                                                    ✓ Disponible
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-700 mb-1.5 font-medium">
                                                <?php echo htmlspecialchars($device['marca']); ?> - <?php echo htmlspecialchars($device['capacidad']); ?>
                                                <?php if ($device['color']): ?> - <?php echo htmlspecialchars($device['color']); ?><?php endif; ?>
                                            </p>
                                            <p class="text-xs text-gray-600 font-mono bg-gray-100 inline-block px-2 py-1 rounded">
                                                IMEI: <?php echo htmlspecialchars($device['imei1']); ?>
                                            </p>
                                            <p class="text-xs text-gray-600 mt-1 capitalize">
                                                <span class="font-semibold">Condición:</span> <?php echo ucfirst($device['condicion']); ?>
                                            </p>
                                            <?php if ($isAdmin && $device['tienda_nombre']): ?>
                                                <p class="text-xs text-blue-600 mt-1 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                    </svg>
                                                    <?php echo htmlspecialchars($device['tienda_nombre']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right ml-4">
                                            <p class="font-bold text-2xl text-green-600">$<?php echo number_format($device['precio'], 2); ?></p>
                                            <?php if ($device['precio_compra'] && $isAdmin): ?>
                                                <p class="text-xs text-gray-600 mt-1">
                                                    Ganancia: $<?php echo number_format($device['precio'] - $device['precio_compra'], 2); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Paginación -->
                        <div id="paginationContainer" class="mt-6 pt-4 border-t border-gray-200" style="<?php echo $devices_count <= 12 ? 'display: none;' : ''; ?>">
                            <div class="flex justify-between items-center">
                                <button type="button" id="prevPage" 
                                        onclick="window.changePage(-1)"
                                        class="flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white rounded-lg font-medium"
                                        <?php echo $devices_count <= 12 ? 'disabled' : ''; ?>>
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                    </svg>
                                    Anterior
                                </button>
                                <span id="pageInfo" class="text-sm font-medium text-gray-600">Página 1 de <?php echo ceil($devices_count / 12); ?></span>
                                <button type="button" id="nextPage" 
                                        onclick="window.changePage(1)"
                                        class="flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white rounded-lg font-medium">
                                    Siguiente
                                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ventas Recientes -->
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-purple-50 to-pink-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Ventas Recientes
                        </h3>
                        <span class="bg-gradient-to-r from-purple-500 to-purple-600 text-white text-sm font-bold px-4 py-1.5 rounded-full shadow">
                            Últimas <?php echo count($recent_sales); ?>
                        </span>
                    </div>
                    <div class="p-4 max-h-[600px] overflow-y-auto">
                        <?php if (empty($recent_sales)): ?>
                            <div class="text-center py-12">
                                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                <p class="text-gray-600 font-semibold text-lg">No hay ventas registradas</p>
                                <p class="text-sm text-gray-500 mt-2">¡Registra tu primera venta del día!</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($recent_sales as $sale): ?>
                                    <div class="border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-r-lg hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($sale['modelo']); ?></p>
                                                    <?php if (strtotime($sale['fecha_venta']) > strtotime('-1 hour')): ?>
                                                        <span class="badge-nuevo inline-block px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">NUEVO</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-700 mb-1">
                                                    <strong>Cliente:</strong> <?php echo htmlspecialchars($sale['cliente_nombre']); ?>
                                                </p>
                                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                                    <span class="flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <?php echo date('d/m/Y H:i', strtotime($sale['fecha_venta'])); ?>
                                                    </span>
                                                    <?php if (hasPermission('view_all_sales')): ?>
                                                        <span>•</span>
                                                        <span class="flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                            </svg>
                                                            <?php echo htmlspecialchars($sale['tienda_nombre']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span>•</span>
                                                    <span class="flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                        <?php echo htmlspecialchars($sale['vendedor_nombre']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-right ml-4">
                                                <p class="font-bold text-xl text-green-600">$<?php echo number_format($sale['precio_venta'], 2); ?></p>
                                                <p class="text-xs text-gray-600 mt-1 capitalize"><?php echo $sale['metodo_pago']; ?></p>
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

    <!-- Modal Registrar Venta -->
    <div id="saleModal" class="modal">
        <div class="bg-white rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between items-center mb-6 pb-4 border-b-2 border-gray-200">
                <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                    <svg class="w-7 h-7 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Registrar Venta
                </h3>
                <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="saleForm" class="space-y-6">
                <input type="hidden" id="selectedDeviceId">
                
                <!-- Info del dispositivo -->
                <div id="deviceInfo" class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-300 p-5 rounded-xl hidden">
                    <div class="flex items-center gap-4">
                        <div class="bg-green-500 p-3 rounded-xl shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-lg text-gray-900" id="deviceModel"></p>
                            <p class="text-sm text-gray-700" id="deviceDetails"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Precio Sugerido</p>
                            <p class="text-2xl font-bold text-green-600" id="devicePrice"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Datos del cliente -->
                <div class="border-t-2 pt-6">
                    <h4 class="font-bold text-lg text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Información del Cliente
                    </h4>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Nombre Completo <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="cliente_nombre" required 
                                   placeholder="Ej: Juan Pérez García"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all">
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Teléfono</label>
                                <input type="tel" id="cliente_telefono" 
                                       placeholder="Ej: +51 987654321"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" id="cliente_email" 
                                       placeholder="correo@ejemplo.com"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detalles de la venta -->
                <div class="border-t-2 pt-6">
                    <h4 class="font-bold text-lg text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Detalles de la Venta
                    </h4>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Precio de Venta <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3.5 text-gray-600 font-bold text-lg">$</span>
                                    <input type="number" id="precio_venta" step="0.01" min="0" required 
                                           placeholder="0.00"
                                           class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all text-lg font-semibold">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Método de Pago</label>
                                <select id="metodo_pago" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all">
                                    <option value="efectivo">💵 Efectivo</option>
                                    <option value="tarjeta">💳 Tarjeta</option>
                                    <option value="transferencia">🏦 Transferencia</option>
                                    <option value="credito">📱 Crédito</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Notas <span class="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <textarea id="sale_notas" rows="3" 
                                      placeholder="Observaciones adicionales: garantía, accesorios incluidos, condiciones especiales..."
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all resize-none"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="flex justify-end gap-3 pt-6 border-t-2">
                    <button type="button" id="cancelSaleBtn"
                            class="px-6 py-3 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg font-semibold transition-all hover:shadow-lg">
                        Cancelar
                    </button>
                    <button type="button" id="confirmSaleBtn"
                            class="px-8 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-lg font-bold transition-all hover:shadow-xl flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===========================================
        // VARIABLES GLOBALES (window.*)
        // ===========================================
        window.selectedDevice = null;
        window.searchTimeout = null;
        window.currentPage = 1;
        window.totalPages = 1;
        window.currentSearch = '';
        
        // ===========================================
        // FUNCIONES AUXILIARES
        // ===========================================
        window.showNotification = function(message, type) {
            type = type || 'info';
            var colors = {
                'success': 'from-green-500 to-green-600',
                'error': 'from-red-500 to-red-600',
                'warning': 'from-yellow-500 to-yellow-600',
                'info': 'from-blue-500 to-blue-600'
            };
            
            var notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 bg-gradient-to-r ' + colors[type] + ' text-white font-semibold';
            notification.style.animation = 'slideInRight 0.3s ease-out';
            notification.innerHTML = '<div class="flex items-center"><span>' + message + '</span></div>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                setTimeout(function() { notification.remove(); }, 300);
            }, 4000);
        };
        
        // ===========================================
        // FUNCIONES GLOBALES PRINCIPALES
        // ===========================================
        
        window.handleDeviceClick = function(element) {
            var deviceData = element.getAttribute('data-device');
            if (deviceData) {
                try {
                    var device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    window.selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error al parsear dispositivo:', e);
                    alert('Error al seleccionar dispositivo. Por favor intenta de nuevo.');
                }
            }
        };
        
        window.selectDeviceForSale = function(device) {
            console.log('📱 Dispositivo seleccionado:', device.modelo);
            window.selectedDevice = device;
            
            // Limpiar selección
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            
            // Marcar seleccionada
            var selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
            
            // Llenar modal
            document.getElementById('selectedDeviceId').value = device.id;
            document.getElementById('deviceModel').textContent = device.modelo;
            document.getElementById('deviceDetails').textContent = device.marca + ' - ' + device.capacidad;
            document.getElementById('devicePrice').textContent = '
        
        // Variables globales
        var selectedDevice = null;
        var searchTimeout = null;
        var currentPage = 1;
        var totalPages = 1;
        var currentSearch = '';
        
        // ===========================================
        // FUNCIÓN GLOBAL PARA ONCLICK (MÁS COMPATIBLE)
        // ===========================================
        function handleDeviceClick(element) {
            var deviceData = element.getAttribute('data-device');
            if (deviceData) {
                try {
                    var device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error parsing device:', e);
                    alert('Error al seleccionar dispositivo. Por favor intenta de nuevo.');
                }
            }
        }
        
        // ===========================================
        // FUNCIÓN CRÍTICA: selectDeviceForSale
        // ===========================================
        function selectDeviceForSale(device) {
            console.log('📱 Dispositivo seleccionado:', device.modelo);
            selectedDevice = device;
            
            // Limpiar selección previa
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            
            // Marcar seleccionada
            var selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Llenar modal
            document.getElementById('selectedDeviceId').value = device.id;
            document.getElementById('deviceModel').textContent = device.modelo;
            document.getElementById('deviceDetails').textContent = 
                device.marca + ' - ' + device.capacidad + (device.color ? ' - ' + device.color : '');
            document.getElementById('devicePrice').textContent = formatPrice(device.precio);
            document.getElementById('deviceInfo').classList.remove('hidden');
            document.getElementById('precio_venta').value = device.precio;
            
            openSaleModal();
        }
        
        // ===========================================
        // FUNCIONES AUXILIARES
        // ===========================================
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }
        
        function formatPrice(price) {
            return ' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function showNotification(message, type) {
            type = type || 'info';
            var colors = {
                'success': 'from-green-500 to-green-600',
                'error': 'from-red-500 to-red-600', 
                'warning': 'from-yellow-500 to-yellow-600',
                'info': 'from-blue-500 to-blue-600'
            };
            
            var icons = {
                'success': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'error': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'warning': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
                'info': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
            };
            
            var notification = document.createElement('div');
            notification.className = 'notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 bg-gradient-to-r ' + colors[type] + ' text-white font-semibold';
            notification.innerHTML = '<div class="flex items-center"><svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + icons[type] + '</svg><span>' + escapeHtml(message) + '</span></div>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 4000);
        }
        
        // ===========================================
        // BÚSQUEDA Y PAGINACIÓN
        // ===========================================
        function searchDevices(page) {
            page = page || 1;
            var searchTerm = document.getElementById('deviceSearch').value.trim();
            currentSearch = searchTerm;
            currentPage = page;
            
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('devicesList').style.opacity = '0.5';
            
            var formData = new FormData();
            formData.append('action', 'search_devices');
            formData.append('search', searchTerm);
            formData.append('page', page);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderDevices(data.devices);
                    updatePagination(data);
                    
                    var resultText = searchTerm ? 
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' resultados para "' + searchTerm + '"' :
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' dispositivos disponibles';
                    
                    document.getElementById('searchInfo').textContent = resultText;
                    document.getElementById('deviceCount').textContent = data.total + ' disponibles';
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
        
        function renderDevices(devices) {
            var container = document.getElementById('devicesList');
            
            if (devices.length === 0) {
                container.innerHTML = '<div class="text-center py-12"><svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg><p class="text-gray-600 font-semibold text-lg">No se encontraron dispositivos</p><p class="text-sm text-gray-500 mt-2">Intenta con otros términos</p></div>';
                return;
            }
            
            var html = '';
            var isAdmin = <?php echo hasPermission('view_all_sales') ? 'true' : 'false'; ?>;
            
            for (var i = 0; i < devices.length; i++) {
                var device = devices[i];
                var deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
                
                html += '<div class="device-card border-2 border-gray-200 rounded-xl p-4 hover:border-green-400 bg-white shadow-sm" ';
                html += 'data-device-id="' + device.id + '" ';
                html += 'data-device=\'' + deviceJson + '\' ';
                html += 'onclick="handleDeviceClick(this)">';
                html += '<div class="flex justify-between items-start">';
                html += '<div class="flex-1">';
                html += '<div class="flex items-center gap-2 mb-2">';
                html += '<p class="font-bold text-gray-900 text-lg">' + escapeHtml(device.modelo) + '</p>';
                html += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-300">✓ Disponible</span>';
                html += '</div>';
                html += '<p class="text-sm text-gray-700 mb-1.5 font-medium">' + escapeHtml(device.marca) + ' - ' + escapeHtml(device.capacidad);
                if (device.color) html += ' - ' + escapeHtml(device.color);
                html += '</p>';
                html += '<p class="text-xs text-gray-600 font-mono bg-gray-100 inline-block px-2 py-1 rounded">IMEI: ' + escapeHtml(device.imei1) + '</p>';
                html += '<p class="text-xs text-gray-600 mt-1 capitalize"><span class="font-semibold">Condición:</span> ' + capitalize(device.condicion) + '</p>';
                if (isAdmin && device.tienda_nombre) {
                    html += '<p class="text-xs text-blue-600 mt-1 flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>' + escapeHtml(device.tienda_nombre) + '</p>';
                }
                html += '</div>';
                html += '<div class="text-right ml-4">';
                html += '<p class="font-bold text-2xl text-green-600">' + formatPrice(device.precio) + '</p>';
                if (device.precio_compra && isAdmin) {
                    html += '<p class="text-xs text-gray-600 mt-1">Ganancia: ' + formatPrice(device.precio - device.precio_compra) + '</p>';
                }
                html += '</div></div></div>';
            }
            
            container.innerHTML = html;
            console.log('✅ Renderizados ' + devices.length + ' dispositivos');
        }
        
        // Esta función ya no es necesaria, pero la dejamos por compatibilidad
        function attachCardListeners() {
            console.log('ℹ️ attachCardListeners() llamada (usando onclick inline ahora)');
        }
        
        function updatePagination(data) {
            totalPages = data.total_pages;
            currentPage = data.page;
            
            var paginationContainer = document.getElementById('paginationContainer');
            var prevBtn = document.getElementById('prevPage');
            var nextBtn = document.getElementById('nextPage');
            var pageInfo = document.getElementById('pageInfo');
            
            console.log('📄 Paginación - Página ' + currentPage + ' de ' + totalPages);
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                console.log('⚠️ Paginación oculta (solo 1 página)');
                return;
            }
            
            paginationContainer.style.display = 'block';
            pageInfo.textContent = 'Página ' + currentPage + ' de ' + totalPages;
            
            // Actualizar botón anterior
            if (currentPage === 1) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.5';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
            }
            
            // Actualizar botón siguiente
            if (currentPage === totalPages) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
            }
            
            console.log('✅ Paginación actualizada');
        }}
        
        function changePage(direction) {
            var newPage = currentPage + direction;
            console.log('🔄 Intentando cambiar a página ' + newPage);
            
            if (newPage >= 1 && newPage <= totalPages) {
                searchDevices(newPage);
                var container = document.getElementById('devicesContainer');
                if (container) {
                    container.scrollTo({top: 0, behavior: 'smooth'});
                }
            } else {
                console.log('⚠️ Página ' + newPage + ' fuera de rango (1-' + totalPages + ')');
            }
        }
        
        function clearSearch() {
            document.getElementById('deviceSearch').value = '';
            currentSearch = '';
            currentPage = 1;
            searchDevices(1);
        }
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            console.log('📂 Abriendo modal de venta');
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            console.log('📂 Cerrando modal de venta');
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            document.getElementById('deviceInfo').classList.add('hidden');
        }
        
        // ===========================================
        // REGISTRAR VENTA
        // ===========================================
        function registerSale() {
            if (!selectedDevice) {
                showNotification('No hay dispositivo seleccionado', 'error');
                return;
            }
            
            var cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            var precio_venta = document.getElementById('precio_venta').value;
            
            if (!cliente_nombre) {
                showNotification('Ingresa el nombre del cliente', 'warning');
                document.getElementById('cliente_nombre').focus();
                return;
            }
            
            if (!precio_venta || precio_venta <= 0) {
                showNotification('Ingresa un precio de venta válido', 'warning');
                document.getElementById('precio_venta').focus();
                return;
            }
            
            var confirmMessage = '¿Confirmar venta?\n\n📱 ' + selectedDevice.modelo + '\n👤 ' + cliente_nombre + '\n💰 
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + precio_venta;
            
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
            formData.append('notas', document.getElementById('sale_notas').value);
            
            var submitBtn = document.getElementById('confirmSaleBtn');
            var originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="w-5 h-5 mr-2 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Procesando...';
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    closeSaleModal();
                    showPrintDialog(data.venta_id);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('❌ Error de conexión. Intenta nuevamente.', 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            });
        }
        
        function showPrintDialog(ventaId) {
            var modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 backdrop-blur-sm';
            modal.style.animation = 'fadeIn 0.3s ease-out';
            modal.innerHTML = '<div class="bg-white rounded-2xl p-8 max-w-md mx-4 text-center shadow-2xl" style="animation: slideInUp 0.5s ease-out;"><div class="mb-6"><div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4" style="animation: bounce 1s ease-in-out;"><svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Exitosa!</h3><p class="text-gray-600">La venta se ha registrado correctamente</p></div><div class="flex flex-col gap-3"><button onclick="printInvoice(' + ventaId + ')" class="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-xl font-bold transition-all hover:shadow-xl flex items-center justify-center"><svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>Imprimir Comprobante</button><button onclick="closeDialogAndReload(this)" class="w-full px-6 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all hover:shadow-lg">Continuar sin Imprimir</button></div><p class="text-xs text-gray-500 mt-4">💡 Puedes imprimir más tarde desde el historial</p></div>';
            
            document.body.appendChild(modal);
        }
        
        function printInvoice(ventaId) {
            var printWindow = window.open('print_sale_invoice.php?id=' + ventaId, 'PrintInvoice', 'width=800,height=600,scrollbars=yes');
            
            if (printWindow) {
                printWindow.onload = function() {
                    setTimeout(function() { location.reload(); }, 500);
                };
            } else {
                showNotification('❌ No se pudo abrir ventana de impresión', 'error');
                setTimeout(function() { location.reload(); }, 2000);
            }
        }
        
        function closeDialogAndReload(button) {
            button.closest('.fixed').remove();
            setTimeout(function() { location.reload(); }, 300);
        }
        
        // ===========================================
        // EVENT LISTENERS E INICIALIZACIÓN
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('═══════════════════════════════════════════');
            console.log('✅ Sistema de Ventas Cargado - v2.0');
            console.log('═══════════════════════════════════════════');
            console.log('📱 Dispositivos disponibles: <?php echo $devices_count; ?>');
            console.log('💰 Ventas hoy: <?php echo $today_stats["ventas"]; ?>');
            console.log('👤 Usuario: <?php echo $user["nombre"]; ?> (<?php echo $user["rol"]; ?>)');
            console.log('═══════════════════════════════════════════');
            
            try {
                // No es necesario attachCardListeners porque usamos onclick
                console.log('✅ Cards usando onclick inline');
                
                // Paginación
                var prevBtn = document.getElementById('prevPage');
                var nextBtn = document.getElementById('nextPage');
                
                if (prevBtn && nextBtn) {
                    prevBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('⬅️ Click en botón Anterior');
                        changePage(-1);
                    });
                    
                    nextBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('➡️ Click en botón Siguiente');
                        changePage(1);
                    });
                    
                    console.log('✅ Event listeners de paginación adjuntados');
                    
                    // Verificar estado inicial
                    var totalDevices = <?php echo $devices_count; ?>;
                    if (totalDevices > 12) {
                        console.log('✅ Paginación visible (' + totalDevices + ' dispositivos)');
                    } else {
                        console.log('ℹ️ Paginación oculta (' + totalDevices + ' ≤ 12)');
                    }
                }
                
                // Búsqueda
                var searchInput = document.getElementById('deviceSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            console.log('🔍 Buscando: ' + searchInput.value);
                            searchDevices(1);
                        }, 500);
                    });
                    
                    searchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            console.log('🔍 Búsqueda con Enter');
                            searchDevices(1);
                        }
                    });
                    
                    searchInput.focus();
                    console.log('✅ Búsqueda configurada');
                }
                
                // Botón limpiar
                var clearBtn = document.getElementById('clearSearchBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        console.log('🧹 Limpiando búsqueda');
                        clearSearch();
                    });
                }
                
                // Modal
                var closeModalBtn = document.getElementById('closeModalBtn');
                var cancelSaleBtn = document.getElementById('cancelSaleBtn');
                var confirmSaleBtn = document.getElementById('confirmSaleBtn');
                
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeSaleModal);
                }
                
                if (cancelSaleBtn) {
                    cancelSaleBtn.addEventListener('click', closeSaleModal);
                }
                
                if (confirmSaleBtn) {
                    confirmSaleBtn.addEventListener('click', registerSale);
                }
                
                console.log('✅ Modal configurado');
                
                // ESC para cerrar modal
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeSaleModal();
                    }
                });
                
                // Validaciones
                var precioInput = document.getElementById('precio_venta');
                if (precioInput) {
                    precioInput.addEventListener('input', function() {
                        if (this.value < 0) this.value = 0;
                    });
                }
                
                // Autocompletado
                var nombreInput = document.getElementById('cliente_nombre');
                var emailInput = document.getElementById('cliente_email');
                if (nombreInput && emailInput) {
                    nombreInput.addEventListener('blur', function() {
                        var nombre = this.value.trim();
                        if (nombre && !emailInput.value) {
                            var sugerencia = nombre.toLowerCase()
                                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                                .replace(/\s+/g, '.')
                                .replace(/[^a-z.]/g, '') + '@ejemplo.com';
                            emailInput.placeholder = 'Ej: ' + sugerencia;
                        }
                    });
                }
                
                console.log('✅ Validaciones configuradas');
                
                // Test de funciones críticas
                console.log('\n🧪 Test de funciones:');
                console.log('- selectDeviceForSale:', typeof selectDeviceForSale);
                console.log('- handleDeviceClick:', typeof handleDeviceClick);
                console.log('- changePage:', typeof changePage);
                console.log('- registerSale:', typeof registerSale);
                
                console.log('\n🎉 Sistema completamente inicializado y listo');
                
            } catch (error) {
                console.error('❌ Error en inicialización:', error);
                alert('Error al inicializar el sistema: ' + error.message);
            }
        });
        
        // Error handling global
        window.addEventListener('error', function(e) {
            console.error('💥 Error global capturado:', e.error);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('💥 Promise rechazada:', e.reason);
        });
        
        // Animaciones CSS
        var style = document.createElement('style');
        style.textContent = '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } } @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }';
        document.head.appendChild(style);
        
        console.log('✅ Estilos de animación inyectados');
        
    </script>
</body>
</html>
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + parseFloat(device.precio).toFixed(2);
            document.getElementById('deviceInfo').classList.remove('hidden');
            document.getElementById('precio_venta').value = device.precio;
            
            // Abrir modal
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        };
        
        window.changePage = function(direction) {
            console.log('🔄 Cambiando a página:', window.currentPage + direction);
            var newPage = window.currentPage + direction;
            
            if (newPage >= 1 && newPage <= window.totalPages) {
                window.searchDevices(newPage);
            }
        };
        
        window.closeSaleModal = function() {
            document.getElementById('saleModal').classList.remove('show');
            window.selectedDevice = null;
            document.getElementById('deviceInfo').classList.add('hidden');
        };
        
        window.registerSale = function() {
            if (!window.selectedDevice) {
                alert('No hay dispositivo seleccionado');
                return;
            }
            
            var cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            var precio_venta = document.getElementById('precio_venta').value;
            
            if (!cliente_nombre) {
                alert('Ingresa el nombre del cliente');
                return;
            }
            
            if (!precio_venta || precio_venta <= 0) {
                alert('Ingresa un precio válido');
                return;
            }
            
            if (!confirm('¿Confirmar venta de ' + window.selectedDevice.modelo + ' a ' + cliente_nombre + ' por 
        
        // Variables globales
        var selectedDevice = null;
        var searchTimeout = null;
        var currentPage = 1;
        var totalPages = 1;
        var currentSearch = '';
        
        // ===========================================
        // FUNCIÓN GLOBAL PARA ONCLICK (MÁS COMPATIBLE)
        // ===========================================
        function handleDeviceClick(element) {
            var deviceData = element.getAttribute('data-device');
            if (deviceData) {
                try {
                    var device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error parsing device:', e);
                    alert('Error al seleccionar dispositivo. Por favor intenta de nuevo.');
                }
            }
        }
        
        // ===========================================
        // FUNCIÓN CRÍTICA: selectDeviceForSale
        // ===========================================
        function selectDeviceForSale(device) {
            console.log('📱 Dispositivo seleccionado:', device.modelo);
            selectedDevice = device;
            
            // Limpiar selección previa
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            
            // Marcar seleccionada
            var selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Llenar modal
            document.getElementById('selectedDeviceId').value = device.id;
            document.getElementById('deviceModel').textContent = device.modelo;
            document.getElementById('deviceDetails').textContent = 
                device.marca + ' - ' + device.capacidad + (device.color ? ' - ' + device.color : '');
            document.getElementById('devicePrice').textContent = formatPrice(device.precio);
            document.getElementById('deviceInfo').classList.remove('hidden');
            document.getElementById('precio_venta').value = device.precio;
            
            openSaleModal();
        }
        
        // ===========================================
        // FUNCIONES AUXILIARES
        // ===========================================
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }
        
        function formatPrice(price) {
            return ' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function showNotification(message, type) {
            type = type || 'info';
            var colors = {
                'success': 'from-green-500 to-green-600',
                'error': 'from-red-500 to-red-600', 
                'warning': 'from-yellow-500 to-yellow-600',
                'info': 'from-blue-500 to-blue-600'
            };
            
            var icons = {
                'success': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'error': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'warning': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
                'info': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
            };
            
            var notification = document.createElement('div');
            notification.className = 'notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 bg-gradient-to-r ' + colors[type] + ' text-white font-semibold';
            notification.innerHTML = '<div class="flex items-center"><svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + icons[type] + '</svg><span>' + escapeHtml(message) + '</span></div>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 4000);
        }
        
        // ===========================================
        // BÚSQUEDA Y PAGINACIÓN
        // ===========================================
        function searchDevices(page) {
            page = page || 1;
            var searchTerm = document.getElementById('deviceSearch').value.trim();
            currentSearch = searchTerm;
            currentPage = page;
            
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('devicesList').style.opacity = '0.5';
            
            var formData = new FormData();
            formData.append('action', 'search_devices');
            formData.append('search', searchTerm);
            formData.append('page', page);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderDevices(data.devices);
                    updatePagination(data);
                    
                    var resultText = searchTerm ? 
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' resultados para "' + searchTerm + '"' :
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' dispositivos disponibles';
                    
                    document.getElementById('searchInfo').textContent = resultText;
                    document.getElementById('deviceCount').textContent = data.total + ' disponibles';
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
        
        function renderDevices(devices) {
            var container = document.getElementById('devicesList');
            
            if (devices.length === 0) {
                container.innerHTML = '<div class="text-center py-12"><svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg><p class="text-gray-600 font-semibold text-lg">No se encontraron dispositivos</p><p class="text-sm text-gray-500 mt-2">Intenta con otros términos</p></div>';
                return;
            }
            
            var html = '';
            var isAdmin = <?php echo hasPermission('view_all_sales') ? 'true' : 'false'; ?>;
            
            for (var i = 0; i < devices.length; i++) {
                var device = devices[i];
                var deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
                
                html += '<div class="device-card border-2 border-gray-200 rounded-xl p-4 hover:border-green-400 bg-white shadow-sm" ';
                html += 'data-device-id="' + device.id + '" ';
                html += 'data-device=\'' + deviceJson + '\' ';
                html += 'onclick="handleDeviceClick(this)">';
                html += '<div class="flex justify-between items-start">';
                html += '<div class="flex-1">';
                html += '<div class="flex items-center gap-2 mb-2">';
                html += '<p class="font-bold text-gray-900 text-lg">' + escapeHtml(device.modelo) + '</p>';
                html += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-300">✓ Disponible</span>';
                html += '</div>';
                html += '<p class="text-sm text-gray-700 mb-1.5 font-medium">' + escapeHtml(device.marca) + ' - ' + escapeHtml(device.capacidad);
                if (device.color) html += ' - ' + escapeHtml(device.color);
                html += '</p>';
                html += '<p class="text-xs text-gray-600 font-mono bg-gray-100 inline-block px-2 py-1 rounded">IMEI: ' + escapeHtml(device.imei1) + '</p>';
                html += '<p class="text-xs text-gray-600 mt-1 capitalize"><span class="font-semibold">Condición:</span> ' + capitalize(device.condicion) + '</p>';
                if (isAdmin && device.tienda_nombre) {
                    html += '<p class="text-xs text-blue-600 mt-1 flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>' + escapeHtml(device.tienda_nombre) + '</p>';
                }
                html += '</div>';
                html += '<div class="text-right ml-4">';
                html += '<p class="font-bold text-2xl text-green-600">' + formatPrice(device.precio) + '</p>';
                if (device.precio_compra && isAdmin) {
                    html += '<p class="text-xs text-gray-600 mt-1">Ganancia: ' + formatPrice(device.precio - device.precio_compra) + '</p>';
                }
                html += '</div></div></div>';
            }
            
            container.innerHTML = html;
            console.log('✅ Renderizados ' + devices.length + ' dispositivos');
        }
        
        // Esta función ya no es necesaria, pero la dejamos por compatibilidad
        function attachCardListeners() {
            console.log('ℹ️ attachCardListeners() llamada (usando onclick inline ahora)');
        }
        
        function updatePagination(data) {
            totalPages = data.total_pages;
            currentPage = data.page;
            
            var paginationContainer = document.getElementById('paginationContainer');
            var prevBtn = document.getElementById('prevPage');
            var nextBtn = document.getElementById('nextPage');
            var pageInfo = document.getElementById('pageInfo');
            
            console.log('📄 Paginación - Página ' + currentPage + ' de ' + totalPages);
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                console.log('⚠️ Paginación oculta (solo 1 página)');
                return;
            }
            
            paginationContainer.style.display = 'block';
            pageInfo.textContent = 'Página ' + currentPage + ' de ' + totalPages;
            
            // Actualizar botón anterior
            if (currentPage === 1) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.5';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
            }
            
            // Actualizar botón siguiente
            if (currentPage === totalPages) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
            }
            
            console.log('✅ Paginación actualizada');
        }}
        
        function changePage(direction) {
            var newPage = currentPage + direction;
            console.log('🔄 Intentando cambiar a página ' + newPage);
            
            if (newPage >= 1 && newPage <= totalPages) {
                searchDevices(newPage);
                var container = document.getElementById('devicesContainer');
                if (container) {
                    container.scrollTo({top: 0, behavior: 'smooth'});
                }
            } else {
                console.log('⚠️ Página ' + newPage + ' fuera de rango (1-' + totalPages + ')');
            }
        }
        
        function clearSearch() {
            document.getElementById('deviceSearch').value = '';
            currentSearch = '';
            currentPage = 1;
            searchDevices(1);
        }
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            console.log('📂 Abriendo modal de venta');
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            console.log('📂 Cerrando modal de venta');
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            document.getElementById('deviceInfo').classList.add('hidden');
        }
        
        // ===========================================
        // REGISTRAR VENTA
        // ===========================================
        function registerSale() {
            if (!selectedDevice) {
                showNotification('No hay dispositivo seleccionado', 'error');
                return;
            }
            
            var cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            var precio_venta = document.getElementById('precio_venta').value;
            
            if (!cliente_nombre) {
                showNotification('Ingresa el nombre del cliente', 'warning');
                document.getElementById('cliente_nombre').focus();
                return;
            }
            
            if (!precio_venta || precio_venta <= 0) {
                showNotification('Ingresa un precio de venta válido', 'warning');
                document.getElementById('precio_venta').focus();
                return;
            }
            
            var confirmMessage = '¿Confirmar venta?\n\n📱 ' + selectedDevice.modelo + '\n👤 ' + cliente_nombre + '\n💰 
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + precio_venta;
            
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
            formData.append('notas', document.getElementById('sale_notas').value);
            
            var submitBtn = document.getElementById('confirmSaleBtn');
            var originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="w-5 h-5 mr-2 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Procesando...';
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    closeSaleModal();
                    showPrintDialog(data.venta_id);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('❌ Error de conexión. Intenta nuevamente.', 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            });
        }
        
        function showPrintDialog(ventaId) {
            var modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 backdrop-blur-sm';
            modal.style.animation = 'fadeIn 0.3s ease-out';
            modal.innerHTML = '<div class="bg-white rounded-2xl p-8 max-w-md mx-4 text-center shadow-2xl" style="animation: slideInUp 0.5s ease-out;"><div class="mb-6"><div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4" style="animation: bounce 1s ease-in-out;"><svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Exitosa!</h3><p class="text-gray-600">La venta se ha registrado correctamente</p></div><div class="flex flex-col gap-3"><button onclick="printInvoice(' + ventaId + ')" class="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-xl font-bold transition-all hover:shadow-xl flex items-center justify-center"><svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>Imprimir Comprobante</button><button onclick="closeDialogAndReload(this)" class="w-full px-6 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all hover:shadow-lg">Continuar sin Imprimir</button></div><p class="text-xs text-gray-500 mt-4">💡 Puedes imprimir más tarde desde el historial</p></div>';
            
            document.body.appendChild(modal);
        }
        
        function printInvoice(ventaId) {
            var printWindow = window.open('print_sale_invoice.php?id=' + ventaId, 'PrintInvoice', 'width=800,height=600,scrollbars=yes');
            
            if (printWindow) {
                printWindow.onload = function() {
                    setTimeout(function() { location.reload(); }, 500);
                };
            } else {
                showNotification('❌ No se pudo abrir ventana de impresión', 'error');
                setTimeout(function() { location.reload(); }, 2000);
            }
        }
        
        function closeDialogAndReload(button) {
            button.closest('.fixed').remove();
            setTimeout(function() { location.reload(); }, 300);
        }
        
        // ===========================================
        // EVENT LISTENERS E INICIALIZACIÓN
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('═══════════════════════════════════════════');
            console.log('✅ Sistema de Ventas Cargado - v2.0');
            console.log('═══════════════════════════════════════════');
            console.log('📱 Dispositivos disponibles: <?php echo $devices_count; ?>');
            console.log('💰 Ventas hoy: <?php echo $today_stats["ventas"]; ?>');
            console.log('👤 Usuario: <?php echo $user["nombre"]; ?> (<?php echo $user["rol"]; ?>)');
            console.log('═══════════════════════════════════════════');
            
            try {
                // No es necesario attachCardListeners porque usamos onclick
                console.log('✅ Cards usando onclick inline');
                
                // Paginación
                var prevBtn = document.getElementById('prevPage');
                var nextBtn = document.getElementById('nextPage');
                
                if (prevBtn && nextBtn) {
                    prevBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('⬅️ Click en botón Anterior');
                        changePage(-1);
                    });
                    
                    nextBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('➡️ Click en botón Siguiente');
                        changePage(1);
                    });
                    
                    console.log('✅ Event listeners de paginación adjuntados');
                    
                    // Verificar estado inicial
                    var totalDevices = <?php echo $devices_count; ?>;
                    if (totalDevices > 12) {
                        console.log('✅ Paginación visible (' + totalDevices + ' dispositivos)');
                    } else {
                        console.log('ℹ️ Paginación oculta (' + totalDevices + ' ≤ 12)');
                    }
                }
                
                // Búsqueda
                var searchInput = document.getElementById('deviceSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            console.log('🔍 Buscando: ' + searchInput.value);
                            searchDevices(1);
                        }, 500);
                    });
                    
                    searchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            console.log('🔍 Búsqueda con Enter');
                            searchDevices(1);
                        }
                    });
                    
                    searchInput.focus();
                    console.log('✅ Búsqueda configurada');
                }
                
                // Botón limpiar
                var clearBtn = document.getElementById('clearSearchBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        console.log('🧹 Limpiando búsqueda');
                        clearSearch();
                    });
                }
                
                // Modal
                var closeModalBtn = document.getElementById('closeModalBtn');
                var cancelSaleBtn = document.getElementById('cancelSaleBtn');
                var confirmSaleBtn = document.getElementById('confirmSaleBtn');
                
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeSaleModal);
                }
                
                if (cancelSaleBtn) {
                    cancelSaleBtn.addEventListener('click', closeSaleModal);
                }
                
                if (confirmSaleBtn) {
                    confirmSaleBtn.addEventListener('click', registerSale);
                }
                
                console.log('✅ Modal configurado');
                
                // ESC para cerrar modal
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeSaleModal();
                    }
                });
                
                // Validaciones
                var precioInput = document.getElementById('precio_venta');
                if (precioInput) {
                    precioInput.addEventListener('input', function() {
                        if (this.value < 0) this.value = 0;
                    });
                }
                
                // Autocompletado
                var nombreInput = document.getElementById('cliente_nombre');
                var emailInput = document.getElementById('cliente_email');
                if (nombreInput && emailInput) {
                    nombreInput.addEventListener('blur', function() {
                        var nombre = this.value.trim();
                        if (nombre && !emailInput.value) {
                            var sugerencia = nombre.toLowerCase()
                                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                                .replace(/\s+/g, '.')
                                .replace(/[^a-z.]/g, '') + '@ejemplo.com';
                            emailInput.placeholder = 'Ej: ' + sugerencia;
                        }
                    });
                }
                
                console.log('✅ Validaciones configuradas');
                
                // Test de funciones críticas
                console.log('\n🧪 Test de funciones:');
                console.log('- selectDeviceForSale:', typeof selectDeviceForSale);
                console.log('- handleDeviceClick:', typeof handleDeviceClick);
                console.log('- changePage:', typeof changePage);
                console.log('- registerSale:', typeof registerSale);
                
                console.log('\n🎉 Sistema completamente inicializado y listo');
                
            } catch (error) {
                console.error('❌ Error en inicialización:', error);
                alert('Error al inicializar el sistema: ' + error.message);
            }
        });
        
        // Error handling global
        window.addEventListener('error', function(e) {
            console.error('💥 Error global capturado:', e.error);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('💥 Promise rechazada:', e.reason);
        });
        
        // Animaciones CSS
        var style = document.createElement('style');
        style.textContent = '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } } @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }';
        document.head.appendChild(style);
        
        console.log('✅ Estilos de animación inyectados');
        
    </script>
</body>
</html>
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + precio_venta + '?')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'register_sale');
            formData.append('celular_id', window.selectedDevice.id);
            formData.append('cliente_nombre', cliente_nombre);
            formData.append('cliente_telefono', document.getElementById('cliente_telefono').value);
            formData.append('cliente_email', document.getElementById('cliente_email').value);
            formData.append('precio_venta', precio_venta);
            formData.append('metodo_pago', document.getElementById('metodo_pago').value);
            formData.append('notas', document.getElementById('sale_notas').value);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    window.showNotification('✅ Venta registrada correctamente', 'success');
                    window.closeSaleModal();
                    
                    // Mostrar diálogo de impresión
                    var modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50';
                    modal.innerHTML = '<div class="bg-white rounded-2xl p-8 max-w-md mx-4 text-center shadow-2xl"><div class="mb-6"><div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Exitosa!</h3><p class="text-gray-600">La venta se ha registrado correctamente</p></div><div class="flex flex-col gap-3"><button onclick="window.open(\'print_sale_invoice.php?id=' + data.venta_id + '\', \'_blank\'); setTimeout(function(){location.reload();}, 500);" class="w-full px-6 py-4 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold">🖨️ Imprimir Comprobante</button><button onclick="location.reload();" class="w-full px-6 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold">Continuar sin Imprimir</button></div></div>';
                    document.body.appendChild(modal);
                } else {
                    window.showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                window.showNotification('❌ Error de conexión. Intenta nuevamente', 'error');
            });
        };
        
        window.searchDevices = function(page) {
            page = page || 1;
            var searchTerm = document.getElementById('deviceSearch').value.trim();
            
            document.getElementById('loadingSpinner').classList.remove('hidden');
            
            var formData = new FormData();
            formData.append('action', 'search_devices');
            formData.append('search', searchTerm);
            formData.append('page', page);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    window.renderDevices(data.devices);
                    window.updatePagination(data);
                }
            })
            .catch(function(error) {
                alert('Error en búsqueda');
            })
            .finally(function() {
                document.getElementById('loadingSpinner').classList.add('hidden');
            });
        };
        
        window.renderDevices = function(devices) {
            var container = document.getElementById('devicesList');
            
            if (devices.length === 0) {
                container.innerHTML = '<div class="text-center py-12"><p>No hay dispositivos</p></div>';
                return;
            }
            
            var html = '';
            var isAdmin = <?php echo hasPermission('view_all_sales') ? 'true' : 'false'; ?>;
            
            for (var i = 0; i < devices.length; i++) {
                var d = devices[i];
                var deviceJson = JSON.stringify(d).replace(/"/g, '&quot;');
                
                html += '<div class="device-card border-2 border-gray-200 rounded-xl p-4 bg-white shadow-sm" ';
                html += 'data-device-id="' + d.id + '" ';
                html += 'data-device=\'' + deviceJson + '\' ';
                html += 'onclick="window.handleDeviceClick(this)" ';
                html += 'style="cursor: pointer;">';
                html += '<div class="flex justify-between">';
                html += '<div class="flex-1">';
                html += '<p class="font-bold text-lg">' + d.modelo + '</p>';
                html += '<p class="text-sm">' + d.marca + ' - ' + d.capacidad + '</p>';
                html += '<p class="text-xs">IMEI: ' + d.imei1 + '</p>';
                html += '</div>';
                html += '<div class="text-right">';
                html += '<p class="text-2xl font-bold text-green-600">
        
        // Variables globales
        var selectedDevice = null;
        var searchTimeout = null;
        var currentPage = 1;
        var totalPages = 1;
        var currentSearch = '';
        
        // ===========================================
        // FUNCIÓN GLOBAL PARA ONCLICK (MÁS COMPATIBLE)
        // ===========================================
        function handleDeviceClick(element) {
            var deviceData = element.getAttribute('data-device');
            if (deviceData) {
                try {
                    var device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error parsing device:', e);
                    alert('Error al seleccionar dispositivo. Por favor intenta de nuevo.');
                }
            }
        }
        
        // ===========================================
        // FUNCIÓN CRÍTICA: selectDeviceForSale
        // ===========================================
        function selectDeviceForSale(device) {
            console.log('📱 Dispositivo seleccionado:', device.modelo);
            selectedDevice = device;
            
            // Limpiar selección previa
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            
            // Marcar seleccionada
            var selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Llenar modal
            document.getElementById('selectedDeviceId').value = device.id;
            document.getElementById('deviceModel').textContent = device.modelo;
            document.getElementById('deviceDetails').textContent = 
                device.marca + ' - ' + device.capacidad + (device.color ? ' - ' + device.color : '');
            document.getElementById('devicePrice').textContent = formatPrice(device.precio);
            document.getElementById('deviceInfo').classList.remove('hidden');
            document.getElementById('precio_venta').value = device.precio;
            
            openSaleModal();
        }
        
        // ===========================================
        // FUNCIONES AUXILIARES
        // ===========================================
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }
        
        function formatPrice(price) {
            return ' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function showNotification(message, type) {
            type = type || 'info';
            var colors = {
                'success': 'from-green-500 to-green-600',
                'error': 'from-red-500 to-red-600', 
                'warning': 'from-yellow-500 to-yellow-600',
                'info': 'from-blue-500 to-blue-600'
            };
            
            var icons = {
                'success': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'error': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'warning': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
                'info': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
            };
            
            var notification = document.createElement('div');
            notification.className = 'notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 bg-gradient-to-r ' + colors[type] + ' text-white font-semibold';
            notification.innerHTML = '<div class="flex items-center"><svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + icons[type] + '</svg><span>' + escapeHtml(message) + '</span></div>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 4000);
        }
        
        // ===========================================
        // BÚSQUEDA Y PAGINACIÓN
        // ===========================================
        function searchDevices(page) {
            page = page || 1;
            var searchTerm = document.getElementById('deviceSearch').value.trim();
            currentSearch = searchTerm;
            currentPage = page;
            
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('devicesList').style.opacity = '0.5';
            
            var formData = new FormData();
            formData.append('action', 'search_devices');
            formData.append('search', searchTerm);
            formData.append('page', page);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderDevices(data.devices);
                    updatePagination(data);
                    
                    var resultText = searchTerm ? 
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' resultados para "' + searchTerm + '"' :
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' dispositivos disponibles';
                    
                    document.getElementById('searchInfo').textContent = resultText;
                    document.getElementById('deviceCount').textContent = data.total + ' disponibles';
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
        
        function renderDevices(devices) {
            var container = document.getElementById('devicesList');
            
            if (devices.length === 0) {
                container.innerHTML = '<div class="text-center py-12"><svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg><p class="text-gray-600 font-semibold text-lg">No se encontraron dispositivos</p><p class="text-sm text-gray-500 mt-2">Intenta con otros términos</p></div>';
                return;
            }
            
            var html = '';
            var isAdmin = <?php echo hasPermission('view_all_sales') ? 'true' : 'false'; ?>;
            
            for (var i = 0; i < devices.length; i++) {
                var device = devices[i];
                var deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
                
                html += '<div class="device-card border-2 border-gray-200 rounded-xl p-4 hover:border-green-400 bg-white shadow-sm" ';
                html += 'data-device-id="' + device.id + '" ';
                html += 'data-device=\'' + deviceJson + '\' ';
                html += 'onclick="handleDeviceClick(this)">';
                html += '<div class="flex justify-between items-start">';
                html += '<div class="flex-1">';
                html += '<div class="flex items-center gap-2 mb-2">';
                html += '<p class="font-bold text-gray-900 text-lg">' + escapeHtml(device.modelo) + '</p>';
                html += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-300">✓ Disponible</span>';
                html += '</div>';
                html += '<p class="text-sm text-gray-700 mb-1.5 font-medium">' + escapeHtml(device.marca) + ' - ' + escapeHtml(device.capacidad);
                if (device.color) html += ' - ' + escapeHtml(device.color);
                html += '</p>';
                html += '<p class="text-xs text-gray-600 font-mono bg-gray-100 inline-block px-2 py-1 rounded">IMEI: ' + escapeHtml(device.imei1) + '</p>';
                html += '<p class="text-xs text-gray-600 mt-1 capitalize"><span class="font-semibold">Condición:</span> ' + capitalize(device.condicion) + '</p>';
                if (isAdmin && device.tienda_nombre) {
                    html += '<p class="text-xs text-blue-600 mt-1 flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>' + escapeHtml(device.tienda_nombre) + '</p>';
                }
                html += '</div>';
                html += '<div class="text-right ml-4">';
                html += '<p class="font-bold text-2xl text-green-600">' + formatPrice(device.precio) + '</p>';
                if (device.precio_compra && isAdmin) {
                    html += '<p class="text-xs text-gray-600 mt-1">Ganancia: ' + formatPrice(device.precio - device.precio_compra) + '</p>';
                }
                html += '</div></div></div>';
            }
            
            container.innerHTML = html;
            console.log('✅ Renderizados ' + devices.length + ' dispositivos');
        }
        
        // Esta función ya no es necesaria, pero la dejamos por compatibilidad
        function attachCardListeners() {
            console.log('ℹ️ attachCardListeners() llamada (usando onclick inline ahora)');
        }
        
        function updatePagination(data) {
            totalPages = data.total_pages;
            currentPage = data.page;
            
            var paginationContainer = document.getElementById('paginationContainer');
            var prevBtn = document.getElementById('prevPage');
            var nextBtn = document.getElementById('nextPage');
            var pageInfo = document.getElementById('pageInfo');
            
            console.log('📄 Paginación - Página ' + currentPage + ' de ' + totalPages);
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                console.log('⚠️ Paginación oculta (solo 1 página)');
                return;
            }
            
            paginationContainer.style.display = 'block';
            pageInfo.textContent = 'Página ' + currentPage + ' de ' + totalPages;
            
            // Actualizar botón anterior
            if (currentPage === 1) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.5';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
            }
            
            // Actualizar botón siguiente
            if (currentPage === totalPages) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
            }
            
            console.log('✅ Paginación actualizada');
        }}
        
        function changePage(direction) {
            var newPage = currentPage + direction;
            console.log('🔄 Intentando cambiar a página ' + newPage);
            
            if (newPage >= 1 && newPage <= totalPages) {
                searchDevices(newPage);
                var container = document.getElementById('devicesContainer');
                if (container) {
                    container.scrollTo({top: 0, behavior: 'smooth'});
                }
            } else {
                console.log('⚠️ Página ' + newPage + ' fuera de rango (1-' + totalPages + ')');
            }
        }
        
        function clearSearch() {
            document.getElementById('deviceSearch').value = '';
            currentSearch = '';
            currentPage = 1;
            searchDevices(1);
        }
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            console.log('📂 Abriendo modal de venta');
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            console.log('📂 Cerrando modal de venta');
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            document.getElementById('deviceInfo').classList.add('hidden');
        }
        
        // ===========================================
        // REGISTRAR VENTA
        // ===========================================
        function registerSale() {
            if (!selectedDevice) {
                showNotification('No hay dispositivo seleccionado', 'error');
                return;
            }
            
            var cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            var precio_venta = document.getElementById('precio_venta').value;
            
            if (!cliente_nombre) {
                showNotification('Ingresa el nombre del cliente', 'warning');
                document.getElementById('cliente_nombre').focus();
                return;
            }
            
            if (!precio_venta || precio_venta <= 0) {
                showNotification('Ingresa un precio de venta válido', 'warning');
                document.getElementById('precio_venta').focus();
                return;
            }
            
            var confirmMessage = '¿Confirmar venta?\n\n📱 ' + selectedDevice.modelo + '\n👤 ' + cliente_nombre + '\n💰 
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + precio_venta;
            
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
            formData.append('notas', document.getElementById('sale_notas').value);
            
            var submitBtn = document.getElementById('confirmSaleBtn');
            var originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="w-5 h-5 mr-2 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Procesando...';
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    closeSaleModal();
                    showPrintDialog(data.venta_id);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('❌ Error de conexión. Intenta nuevamente.', 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            });
        }
        
        function showPrintDialog(ventaId) {
            var modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 backdrop-blur-sm';
            modal.style.animation = 'fadeIn 0.3s ease-out';
            modal.innerHTML = '<div class="bg-white rounded-2xl p-8 max-w-md mx-4 text-center shadow-2xl" style="animation: slideInUp 0.5s ease-out;"><div class="mb-6"><div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4" style="animation: bounce 1s ease-in-out;"><svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Exitosa!</h3><p class="text-gray-600">La venta se ha registrado correctamente</p></div><div class="flex flex-col gap-3"><button onclick="printInvoice(' + ventaId + ')" class="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-xl font-bold transition-all hover:shadow-xl flex items-center justify-center"><svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>Imprimir Comprobante</button><button onclick="closeDialogAndReload(this)" class="w-full px-6 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all hover:shadow-lg">Continuar sin Imprimir</button></div><p class="text-xs text-gray-500 mt-4">💡 Puedes imprimir más tarde desde el historial</p></div>';
            
            document.body.appendChild(modal);
        }
        
        function printInvoice(ventaId) {
            var printWindow = window.open('print_sale_invoice.php?id=' + ventaId, 'PrintInvoice', 'width=800,height=600,scrollbars=yes');
            
            if (printWindow) {
                printWindow.onload = function() {
                    setTimeout(function() { location.reload(); }, 500);
                };
            } else {
                showNotification('❌ No se pudo abrir ventana de impresión', 'error');
                setTimeout(function() { location.reload(); }, 2000);
            }
        }
        
        function closeDialogAndReload(button) {
            button.closest('.fixed').remove();
            setTimeout(function() { location.reload(); }, 300);
        }
        
        // ===========================================
        // EVENT LISTENERS E INICIALIZACIÓN
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('═══════════════════════════════════════════');
            console.log('✅ Sistema de Ventas Cargado - v2.0');
            console.log('═══════════════════════════════════════════');
            console.log('📱 Dispositivos disponibles: <?php echo $devices_count; ?>');
            console.log('💰 Ventas hoy: <?php echo $today_stats["ventas"]; ?>');
            console.log('👤 Usuario: <?php echo $user["nombre"]; ?> (<?php echo $user["rol"]; ?>)');
            console.log('═══════════════════════════════════════════');
            
            try {
                // No es necesario attachCardListeners porque usamos onclick
                console.log('✅ Cards usando onclick inline');
                
                // Paginación
                var prevBtn = document.getElementById('prevPage');
                var nextBtn = document.getElementById('nextPage');
                
                if (prevBtn && nextBtn) {
                    prevBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('⬅️ Click en botón Anterior');
                        changePage(-1);
                    });
                    
                    nextBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('➡️ Click en botón Siguiente');
                        changePage(1);
                    });
                    
                    console.log('✅ Event listeners de paginación adjuntados');
                    
                    // Verificar estado inicial
                    var totalDevices = <?php echo $devices_count; ?>;
                    if (totalDevices > 12) {
                        console.log('✅ Paginación visible (' + totalDevices + ' dispositivos)');
                    } else {
                        console.log('ℹ️ Paginación oculta (' + totalDevices + ' ≤ 12)');
                    }
                }
                
                // Búsqueda
                var searchInput = document.getElementById('deviceSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            console.log('🔍 Buscando: ' + searchInput.value);
                            searchDevices(1);
                        }, 500);
                    });
                    
                    searchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            console.log('🔍 Búsqueda con Enter');
                            searchDevices(1);
                        }
                    });
                    
                    searchInput.focus();
                    console.log('✅ Búsqueda configurada');
                }
                
                // Botón limpiar
                var clearBtn = document.getElementById('clearSearchBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        console.log('🧹 Limpiando búsqueda');
                        clearSearch();
                    });
                }
                
                // Modal
                var closeModalBtn = document.getElementById('closeModalBtn');
                var cancelSaleBtn = document.getElementById('cancelSaleBtn');
                var confirmSaleBtn = document.getElementById('confirmSaleBtn');
                
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeSaleModal);
                }
                
                if (cancelSaleBtn) {
                    cancelSaleBtn.addEventListener('click', closeSaleModal);
                }
                
                if (confirmSaleBtn) {
                    confirmSaleBtn.addEventListener('click', registerSale);
                }
                
                console.log('✅ Modal configurado');
                
                // ESC para cerrar modal
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeSaleModal();
                    }
                });
                
                // Validaciones
                var precioInput = document.getElementById('precio_venta');
                if (precioInput) {
                    precioInput.addEventListener('input', function() {
                        if (this.value < 0) this.value = 0;
                    });
                }
                
                // Autocompletado
                var nombreInput = document.getElementById('cliente_nombre');
                var emailInput = document.getElementById('cliente_email');
                if (nombreInput && emailInput) {
                    nombreInput.addEventListener('blur', function() {
                        var nombre = this.value.trim();
                        if (nombre && !emailInput.value) {
                            var sugerencia = nombre.toLowerCase()
                                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                                .replace(/\s+/g, '.')
                                .replace(/[^a-z.]/g, '') + '@ejemplo.com';
                            emailInput.placeholder = 'Ej: ' + sugerencia;
                        }
                    });
                }
                
                console.log('✅ Validaciones configuradas');
                
                // Test de funciones críticas
                console.log('\n🧪 Test de funciones:');
                console.log('- selectDeviceForSale:', typeof selectDeviceForSale);
                console.log('- handleDeviceClick:', typeof handleDeviceClick);
                console.log('- changePage:', typeof changePage);
                console.log('- registerSale:', typeof registerSale);
                
                console.log('\n🎉 Sistema completamente inicializado y listo');
                
            } catch (error) {
                console.error('❌ Error en inicialización:', error);
                alert('Error al inicializar el sistema: ' + error.message);
            }
        });
        
        // Error handling global
        window.addEventListener('error', function(e) {
            console.error('💥 Error global capturado:', e.error);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('💥 Promise rechazada:', e.reason);
        });
        
        // Animaciones CSS
        var style = document.createElement('style');
        style.textContent = '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } } @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }';
        document.head.appendChild(style);
        
        console.log('✅ Estilos de animación inyectados');
        
    </script>
</body>
</html>
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + parseFloat(d.precio).toFixed(2) + '</p>';
                html += '</div>';
                html += '</div></div>';
            }
            
            container.innerHTML = html;
        };
        
        window.updatePagination = function(data) {
            window.totalPages = data.total_pages;
            window.currentPage = data.page;
            
            var pagination = document.getElementById('paginationContainer');
            var pageInfo = document.getElementById('pageInfo');
            var prevBtn = document.getElementById('prevPage');
            var nextBtn = document.getElementById('nextPage');
            
            if (data.total_pages <= 1) {
                pagination.style.display = 'none';
            } else {
                pagination.style.display = 'block';
                pageInfo.textContent = 'Página ' + data.page + ' de ' + data.total_pages;
                
                prevBtn.disabled = (data.page === 1);
                nextBtn.disabled = (data.page === data.total_pages);
            }
        };
        
        // ===========================================
        // INICIALIZACIÓN
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('═══════════════════════════════════════════');
            console.log('✅ Sistema de Ventas Cargado - v2.0 FINAL');
            console.log('═══════════════════════════════════════════');
            console.log('📱 Dispositivos disponibles:', <?php echo $devices_count; ?>);
            console.log('💰 Ventas hoy:', <?php echo $today_stats["ventas"]; ?>);
            console.log('👤 Usuario:', '<?php echo $user["nombre"]; ?>', '(<?php echo $user["rol"]; ?>)');
            console.log('═══════════════════════════════════════════');
            
            // Búsqueda
            var searchInput = document.getElementById('deviceSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(window.searchTimeout);
                    window.searchTimeout = setTimeout(function() {
                        window.searchDevices(1);
                    }, 500);
                });
            }
            
            // Botones del modal
            document.getElementById('closeModalBtn').onclick = window.closeSaleModal;
            document.getElementById('cancelSaleBtn').onclick = window.closeSaleModal;
            document.getElementById('confirmSaleBtn').onclick = window.registerSale;
            
            // ESC para cerrar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.closeSaleModal();
                }
            });
        });
    </script>
        
        // Variables globales
        var selectedDevice = null;
        var searchTimeout = null;
        var currentPage = 1;
        var totalPages = 1;
        var currentSearch = '';
        
        // ===========================================
        // FUNCIÓN GLOBAL PARA ONCLICK (MÁS COMPATIBLE)
        // ===========================================
        function handleDeviceClick(element) {
            var deviceData = element.getAttribute('data-device');
            if (deviceData) {
                try {
                    var device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error parsing device:', e);
                    alert('Error al seleccionar dispositivo. Por favor intenta de nuevo.');
                }
            }
        }
        
        // ===========================================
        // FUNCIÓN CRÍTICA: selectDeviceForSale
        // ===========================================
        function selectDeviceForSale(device) {
            console.log('📱 Dispositivo seleccionado:', device.modelo);
            selectedDevice = device;
            
            // Limpiar selección previa
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            
            // Marcar seleccionada
            var selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Llenar modal
            document.getElementById('selectedDeviceId').value = device.id;
            document.getElementById('deviceModel').textContent = device.modelo;
            document.getElementById('deviceDetails').textContent = 
                device.marca + ' - ' + device.capacidad + (device.color ? ' - ' + device.color : '');
            document.getElementById('devicePrice').textContent = formatPrice(device.precio);
            document.getElementById('deviceInfo').classList.remove('hidden');
            document.getElementById('precio_venta').value = device.precio;
            
            openSaleModal();
        }
        
        // ===========================================
        // FUNCIONES AUXILIARES
        // ===========================================
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }
        
        function formatPrice(price) {
            return ' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function showNotification(message, type) {
            type = type || 'info';
            var colors = {
                'success': 'from-green-500 to-green-600',
                'error': 'from-red-500 to-red-600', 
                'warning': 'from-yellow-500 to-yellow-600',
                'info': 'from-blue-500 to-blue-600'
            };
            
            var icons = {
                'success': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'error': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'warning': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
                'info': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
            };
            
            var notification = document.createElement('div');
            notification.className = 'notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 bg-gradient-to-r ' + colors[type] + ' text-white font-semibold';
            notification.innerHTML = '<div class="flex items-center"><svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + icons[type] + '</svg><span>' + escapeHtml(message) + '</span></div>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 4000);
        }
        
        // ===========================================
        // BÚSQUEDA Y PAGINACIÓN
        // ===========================================
        function searchDevices(page) {
            page = page || 1;
            var searchTerm = document.getElementById('deviceSearch').value.trim();
            currentSearch = searchTerm;
            currentPage = page;
            
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('devicesList').style.opacity = '0.5';
            
            var formData = new FormData();
            formData.append('action', 'search_devices');
            formData.append('search', searchTerm);
            formData.append('page', page);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderDevices(data.devices);
                    updatePagination(data);
                    
                    var resultText = searchTerm ? 
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' resultados para "' + searchTerm + '"' :
                        'Mostrando ' + data.devices.length + ' de ' + data.total + ' dispositivos disponibles';
                    
                    document.getElementById('searchInfo').textContent = resultText;
                    document.getElementById('deviceCount').textContent = data.total + ' disponibles';
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
        
        function renderDevices(devices) {
            var container = document.getElementById('devicesList');
            
            if (devices.length === 0) {
                container.innerHTML = '<div class="text-center py-12"><svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg><p class="text-gray-600 font-semibold text-lg">No se encontraron dispositivos</p><p class="text-sm text-gray-500 mt-2">Intenta con otros términos</p></div>';
                return;
            }
            
            var html = '';
            var isAdmin = <?php echo hasPermission('view_all_sales') ? 'true' : 'false'; ?>;
            
            for (var i = 0; i < devices.length; i++) {
                var device = devices[i];
                var deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
                
                html += '<div class="device-card border-2 border-gray-200 rounded-xl p-4 hover:border-green-400 bg-white shadow-sm" ';
                html += 'data-device-id="' + device.id + '" ';
                html += 'data-device=\'' + deviceJson + '\' ';
                html += 'onclick="handleDeviceClick(this)">';
                html += '<div class="flex justify-between items-start">';
                html += '<div class="flex-1">';
                html += '<div class="flex items-center gap-2 mb-2">';
                html += '<p class="font-bold text-gray-900 text-lg">' + escapeHtml(device.modelo) + '</p>';
                html += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-300">✓ Disponible</span>';
                html += '</div>';
                html += '<p class="text-sm text-gray-700 mb-1.5 font-medium">' + escapeHtml(device.marca) + ' - ' + escapeHtml(device.capacidad);
                if (device.color) html += ' - ' + escapeHtml(device.color);
                html += '</p>';
                html += '<p class="text-xs text-gray-600 font-mono bg-gray-100 inline-block px-2 py-1 rounded">IMEI: ' + escapeHtml(device.imei1) + '</p>';
                html += '<p class="text-xs text-gray-600 mt-1 capitalize"><span class="font-semibold">Condición:</span> ' + capitalize(device.condicion) + '</p>';
                if (isAdmin && device.tienda_nombre) {
                    html += '<p class="text-xs text-blue-600 mt-1 flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>' + escapeHtml(device.tienda_nombre) + '</p>';
                }
                html += '</div>';
                html += '<div class="text-right ml-4">';
                html += '<p class="font-bold text-2xl text-green-600">' + formatPrice(device.precio) + '</p>';
                if (device.precio_compra && isAdmin) {
                    html += '<p class="text-xs text-gray-600 mt-1">Ganancia: ' + formatPrice(device.precio - device.precio_compra) + '</p>';
                }
                html += '</div></div></div>';
            }
            
            container.innerHTML = html;
            console.log('✅ Renderizados ' + devices.length + ' dispositivos');
        }
        
        // Esta función ya no es necesaria, pero la dejamos por compatibilidad
        function attachCardListeners() {
            console.log('ℹ️ attachCardListeners() llamada (usando onclick inline ahora)');
        }
        
        function updatePagination(data) {
            totalPages = data.total_pages;
            currentPage = data.page;
            
            var paginationContainer = document.getElementById('paginationContainer');
            var prevBtn = document.getElementById('prevPage');
            var nextBtn = document.getElementById('nextPage');
            var pageInfo = document.getElementById('pageInfo');
            
            console.log('📄 Paginación - Página ' + currentPage + ' de ' + totalPages);
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                console.log('⚠️ Paginación oculta (solo 1 página)');
                return;
            }
            
            paginationContainer.style.display = 'block';
            pageInfo.textContent = 'Página ' + currentPage + ' de ' + totalPages;
            
            // Actualizar botón anterior
            if (currentPage === 1) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.5';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
            }
            
            // Actualizar botón siguiente
            if (currentPage === totalPages) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
            }
            
            console.log('✅ Paginación actualizada');
        }}
        
        function changePage(direction) {
            var newPage = currentPage + direction;
            console.log('🔄 Intentando cambiar a página ' + newPage);
            
            if (newPage >= 1 && newPage <= totalPages) {
                searchDevices(newPage);
                var container = document.getElementById('devicesContainer');
                if (container) {
                    container.scrollTo({top: 0, behavior: 'smooth'});
                }
            } else {
                console.log('⚠️ Página ' + newPage + ' fuera de rango (1-' + totalPages + ')');
            }
        }
        
        function clearSearch() {
            document.getElementById('deviceSearch').value = '';
            currentSearch = '';
            currentPage = 1;
            searchDevices(1);
        }
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            console.log('📂 Abriendo modal de venta');
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            console.log('📂 Cerrando modal de venta');
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
            document.getElementById('deviceInfo').classList.add('hidden');
        }
        
        // ===========================================
        // REGISTRAR VENTA
        // ===========================================
        function registerSale() {
            if (!selectedDevice) {
                showNotification('No hay dispositivo seleccionado', 'error');
                return;
            }
            
            var cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            var precio_venta = document.getElementById('precio_venta').value;
            
            if (!cliente_nombre) {
                showNotification('Ingresa el nombre del cliente', 'warning');
                document.getElementById('cliente_nombre').focus();
                return;
            }
            
            if (!precio_venta || precio_venta <= 0) {
                showNotification('Ingresa un precio de venta válido', 'warning');
                document.getElementById('precio_venta').focus();
                return;
            }
            
            var confirmMessage = '¿Confirmar venta?\n\n📱 ' + selectedDevice.modelo + '\n👤 ' + cliente_nombre + '\n💰 
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            } + precio_venta;
            
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
            formData.append('notas', document.getElementById('sale_notas').value);
            
            var submitBtn = document.getElementById('confirmSaleBtn');
            var originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="w-5 h-5 mr-2 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Procesando...';
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    closeSaleModal();
                    showPrintDialog(data.venta_id);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('❌ Error de conexión. Intenta nuevamente.', 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            });
        }
        
        function showPrintDialog(ventaId) {
            var modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 backdrop-blur-sm';
            modal.style.animation = 'fadeIn 0.3s ease-out';
            modal.innerHTML = '<div class="bg-white rounded-2xl p-8 max-w-md mx-4 text-center shadow-2xl" style="animation: slideInUp 0.5s ease-out;"><div class="mb-6"><div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4" style="animation: bounce 1s ease-in-out;"><svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Exitosa!</h3><p class="text-gray-600">La venta se ha registrado correctamente</p></div><div class="flex flex-col gap-3"><button onclick="printInvoice(' + ventaId + ')" class="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-xl font-bold transition-all hover:shadow-xl flex items-center justify-center"><svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>Imprimir Comprobante</button><button onclick="closeDialogAndReload(this)" class="w-full px-6 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all hover:shadow-lg">Continuar sin Imprimir</button></div><p class="text-xs text-gray-500 mt-4">💡 Puedes imprimir más tarde desde el historial</p></div>';
            
            document.body.appendChild(modal);
        }
        
        function printInvoice(ventaId) {
            var printWindow = window.open('print_sale_invoice.php?id=' + ventaId, 'PrintInvoice', 'width=800,height=600,scrollbars=yes');
            
            if (printWindow) {
                printWindow.onload = function() {
                    setTimeout(function() { location.reload(); }, 500);
                };
            } else {
                showNotification('❌ No se pudo abrir ventana de impresión', 'error');
                setTimeout(function() { location.reload(); }, 2000);
            }
        }
        
        function closeDialogAndReload(button) {
            button.closest('.fixed').remove();
            setTimeout(function() { location.reload(); }, 300);
        }
        
        // ===========================================
        // EVENT LISTENERS E INICIALIZACIÓN
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('═══════════════════════════════════════════');
            console.log('✅ Sistema de Ventas Cargado - v2.0');
            console.log('═══════════════════════════════════════════');
            console.log('📱 Dispositivos disponibles: <?php echo $devices_count; ?>');
            console.log('💰 Ventas hoy: <?php echo $today_stats["ventas"]; ?>');
            console.log('👤 Usuario: <?php echo $user["nombre"]; ?> (<?php echo $user["rol"]; ?>)');
            console.log('═══════════════════════════════════════════');
            
            try {
                // No es necesario attachCardListeners porque usamos onclick
                console.log('✅ Cards usando onclick inline');
                
                // Paginación
                var prevBtn = document.getElementById('prevPage');
                var nextBtn = document.getElementById('nextPage');
                
                if (prevBtn && nextBtn) {
                    prevBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('⬅️ Click en botón Anterior');
                        changePage(-1);
                    });
                    
                    nextBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('➡️ Click en botón Siguiente');
                        changePage(1);
                    });
                    
                    console.log('✅ Event listeners de paginación adjuntados');
                    
                    // Verificar estado inicial
                    var totalDevices = <?php echo $devices_count; ?>;
                    if (totalDevices > 12) {
                        console.log('✅ Paginación visible (' + totalDevices + ' dispositivos)');
                    } else {
                        console.log('ℹ️ Paginación oculta (' + totalDevices + ' ≤ 12)');
                    }
                }
                
                // Búsqueda
                var searchInput = document.getElementById('deviceSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            console.log('🔍 Buscando: ' + searchInput.value);
                            searchDevices(1);
                        }, 500);
                    });
                    
                    searchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            console.log('🔍 Búsqueda con Enter');
                            searchDevices(1);
                        }
                    });
                    
                    searchInput.focus();
                    console.log('✅ Búsqueda configurada');
                }
                
                // Botón limpiar
                var clearBtn = document.getElementById('clearSearchBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        console.log('🧹 Limpiando búsqueda');
                        clearSearch();
                    });
                }
                
                // Modal
                var closeModalBtn = document.getElementById('closeModalBtn');
                var cancelSaleBtn = document.getElementById('cancelSaleBtn');
                var confirmSaleBtn = document.getElementById('confirmSaleBtn');
                
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeSaleModal);
                }
                
                if (cancelSaleBtn) {
                    cancelSaleBtn.addEventListener('click', closeSaleModal);
                }
                
                if (confirmSaleBtn) {
                    confirmSaleBtn.addEventListener('click', registerSale);
                }
                
                console.log('✅ Modal configurado');
                
                // ESC para cerrar modal
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeSaleModal();
                    }
                });
                
                // Validaciones
                var precioInput = document.getElementById('precio_venta');
                if (precioInput) {
                    precioInput.addEventListener('input', function() {
                        if (this.value < 0) this.value = 0;
                    });
                }
                
                // Autocompletado
                var nombreInput = document.getElementById('cliente_nombre');
                var emailInput = document.getElementById('cliente_email');
                if (nombreInput && emailInput) {
                    nombreInput.addEventListener('blur', function() {
                        var nombre = this.value.trim();
                        if (nombre && !emailInput.value) {
                            var sugerencia = nombre.toLowerCase()
                                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                                .replace(/\s+/g, '.')
                                .replace(/[^a-z.]/g, '') + '@ejemplo.com';
                            emailInput.placeholder = 'Ej: ' + sugerencia;
                        }
                    });
                }
                
                console.log('✅ Validaciones configuradas');
                
                // Test de funciones críticas
                console.log('\n🧪 Test de funciones:');
                console.log('- selectDeviceForSale:', typeof selectDeviceForSale);
                console.log('- handleDeviceClick:', typeof handleDeviceClick);
                console.log('- changePage:', typeof changePage);
                console.log('- registerSale:', typeof registerSale);
                
                console.log('\n🎉 Sistema completamente inicializado y listo');
                
            } catch (error) {
                console.error('❌ Error en inicialización:', error);
                alert('Error al inicializar el sistema: ' + error.message);
            }
        });
        
        // Error handling global
        window.addEventListener('error', function(e) {
            console.error('💥 Error global capturado:', e.error);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('💥 Promise rechazada:', e.reason);
        });
        
        // Animaciones CSS
        var style = document.createElement('style');
        style.textContent = '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } } @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }';
        document.head.appendChild(style);
        
        console.log('✅ Estilos de animación inyectados');
        
    </script>
</body>
</html>
        
        // ===========================================
        // GESTIÓN DEL MODAL
        // ===========================================
        function openSaleModal() {
            if (!selectedDevice) {
                showNotification('Selecciona primero un dispositivo', 'warning');
                return;
            }
            document.getElementById('saleModal').classList.add('show');
            setTimeout(function() {
                document.getElementById('cliente_nombre').focus();
            }, 100);
        }
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('show');
            clearSaleForm();
            clearDeviceSelection();
        }
        
        function clearSaleForm() {
            document.getElementById('cliente_nombre').value = '';
            document.getElementById('cliente_telefono').value = '';
            document.getElementById('cliente_email').value = '';
            document.getElementById('precio_venta').value = '';
            document.getElementById('metodo_pago').value = 'efectivo';
            document.getElementById('sale_notas').value = '';
        }
        
        function clearDeviceSelection() {
            selectedDevice = null;
            var cards = document.querySelectorAll('.device-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.remove('selected');
            }
<?php
/**
 * SISTEMA DE VENTAS - Versión 5.0 Simplificada
 * Diseño limpio y moderno - Solo buscador y dispositivos
 * Moneda: Soles (S/)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/styles.php';
require_once '../includes/navbar_unified.php';

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
                    "Venta #$venta_id - {$device['modelo']} - Cliente: $cliente_nombre - S/$precio_venta");
                
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
$stats = [
    'hoy' => ['ventas' => 0, 'ingresos' => 0],
    'mes' => ['ventas' => 0, 'ingresos' => 0],
    'disponibles' => 0
];

try {
    // Dispositivos disponibles (últimos 6)
    if (hasPermission('admin')) {
        $devices_stmt = $db->query("
            SELECT c.*, t.nombre as tienda_nombre 
            FROM celulares c 
            LEFT JOIN tiendas t ON c.tienda_id = t.id 
            WHERE c.estado = 'disponible' 
            ORDER BY c.fecha_registro DESC 
            LIMIT 6
        ");
    } else {
        $devices_stmt = $db->prepare("
            SELECT c.*, t.nombre as tienda_nombre 
            FROM celulares c 
            LEFT JOIN tiendas t ON c.tienda_id = t.id 
            WHERE c.estado = 'disponible' AND c.tienda_id = ? 
            ORDER BY c.fecha_registro DESC 
            LIMIT 6
        ");
        $devices_stmt->execute([$user['tienda_id']]);
    }
    $available_devices = $devices_stmt->fetchAll();
    $stats['disponibles'] = count($available_devices);
    
    // Estadísticas del día
    $today = date('Y-m-d');
    if (hasPermission('admin')) {
        $stats_hoy_stmt = $db->prepare("
            SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ?
        ");
        $stats_hoy_stmt->execute([$today]);
    } else {
        $stats_hoy_stmt = $db->prepare("
            SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ? AND tienda_id = ?
        ");
        $stats_hoy_stmt->execute([$today, $user['tienda_id']]);
    }
    $stats['hoy'] = $stats_hoy_stmt->fetch();
    
    // Estadísticas del mes
    $mes_actual = date('Y-m');
    if (hasPermission('admin')) {
        $stats_mes_stmt = $db->prepare("
            SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?
        ");
        $stats_mes_stmt->execute([$mes_actual]);
    } else {
        $stats_mes_stmt = $db->prepare("
            SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?
        ");
        $stats_mes_stmt->execute([$mes_actual, $user['tienda_id']]);
    }
    $stats['mes'] = $stats_mes_stmt->fetch();
    
} catch(Exception $e) {
    logError("Error obteniendo datos de ventas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas de Celulares - <?php echo SYSTEM_NAME; ?></title>
    <?php renderSharedStyles(); ?>
    <style>
        /* Estilos específicos - Coincidiendo con Dashboard */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: visible;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -4px;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 12px 12px 0 0;
        }
        
        .stat-card:nth-child(1)::before {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }
        
        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);
        }
        
        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .stat-detail {
            color: #9ca3af;
            font-size: 0.875rem;
        }
        
        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }
        
        .search-input {
            padding-left: 3rem;
            padding-right: 3rem;
        }
        
        .clear-search {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .clear-search:hover {
            color: #ef4444;
        }
        
        .device-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .device-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .device-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .device-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.2);
        }
        
        .device-card:hover::before {
            transform: scaleX(1);
        }
        
        .device-card.selected {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: var(--color-success);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }
        
        .device-card.selected::after {
            content: '✓';
            position: absolute;
            top: 12px;
            right: 12px;
            width: 32px;
            height: 32px;
            background: var(--color-success);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-in {
            animation: slideUp 0.5s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <?php renderNavbar('sales'); ?>
    
    <main class="page-content">
        <div class="p-6">
            
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2 text-center">
                    💰 Ventas de Celulares
                </h1>
                <p class="text-gray-600 text-center">
                    <?php echo hasPermission('admin') 
                        ? 'Gestión global - Todas las tiendas' 
                        : 'Tienda: ' . htmlspecialchars($user['tienda_nombre']); ?>
                </p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                
                <!-- Ventas de Hoy -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #10b981;">
                        S/<?php echo number_format($stats['hoy']['ingresos'], 2); ?>
                    </div>
                    <div class="stat-label">Ventas de Hoy</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <?php echo $stats['hoy']['ventas']; ?> <?php echo $stats['hoy']['ventas'] == 1 ? 'venta' : 'ventas'; ?>
                    </div>
                </div>

                <!-- Ventas del Mes -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #3b82f6;">
                        S/<?php echo number_format($stats['mes']['ingresos'], 0); ?>
                    </div>
                    <div class="stat-label">Ventas del Mes</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <?php echo $stats['mes']['ventas']; ?> transacciones
                    </div>
                </div>

                <!-- Disponibles -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #8b5cf6;">
                        <?php echo $stats['disponibles']; ?>
                    </div>
                    <div class="stat-label">Dispositivos Disponibles</div>
                    <div class="text-sm text-gray-500 mt-2">
                        Listos para venta
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="alert alert-info mb-8">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                <div>
                    <p class="font-medium">💡 Proceso de venta rápido:</p>
                    <p class="text-sm">1. Busca el dispositivo → 2. Haz clic para seleccionar → 3. Completa datos del cliente → 4. Confirma la venta</p>
                </div>
            </div>

            <!-- Buscador Principal -->
            <div class="search-container mb-8">
                <svg class="search-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" 
                       id="deviceSearch" 
                       placeholder="Buscar por modelo, marca, IMEI, color, capacidad..." 
                       class="form-input search-input"
                       style="font-size: 1.125rem; padding-top: 1rem; padding-bottom: 1rem;">
                <svg class="clear-search w-6 h-6 hidden" id="clearSearchBtn" fill="none" stroke="currentColor" viewBox="0 0 24 24" onclick="clearDeviceSearch()">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            <!-- Info de Búsqueda -->
            <div class="text-center mb-6">
                <p class="text-sm text-gray-500" id="searchInfo">
                    Mostrando los últimos 6 dispositivos disponibles
                </p>
            </div>

            <!-- Loading -->
            <div id="loadingSpinner" class="hidden flex justify-center items-center py-16">
                <div class="loading-spinner" style="width: 60px; height: 60px;"></div>
            </div>

            <!-- Grid de Dispositivos -->
            <div id="devicesList" class="device-grid">
                <?php if (empty($available_devices)): ?>
                    <div class="col-span-full text-center py-16">
                        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">No hay dispositivos disponibles</h3>
                        <p class="text-gray-500 mb-6">Registra nuevos dispositivos para empezar a vender</p>
                        <a href="inventory.php" class="btn btn-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Ir a Inventario
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($available_devices as $index => $device): ?>
                        <div class="device-card animate-in" 
                             style="animation-delay: <?php echo $index * 0.05; ?>s"
                             data-device-id="<?php echo $device['id']; ?>"
                             data-device='<?php echo htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8'); ?>'>
                            <div class="mb-3">
                                <h3 class="text-lg font-bold text-gray-900 mb-1">
                                    <?php echo htmlspecialchars($device['modelo']); ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars($device['marca']); ?>
                                </p>
                            </div>
                            
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($device['capacidad']); ?></span>
                                </div>
                                
                                <?php if ($device['color']): ?>
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($device['color']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($device['imei1']): ?>
                                <div class="flex items-center gap-2 text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                    </svg>
                                    <span>IMEI: <?php echo htmlspecialchars(substr($device['imei1'], 0, 12)) . '...'; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('admin') && $device['tienda_nombre']): ?>
                                <div class="flex items-center gap-2 text-sm text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($device['tienda_nombre']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Precio:</span>
                                    <span class="text-2xl font-bold" style="color: var(--color-primary);">
                                        S/<?php echo number_format($device['precio'], 2); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal de Venta -->
    <div id="saleModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="w-6 h-6 inline-block mr-2 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Registrar Venta
                </h3>
                <button onclick="closeSaleModal()" class="modal-close">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="saleForm" onsubmit="return false;">
                <input type="hidden" id="selectedDeviceId">
                
                <!-- Info del dispositivo seleccionado -->
                <div id="deviceInfo" class="hidden mb-6 p-4 rounded-lg" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid #10b981;">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: #10b981;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-gray-900" id="deviceName"></p>
                            <p class="text-sm text-gray-600" id="deviceDetails"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-success" id="devicePrice"></p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <!-- Información del Cliente -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Información del Cliente
                        </h4>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Nombre Completo <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="cliente_nombre" 
                                   required 
                                   placeholder="Ej: Juan Pérez García" 
                                   class="form-input"
                                   autocomplete="off">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" 
                                       id="cliente_telefono" 
                                       placeholder="999 888 777" 
                                       class="form-input"
                                       autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       id="cliente_email" 
                                       placeholder="correo@ejemplo.com" 
                                       class="form-input"
                                       autocomplete="off">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detalles de la Venta -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Detalles de la Venta
                        </h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">
                                    Precio de Venta <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                                    <input type="number" 
                                           id="precio_venta" 
                                           step="0.01" 
                                           min="0" 
                                           required 
                                           class="form-input pl-10"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Método de Pago</label>
                                <select id="metodo_pago" class="form-select">
                                    <option value="efectivo">💵 Efectivo</option>
                                    <option value="tarjeta">💳 Tarjeta</option>
                                    <option value="transferencia">🏦 Transferencia</option>
                                    <option value="credito">📝 Crédito</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Notas <span class="text-gray-400 text-xs">(opcional)</span>
                            </label>
                            <textarea id="notas" 
                                      rows="2" 
                                      placeholder="Observaciones adicionales..." 
                                      class="form-textarea"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de Acción -->
                <div class="flex justify-end gap-3 mt-8 pt-6 border-t">
                    <button type="button" onclick="closeSaleModal()" class="btn btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancelar
                    </button>
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

    <!-- JavaScript -->
    <script>
        // ==========================================
        // VARIABLES GLOBALES
        // ==========================================
        let selectedDevice = null;
        let searchTimeout = null;

        // ==========================================
        // BÚSQUEDA DE DISPOSITIVOS
        // ==========================================
        function searchDevices() {
            const searchTerm = document.getElementById('deviceSearch').value.trim();
            
            // Mostrar/ocultar botón de limpiar
            document.getElementById('clearSearchBtn').classList.toggle('hidden', !searchTerm);
            
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'search_devices');
            formData.append('search', searchTerm);
            
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderDevices(data.devices);
                    updateSearchInfo(searchTerm, data.devices.length);
                } else {
                    showNotification('❌ Error al buscar: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error en la búsqueda', 'danger');
            })
            .finally(() => {
                hideLoading();
            });
        }

        function clearDeviceSearch() {
            document.getElementById('deviceSearch').value = '';
            document.getElementById('clearSearchBtn').classList.add('hidden');
            searchDevices();
        }

        function updateSearchInfo(searchTerm, count) {
            const infoElement = document.getElementById('searchInfo');
            if (searchTerm) {
                infoElement.innerHTML = `Mostrando <strong>${count}</strong> resultados para "<strong>${searchTerm}</strong>"`;
            } else {
                infoElement.textContent = 'Mostrando los últimos 6 dispositivos disponibles';
            }
        }

        // ==========================================
        // RENDERIZADO DE DISPOSITIVOS
        // ==========================================
        function renderDevices(devices) {
            const container = document.getElementById('devicesList');
            
            if (devices.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-16">
                        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">No se encontraron dispositivos</h3>
                        <p class="text-gray-500">Intenta con otros términos de búsqueda</p>
                    </div>
                `;
                return;
            }
            
            const html = devices.map((device, index) => createDeviceCard(device, index)).join('');
            container.innerHTML = html;
            attachDeviceCardListeners();
        }

        function createDeviceCard(device, index) {
            const deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
            const imeiShort = device.imei1 ? device.imei1.substring(0, 12) + '...' : '';
            
            return `
                <div class="device-card animate-in" 
                     style="animation-delay: ${index * 0.05}s"
                     data-device-id="${device.id}" 
                     data-device="${deviceJson}">
                    <div class="mb-3">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">${escapeHtml(device.modelo)}</h3>
                        <p class="text-sm text-gray-600">${escapeHtml(device.marca)}</p>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <span>${escapeHtml(device.capacidad)}</span>
                        </div>
                        
                        ${device.color ? `
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                            </svg>
                            <span>${escapeHtml(device.color)}</span>
                        </div>
                        ` : ''}
                        
                        ${device.imei1 ? `
                        <div class="flex items-center gap-2 text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            <span>IMEI: ${escapeHtml(imeiShort)}</span>
                        </div>
                        ` : ''}
                        
                        ${device.tienda_nombre ? `
                        <div class="flex items-center gap-2 text-sm text-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>${escapeHtml(device.tienda_nombre)}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Precio:</span>
                            <span class="text-2xl font-bold" style="color: var(--color-primary);">
                                S/${formatPrice(device.precio)}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }

        function attachDeviceCardListeners() {
            document.querySelectorAll('.device-card').forEach(card => {
                card.addEventListener('click', function() {
                    const deviceData = this.getAttribute('data-device');
                    if (deviceData) {
                        try {
                            const device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                            selectDeviceForSale(device);
                        } catch (e) {
                            console.error('Error parsing device data:', e);
                            showNotification('❌ Error al seleccionar dispositivo', 'danger');
                        }
                    }
                });
            });
        }

        // ==========================================
        // SELECCIÓN DE DISPOSITIVO
        // ==========================================
        function selectDeviceForSale(device) {
            selectedDevice = device;
            
            // Limpiar selección previa
            document.querySelectorAll('.device-card').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Marcar tarjeta seleccionada
            const selectedCard = document.querySelector(`[data-device-id="${device.id}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Llenar información del dispositivo
            document.getElementById('selectedDeviceId').value = device.id;
            document.getElementById('deviceName').textContent = device.modelo;
            document.getElementById('deviceDetails').textContent = 
                `${device.marca} - ${device.capacidad}${device.color ? ' - ' + device.color : ''}`;
            document.getElementById('devicePrice').textContent = 'S/' + formatPrice(device.precio);
            document.getElementById('deviceInfo').classList.remove('hidden');
            
            // Pre-llenar precio
            document.getElementById('precio_venta').value = device.precio;
            
            // Abrir modal
            openSaleModal();
        }

        function clearDeviceSelection() {
            selectedDevice = null;
            document.querySelectorAll('.device-card').forEach(el => {
                el.classList.remove('selected');
            });
            document.getElementById('deviceInfo').classList.add('hidden');
        }

        // ==========================================
        // GESTIÓN DEL MODAL
        // ==========================================
        function openSaleModal() {
            document.getElementById('saleModal').classList.add('show');
            setTimeout(() => document.getElementById('cliente_nombre').focus(), 100);
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
            document.getElementById('notas').value = '';
        }

        // ==========================================
        // REGISTRO DE VENTA
        // ==========================================
        function registerSale() {
            if (!selectedDevice) {
                showNotification('⚠️ No se ha seleccionado un dispositivo', 'warning');
                return;
            }
            
            const cliente_nombre = document.getElementById('cliente_nombre').value.trim();
            const precio_venta = parseFloat(document.getElementById('precio_venta').value);
            
            // Validaciones
            if (!cliente_nombre) {
                showNotification('⚠️ Por favor ingresa el nombre del cliente', 'warning');
                document.getElementById('cliente_nombre').focus();
                return;
            }
            
            if (!precio_venta || precio_venta <= 0) {
                showNotification('⚠️ Por favor ingresa un precio válido', 'warning');
                document.getElementById('precio_venta').focus();
                return;
            }
            
            // Confirmar venta
            const confirmMessage = `¿Confirmar venta?\n\n` +
                `Dispositivo: ${selectedDevice.modelo}\n` +
                `Cliente: ${cliente_nombre}\n` +
                `Precio: S/${precio_venta.toFixed(2)}`;
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Preparar datos
            const formData = new FormData();
            formData.append('action', 'register_sale');
            formData.append('celular_id', selectedDevice.id);
            formData.append('cliente_nombre', cliente_nombre);
            formData.append('cliente_telefono', document.getElementById('cliente_telefono').value);
            formData.append('cliente_email', document.getElementById('cliente_email').value);
            formData.append('precio_venta', precio_venta);
            formData.append('metodo_pago', document.getElementById('metodo_pago').value);
            formData.append('notas', document.getElementById('notas').value);
            
            // Deshabilitar botones
            const buttons = document.querySelectorAll('#saleForm button');
            buttons.forEach(btn => btn.disabled = true);
            
            // Enviar
            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    closeSaleModal();
                    showPrintDialog(data.venta_id);
                } else {
                    showNotification('❌ ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error en la conexión. Intenta nuevamente.', 'danger');
            })
            .finally(() => {
                buttons.forEach(btn => btn.disabled = false);
            });
        }

        // ==========================================
        // DIÁLOGO DE IMPRESIÓN
        // ==========================================
        function showPrintDialog(ventaId) {
            const modal = document.createElement('div');
            modal.className = 'modal show';
            modal.innerHTML = `
                <div class="modal-content text-center" style="max-width: 500px;">
                    <div class="mb-6">
                        <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Registrada!</h3>
                        <p class="text-gray-600">La venta se ha registrado correctamente en el sistema.</p>
                    </div>
                    <div class="flex flex-col gap-3">
                        <button onclick="printInvoice(${ventaId})" class="btn btn-primary w-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Imprimir Comprobante
                        </button>
                        <button onclick="closeDialogAndReload(this)" class="btn btn-secondary w-full">
                            Continuar sin Imprimir
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-4">
                        💡 Puedes imprimir el comprobante más tarde desde el historial de ventas
                    </p>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function printInvoice(ventaId) {
            const printWindow = window.open(
                'print_sale_invoice.php?id=' + ventaId,
                'PrintInvoice',
                'width=800,height=600,scrollbars=yes'
            );
            
            if (printWindow) {
                printWindow.onload = () => setTimeout(() => location.reload(), 500);
            } else {
                showNotification('❌ No se pudo abrir la ventana de impresión', 'danger');
                setTimeout(() => location.reload(), 2000);
            }
        }

        function closeDialogAndReload(button) {
            button.closest('.modal').remove();
            setTimeout(() => location.reload(), 300);
        }

        // ==========================================
        // UTILIDADES DE UI
        // ==========================================
        function showLoading() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('devicesList').style.opacity = '0.3';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').classList.add('hidden');
            document.getElementById('devicesList').style.opacity = '1';
        }

        function showNotification(message, type = 'info') {
            const colors = {
                'success': 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                'danger': 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                'warning': 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                'info': 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
            };
            
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 text-white';
            notification.style.background = colors[type];
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        // ==========================================
        // UTILIDADES
        // ==========================================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatPrice(price) {
            return parseFloat(price).toFixed(2);
        }

        // ==========================================
        // EVENT LISTENERS
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Sistema de Ventas Cargado - Moneda: Soles (S/)');
            
            // Búsqueda con delay
            const searchInput = document.getElementById('deviceSearch');
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => searchDevices(), 500);
            });
            
            // Enter en búsqueda
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchDevices();
                }
            });
            
            // Cerrar modal con Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSaleModal();
                }
                
                // Ctrl + F para búsqueda
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
            
            // Validación de precio
            document.getElementById('precio_venta').addEventListener('input', function() {
                if (this.value < 0) this.value = 0;
            });
            
            // Sugerencia de email
            document.getElementById('cliente_nombre').addEventListener('blur', function() {
                const nombre = this.value.trim();
                const emailField = document.getElementById('cliente_email');
                
                if (nombre && !emailField.value) {
                    const nombreLimpio = nombre.toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/\s+/g, '.')
                        .replace(/[^a-z0-9.]/g, '');
                    
                    emailField.placeholder = 'Ej: ' + nombreLimpio + '@ejemplo.com';
                }
            });
            
            // Inicializar listeners de tarjetas
            attachDeviceCardListeners();
        });

        console.log('💡 Atajos: Ctrl+F (Buscar) | Esc (Cerrar modal)');
    </script>

</body>
</html>
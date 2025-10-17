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
$stats = [
    'hoy' => ['ventas' => 0, 'ingresos' => 0],
    'mes' => ['ventas' => 0, 'ingresos' => 0],
    'disponibles' => 0
];

try {
    // TOTAL de dispositivos disponibles (NO solo 6)
    if (hasPermission('admin')) {
        $disponibles_stmt = $db->query("
            SELECT COUNT(*) as total
            FROM celulares
            WHERE estado = 'disponible'
        ");
    } else {
        $disponibles_stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM celulares
            WHERE estado = 'disponible' AND tienda_id = ?
        ");
        $disponibles_stmt->execute([$user['tienda_id']]);
    }
    $stats['disponibles'] = $disponibles_stmt->fetch()['total'];
    
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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📱</text></svg>">
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
        
        .device-card:active {
            transform: translateY(-2px);
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
        <div class="p-6 pb-2">
            
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

                <!-- Disponibles - AHORA MUESTRA TOTAL -->
                <div class="stat-card animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-value" style="color: #8b5cf6;">
                        <?php echo $stats['disponibles']; ?>
                    </div>
                    <div class="stat-label">Equipos Disponibles</div>
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
                <p class="text-sm text-gray-500" id="searchInfo"></p>
            </div>

            <!-- Loading -->
            <div id="loadingSpinner" class="hidden flex justify-center items-center py-16">
                <div class="loading-spinner" style="width: 60px; height: 60px;"></div>
            </div>

            <!-- Grid de Dispositivos - SIN DISPOSITIVOS PRECARGADOS -->
            <div id="devicesList" class="device-grid">
                <div class="col-span-full text-center py-8">
                    <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">Busca un dispositivo para vender</h3>
                    <p class="text-gray-500">Usa el buscador arriba para encontrar celulares disponibles</p>
                </div>
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
            
            <form id="saleForm" onsubmit="event.preventDefault(); return false;">
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
    <script src="../assets/js/sales.js"></script>

</body>
</html>
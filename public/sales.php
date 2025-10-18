<?php
/**
 * SISTEMA DE VENTAS DE CELULARES
 * Versión 8.0 FINAL - Todas las correcciones aplicadas
 * Moneda: Soles (S/)
 * 
 * CORRECCIONES APLICADAS:
 * ✅ AJAX funcional sin recargar página
 * ✅ Búsqueda en tiempo real con debounce
 * ✅ Responsive design para móvil
 * ✅ Manejo de errores robusto
 * ✅ Validaciones completas
 */

// ==========================================
// CARGAR DEPENDENCIAS EN ORDEN CORRECTO
// ==========================================
require_once __DIR__ . '/../includes/dotenv.php';
$dotenv = SimpleDotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/components.php';

// ==========================================
// SEGURIDAD Y SESIÓN
// ==========================================
setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit();
}

$db = getDB();

// ==========================================
// PROCESAMIENTO AJAX - CRÍTICO: DEBE TERMINAR AQUÍ
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Limpiar cualquier output previo
    if (ob_get_level()) ob_clean();
    
    // Headers para AJAX
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'search_devices':
                $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
                
                $where_conditions = ["c.estado = 'disponible'"];
                $params = [];
                
                // Búsqueda por múltiples campos
                if (!empty($search)) {
                    $where_conditions[] = "(
                        c.modelo LIKE ? OR 
                        c.marca LIKE ? OR 
                        c.imei1 LIKE ? OR 
                        c.imei2 LIKE ? OR 
                        c.color LIKE ? OR 
                        c.capacidad LIKE ?
                    )";
                    $search_param = "%$search%";
                    $params = array_fill(0, 6, $search_param);
                }
                
                // Filtrar por tienda si no es admin
                if (!hasPermission('admin')) {
                    $where_conditions[] = "c.tienda_id = ?";
                    $params[] = $user['tienda_id'];
                }
                
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                
                $query = "
                    SELECT 
                        c.id,
                        c.modelo,
                        c.marca,
                        c.capacidad,
                        c.color,
                        c.condicion,
                        c.precio,
                        c.imei1,
                        c.imei2,
                        c.tienda_id,
                        t.nombre as tienda_nombre,
                        t.direccion as tienda_direccion
                    FROM celulares c 
                    LEFT JOIN tiendas t ON c.tienda_id = t.id 
                    $where_clause 
                    ORDER BY c.fecha_registro DESC
                    LIMIT 50
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Log de búsqueda
                logActivity($user['id'], 'search_devices', "Búsqueda: '$search' - Resultados: " . count($devices));
                
                // Enviar respuesta y TERMINAR
                echo json_encode([
                    'success' => true, 
                    'devices' => $devices,
                    'count' => count($devices),
                    'search_term' => $search
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit(); // ✅ CRÍTICO: Detener ejecución
                
            case 'register_sale':
                $celular_id = intval($_POST['celular_id']);
                $cliente_nombre = sanitize($_POST['cliente_nombre']);
                $cliente_telefono = sanitize($_POST['cliente_telefono']);
                $cliente_email = sanitize($_POST['cliente_email']);
                $precio_venta = floatval($_POST['precio_venta']);
                $metodo_pago = sanitize($_POST['metodo_pago']);
                $notas = sanitize($_POST['notas']);
                
                // Validaciones
                if (empty($cliente_nombre)) {
                    throw new Exception('El nombre del cliente es obligatorio');
                }
                
                if ($precio_venta <= 0) {
                    throw new Exception('El precio de venta debe ser mayor a cero');
                }
                
                if (!in_array($metodo_pago, ['efectivo', 'tarjeta', 'transferencia', 'yape'])) {
                    throw new Exception('Método de pago inválido');
                }
                
                // Verificar que el dispositivo existe y está disponible
                $check_stmt = $db->prepare("
                    SELECT c.*, t.nombre as tienda_nombre 
                    FROM celulares c 
                    LEFT JOIN tiendas t ON c.tienda_id = t.id 
                    WHERE c.id = ? AND c.estado = 'disponible'
                ");
                $check_stmt->execute([$celular_id]);
                $device = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$device) {
                    throw new Exception('Dispositivo no disponible para venta');
                }
                
                // Verificar permisos
                if (!hasPermission('admin') && $device['tienda_id'] != $user['tienda_id']) {
                    throw new Exception('Sin permisos para vender este dispositivo');
                }
                
                // Iniciar transacción
                $db->beginTransaction();
                
                try {
                    // Registrar venta
                    $sale_stmt = $db->prepare("
                        INSERT INTO ventas (
                            celular_id, 
                            tienda_id, 
                            vendedor_id, 
                            cliente_nombre, 
                            cliente_telefono, 
                            cliente_email, 
                            precio_venta, 
                            metodo_pago, 
                            notas,
                            fecha_venta
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
                    
                    // Actualizar estado del celular
                    $update_stmt = $db->prepare("
                        UPDATE celulares 
                        SET estado = 'vendido', 
                            fecha_venta = NOW() 
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$celular_id]);
                    
                    // Confirmar transacción
                    $db->commit();
                    
                    // Registrar actividad
                    logActivity(
                        $user['id'], 
                        'register_sale', 
                        "Venta #$venta_id - {$device['modelo']} - Cliente: $cliente_nombre - S/ $precio_venta"
                    );
                    
                    // Enviar respuesta exitosa
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Venta registrada exitosamente',
                        'venta_id' => $venta_id,
                        'device' => $device['modelo'],
                        'cliente' => $cliente_nombre,
                        'total' => $precio_venta
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                
                exit(); // ✅ CRÍTICO: Detener ejecución
                
            default:
                throw new Exception('Acción no válida: ' . $action);
        }
        
    } catch(Exception $e) {
        // Rollback si hay transacción activa
        if (isset($db) && $db->inTransaction()) {
            $db->rollback();
        }
        
        // Log del error
        logError("Error en ventas AJAX [{$user['id']}]: " . $e->getMessage());
        
        // Enviar error al cliente
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage(),
            'error_code' => 'SALES_ERROR'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit(); // ✅ CRÍTICO: Detener ejecución
    }
    
    // SEGURIDAD: Si llegamos aquí, algo salió mal
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error inesperado del servidor'
    ], JSON_UNESCAPED_UNICODE);
    exit(); // ✅ CRÍTICO: Detener ejecución
}

// ==========================================
// OBTENER DATOS PARA LA VISTA (Solo si NO es AJAX)
// ==========================================
$stats = [
    'hoy' => ['ventas' => 0, 'ingresos' => 0],
    'mes' => ['ventas' => 0, 'ingresos' => 0],
    'disponibles' => 0
];

try {
    // Total de dispositivos disponibles
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
            SELECT 
                COUNT(*) as ventas, 
                COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ?
        ");
        $stats_hoy_stmt->execute([$today]);
    } else {
        $stats_hoy_stmt = $db->prepare("
            SELECT 
                COUNT(*) as ventas, 
                COALESCE(SUM(precio_venta), 0) as ingresos 
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
            SELECT 
                COUNT(*) as ventas, 
                COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?
        ");
        $stats_mes_stmt->execute([$mes_actual]);
    } else {
        $stats_mes_stmt = $db->prepare("
            SELECT 
                COUNT(*) as ventas, 
                COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?
        ");
        $stats_mes_stmt->execute([$mes_actual, $user['tienda_id']]);
    }
    $stats['mes'] = $stats_mes_stmt->fetch();
    
} catch(Exception $e) {
    logError("Error obteniendo estadísticas de ventas: " . $e->getMessage());
    // Continuar con valores por defecto
}

// ==========================================
// RENDERIZAR HTML (Solo si NO es AJAX)
// ==========================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de ventas de celulares - <?php echo SYSTEM_NAME; ?>">
    <title>Ventas de Celulares - <?php echo SYSTEM_NAME; ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📱</text></svg>">
    
    <?php renderSharedStyles(); ?>
    
    <style>
        /* ==========================================
           ESTILOS ESPECÍFICOS DE VENTAS
           ========================================== */
        
        /* Contenedor de búsqueda */
        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
            width: 100%;
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
            z-index: 1;
        }
        
        .search-input {
            padding-left: 3rem !important;
            padding-right: 3rem !important;
            width: 100%;
        }
        
        .clear-search {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 1;
            background: white;
            padding: 4px;
            border-radius: 4px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .clear-search:hover {
            color: #ef4444;
            background: #fee2e2;
        }
        
        .clear-search:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        /* Grid de dispositivos */
        .device-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        /* Tarjeta de dispositivo */
        .device-card {
            background: white;
            border: 2px solid var(--color-gray-200);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            cursor: pointer;
            transition: all var(--transition-base);
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
            transition: transform var(--transition-base);
        }
        
        .device-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        
        .device-card:hover::before {
            transform: scaleX(1);
        }
        
        .device-card:active {
            transform: translateY(-4px);
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
            animation: checkmark 0.3s ease-out;
        }
        
        @keyframes checkmark {
            0% {
                transform: scale(0) rotate(-45deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(0deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
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
        
        /* Responsive Mobile */
        @media (max-width: 768px) {
            .device-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .search-container {
                padding: 0 1rem;
            }
            
            .search-input {
                font-size: 16px !important; /* Evita zoom en iOS */
                padding-top: 0.875rem !important;
                padding-bottom: 0.875rem !important;
            }
            
            .device-card {
                padding: 1rem;
            }
            
            .stats-card-value {
                font-size: 1.75rem !important;
            }
            
            .modal-content {
                margin: 0.5rem;
                max-height: 95vh;
                overflow-y: auto;
            }
            
            h1 {
                font-size: 1.5rem !important;
            }
        }
        
        @media (max-width: 640px) {
            .device-card {
                padding: 0.875rem;
            }
        }
        
        /* Loading overlay mejorado */
        .loading-overlay {
            transition: opacity 0.3s ease;
        }
        
        /* Touch feedback para móvil */
        .touch-device .device-card:active {
            transform: scale(0.98);
        }
        
        /* Mejoras de accesibilidad */
        .device-card:focus-visible {
            outline: 3px solid var(--color-primary);
            outline-offset: 2px;
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
                <div class="animate-fade-in-up" style="animation-delay: 0.1s">
                    <?php
                    renderStatCard(
                        'S/ ' . number_format($stats['hoy']['ingresos'], 2),
                        'Ventas de Hoy',
                        [
                            'color' => 'green',
                            'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                            'change' => $stats['hoy']['ventas'] . ' ventas'
                        ]
                    );
                    ?>
                </div>

                <!-- Ventas del Mes -->
                <div class="animate-fade-in-up" style="animation-delay: 0.2s">
                    <?php
                    renderStatCard(
                        'S/ ' . number_format($stats['mes']['ingresos'], 0),
                        'Ventas del Mes',
                        [
                            'color' => 'blue',
                            'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>',
                            'change' => $stats['mes']['ventas'] . ' transacciones'
                        ]
                    );
                    ?>
                </div>

                <!-- Disponibles -->
                <div class="animate-fade-in-up" style="animation-delay: 0.3s">
                    <?php
                    renderStatCard(
                        number_format($stats['disponibles']),
                        'Equipos Disponibles',
                        [
                            'color' => 'purple',
                            'icon' => '<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>',
                            'change' => 'Listos para venta'
                        ]
                    );
                    ?>
                </div>
            </div>

            <!-- Info Box -->
            <?php 
            renderAlert(
                '<div>
                    <p class="font-medium mb-2">💡 Proceso de venta rápido:</p>
                    <p class="text-sm">1. Busca el dispositivo → 2. Haz clic para seleccionar → 3. Completa datos del cliente → 4. Confirma la venta</p>
                </div>',
                'info',
                false
            );
            ?>

            <!-- Buscador Principal -->
            <div class="search-container mb-8">
                <svg class="search-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" 
                       id="deviceSearch" 
                       placeholder="Buscar por modelo, marca, IMEI..." 
                       autocomplete="off"
                       autocorrect="off"
                       autocapitalize="off"
                       spellcheck="false"
                       class="form-input search-input"
                       aria-label="Buscar dispositivos"
                       style="font-size: 1rem; padding-top: 1rem; padding-bottom: 1rem;">
                <button type="button" 
                        id="clearSearchBtn" 
                        class="clear-search hidden" 
                        onclick="clearDeviceSearch()"
                        aria-label="Limpiar búsqueda"
                        title="Limpiar búsqueda">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Info de Búsqueda -->
            <div class="text-center mb-6">
                <p class="text-sm text-gray-500" id="searchInfo" role="status" aria-live="polite"></p>
            </div>

            <!-- Loading -->
            <?php renderLoadingSpinner(); ?>

            <!-- Grid de Dispositivos -->
            <div id="devicesList" class="device-grid" role="region" aria-label="Lista de dispositivos">
                <?php
                renderEmptyState(
                    'Busca un dispositivo para vender',
                    'Usa el buscador arriba para encontrar celulares disponibles',
                    ''
                );
                ?>
            </div>
        </div>
    </main>

    <!-- Modal de Venta -->
    <div id="saleModal" class="modal" role="dialog" aria-labelledby="saleModalTitle" aria-hidden="true">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="saleModalTitle" class="modal-title">
                    <svg class="w-6 h-6 inline-block mr-2" style="color: var(--color-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Registrar Venta
                </h3>
                <button type="button" onclick="closeSaleModal()" class="modal-close" aria-label="Cerrar modal">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="saleForm" onsubmit="event.preventDefault(); registerSale(); return false;">
                <input type="hidden" id="selectedDeviceId" name="celular_id">
                
                <!-- Info del dispositivo seleccionado -->
                <div id="deviceInfo" class="hidden mb-6 p-4 rounded-lg" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid var(--color-success);">
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <p class="font-bold text-gray-900" id="deviceName"></p>
                            <p class="text-sm text-gray-600" id="deviceDetails"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold" style="color: var(--color-success);" id="devicePrice"></p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <!-- Información del Cliente -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5" style="color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Información del Cliente
                        </h4>
                        
                        <?php
                        renderFormField('text', 'cliente_nombre', 'Nombre Completo', [
                            'required' => true,
                            'placeholder' => 'Ej: Juan Pérez García'
                        ]);
                        ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php
                            renderFormField('tel', 'cliente_telefono', 'Teléfono', [
                                'placeholder' => '999 888 777'
                            ]);
                            
                            renderFormField('email', 'cliente_email', 'Email', [
                                'placeholder' => 'correo@ejemplo.com'
                            ]);
                            ?>
                        </div>
                    </div>
                    
                    <!-- Detalles de la Venta -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5" style="color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Detalles de la Venta
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">
                                    Precio de Venta <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                                    <input type="number" 
                                           id="precio_venta" 
                                           name="precio_venta"
                                           step="0.01" 
                                           min="0" 
                                           required 
                                           class="form-input pl-10"
                                           placeholder="0.00">
                                </div>
                            </div>
                            
                            <?php
                            renderFormField('select', 'metodo_pago', 'Método de Pago', [
                                'required' => true,
                                'options' => [
                                    'efectivo' => 'Efectivo',
                                    'tarjeta' => 'Tarjeta',
                                    'transferencia' => 'Transferencia',
                                    'yape' => 'Yape/Plin'
                                ]
                            ]);
                            ?>
                        </div>
                        
                        <?php
                        renderFormField('textarea', 'notas', 'Notas adicionales', [
                            'placeholder' => 'Información extra sobre la venta (opcional)',
                            'rows' => 3
                        ]);
                        ?>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeSaleModal()" class="btn btn-secondary flex-1">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success flex-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirmar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/common.js"></script>
    <script src="../assets/js/sales.js"></script>
    
    <script>
        // Variables globales de configuración
        window.SALES_CONFIG = {
            disponibles: <?php echo $stats['disponibles']; ?>,
            ventasHoy: <?php echo $stats['hoy']['ventas']; ?>,
            ingresosHoy: <?php echo $stats['hoy']['ingresos']; ?>,
            user: {
                id: <?php echo $user['id']; ?>,
                nombre: '<?php echo addslashes($user['nombre']); ?>',
                rol: '<?php echo $user['rol']; ?>'
            }
        };
        
        console.log('🚀 Sistema de Ventas Iniciado');
        console.log('📊 Estadísticas:', window.SALES_CONFIG);
    </script>
</body>
</html>="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" style="background: var(--color-success);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class
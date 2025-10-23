<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

// Verificar permisos de acceso a la página
requirePageAccess('inventory.php');

$user = getCurrentUser();
$db = getDB();

// SOLO ADMIN puede realizar acciones de modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verificar que solo admin puede modificar inventario
    if ($user['rol'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Sin permisos para modificar inventario']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'add_device':
            try {
                $db->beginTransaction();
                
                // NOTA: Permitimos códigos de barras duplicados porque múltiples dispositivos 
                // del mismo modelo pueden tener el mismo código de barras del fabricante.
                // El IMEI es el verdadero identificador único.
                
                // Validar IMEI único (esto sí debe ser único)
                $check_imei = $db->prepare("SELECT id FROM celulares WHERE imei1 = ?");
                $check_imei->execute([sanitize($_POST['imei1'])]);
                if ($check_imei->fetch()) {
                    throw new Exception('El IMEI1 ya existe en el sistema. Cada dispositivo debe tener un IMEI único.');
                }
                
                // Validar IMEI2 si se proporciona
                if (!empty($_POST['imei2'])) {
                    $check_imei2 = $db->prepare("SELECT id FROM celulares WHERE imei1 = ? OR imei2 = ?");
                    $check_imei2->execute([sanitize($_POST['imei2']), sanitize($_POST['imei2'])]);
                    if ($check_imei2->fetch()) {
                        throw new Exception('El IMEI2 ya existe en el sistema.');
                    }
                }
                
                $stmt = $db->prepare("
                    INSERT INTO celulares (modelo, marca, capacidad, precio, precio_compra, imei1, imei2, codigo_barras, color, estado, condicion, tienda_id, usuario_registro_id, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    sanitize($_POST['modelo']),
                    sanitize($_POST['marca']),
                    sanitize($_POST['capacidad']),
                    floatval($_POST['precio']),
                    !empty($_POST['precio_compra']) ? floatval($_POST['precio_compra']) : null,
                    sanitize($_POST['imei1']),
                    !empty($_POST['imei2']) ? sanitize($_POST['imei2']) : null,
                    !empty($_POST['codigo_barras']) ? sanitize($_POST['codigo_barras']) : null,
                    sanitize($_POST['color']),
                    $_POST['estado'],
                    $_POST['condicion'],
                    intval($_POST['tienda_id']),
                    $user['id'],
                    sanitize($_POST['notas'])
                ]);
                
                if ($result) {
                    $device_id = $db->lastInsertId();
                    $db->commit();
                    
                    logActivity($user['id'], 'add_device', "Dispositivo agregado: " . $_POST['modelo'] . " - " . $_POST['imei1']);
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Dispositivo agregado correctamente'
                    ]);
                } else {
                    throw new Exception('Error al agregar dispositivo');
                }
            } catch(Exception $e) {
                $db->rollback();
                logError("Error al agregar dispositivo: " . $e->getMessage());
                if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'imei1') !== false) {
                    echo json_encode(['success' => false, 'message' => 'El IMEI ya existe en el sistema. Cada dispositivo debe tener un IMEI único.']);
                } else {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            exit;
            
        case 'update_device':
            try {
                $device_id = intval($_POST['device_id']);
                
                // NOTA: Permitimos códigos de barras duplicados
                // Solo validamos que el IMEI sea único
                
                // Validar IMEI1 único (excepto el dispositivo actual)
                $check_imei = $db->prepare("SELECT id FROM celulares WHERE imei1 = ? AND id != ?");
                $check_imei->execute([sanitize($_POST['imei1']), $device_id]);
                if ($check_imei->fetch()) {
                    throw new Exception('El IMEI1 ya existe en otro dispositivo');
                }
                
                // Validar IMEI2 si se proporciona
                if (!empty($_POST['imei2'])) {
                    $check_imei2 = $db->prepare("SELECT id FROM celulares WHERE (imei1 = ? OR imei2 = ?) AND id != ?");
                    $check_imei2->execute([sanitize($_POST['imei2']), sanitize($_POST['imei2']), $device_id]);
                    if ($check_imei2->fetch()) {
                        throw new Exception('El IMEI2 ya existe en otro dispositivo');
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE celulares SET 
                        modelo = ?, marca = ?, capacidad = ?, precio = ?, precio_compra = ?, 
                        imei1 = ?, imei2 = ?, codigo_barras = ?, color = ?, estado = ?, condicion = ?, notas = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    sanitize($_POST['modelo']),
                    sanitize($_POST['marca']),
                    sanitize($_POST['capacidad']),
                    floatval($_POST['precio']),
                    !empty($_POST['precio_compra']) ? floatval($_POST['precio_compra']) : null,
                    sanitize($_POST['imei1']),
                    !empty($_POST['imei2']) ? sanitize($_POST['imei2']) : null,
                    !empty($_POST['codigo_barras']) ? sanitize($_POST['codigo_barras']) : null,
                    sanitize($_POST['color']),
                    $_POST['estado'],
                    $_POST['condicion'],
                    sanitize($_POST['notas']),
                    $device_id
                ]);
                
                if ($result) {
                    logActivity($user['id'], 'update_device', "Dispositivo actualizado ID: " . $device_id);
                    echo json_encode(['success' => true, 'message' => 'Dispositivo actualizado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar dispositivo']);
                }
            } catch(Exception $e) {
                logError("Error al actualizar dispositivo: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_device':
            try {
                $device_id = intval($_POST['device_id']);
                
                $stmt = $db->prepare("DELETE FROM celulares WHERE id = ?");
                $result = $stmt->execute([$device_id]);
                
                if ($result) {
                    logActivity($user['id'], 'delete_device', "Dispositivo eliminado ID: " . $device_id);
                    echo json_encode(['success' => true, 'message' => 'Dispositivo eliminado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar dispositivo']);
                }
            } catch(Exception $e) {
                logError("Error al eliminar dispositivo: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error en el sistema']);
            }
            exit;
            
        case 'move_device':
            try {
                $device_id = intval($_POST['device_id']);
                $new_tienda_id = intval($_POST['new_tienda_id']);
                
                // Validar que la tienda existe y está activa
                $check_tienda = $db->prepare("SELECT id, nombre FROM tiendas WHERE id = ? AND activa = 1");
                $check_tienda->execute([$new_tienda_id]);
                $tienda = $check_tienda->fetch();
                
                if (!$tienda) {
                    throw new Exception('La tienda de destino no existe o está inactiva');
                }
                
                // Obtener info del dispositivo antes de moverlo
                $device_stmt = $db->prepare("SELECT modelo, marca, tienda_id FROM celulares WHERE id = ?");
                $device_stmt->execute([$device_id]);
                $device = $device_stmt->fetch();
                
                if (!$device) {
                    throw new Exception('Dispositivo no encontrado');
                }
                
                // Obtener nombre de tienda antigua
                $old_tienda_stmt = $db->prepare("SELECT nombre FROM tiendas WHERE id = ?");
                $old_tienda_stmt->execute([$device['tienda_id']]);
                $old_tienda = $old_tienda_stmt->fetch();
                
                // Actualizar la tienda del dispositivo
                $stmt = $db->prepare("UPDATE celulares SET tienda_id = ? WHERE id = ?");
                $result = $stmt->execute([$new_tienda_id, $device_id]);
                
                if ($result) {
                    // Registrar actividad detallada
                    $description = sprintf(
                        "Dispositivo '%s %s' movido de '%s' a '%s'",
                        $device['marca'] ?: '',
                        $device['modelo'],
                        $old_tienda['nombre'] ?? 'Desconocida',
                        $tienda['nombre']
                    );
                    
                    logActivity($user['id'], 'move_device', $description);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Dispositivo movido exitosamente a {$tienda['nombre']}"
                    ]);
                } else {
                    throw new Exception('Error al mover dispositivo');
                }
                
            } catch(Exception $e) {
                logError("Error al mover dispositivo: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Obtener filtros
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : '';
$tienda_filter = isset($_GET['tienda']) && hasPermission('view_all_inventory') ? intval($_GET['tienda']) : null;

// Construir query según el rol
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(modelo LIKE ? OR marca LIKE ? OR imei1 LIKE ? OR imei2 LIKE ? OR codigo_barras LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($estado_filter)) {
    $where_conditions[] = "estado = ?";
    $params[] = $estado_filter;
}

// CONTROL DE ACCESO POR ROL
if (hasPermission('view_all_inventory')) {
    if ($tienda_filter) {
        $where_conditions[] = "c.tienda_id = ?";
        $params[] = $tienda_filter;
    }
} else {
    $where_conditions[] = "c.tienda_id = ?";
    $params[] = $user['tienda_id'];
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Obtener dispositivos
    $query = "
        SELECT c.*, t.nombre as tienda_nombre, u.nombre as registrado_por,
               CASE WHEN c.precio_compra IS NOT NULL THEN c.precio - c.precio_compra ELSE NULL END as ganancia_estimada
        FROM celulares c
        LEFT JOIN tiendas t ON c.tienda_id = t.id
        LEFT JOIN usuarios u ON c.usuario_registro_id = u.id
        $where_clause 
        ORDER BY c.fecha_registro DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $devices = $stmt->fetchAll();
    
    // Obtener tiendas para admin
    $tiendas = [];
    if (hasPermission('view_all_inventory')) {
        $tiendas_stmt = $db->query("SELECT id, nombre FROM tiendas WHERE activa = 1 ORDER BY nombre");
        $tiendas = $tiendas_stmt->fetchAll();
    }
    
} catch(Exception $e) {
    logError("Error al obtener inventario: " . $e->getMessage());
    $devices = [];
    $tiendas = [];
}

// Incluir el navbar/sidebar unificado
require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <!-- Biblioteca para escanear códigos de barras -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>
        .modal { display: none; }
        .modal.show { display: flex; }
        .readonly-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
        }
        .barcode-highlight {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-left: 3px solid #3b82f6;
        }
        
        /* Estilos para el escáner de código de barras */
        #barcode-scanner {
            display: none;
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        
        #barcode-scanner.active {
            display: block;
        }
        
        #barcode-scanner video {
            width: 100%;
            border-radius: 8px;
        }
        
        #barcode-scanner canvas {
            display: none;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 40%;
            border: 3px solid #22c55e;
            border-radius: 8px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }
        
        .scanner-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #22c55e;
            top: 0;
            animation: scan 2s linear infinite;
        }
        
        @keyframes scan {
            0%, 100% { top: 0; }
            50% { top: 100%; }
        }
        
        .scan-success {
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <?php renderNavbar('inventory'); ?>
    
    <!-- Contenido principal -->
    <main class="page-content">
        <div class="p-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Inventario Celulares</h2>
                    <p class="text-gray-600">
                        <?php if ($user['rol'] === 'admin'): ?>
                            Gestión completa de dispositivos móviles con código de barras
                        <?php else: ?>
                            Consulta de dispositivos disponibles para venta
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if (hasPermission('add_devices')): ?>
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center mt-4 md:mt-0">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Agregar Dispositivo
                    </button>
                <?php else: ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 text-blue-700 text-sm mt-4 md:mt-0">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Solo consulta - Para ventas ir a la sección Ventas
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info sobre código de barras -->
            <div class="barcode-highlight p-4 rounded-lg mb-6">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div>
                        <p class="font-medium text-blue-800">Sistema con Código de Barras</p>
                        <p class="text-sm text-blue-700 mt-1">Puedes usar el mismo código de barras para múltiples dispositivos del mismo modelo. El <strong>IMEI es el identificador único</strong> de cada dispositivo. Ideal para usar con lectores de código de barras USB.</p>
                    </div>
                </div>
            </div>

            <!-- Alerta para vendedores -->
            <?php if ($user['rol'] === 'vendedor'): ?>
            <div class="readonly-warning p-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-amber-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <div>
                        <p class="font-medium text-amber-800">Modo Solo Lectura</p>
                        <p class="text-sm text-amber-700">Puedes consultar el inventario de tu tienda, pero no modificarlo. Para realizar ventas, ve a la sección <a href="sales.php" class="underline">Ventas</a>.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="🔍 Buscar por código de barras, modelo, marca o IMEI..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <select name="estado" class="px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Todos los estados</option>
                            <option value="disponible" <?php echo $estado_filter === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                            <option value="vendido" <?php echo $estado_filter === 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                            <option value="reservado" <?php echo $estado_filter === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                            <option value="reparacion" <?php echo $estado_filter === 'reparacion' ? 'selected' : ''; ?>>En Reparación</option>
                        </select>
                    </div>
                    <?php if (hasPermission('view_all_inventory')): ?>
                    <div>
                        <select name="tienda" class="px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Todas las tiendas</option>
                            <?php foreach($tiendas as $tienda): ?>
                                <option value="<?php echo $tienda['id']; ?>" <?php echo $tienda_filter == $tienda['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tienda['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Filtrar
                    </button>
                </form>
            </div>

            <!-- Tabla de Dispositivos -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dispositivo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código Barras</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IMEI</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <?php if (hasPermission('view_all_inventory')): ?>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tienda</th>
                                <?php endif; ?>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <?php if (hasPermission('edit_devices')): ?>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($devices)): ?>
                                <tr>
                                    <td colspan="<?php echo hasPermission('view_all_inventory') ? (hasPermission('edit_devices') ? '8' : '7') : (hasPermission('edit_devices') ? '7' : '6'); ?>" class="px-4 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                            </svg>
                                            <p class="text-lg font-medium">No se encontraron dispositivos</p>
                                            <?php if ($user['rol'] === 'admin'): ?>
                                                <p class="text-sm mt-1">¿Quieres <button onclick="openAddModal()" class="text-blue-600 underline">agregar el primero</button>?</p>
                                            <?php else: ?>
                                                <p class="text-sm mt-1">No hay dispositivos en tu tienda o que coincidan con los filtros</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($devices as $device): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-4">
                                            <div>
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($device['modelo']); ?></p>
                                                <p class="text-sm text-gray-600">
                                                    <?php if ($device['marca']): ?><?php echo htmlspecialchars($device['marca']); ?> - <?php endif; ?>
                                                    <?php echo htmlspecialchars($device['capacidad']); ?>
                                                    <?php if ($device['color']): ?> - <?php echo htmlspecialchars($device['color']); ?><?php endif; ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?php echo ucfirst($device['condicion']); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <?php if ($device['codigo_barras']): ?>
                                                <div class="inline-flex items-center px-2 py-1 bg-blue-50 border border-blue-200 rounded">
                                                    <svg class="w-4 h-4 text-blue-600 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                    <span class="font-mono text-sm text-blue-800"><?php echo htmlspecialchars($device['codigo_barras']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 italic">Sin código</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-medium text-gray-900">$<?php echo number_format($device['precio'], 2); ?></p>
                                            <?php if ($device['precio_compra'] && hasPermission('view_all_inventory')): ?>
                                                <p class="text-sm text-gray-600">Compra: $<?php echo number_format($device['precio_compra'], 2); ?></p>
                                                <p class="text-xs text-green-600">Ganancia: $<?php echo number_format($device['ganancia_estimada'], 2); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-mono text-sm text-gray-900"><?php echo htmlspecialchars($device['imei1']); ?></p>
                                            <?php if ($device['imei2']): ?>
                                                <p class="font-mono text-sm text-gray-600"><?php echo htmlspecialchars($device['imei2']); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                                echo $device['estado'] === 'disponible' ? 'bg-green-100 text-green-800' : 
                                                    ($device['estado'] === 'vendido' ? 'bg-red-100 text-red-800' : 
                                                    ($device['estado'] === 'reservado' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')); 
                                            ?>">
                                                <?php echo ucfirst($device['estado']); ?>
                                            </span>
                                        </td>
                                        <?php if (hasPermission('view_all_inventory')): ?>
                                            <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['tienda_nombre']); ?></td>
                                        <?php endif; ?>
                                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($device['fecha_registro'])); ?></td>
                                        <?php if (hasPermission('edit_devices')): ?>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-2">
                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($device)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 p-1 rounded transition-colors" title="Editar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </button>
                                                <button onclick="openMoveModal(<?php echo $device['id']; ?>, '<?php echo htmlspecialchars($device['modelo']); ?>', <?php echo $device['tienda_id']; ?>)" 
                                                        class="text-purple-600 hover:text-purple-900 p-1 rounded transition-colors" title="Mover de tienda">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                    </svg>
                                                </button>
                                                <button onclick="deleteDevice(<?php echo $device['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-900 p-1 rounded transition-colors" title="Eliminar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Estadísticas adicionales -->
            <?php if (!empty($devices)): ?>
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">
                            <?php echo count(array_filter($devices, function($d) { return $d['estado'] === 'disponible'; })); ?>
                        </p>
                        <p class="text-sm text-gray-600">Disponibles</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-red-600">
                            <?php echo count(array_filter($devices, function($d) { return $d['estado'] === 'vendido'; })); ?>
                        </p>
                        <p class="text-sm text-gray-600">Vendidos</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-600">
                            $<?php echo number_format(array_sum(array_map(function($d) { return $d['estado'] === 'disponible' ? $d['precio'] : 0; }, $devices)), 0); ?>
                        </p>
                        <p class="text-sm text-gray-600">Valor Inventario</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-600">
                            <?php echo count($devices); ?>
                        </p>
                        <p class="text-sm text-gray-600">Total</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Agregar/Editar -->
    <?php if (hasPermission('add_devices')): ?>
    <div id="deviceModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <!-- Header fijo -->
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 flex-shrink-0">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Agregar Dispositivo</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Contenido con scroll -->
            <div class="overflow-y-auto flex-1 px-6 py-6" style="max-height: calc(90vh - 140px);">
                <form id="deviceForm" class="space-y-6">
                    <input type="hidden" id="deviceId" name="device_id">
                    <input type="hidden" id="formAction" name="action" value="add_device">
                    
                    <!-- SECCIÓN: Información del Dispositivo -->
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center text-base">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            Información del Dispositivo
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Modelo <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modelo" name="modelo" required 
                                       placeholder="Ej: iPhone 15 Pro Max"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Marca</label>
                                <input type="text" id="marca" name="marca" 
                                       placeholder="Ej: Apple, Samsung"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Capacidad <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="capacidad" name="capacidad" required 
                                       placeholder="Ej: 256GB, 512GB"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Color</label>
                                <input type="text" id="color" name="color" 
                                       placeholder="Ej: Negro, Blanco"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Códigos de Identificación -->
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center text-base">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            Códigos de Identificación
                        </h4>
                        
                        <!-- Código de Barras - DESTACADO -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <label class="block text-sm font-medium text-blue-900 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Código de Barras
                                <span class="ml-2 text-xs bg-blue-200 text-blue-800 px-2 py-0.5 rounded-full font-medium">Opcional</span>
                            </label>
                            
                            <div class="flex gap-2 mb-2">
                                <input type="text" id="codigo_barras" name="codigo_barras" 
                                       placeholder="Escanea o escribe el código de barras aquí"
                                       class="flex-1 px-4 py-2.5 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition-all font-mono text-base bg-white">
                                
                                <!-- Botón para abrir escáner de cámara -->
                                <button type="button" id="cameraScanButton" onclick="toggleBarcodeScanner()" 
                                        class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all flex items-center gap-2 shadow-sm hover:shadow-md"
                                        title="Escanear con cámara">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span class="hidden sm:inline font-medium">Cámara</span>
                                </button>
                            </div>
                            
                            <!-- Alerta si no es HTTPS -->
                            <div id="httpsWarning" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-2">
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-yellow-800">Cámara no disponible</p>
                                        <p class="text-xs text-yellow-700 mt-1">La cámara solo funciona en sitios seguros (HTTPS). Usa un lector USB o escribe manualmente.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Visor de la cámara para escanear -->
                            <div id="barcode-scanner" class="mt-3">
                                <div class="relative bg-black rounded-lg overflow-hidden">
                                    <div id="barcode-scanner-viewport"></div>
                                    <div class="scanner-overlay">
                                        <div class="scanner-line"></div>
                                    </div>
                                    <div class="absolute top-3 right-3">
                                        <button type="button" onclick="stopBarcodeScanner()" 
                                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium shadow-lg">
                                            ✕ Cerrar
                                        </button>
                                    </div>
                                    <div class="absolute bottom-3 left-0 right-0 text-center">
                                        <p class="text-white text-sm bg-black bg-opacity-60 inline-block px-4 py-2 rounded-lg font-medium">
                                            📷 Enfoca el código de barras
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-start gap-2 mt-2">
                                <svg class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-xs text-blue-700 leading-relaxed">
                                    Varios dispositivos del mismo modelo pueden compartir el código de barras. El IMEI es el identificador único.
                                </p>
                            </div>
                        </div>
                        
                        <!-- IMEIs -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    IMEI 1 <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="imei1" name="imei1" required 
                                       placeholder="Ej: 123456789012345"
                                       maxlength="15"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all font-mono text-base">
                                <p class="text-xs text-gray-500 mt-1.5">15 dígitos - <strong class="text-gray-700">Debe ser único</strong></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">IMEI 2</label>
                                <input type="text" id="imei2" name="imei2" 
                                       placeholder="Ej: 123456789012345"
                                       maxlength="15"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all font-mono text-base">
                                <p class="text-xs text-gray-500 mt-1.5">Solo para Dual SIM - <strong class="text-gray-700">Debe ser único</strong></p>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Precios -->
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center text-base">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            Información de Precios
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Precio de Venta <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">$</span>
                                    <input type="number" id="precio" name="precio" step="0.01" required 
                                           placeholder="0.00"
                                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Precio de Compra</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">$</span>
                                    <input type="number" id="precio_compra" name="precio_compra" step="0.01" 
                                           placeholder="0.00"
                                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                </div>
                                <p class="text-xs text-gray-500 mt-1.5">Opcional - para calcular ganancia</p>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Estado y Ubicación -->
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center text-base">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            Estado y Ubicación
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado</label>
                                <select id="estado" name="estado" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="disponible">Disponible</option>
                                    <option value="vendido">Vendido</option>
                                    <option value="reservado">Reservado</option>
                                    <option value="reparacion">En Reparación</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Condición</label>
                                <select id="condicion" name="condicion" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="nuevo">Nuevo</option>
                                    <option value="usado">Usado</option>
                                    <option value="refurbished">Refurbished</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Tienda <span class="text-red-500">*</span>
                                </label>
                                <select id="tienda_id" name="tienda_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <?php foreach($tiendas as $tienda): ?>
                                        <option value="<?php echo $tienda['id']; ?>"><?php echo htmlspecialchars($tienda['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Notas -->
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notas Adicionales</label>
                        <textarea id="notas" name="notas" rows="3" 
                                  placeholder="Información adicional, accesorios incluidos, observaciones, etc..."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"></textarea>
                    </div>
                </form>
            </div>
            
            <!-- Footer fijo -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                <button type="button" onclick="closeModal()" 
                        class="px-5 py-2.5 text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-all font-medium">
                    Cancelar
                </button>
                <button type="button" onclick="saveDevice()" 
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all flex items-center font-medium shadow-sm hover:shadow-md">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Guardar Dispositivo
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Mover Dispositivo -->
    <div id="moveModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-lg font-semibold flex items-center">
                    <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                    Mover Dispositivo
                </h3>
                <button onclick="closeMoveModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="p-6">
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Dispositivo:</p>
                    <p class="font-medium text-gray-900" id="moveDeviceName"></p>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mover a tienda:</label>
                    <select id="moveToTienda" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <?php foreach($tiendas as $tienda): ?>
                            <option value="<?php echo $tienda['id']; ?>"><?php echo htmlspecialchars($tienda['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-2">El dispositivo se moverá a la tienda seleccionada</p>
                </div>
                
                <input type="hidden" id="moveDeviceId">
                <input type="hidden" id="moveCurrentTiendaId">
                
                <div class="flex justify-end gap-3">
                    <button onclick="closeMoveModal()" 
                            class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button onclick="confirmMoveDevice()" 
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        Mover Dispositivo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
// Variables globales
let isEditMode = false;

// Funciones de gestión de dispositivos
function openAddModal() {
    <?php if (hasPermission('add_devices')): ?>
    isEditMode = false;
    document.getElementById('modalTitle').textContent = 'Agregar Dispositivo';
    document.getElementById('formAction').value = 'add_device';
    document.getElementById('deviceForm').reset();
    document.getElementById('deviceId').value = '';
    document.getElementById('deviceModal').classList.add('show');
    <?php else: ?>
    showNotification('No tienes permisos para agregar dispositivos', 'error');
    <?php endif; ?>
}

function openEditModal(device) {
    <?php if (hasPermission('add_devices')): ?>
    isEditMode = true;
    document.getElementById('modalTitle').textContent = 'Editar Dispositivo';
    document.getElementById('formAction').value = 'update_device';
    document.getElementById('deviceId').value = device.id;
    
    document.getElementById('modelo').value = device.modelo || '';
    document.getElementById('marca').value = device.marca || '';
    document.getElementById('capacidad').value = device.capacidad || '';
    document.getElementById('color').value = device.color || '';
    document.getElementById('precio').value = device.precio || '';
    document.getElementById('precio_compra').value = device.precio_compra || '';
    document.getElementById('imei1').value = device.imei1 || '';
    document.getElementById('imei2').value = device.imei2 || '';
    document.getElementById('codigo_barras').value = device.codigo_barras || '';
    document.getElementById('estado').value = device.estado || 'disponible';
    document.getElementById('condicion').value = device.condicion || 'nuevo';
    document.getElementById('notas').value = device.notas || '';
    document.getElementById('tienda_id').value = device.tienda_id || '';
    
    document.getElementById('deviceModal').classList.add('show');
    <?php else: ?>
    showNotification('No tienes permisos para editar dispositivos', 'error');
    <?php endif; ?>
}

function closeModal() {
    <?php if (hasPermission('add_devices')): ?>
    stopBarcodeScanner();
    const modal = document.getElementById('deviceModal');
    if (modal) {
        modal.classList.remove('show');
    }
    <?php endif; ?>
}

function saveDevice() {
    <?php if (hasPermission('add_devices')): ?>
    const formData = new FormData();
    
    formData.append('action', document.getElementById('formAction').value);
    
    if (isEditMode) {
        formData.append('device_id', document.getElementById('deviceId').value);
    }
    
    formData.append('modelo', document.getElementById('modelo').value);
    formData.append('marca', document.getElementById('marca').value);
    formData.append('capacidad', document.getElementById('capacidad').value);
    formData.append('color', document.getElementById('color').value);
    formData.append('precio', document.getElementById('precio').value);
    formData.append('precio_compra', document.getElementById('precio_compra').value);
    formData.append('imei1', document.getElementById('imei1').value);
    formData.append('imei2', document.getElementById('imei2').value);
    formData.append('codigo_barras', document.getElementById('codigo_barras').value);
    formData.append('estado', document.getElementById('estado').value);
    formData.append('condicion', document.getElementById('condicion').value);
    formData.append('notas', document.getElementById('notas').value);
    formData.append('tienda_id', document.getElementById('tienda_id').value);
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Guardando...';
    
    fetch('inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error en la conexión', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    });
    <?php endif; ?>
}

function deleteDevice(id) {
    <?php if (hasPermission('edit_devices')): ?>
    if (!confirm('¿Estás seguro de que quieres eliminar este dispositivo?\n\nEsta acción no se puede deshacer.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_device');
    formData.append('device_id', id);
    
    fetch('inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error en la conexión', 'error');
    });
    <?php else: ?>
    showNotification('No tienes permisos para eliminar dispositivos', 'error');
    <?php endif; ?>
}

// FUNCIONES PARA MOVER DISPOSITIVO
function openMoveModal(deviceId, deviceName, currentTiendaId) {
    <?php if (hasPermission('edit_devices')): ?>
    document.getElementById('moveDeviceId').value = deviceId;
    document.getElementById('moveCurrentTiendaId').value = currentTiendaId;
    document.getElementById('moveDeviceName').textContent = deviceName;
    
    const selectTienda = document.getElementById('moveToTienda');
    for (let i = 0; i < selectTienda.options.length; i++) {
        if (selectTienda.options[i].value != currentTiendaId) {
            selectTienda.selectedIndex = i;
            break;
        }
    }
    
    document.getElementById('moveModal').classList.add('show');
    <?php else: ?>
    showNotification('No tienes permisos para mover dispositivos', 'error');
    <?php endif; ?>
}

function closeMoveModal() {
    <?php if (hasPermission('edit_devices')): ?>
    const modal = document.getElementById('moveModal');
    if (modal) {
        modal.classList.remove('show');
    }
    <?php endif; ?>
}

function confirmMoveDevice() {
    <?php if (hasPermission('edit_devices')): ?>
    const deviceId = document.getElementById('moveDeviceId').value;
    const currentTiendaId = document.getElementById('moveCurrentTiendaId').value;
    const newTiendaId = document.getElementById('moveToTienda').value;
    
    if (currentTiendaId === newTiendaId) {
        showNotification('El dispositivo ya está en esa tienda', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'move_device');
    formData.append('device_id', deviceId);
    formData.append('new_tienda_id', newTiendaId);
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Moviendo...';
    
    fetch('inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeMoveModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error en la conexión', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    });
    <?php else: ?>
    showNotification('No tienes permisos para mover dispositivos', 'error');
    <?php endif; ?>
}

function showNotification(message, type) {
    type = type || 'info';
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500 text-white' :
                    type === 'error' ? 'bg-red-500 text-white' :
                    type === 'warning' ? 'bg-yellow-500 text-white' :
                    'bg-blue-500 text-white';
    
    notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 ' + bgColor;
    notification.innerHTML = '<div class="flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' + message + '</div>';
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.style.opacity = '0';
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 4000);
}

// Validación en tiempo real para IMEI
const imei1Input = document.getElementById('imei1');
if (imei1Input) {
    imei1Input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 15);
    });
}

const imei2Input = document.getElementById('imei2');
if (imei2Input) {
    imei2Input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 15);
    });
}

// Highlight del código de barras cuando se escanea
const codigoBarrasInput = document.getElementById('codigo_barras');
if (codigoBarrasInput) {
    codigoBarrasInput.addEventListener('input', function() {
        if (this.value.length > 5) {
            this.classList.add('border-green-500', 'bg-green-50');
            setTimeout(() => {
                this.classList.remove('border-green-500', 'bg-green-50');
            }, 500);
        }
    });
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeMoveModal();
    }
});

// Auto-focus en búsqueda con "/"
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
    }
});

console.log('✅ Sistema de Código de Barras activado');
console.log('✅ Sistema de Mover Dispositivos activado');
console.log('✅ Escáner de cámara disponible');
console.log('💡 Puedes usar un lector USB o escribir manualmente');

// ============================================================================
// FUNCIONES PARA ESCANEAR CÓDIGO DE BARRAS CON CÁMARA
// ============================================================================

let scannerActive = false;

// Verificar si el sitio es HTTPS
function isSecureContext() {
    return window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
}

// Verificar disponibilidad de cámara al cargar
window.addEventListener('load', function() {
    const cameraButton = document.getElementById('cameraScanButton');
    const httpsWarning = document.getElementById('httpsWarning');
    
    if (!isSecureContext()) {
        if (cameraButton) {
            cameraButton.disabled = true;
            cameraButton.classList.remove('bg-green-600', 'hover:bg-green-700');
            cameraButton.classList.add('bg-gray-400', 'cursor-not-allowed');
            cameraButton.title = 'Cámara no disponible (requiere HTTPS)';
        }
        if (httpsWarning) {
            httpsWarning.classList.remove('hidden');
        }
        console.warn('⚠️ Cámara deshabilitada: Requiere HTTPS');
    } else if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        console.log('📷 Cámara disponible para escanear códigos de barras');
    } else {
        console.warn('⚠️ Cámara no disponible en este navegador');
        if (cameraButton) {
            cameraButton.disabled = true;
            cameraButton.classList.remove('bg-green-600', 'hover:bg-green-700');
            cameraButton.classList.add('bg-gray-400', 'cursor-not-allowed');
            cameraButton.title = 'Navegador no compatible con cámara';
        }
    }
});

function toggleBarcodeScanner() {
    if (!isSecureContext()) {
        showNotification('⚠️ La cámara solo funciona en sitios HTTPS. Usa un lector USB o escribe manualmente.', 'warning');
        return;
    }
    
    if (scannerActive) {
        stopBarcodeScanner();
    } else {
        startBarcodeScanner();
    }
}

function startBarcodeScanner() {
    const scannerDiv = document.getElementById('barcode-scanner');
    
    if (!scannerDiv) {
        showNotification('No se pudo inicializar el escáner', 'error');
        return;
    }
    
    if (!isSecureContext()) {
        showNotification('⚠️ La cámara requiere HTTPS. Accede con https:// o usa localhost', 'error');
        return;
    }
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showNotification('Tu navegador no soporta acceso a la cámara. Usa Chrome, Safari o Firefox actualizado.', 'error');
        return;
    }
    
    scannerDiv.classList.add('active');
    scannerActive = true;
    
    Quagga.init({
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: document.querySelector('#barcode-scanner-viewport'),
            constraints: {
                width: { min: 640, ideal: 1280, max: 1920 },
                height: { min: 480, ideal: 720, max: 1080 },
                facingMode: "environment",
                aspectRatio: { min: 1, max: 2 }
            }
        },
        decoder: {
            readers: [
                "code_128_reader",
                "ean_reader",
                "ean_8_reader",
                "code_39_reader",
                "code_39_vin_reader",
                "codabar_reader",
                "upc_reader",
                "upc_e_reader",
                "i2of5_reader"
            ],
            debug: {
                drawBoundingBox: false,
                showFrequency: false,
                drawScanline: true,
                showPattern: false
            },
            multiple: false
        },
        locator: {
            patchSize: "medium",
            halfSample: true
        },
        locate: true,
        numOfWorkers: navigator.hardwareConcurrency || 4,
        frequency: 10
    }, function(err) {
        if (err) {
            console.error('Error al iniciar escáner:', err);
            
            if (err.name === 'NotAllowedError') {
                showNotification('❌ Permiso de cámara denegado. Permite el acceso en la configuración del navegador.', 'error');
            } else if (err.name === 'NotFoundError') {
                showNotification('❌ No se encontró cámara en tu dispositivo.', 'error');
            } else if (err.name === 'NotReadableError') {
                showNotification('❌ La cámara está siendo usada por otra aplicación.', 'error');
            } else if (err.name === 'SecurityError') {
                showNotification('❌ Error de seguridad: Asegúrate de usar HTTPS.', 'error');
            } else {
                showNotification('Error al acceder a la cámara: ' + err.message, 'error');
            }
            
            stopBarcodeScanner();
            return;
        }
        
        console.log("✅ Escáner iniciado");
        Quagga.start();
    });
    
    Quagga.onDetected(function(result) {
        if (result && result.codeResult && result.codeResult.code) {
            const code = result.codeResult.code;
            
            if (code.length >= 4) {
                const input = document.getElementById('codigo_barras');
                input.value = code;
                input.classList.add('scan-success');
                
                showNotification('✅ Código escaneado: ' + code, 'success');
                
                if (navigator.vibrate) {
                    navigator.vibrate(200);
                }
                
                setTimeout(() => {
                    stopBarcodeScanner();
                    input.classList.remove('scan-success');
                    document.getElementById('imei1')?.focus();
                }, 1000);
            }
        }
    });
}

function stopBarcodeScanner() {
    const scannerDiv = document.getElementById('barcode-scanner');
    
    if (scannerActive) {
        try {
            Quagga.stop();
            Quagga.offDetected();
        } catch (e) {
            console.error('Error deteniendo escáner:', e);
        }
        scannerDiv.classList.remove('active');
        scannerActive = false;
        console.log("⏹ Escáner detenido");
    }
}
    </script>
</body>
</html>
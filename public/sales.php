<?php
/**
 * VENTAS DE CELULARES
 * Sistema de Inventario con AdminLTE 4
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/components.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Procesar AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'search_devices') {
            $search = trim($_POST['search'] ?? '');
            
            $query = "SELECT c.*, t.nombre as tienda_nombre 
                     FROM celulares c 
                     LEFT JOIN tiendas t ON c.tienda_id = t.id 
                     WHERE c.estado = 'disponible'";
            
            $params = [];
            
            if ($user['rol'] !== 'admin') {
                $query .= " AND c.tienda_id = ?";
                $params[] = $user['tienda_id'];
            }
            
            if (!empty($search)) {
                $query .= " AND (c.modelo LIKE ? OR c.marca LIKE ? OR c.capacidad LIKE ? OR c.imei1 LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }
            
            $query .= " ORDER BY c.fecha_registro DESC LIMIT 50";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'devices' => $devices,
                'count' => count($devices)
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'register_sale') {
            $celular_id = intval($_POST['celular_id']);
            $cliente_nombre = trim($_POST['cliente_nombre']);
            $cliente_telefono = trim($_POST['cliente_telefono'] ?? '');
            $cliente_email = trim($_POST['cliente_email'] ?? '');
            $precio_venta = floatval($_POST['precio_venta']);
            $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
            $notas = trim($_POST['notas'] ?? '');
            
            if (empty($cliente_nombre) || $precio_venta <= 0) {
                throw new Exception('Datos incompletos');
            }
            
            $db->beginTransaction();
            
            // Verificar que el celular está disponible
            $stmt = $db->prepare("SELECT * FROM celulares WHERE id = ? AND estado = 'disponible' FOR UPDATE");
            $stmt->execute([$celular_id]);
            $celular = $stmt->fetch();
            
            if (!$celular) {
                throw new Exception('Celular no disponible');
            }
            
            // Registrar venta
            $stmt = $db->prepare("
                INSERT INTO ventas (celular_id, cliente_nombre, cliente_telefono, cliente_email, 
                                   precio_venta, metodo_pago, notas, vendedor_id, tienda_id, fecha_venta)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $celular_id, $cliente_nombre, $cliente_telefono, $cliente_email,
                $precio_venta, $metodo_pago, $notas, $user['id'], $user['tienda_id']
            ]);
            
            $venta_id = $db->lastInsertId();
            
            // Actualizar estado del celular
            $stmt = $db->prepare("UPDATE celulares SET estado = 'vendido', fecha_venta = NOW() WHERE id = ?");
            $stmt->execute([$celular_id]);
            
            // Registrar actividad
            if (function_exists('logActivity')) {
                logActivity($user['id'], 'register_sale', "Venta registrada: {$celular['marca']} {$celular['modelo']}");
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => '¡Venta registrada exitosamente!',
                'venta_id' => $venta_id
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vender Celulares - <?php echo SYSTEM_NAME; ?></title>
    <?php renderSharedStyles(); ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <?php renderNavbar('sales', $user); ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><i class="fas fa-cash-register"></i> Vender Celulares</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Ventas</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">

                <!-- Buscador -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-search"></i> Buscar Dispositivo</h3>
                            </div>
                            <div class="card-body">
                                <div class="input-group input-group-lg">
                                    <input type="text" 
                                           id="deviceSearch" 
                                           class="form-control" 
                                           placeholder="Buscar por modelo, marca, capacidad o IMEI...">
                                    <div class="input-group-append">
                                        <button class="btn btn-default" type="button" id="clearSearchBtn" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <button class="btn btn-primary" type="button" onclick="searchDevices()">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted" id="searchInfo"></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resultados -->
                <div class="row" id="devicesList">
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>Busca un dispositivo para vender</h3>
                            <p>Usa el buscador arriba para encontrar celulares disponibles</p>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <?php echo SYSTEM_NAME; ?>.</strong>
        Todos los derechos reservados.
    </footer>
</div>

<!-- Modal de Venta -->
<div class="modal fade" id="saleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h4 class="modal-title"><i class="fas fa-cash-register"></i> Registrar Venta</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="saleForm" onsubmit="return registerSale(event)">
                <div class="modal-body">
                    <input type="hidden" id="selectedDeviceId" name="celular_id">
                    
                    <!-- Info del dispositivo -->
                    <div class="alert alert-info" id="deviceInfo" style="display: none;">
                        <h5><i class="fas fa-mobile-alt"></i> <span id="deviceName"></span></h5>
                        <p class="mb-1" id="deviceDetails"></p>
                        <p class="mb-0"><strong>Precio: <span id="devicePrice"></span></strong></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cliente <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cliente_nombre" name="cliente_nombre" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="tel" class="form-control" id="cliente_telefono" name="cliente_telefono">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" id="cliente_email" name="cliente_email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Precio Venta (S/) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="precio_venta" name="precio_venta" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Método de Pago</label>
                                <select class="form-control" id="metodo_pago" name="metodo_pago">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="yape">Yape/Plin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Notas</label>
                                <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Loading -->
<div id="loadingSpinner" class="loading-overlay" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Cargando...</span>
    </div>
</div>

<?php renderCommonScripts(); ?>

<script>
let selectedDevice = null;
let searchTimeout = null;

// Búsqueda con debounce
document.getElementById('deviceSearch').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const value = this.value.trim();
    
    document.getElementById('clearSearchBtn').style.display = value ? 'block' : 'none';
    
    searchTimeout = setTimeout(() => searchDevices(), 500);
});

document.getElementById('deviceSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimeout);
        searchDevices();
    }
});

document.getElementById('clearSearchBtn').addEventListener('click', function() {
    document.getElementById('deviceSearch').value = '';
    this.style.display = 'none';
    document.getElementById('devicesList').innerHTML = '<div class="col-12"><div class="empty-state"><i class="fas fa-search"></i><h3>Busca un dispositivo para vender</h3></div></div>';
});

function searchDevices() {
    const search = document.getElementById('deviceSearch').value.trim();
    
    document.getElementById('loadingSpinner').style.display = 'flex';
    
    fetch('sales.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'search_devices',
            search: search
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            renderDevices(data.devices);
            updateSearchInfo(search, data.count);
        } else {
            showToast('Error al buscar', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error de conexión', 'error');
    })
    .finally(() => {
        document.getElementById('loadingSpinner').style.display = 'none';
    });
}

function renderDevices(devices) {
    const container = document.getElementById('devicesList');
    
    if (devices.length === 0) {
        container.innerHTML = '<div class="col-12"><div class="empty-state"><i class="fas fa-mobile-alt"></i><h3>No se encontraron dispositivos</h3></div></div>';
        return;
    }
    
    let html = '';
    devices.forEach(device => {
        html += `
        <div class="col-md-4 mb-3">
            <div class="product-card" onclick='selectDevice(${JSON.stringify(device).replace(/'/g, "&#39;")})'>
                <h5 class="mb-2">${escapeHtml(device.modelo)}</h5>
                <p class="text-muted mb-2">${escapeHtml(device.marca)}</p>
                <div class="mb-2">
                    <span class="badge badge-info">${escapeHtml(device.capacidad)}</span>
                    ${device.color ? `<span class="badge badge-secondary">${escapeHtml(device.color)}</span>` : ''}
                </div>
                ${device.tienda_nombre ? `<small class="text-muted"><i class="fas fa-store"></i> ${escapeHtml(device.tienda_nombre)}</small><br>` : ''}
                <h4 class="text-primary mt-2">S/ ${parseFloat(device.precio).toFixed(2)}</h4>
            </div>
        </div>
        `;
    });
    
    container.innerHTML = html;
}

function selectDevice(device) {
    selectedDevice = device;
    
    document.getElementById('selectedDeviceId').value = device.id;
    document.getElementById('deviceName').textContent = device.modelo;
    document.getElementById('deviceDetails').textContent = `${device.marca} - ${device.capacidad}${device.color ? ' - ' + device.color : ''}`;
    document.getElementById('devicePrice').textContent = 'S/ ' + parseFloat(device.precio).toFixed(2);
    document.getElementById('deviceInfo').style.display = 'block';
    document.getElementById('precio_venta').value = device.precio;
    
    $('#saleModal').modal('show');
}

function registerSale(e) {
    e.preventDefault();
    
    if (!selectedDevice) {
        showToast('Selecciona un dispositivo', 'warning');
        return false;
    }
    
    const formData = new FormData(e.target);
    formData.append('action', 'register_sale');
    
    const confirmMsg = `¿Confirmar venta?\n\nDispositivo: ${selectedDevice.modelo}\nCliente: ${formData.get('cliente_nombre')}\nPrecio: S/ ${parseFloat(formData.get('precio_venta')).toFixed(2)}`;
    
    if (!confirm(confirmMsg)) {
        return false;
    }
    
    document.getElementById('loadingSpinner').style.display = 'flex';
    
    fetch('sales.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            $('#saleModal').modal('hide');
            document.getElementById('saleForm').reset();
            selectedDevice = null;
            searchDevices();
            
            // Preguntar si quiere imprimir
            if (confirm('¿Desea imprimir el comprobante?')) {
                window.open(`print_sale_invoice.php?id=${data.venta_id}`, '_blank');
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error al registrar venta', 'error');
    })
    .finally(() => {
        document.getElementById('loadingSpinner').style.display = 'none';
    });
    
    return false;
}

function updateSearchInfo(search, count) {
    const info = document.getElementById('searchInfo');
    if (search) {
        info.textContent = `Mostrando ${count} resultado${count !== 1 ? 's' : ''} para "${search}"`;
    } else {
        info.textContent = '';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type) {
    const bgClass = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info'
    }[type] || 'bg-info';
    
    $(document).Toasts('create', {
        class: bgClass,
        title: type === 'success' ? 'Éxito' : type === 'error' ? 'Error' : 'Información',
        body: message,
        autohide: true,
        delay: 3000
    });
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        $('#saleModal').modal('hide');
    }
});

console.log('✅ Sistema de ventas cargado con AdminLTE 4');
</script>

</body>
</html>
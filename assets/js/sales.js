/**
 * SALES.JS - Módulo de Ventas
 * Versión 2.0 - Código modularizado y optimizado
 */

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
            updateDeviceCount(data.devices.length);
            updateSearchInfo(searchTerm, data.devices.length);
        } else {
            showNotification('Error al buscar: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error en la búsqueda', 'danger');
    })
    .finally(() => {
        hideLoading();
    });
}

function clearDeviceSearch() {
    document.getElementById('deviceSearch').value = '';
    searchDevices();
}

function updateDeviceCount(count) {
    document.getElementById('deviceCount').textContent = count + ' encontrados';
}

function updateSearchInfo(searchTerm, count) {
    const infoElement = document.getElementById('searchInfo');
    if (searchTerm) {
        infoElement.textContent = `Mostrando ${count} resultados para "${searchTerm}"`;
    } else {
        infoElement.textContent = 'Mostrando los últimos 20 dispositivos disponibles';
    }
}

// ==========================================
// RENDERIZADO DE DISPOSITIVOS
// ==========================================
function renderDevices(devices) {
    const container = document.getElementById('devicesList');
    
    if (devices.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <p class="text-gray-500 font-medium">No se encontraron dispositivos</p>
                <p class="text-sm text-gray-400 mt-1">Intenta con otros términos de búsqueda</p>
            </div>
        `;
        return;
    }
    
    const html = devices.map(device => createDeviceCard(device)).join('');
    container.innerHTML = html;
    attachDeviceCardListeners();
}

function createDeviceCard(device) {
    const deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
    
    return `
        <div class="interactive-card" 
             data-device-id="${device.id}" 
             data-device="${deviceJson}">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <p class="font-medium text-gray-900">${escapeHtml(device.modelo)}</p>
                        <span class="badge badge-success">Disponible</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-1">
                        ${escapeHtml(device.marca)} - ${escapeHtml(device.capacidad)}
                        ${device.color ? ' - ' + escapeHtml(device.color) : ''}
                    </p>
                    ${device.imei1 ? `
                        <p class="text-xs text-gray-500 font-mono bg-gray-100 inline-block px-2 py-1 rounded mb-1">
                            IMEI: ${escapeHtml(device.imei1)}
                        </p>
                    ` : ''}
                    ${device.tienda_nombre ? `
                        <p class="text-xs text-primary mt-1">${escapeHtml(device.tienda_nombre)}</p>
                    ` : ''}
                </div>
                <div class="text-right ml-4">
                    <p class="font-bold text-lg text-primary">${formatPrice(device.precio)}</p>
                </div>
            </div>
        </div>
    `;
}

function attachDeviceCardListeners() {
    document.querySelectorAll('.interactive-card').forEach(card => {
        card.addEventListener('click', function() {
            const deviceData = this.getAttribute('data-device');
            if (deviceData) {
                try {
                    const device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
                    selectDeviceForSale(device);
                } catch (e) {
                    console.error('Error parsing device data:', e);
                    showNotification('Error al seleccionar dispositivo', 'danger');
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
    document.querySelectorAll('.interactive-card').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Marcar tarjeta seleccionada
    const selectedCard = document.querySelector(`[data-device-id="${device.id}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
        selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // Llenar información del dispositivo
    document.getElementById('selectedDeviceId').value = device.id;
    document.getElementById('deviceName').textContent = device.modelo;
    document.getElementById('deviceDetails').textContent = 
        `${device.marca} - ${device.capacidad}${device.color ? ' - ' + device.color : ''}`;
    document.getElementById('devicePrice').textContent = ' + formatPrice(device.precio);
    document.getElementById('deviceInfo').classList.remove('hidden');
    
    // Pre-llenar precio
    document.getElementById('precio_venta').value = device.precio;
    
    // Abrir modal
    openSaleModal();
}

function clearDeviceSelection() {
    selectedDevice = null;
    document.querySelectorAll('.interactive-card').forEach(el => {
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
        showNotification('No se ha seleccionado un dispositivo', 'warning');
        return;
    }
    
    const cliente_nombre = document.getElementById('cliente_nombre').value.trim();
    const precio_venta = parseFloat(document.getElementById('precio_venta').value);
    
    // Validaciones
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
    
    // Confirmar venta
    const confirmMessage = `¿Confirmar venta?\n\nDispositivo: ${selectedDevice.modelo}\nCliente: ${cliente_nombre}\nPrecio: ${precio_venta.toFixed(2)}`;
    
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
        <div class="modal-content text-center">
            <div class="mb-4">
                <svg class="w-16 h-16 text-success mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">¡Venta Registrada!</h3>
            <p class="text-gray-600 mb-6">La venta se ha registrado correctamente.</p>
            <div class="flex gap-3 justify-center">
                <button onclick="printInvoice(${ventaId})" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Imprimir Comprobante
                </button>
                <button onclick="closeDialogAndReload(this)" class="btn btn-secondary">
                    Continuar sin Imprimir
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-4">
                Puedes imprimir el comprobante más tarde desde el historial
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
        showNotification('No se pudo abrir la ventana de impresión', 'danger');
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
    document.getElementById('devicesList').style.opacity = '0.5';
}

function hideLoading() {
    document.getElementById('loadingSpinner').classList.add('hidden');
    document.getElementById('devicesList').style.opacity = '1';
}

function showNotification(message, type = 'info') {
    const colors = {
        'success': 'bg-green-500',
        'danger': 'bg-red-500',
        'warning': 'bg-yellow-500',
        'info': 'bg-blue-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 ${colors[type]} text-white`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
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
    console.log('✅ Sistema de Ventas Cargado');
    
    // Búsqueda con delay
    document.getElementById('deviceSearch').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchDevices(), 500);
    });
    
    // Enter en búsqueda
    document.getElementById('deviceSearch').addEventListener('keypress', function(e) {
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

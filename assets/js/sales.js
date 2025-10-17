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
    document.getElementById('clearSearchBtn').classList.add('hidden');
    document.getElementById('searchInfo').textContent = '';
    
    // Limpiar resultados y mostrar mensaje inicial
    const container = document.getElementById('devicesList');
    container.innerHTML = '<div class="col-span-full text-center py-16">' +
        '<svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>' +
        '</svg>' +
        '<h3 class="text-xl font-bold text-gray-700 mb-2">Busca un dispositivo para vender</h3>' +
        '<p class="text-gray-500">Usa el buscador arriba para encontrar celulares disponibles</p>' +
        '</div>';
}

function updateSearchInfo(searchTerm, count) {
    const infoElement = document.getElementById('searchInfo');
    if (searchTerm) {
        infoElement.innerHTML = 'Mostrando <strong>' + count + '</strong> resultados para "<strong>' + escapeHtml(searchTerm) + '</strong>"';
    } else {
        infoElement.textContent = '';
    }
}

// ==========================================
// RENDERIZADO DE DISPOSITIVOS
// ==========================================
function renderDevices(devices) {
    const container = document.getElementById('devicesList');
    
    if (devices.length === 0) {
        container.innerHTML = 
            '<div class="col-span-full text-center py-16">' +
            '<svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>' +
            '</svg>' +
            '<h3 class="text-xl font-bold text-gray-700 mb-2">No se encontraron dispositivos</h3>' +
            '<p class="text-gray-500">Intenta con otros términos de búsqueda</p>' +
            '</div>';
        return;
    }
    
    const html = devices.map((device, index) => createDeviceCard(device, index)).join('');
    container.innerHTML = html;
    attachDeviceCardListeners();
}

function createDeviceCard(device, index) {
    const deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
    const imeiShort = device.imei1 ? device.imei1.substring(0, 12) + '...' : '';
    
    const parts = [];
    parts.push('<div class="device-card animate-in" style="animation-delay: ' + (index * 0.05) + 's" data-device-id="' + device.id + '" data-device="' + deviceJson + '">');
    parts.push('<div class="mb-3">');
    parts.push('<h3 class="text-lg font-bold text-gray-900 mb-1">' + escapeHtml(device.modelo) + '</h3>');
    parts.push('<p class="text-sm text-gray-600">' + escapeHtml(device.marca) + '</p>');
    parts.push('</div>');
    
    parts.push('<div class="space-y-2 mb-4">');
    parts.push('<div class="flex items-center gap-2 text-sm text-gray-600">');
    parts.push('<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">');
    parts.push('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>');
    parts.push('</svg>');
    parts.push('<span>' + escapeHtml(device.capacidad) + '</span>');
    parts.push('</div>');
    
    if (device.color) {
        parts.push('<div class="flex items-center gap-2 text-sm text-gray-600">');
        parts.push('<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">');
        parts.push('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>');
        parts.push('</svg>');
        parts.push('<span>' + escapeHtml(device.color) + '</span>');
        parts.push('</div>');
    }
    
    if (device.imei1) {
        parts.push('<div class="flex items-center gap-2 text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">');
        parts.push('<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">');
        parts.push('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>');
        parts.push('</svg>');
        parts.push('<span>IMEI: ' + escapeHtml(imeiShort) + '</span>');
        parts.push('</div>');
    }
    
    if (device.tienda_nombre) {
        parts.push('<div class="flex items-center gap-2 text-sm text-primary">');
        parts.push('<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">');
        parts.push('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>');
        parts.push('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>');
        parts.push('</svg>');
        parts.push('<span>' + escapeHtml(device.tienda_nombre) + '</span>');
        parts.push('</div>');
    }
    
    parts.push('</div>');
    
    parts.push('<div class="pt-4 border-t border-gray-200">');
    parts.push('<div class="flex items-center justify-between">');
    parts.push('<span class="text-sm text-gray-500">Precio:</span>');
    parts.push('<span class="text-2xl font-bold" style="color: var(--color-primary);">S/' + formatPrice(device.precio) + '</span>');
    parts.push('</div>');
    parts.push('</div>');
    
    parts.push('</div>');
    
    return parts.join('');
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
    document.querySelectorAll('.device-card').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Marcar tarjeta seleccionada
    const selectedCard = document.querySelector('[data-device-id="' + device.id + '"]');
    if (selectedCard) {
        selectedCard.classList.add('selected');
        selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Llenar información del dispositivo
    document.getElementById('selectedDeviceId').value = device.id;
    document.getElementById('deviceName').textContent = device.modelo;
    document.getElementById('deviceDetails').textContent = 
        device.marca + ' - ' + device.capacidad + (device.color ? ' - ' + device.color : '');
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
    const confirmMessage = '¿Confirmar venta?\n\nDispositivo: ' + selectedDevice.modelo + '\nCliente: ' + cliente_nombre + '\nPrecio: S/' + precio_venta.toFixed(2);
    
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
    modal.innerHTML = 
        '<div class="modal-content text-center" style="max-width: 500px;">' +
        '<div class="mb-6">' +
        '<div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">' +
        '<svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
        '</svg>' +
        '</div>' +
        '<h3 class="text-2xl font-bold text-gray-900 mb-2">¡Venta Registrada!</h3>' +
        '<p class="text-gray-600">La venta se ha registrado correctamente en el sistema.</p>' +
        '</div>' +
        '<div class="flex flex-col gap-3">' +
        '<button onclick="printInvoice(' + ventaId + ')" class="btn btn-primary w-full">' +
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>' +
        '</svg>' +
        'Imprimir Comprobante' +
        '</button>' +
        '<button onclick="closeDialogAndReload(this)" class="btn btn-secondary w-full">' +
        'Continuar sin Imprimir' +
        '</button>' +
        '</div>' +
        '<p class="text-xs text-gray-500 mt-4">' +
        '💡 Puedes imprimir el comprobante más tarde desde el historial de ventas' +
        '</p>' +
        '</div>';
    
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
    document.getElementById('devicesList').style.opacity = '0.3';
}

function hideLoading() {
    document.getElementById('loadingSpinner').classList.add('hidden');
    document.getElementById('devicesList').style.opacity = '1';
}

function showNotification(message, type) {
    type = type || 'info';
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
    
    setTimeout(function() {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(function() {
            notification.remove();
        }, 300);
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
    
    console.log('💡 Atajos: Ctrl+F (Buscar) | Esc (Cerrar modal)');
});
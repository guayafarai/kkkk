/**
 * SALES.JS - Sistema de Ventas de Celulares
 * Versión 8.1 FINAL - CORRECCIONES APLICADAS
 * 
 * CORRECCIONES EN ESTA VERSIÓN:
 * ✅ Función formatPrice() agregada y funcional
 * ✅ AJAX funcional sin recargar página
 * ✅ Búsqueda en tiempo real optimizada
 * ✅ Prevención de submits accidentales
 * ✅ Manejo de errores robusto
 */

// ==========================================
// VARIABLES GLOBALES
// ==========================================
let selectedDevice = null;
let searchTimeout = null;

// ==========================================
// UTILIDADES - CRÍTICO: DEFINIR ANTES DE USAR
// ==========================================

function escapeHtml(text) {
    if (!text) return '';
    const precioInput = document.getElementById('precio_venta');
    if (precioInput) {
        precioInput.addEventListener('input', function() {
            let value = parseFloat(this.value);
            
            if (isNaN(value) || value < 0) {
                this.value = 0;
            }
        });
        
        precioInput.addEventListener('blur', function() {
            const value = parseFloat(this.value);
            if (isNaN(value) || value <= 0) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
    
    const nombreInput = document.getElementById('cliente_nombre');
    const emailField = document.getElementById('cliente_email');
    if (nombreInput && emailField) {
        nombreInput.addEventListener('blur', function() {
            const nombre = this.value.trim();
            
            if (nombre && !emailField.value) {
                const nombreLimpio = nombre.toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/\s+/g, '.')
                    .replace(/[^a-z0-9.]/g, '');
                
                emailField.placeholder = `Ej: ${nombreLimpio}@ejemplo.com`;
            }
        });
    }
    
    const saleForm = document.getElementById('saleForm');
    if (saleForm) {
        saleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            registerSale();
            return false;
        });
    }
    
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
        console.log('📱 Dispositivo táctil detectado');
    }
    
    console.log('💡 Atajos: Ctrl+F (Buscar) | Esc (Cerrar modal)');
    console.log('🔍 Escribe en el buscador para encontrar dispositivos');
    
    if (window.SALES_CONFIG) {
        console.log('📊 Configuración:', {
            disponibles: window.SALES_CONFIG.disponibles,
            ventasHoy: window.SALES_CONFIG.ventasHoy
        });
    }
    
    console.log('🚀 Sistema completamente inicializado');
}); div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatPrice(price) {
    const num = parseFloat(price);
    if (isNaN(num)) return '0.00';
    return num.toFixed(2);
}

// ==========================================
// BÚSQUEDA DE DISPOSITIVOS
// ==========================================
function searchDevices() {
    const searchInput = document.getElementById('deviceSearch');
    const searchTerm = searchInput ? searchInput.value.trim() : '';
    
    console.log('🔍 Buscando:', searchTerm || '[búsqueda vacía]');
    
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) {
        clearBtn.classList.toggle('hidden', !searchTerm);
    }
    
    showLoading();
    
    const formData = new FormData();
    formData.append('action', 'search_devices');
    formData.append('search', searchTerm);
    
    fetch('sales.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('📡 Respuesta recibida - Status:', response.status);
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('❌ Respuesta no es JSON. Content-Type:', contentType);
            throw new Error('El servidor no devolvió JSON. Posible error de PHP.');
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('📦 Datos recibidos:', {
            success: data.success,
            count: data.count || (data.devices ? data.devices.length : 0)
        });
        
        if (data.success) {
            renderDevices(data.devices);
            updateSearchInfo(searchTerm, data.devices.length);
        } else {
            console.error('❌ Error del servidor:', data.message);
            showNotification('Error: ' + (data.message || 'Error desconocido'), 'danger');
            renderDevices([]);
        }
    })
    .catch(error => {
        console.error('❌ Error en búsqueda:', error);
        showNotification('Error de conexión. Por favor intenta de nuevo.', 'danger');
        
        const container = document.getElementById('devicesList');
        if (container) {
            container.innerHTML = `
                <div class="col-span-full text-center py-16">
                    <svg class="w-24 h-24 mx-auto text-red-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-xl font-bold text-red-700 mb-2">Error de conexión</h3>
                    <p class="text-gray-500 mb-2">${escapeHtml(error.message)}</p>
                    <p class="text-sm text-gray-400 mb-4">Verifica tu conexión a internet</p>
                    <button onclick="searchDevices()" class="btn btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Reintentar
                    </button>
                </div>
            `;
        }
    })
    .finally(() => {
        hideLoading();
        console.log('✅ Búsqueda completada');
    });
}

function clearDeviceSearch() {
    console.log('🧹 Limpiando búsqueda...');
    
    const searchInput = document.getElementById('deviceSearch');
    const clearBtn = document.getElementById('clearSearchBtn');
    const searchInfo = document.getElementById('searchInfo');
    const container = document.getElementById('devicesList');
    
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    
    if (clearBtn) {
        clearBtn.classList.add('hidden');
    }
    
    if (searchInfo) {
        searchInfo.textContent = '';
    }
    
    if (container) {
        container.innerHTML = `
            <div class="col-span-full text-center py-16">
                <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <h3 class="text-xl font-bold text-gray-700 mb-2">Busca un dispositivo para vender</h3>
                <p class="text-gray-500">Usa el buscador arriba para encontrar celulares disponibles</p>
            </div>
        `;
    }
    
    console.log('✅ Búsqueda limpiada');
}

function updateSearchInfo(searchTerm, count) {
    const infoElement = document.getElementById('searchInfo');
    if (!infoElement) return;
    
    if (searchTerm) {
        infoElement.innerHTML = `Mostrando <strong>${count}</strong> resultado${count !== 1 ? 's' : ''} para "<strong>${escapeHtml(searchTerm)}</strong>"`;
    } else {
        infoElement.textContent = '';
    }
}

// ==========================================
// RENDERIZADO DE DISPOSITIVOS
// ==========================================
function renderDevices(devices) {
    const container = document.getElementById('devicesList');
    if (!container) {
        console.error('❌ Contenedor #devicesList no encontrado');
        return;
    }
    
    if (!Array.isArray(devices) || devices.length === 0) {
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
             data-device="${deviceJson}"
             tabindex="0"
             role="button"
             aria-label="Seleccionar ${escapeHtml(device.modelo)} para venta">
            
            <div class="mb-3">
                <h3 class="text-lg font-bold text-gray-900 mb-1">${escapeHtml(device.modelo)}</h3>
                <p class="text-sm text-gray-600">${escapeHtml(device.marca)}</p>
            </div>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span>${escapeHtml(device.capacidad)}</span>
                </div>
                
                ${device.color ? `
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                    </svg>
                    <span>${escapeHtml(device.color)}</span>
                </div>
                ` : ''}
                
                ${device.imei1 ? `
                <div class="flex items-center gap-2 text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                    <span>IMEI: ${escapeHtml(imeiShort)}</span>
                </div>
                ` : ''}
                
                ${device.tienda_nombre ? `
                <div class="flex items-center gap-2 text-sm text-primary">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <span class="text-2xl font-bold" style="color: var(--color-primary);">S/ ${formatPrice(device.precio)}</span>
                </div>
            </div>
        </div>
    `;
}

function attachDeviceCardListeners() {
    document.querySelectorAll('.device-card').forEach(card => {
        card.addEventListener('click', function() {
            handleDeviceSelection(this);
        });
        
        card.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleDeviceSelection(this);
            }
        });
    });
}

function handleDeviceSelection(card) {
    const deviceData = card.getAttribute('data-device');
    if (deviceData) {
        try {
            const device = JSON.parse(deviceData.replace(/&quot;/g, '"'));
            selectDeviceForSale(device);
        } catch (e) {
            console.error('Error parsing device data:', e);
            showNotification('Error al seleccionar dispositivo', 'danger');
        }
    }
}

// ==========================================
// SELECCIÓN DE DISPOSITIVO
// ==========================================
function selectDeviceForSale(device) {
    selectedDevice = device;
    console.log('✅ Dispositivo seleccionado:', device.modelo);
    
    document.querySelectorAll('.device-card').forEach(el => {
        el.classList.remove('selected');
        el.setAttribute('aria-selected', 'false');
    });
    
    const selectedCard = document.querySelector(`[data-device-id="${device.id}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
        selectedCard.setAttribute('aria-selected', 'true');
        selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    document.getElementById('selectedDeviceId').value = device.id;
    document.getElementById('deviceName').textContent = device.modelo;
    document.getElementById('deviceDetails').textContent = 
        `${device.marca} - ${device.capacidad}${device.color ? ' - ' + device.color : ''}`;
    document.getElementById('devicePrice').textContent = 'S/ ' + formatPrice(device.precio);
    document.getElementById('deviceInfo').classList.remove('hidden');
    
    document.getElementById('precio_venta').value = device.precio;
    
    openSaleModal();
}

function clearDeviceSelection() {
    selectedDevice = null;
    document.querySelectorAll('.device-card').forEach(el => {
        el.classList.remove('selected');
        el.setAttribute('aria-selected', 'false');
    });
    document.getElementById('deviceInfo').classList.add('hidden');
}

// ==========================================
// GESTIÓN DEL MODAL
// ==========================================
function openSaleModal() {
    const modal = document.getElementById('saleModal');
    if (modal) {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            const nombreInput = document.getElementById('cliente_nombre');
            if (nombreInput) nombreInput.focus();
        }, 100);
    }
}

function closeSaleModal() {
    const modal = document.getElementById('saleModal');
    if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = 'auto';
    }
    
    clearSaleForm();
    clearDeviceSelection();
}

function clearSaleForm() {
    const form = document.getElementById('saleForm');
    if (form) {
        form.reset();
    }
    
    const fields = ['cliente_nombre', 'cliente_telefono', 'cliente_email', 'precio_venta', 'notas'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            field.classList.remove('is-valid', 'is-invalid');
        }
    });
    
    const metodoPago = document.getElementById('metodo_pago');
    if (metodoPago) {
        metodoPago.value = 'efectivo';
    }
}

// ==========================================
// REGISTRO DE VENTA
// ==========================================
function registerSale() {
    console.log('💰 Registrando venta...');
    
    if (!selectedDevice) {
        showNotification('No se ha seleccionado un dispositivo', 'warning');
        return false;
    }
    
    const cliente_nombre = document.getElementById('cliente_nombre').value.trim();
    const precio_venta = parseFloat(document.getElementById('precio_venta').value);
    
    if (!cliente_nombre) {
        showNotification('Por favor ingresa el nombre del cliente', 'warning');
        document.getElementById('cliente_nombre').focus();
        return false;
    }
    
    if (!precio_venta || precio_venta <= 0) {
        showNotification('Por favor ingresa un precio válido', 'warning');
        document.getElementById('precio_venta').focus();
        return false;
    }
    
    const confirmMessage = `¿Confirmar venta?\n\nDispositivo: ${selectedDevice.modelo}\nCliente: ${cliente_nombre}\nPrecio: S/ ${precio_venta.toFixed(2)}`;
    
    if (!confirm(confirmMessage)) {
        return false;
    }
    
    const formData = new FormData();
    formData.append('action', 'register_sale');
    formData.append('celular_id', selectedDevice.id);
    formData.append('cliente_nombre', cliente_nombre);
    formData.append('cliente_telefono', document.getElementById('cliente_telefono').value);
    formData.append('cliente_email', document.getElementById('cliente_email').value);
    formData.append('precio_venta', precio_venta);
    formData.append('metodo_pago', document.getElementById('metodo_pago').value);
    formData.append('notas', document.getElementById('notas').value);
    
    const buttons = document.querySelectorAll('#saleForm button');
    buttons.forEach(btn => btn.disabled = true);
    
    showLoading();
    
    fetch('sales.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Respuesta inválida del servidor');
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('📦 Respuesta de venta:', data);
        
        if (data.success) {
            showNotification('✅ ' + data.message, 'success');
            closeSaleModal();
            showPrintDialog(data.venta_id);
        } else {
            showNotification('❌ ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('❌ Error registrando venta:', error);
        showNotification('❌ Error en la conexión. Intenta nuevamente.', 'danger');
    })
    .finally(() => {
        hideLoading();
        buttons.forEach(btn => btn.disabled = false);
    });
    
    return false;
}

// ==========================================
// DIÁLOGO DE IMPRESIÓN
// ==========================================
function showPrintDialog(ventaId) {
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
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
        `print_sale_invoice.php?id=${ventaId}`,
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
    const modal = button.closest('.modal');
    if (modal) {
        modal.remove();
    }
    setTimeout(() => location.reload(), 300);
}

// ==========================================
// UTILIDADES DE UI
// ==========================================
function showLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.classList.remove('hidden');
    }
    const devicesList = document.getElementById('devicesList');
    if (devicesList) {
        devicesList.style.opacity = '0.3';
        devicesList.style.pointerEvents = 'none';
    }
}

function hideLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.classList.add('hidden');
    }
    const devicesList = document.getElementById('devicesList');
    if (devicesList) {
        devicesList.style.opacity = '1';
        devicesList.style.pointerEvents = 'auto';
    }
}

function showNotification(message, type) {
    if (window.showNotification && typeof window.showNotification === 'function') {
        window.showNotification(message, type);
        return;
    }
    
    const colors = {
        'success': 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'danger': 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
        'warning': 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'info': 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
    };
    
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-2xl max-w-md transition-all duration-300 text-white';
    notification.style.background = colors[type] || colors['info'];
    notification.style.transform = 'translateX(120%)';
    notification.textContent = message;
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'assertive');
    
    document.body.appendChild(notification);
    
    requestAnimationFrame(() => {
        notification.style.transform = 'translateX(0)';
    });
    
    setTimeout(() => {
        notification.style.transform = 'translateX(120%)';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// ==========================================
// EVENT LISTENERS - INICIALIZACIÓN
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Sistema de Ventas Cargado - Moneda: Soles (S/)');
    
    const searchInput = document.getElementById('deviceSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            e.preventDefault();
            
            clearTimeout(searchTimeout);
            const value = this.value.trim();
            
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) {
                if (value) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }
            
            searchTimeout = setTimeout(() => searchDevices(), 500);
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                clearTimeout(searchTimeout);
                searchDevices();
                return false;
            }
        });
        
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                return false;
            }
        });
        
        searchInput.addEventListener('focus', function() {
            console.log('🔍 Buscador activado');
        });
        
        if (window.innerWidth > 768) {
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }
    } else {
        console.error('❌ No se encontró el input de búsqueda #deviceSearch');
    }
    
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearDeviceSearch();
            return false;
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('saleModal');
            if (modal && modal.classList.contains('show')) {
                closeSaleModal();
            }
        }
    });
    
    const modal = document.getElementById('saleModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSaleModal();
            }
        });
    }
    
    const
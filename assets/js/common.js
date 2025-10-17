/**
 * COMMON.JS - JavaScript Centralizado
 * Funciones y utilidades compartidas para todo el sistema
 * Versión: 2.0
 */

// ==========================================
// CONFIGURACIÓN GLOBAL
// ==========================================
const AppConfig = {
    currency: 'S/',
    locale: 'es-PE',
    dateFormat: 'dd/mm/yyyy',
    debounceDelay: 500,
    notificationDuration: 4000,
    apiTimeout: 30000
};

// ==========================================
// SISTEMA DE NOTIFICACIONES TOAST
// ==========================================
class ToastNotification {
    static show(message, type = 'info', duration = AppConfig.notificationDuration) {
        const toast = this.create(message, type);
        document.body.appendChild(toast);
        
        // Animación de entrada
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });
        
        // Auto-cerrar
        setTimeout(() => this.hide(toast), duration);
        
        // Cerrar al hacer clic
        toast.addEventListener('click', () => this.hide(toast));
        
        return toast;
    }
    
    static create(message, type) {
        const colors = {
            success: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            danger: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
            warning: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            info: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
        };
        
        const icons = {
            success: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>`,
            danger: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>`,
            warning: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>`,
            info: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>`
        };
        
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            background: ${colors[type] || colors.info};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 400px;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.3s ease;
            cursor: pointer;
        `;
        
        toast.innerHTML = `
            ${icons[type] || icons.info}
            <span style="flex: 1;">${message}</span>
        `;
        
        return toast;
    }
    
    static hide(toast) {
        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }
}

// Alias para compatibilidad
window.showNotification = (message, type, duration) => ToastNotification.show(message, type, duration);

// ==========================================
// SISTEMA DE MODALES
// ==========================================
class Modal {
    constructor(id) {
        this.id = id;
        this.element = document.getElementById(id);
    }
    
    open() {
        if (this.element) {
            this.element.classList.add('show');
            document.body.style.overflow = 'hidden';
            this.element.dispatchEvent(new Event('modal:opened'));
        }
    }
    
    close() {
        if (this.element) {
            this.element.classList.remove('show');
            document.body.style.overflow = 'auto';
            this.element.dispatchEvent(new Event('modal:closed'));
        }
    }
    
    toggle() {
        if (this.element.classList.contains('show')) {
            this.close();
        } else {
            this.open();
        }
    }
    
    static closeAll() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
        });
        document.body.style.overflow = 'auto';
    }
}

// Funciones globales para modales
window.openModal = (id) => new Modal(id).open();
window.closeModal = (id) => new Modal(id).close();
window.toggleModal = (id) => new Modal(id).toggle();

// ==========================================
// LOADING OVERLAY
// ==========================================
class LoadingOverlay {
    static spinners = new Set();
    
    static show(id = 'loadingSpinner') {
        const spinner = document.getElementById(id);
        if (spinner) {
            spinner.classList.remove('hidden');
            this.spinners.add(id);
        }
    }
    
    static hide(id = 'loadingSpinner') {
        const spinner = document.getElementById(id);
        if (spinner) {
            spinner.classList.add('hidden');
            this.spinners.delete(id);
        }
    }
    
    static hideAll() {
        this.spinners.forEach(id => this.hide(id));
    }
    
    static isVisible(id = 'loadingSpinner') {
        return this.spinners.has(id);
    }
}

window.showLoading = (id) => LoadingOverlay.show(id);
window.hideLoading = (id) => LoadingOverlay.hide(id);

// ==========================================
// VALIDACIÓN DE FORMULARIOS
// ==========================================
class FormValidator {
    static validate(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        let isValid = true;
        const inputs = form.querySelectorAll('[required]');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    static validateField(input) {
        const value = input.value.trim();
        const type = input.type;
        let isValid = true;
        
        // Validación de campo requerido
        if (input.hasAttribute('required') && !value) {
            isValid = false;
        }
        
        // Validación por tipo
        if (value) {
            switch (type) {
                case 'email':
                    isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                    break;
                case 'tel':
                    isValid = /^\d{9,}$/.test(value.replace(/\s/g, ''));
                    break;
                case 'number':
                    const min = input.getAttribute('min');
                    const max = input.getAttribute('max');
                    const num = parseFloat(value);
                    if (min !== null && num < parseFloat(min)) isValid = false;
                    if (max !== null && num > parseFloat(max)) isValid = false;
                    break;
                case 'url':
                    isValid = /^https?:\/\/.+/.test(value);
                    break;
            }
        }
        
        // Validación de patrón personalizado
        const pattern = input.getAttribute('pattern');
        if (pattern && value) {
            isValid = new RegExp(pattern).test(value);
        }
        
        // Aplicar clases de validación
        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
        }
        
        return isValid;
    }
    
    static clearValidation(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        form.querySelectorAll('.is-valid, .is-invalid').forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });
    }
}

window.validateForm = (id) => FormValidator.validate(id);
window.clearFormValidation = (id) => FormValidator.clearValidation(id);

// ==========================================
// AJAX / FETCH HELPERS
// ==========================================
class API {
    static async request(url, options = {}) {
        try {
            showLoading();
            
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                ...options
            };
            
            const response = await fetch(url, defaultOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            hideLoading();
            
            return data;
            
        } catch (error) {
            hideLoading();
            console.error('API Error:', error);
            showNotification('Error de conexión: ' + error.message, 'danger');
            throw error;
        }
    }
    
    static async get(url) {
        return this.request(url, { method: 'GET' });
    }
    
    static async post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    static async postForm(url, formData) {
        return this.request(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
    }
    
    static async put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    static async delete(url) {
        return this.request(url, { method: 'DELETE' });
    }
}

window.API = API;

// ==========================================
// UTILIDADES DE FORMATO
// ==========================================
class Formatter {
    static price(value) {
        return `${AppConfig.currency} ${parseFloat(value).toFixed(2)}`;
    }
    
    static number(value) {
        return parseInt(value).toLocaleString(AppConfig.locale);
    }
    
    static decimal(value, decimals = 2) {
        return parseFloat(value).toLocaleString(AppConfig.locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    static percentage(value) {
        return parseFloat(value).toFixed(1) + '%';
    }
    
    static date(dateString, format = 'short') {
        const date = new Date(dateString);
        
        const formats = {
            short: { year: 'numeric', month: '2-digit', day: '2-digit' },
            long: { year: 'numeric', month: 'long', day: 'numeric' },
            full: { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }
        };
        
        return date.toLocaleDateString(AppConfig.locale, formats[format] || formats.short);
    }
    
    static time(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString(AppConfig.locale, {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    static dateTime(dateString) {
        return this.date(dateString, 'long') + ' ' + this.time(dateString);
    }
    
    static timeAgo(dateString) {
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);
        
        const intervals = {
            año: 31536000,
            mes: 2592000,
            semana: 604800,
            día: 86400,
            hora: 3600,
            minuto: 60
        };
        
        for (const [name, secondsInInterval] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInInterval);
            if (interval >= 1) {
                return `Hace ${interval} ${name}${interval > 1 ? 's' : ''}`;
            }
        }
        
        return 'Hace un momento';
    }
    
    static phone(phone) {
        // Formato: +51 999 999 999
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length === 9) {
            return cleaned.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
        }
        return phone;
    }
    
    static truncate(text, maxLength = 100) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
}

window.formatPrice = (v) => Formatter.price(v);
window.formatNumber = (v) => Formatter.number(v);
window.formatDate = (v, f) => Formatter.date(v, f);
window.formatDateTime = (v) => Formatter.dateTime(v);
window.timeAgo = (v) => Formatter.timeAgo(v);

// ==========================================
// UTILIDADES DE TEXTO
// ==========================================
class TextUtils {
    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    static capitalize(text) {
        return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
    }
    
    static titleCase(text) {
        return text.toLowerCase().split(' ').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }
    
    static slugify(text) {
        return text
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
    
    static copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text)
                .then(() => showNotification('✅ Copiado al portapapeles', 'success', 2000))
                .catch(() => showNotification('❌ Error al copiar', 'danger', 2000));
        } else {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showNotification('✅ Copiado al portapapeles', 'success', 2000);
            } catch (err) {
                showNotification('❌ Error al copiar', 'danger', 2000);
            }
            document.body.removeChild(textarea);
        }
    }
}

window.escapeHtml = (t) => TextUtils.escapeHtml(t);
window.copyToClipboard = (t) => TextUtils.copyToClipboard(t);

// ==========================================
// UTILIDADES DE BÚSQUEDA Y FILTRADO
// ==========================================
class SearchUtils {
    static debounce(func, delay = AppConfig.debounceDelay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }
    
    static filterTable(tableId, searchValue) {
        const table = document.getElementById(tableId);
        if (!table) return 0;
        
        const rows = table.querySelectorAll('tbody tr');
        const searchLower = searchValue.toLowerCase();
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchLower) || !searchValue) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        return visibleCount;
    }
    
    static filterItems(containerId, searchValue) {
        const container = document.getElementById(containerId);
        if (!container) return 0;
        
        const items = container.children;
        const searchLower = searchValue.toLowerCase();
        let visibleCount = 0;
        
        Array.from(items).forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchLower) || !searchValue) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        return visibleCount;
    }
    
    static highlightText(text, search) {
        if (!search) return text;
        
        const regex = new RegExp(`(${search})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
}

window.debounce = (f, d) => SearchUtils.debounce(f, d);
window.searchTable = (t, s) => SearchUtils.filterTable(t, s);

// ==========================================
// UTILIDADES DE ANIMACIÓN
// ==========================================
class AnimationUtils {
    static animateValue(element, start, end, duration = 1000, options = {}) {
        const { prefix = '', suffix = '', decimals = 0 } = options;
        const startTime = performance.now();
        
        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing: easeOutQuart
            const eased = 1 - Math.pow(1 - progress, 4);
            const current = start + (end - start) * eased;
            
            const formatted = decimals > 0 
                ? current.toFixed(decimals)
                : Math.floor(current).toLocaleString(AppConfig.locale);
            
            element.textContent = prefix + formatted + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        };
        
        requestAnimationFrame(update);
    }
    
    static fadeIn(element, duration = 300) {
        element.style.opacity = '0';
        element.style.display = 'block';
        
        let start = null;
        const animate = (timestamp) => {
            if (!start) start = timestamp;
            const progress = (timestamp - start) / duration;
            
            element.style.opacity = Math.min(progress, 1);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }
    
    static fadeOut(element, duration = 300) {
        let start = null;
        const animate = (timestamp) => {
            if (!start) start = timestamp;
            const progress = (timestamp - start) / duration;
            
            element.style.opacity = 1 - Math.min(progress, 1);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                element.style.display = 'none';
            }
        };
        
        requestAnimationFrame(animate);
    }
    
    static smoothScrollTo(elementOrId) {
        const element = typeof elementOrId === 'string' 
            ? document.getElementById(elementOrId)
            : elementOrId;
        
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

window.animateValue = (e, s, end, d, o) => AnimationUtils.animateValue(e, s, end, d, o);
window.smoothScrollTo = (e) => AnimationUtils.smoothScrollTo(e);

// ==========================================
// UTILIDADES DE EXPORTACIÓN
// ==========================================
class ExportUtils {
    static tableToCSV(tableId, filename = 'export.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = Array.from(cols).map(col => {
                let text = col.textContent.trim();
                text = text.replace(/"/g, '""');
                return `"${text}"`;
            });
            csv.push(rowData.join(','));
        });
        
        this.downloadFile(csv.join('\n'), filename, 'text/csv');
    }
    
    static dataToJSON(data, filename = 'export.json') {
        const json = JSON.stringify(data, null, 2);
        this.downloadFile(json, filename, 'application/json');
    }
    
    static downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        URL.revokeObjectURL(url);
    }
    
    static printElement(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const printWindow = window.open('', '', 'height=600,width=800');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Imprimir</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    @media print {
                        .no-print { display: none !important; }
                        @page { margin: 1cm; }
                    }
                </style>
            </head>
            <body>
                ${element.innerHTML}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
        
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
}

window.exportTableToCSV = (t, f) => ExportUtils.tableToCSV(t, f);
window.printElement = (e) => ExportUtils.printElement(e);

// ==========================================
// UTILIDADES DE CONFIRMACIÓN
// ==========================================
class ConfirmDialog {
    static show(message, onConfirm, options = {}) {
        const {
            title = '¿Estás seguro?',
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            type = 'warning'
        } = options;
        
        if (confirm(message)) {
            onConfirm();
        }
    }
}

window.confirmAction = (m, c, o) => ConfirmDialog.show(m, c, o);

// ==========================================
// UTILIDADES DE ELEMENTOS DOM
// ==========================================
class DOMUtils {
    static show(elementId) {
        const el = document.getElementById(elementId);
        if (el) el.classList.remove('hidden');
    }
    
    static hide(elementId) {
        const el = document.getElementById(elementId);
        if (el) el.classList.add('hidden');
    }
    
    static toggle(elementId) {
        const el = document.getElementById(elementId);
        if (el) el.classList.toggle('hidden');
    }
    
    static enable(elementId) {
        const el = document.getElementById(elementId);
        if (el) el.disabled = false;
    }
    
    static disable(elementId) {
        const el = document.getElementById(elementId);
        if (el) el.disabled = true;
    }
    
    static setValue(elementId, value) {
        const el = document.getElementById(elementId);
        if (el) el.value = value;
    }
    
    static getValue(elementId) {
        const el = document.getElementById(elementId);
        return el ? el.value : null;
    }
}

window.showElement = (e) => DOMUtils.show(e);
window.hideElement = (e) => DOMUtils.hide(e);
window.toggleElement = (e) => DOMUtils.toggle(e);

// ==========================================
// DETECTOR DE CAMBIOS EN FORMULARIOS
// ==========================================
class FormChangeDetector {
    static forms = new Map();
    
    static track(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        let hasChanges = false;
        
        form.addEventListener('input', () => {
            hasChanges = true;
            this.forms.set(formId, true);
        });
        
        form.addEventListener('submit', () => {
            hasChanges = false;
            this.forms.set(formId, false);
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '¿Seguro que deseas salir? Hay cambios sin guardar.';
                return e.returnValue;
            }
        });
    }
    
    static hasChanges(formId) {
        return this.forms.get(formId) || false;
    }
    
    static clearChanges(formId) {
        this.forms.set(formId, false);
    }
}

window.trackFormChanges = (f) => FormChangeDetector.track(f);

// ==========================================
// INICIALIZACIÓN GLOBAL
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Common.js cargado - Sistema de componentes JavaScript');
    
    // Auto-cerrar modales al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            const backdrop = e.target.getAttribute('data-backdrop');
            if (backdrop !== 'static') {
                closeModal(e.target.id);
            }
        }
    });
    
    // Cerrar modales con ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            Modal.closeAll();
        }
    });
    
    // Auto-validación en tiempo real
    document.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
        input.addEventListener('blur', () => {
            FormValidator.validateField(input);
        });
        
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) {
                FormValidator.validateField(input);
            }
        });
    });
    
    // Agregar tooltips automáticamente
    document.querySelectorAll('[title]').forEach(el => {
        el.setAttribute('data-tooltip', el.getAttribute('title'));
        el.removeAttribute('title');
    });
    
    console.log('💡 Componentes disponibles: Modal, API, Formatter, ToastNotification');
});

// Exportar para uso global
window.App = {
    Modal,
    API,
    Formatter,
    TextUtils,
    SearchUtils,
    AnimationUtils,
    ExportUtils,
    FormValidator,
    LoadingOverlay,
    ToastNotification,
    Config: AppConfig
};

console.log('📦 Common.js v2.0 - Sistema completo de utilidades cargado');
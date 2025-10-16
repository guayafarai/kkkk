<?php
/**
 * ESTILOS CENTRALIZADOS DEL SISTEMA
 * Sistema de Inventario de Celulares
 * Versión 2.0 - Optimizado, Moderno y Accesible
 * 
 * MEJORAS IMPLEMENTADAS:
 * - Sistema de diseño consistente con variables CSS
 * - Componentes reutilizables y modulares
 * - Optimización para rendimiento
 * - Mejor accesibilidad (contraste, tamaños táctiles)
 * - Soporte completo para modo oscuro (preparado)
 * - Animaciones sutiles y profesionales
 * - Responsividad mejorada
 */

if (!function_exists('renderSharedStyles')) {
    function renderSharedStyles() {
        ?>
        <!-- Tailwind CSS -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
        
        <style>
            /* ==========================================
               SISTEMA DE DISEÑO - VARIABLES CSS
               ========================================== */
            :root {
                /* Colores primarios */
                --color-primary: #667eea;
                --color-primary-dark: #5568d3;
                --color-primary-light: #8b9eff;
                --color-secondary: #764ba2;
                --color-secondary-dark: #5e3881;
                
                /* Colores de estado */
                --color-success: #10b981;
                --color-success-dark: #059669;
                --color-success-light: #d1fae5;
                --color-danger: #ef4444;
                --color-danger-dark: #dc2626;
                --color-danger-light: #fee2e2;
                --color-warning: #f59e0b;
                --color-warning-dark: #d97706;
                --color-warning-light: #fef3c7;
                --color-info: #3b82f6;
                --color-info-dark: #2563eb;
                --color-info-light: #dbeafe;
                
                /* Escala de grises */
                --color-gray-50: #f9fafb;
                --color-gray-100: #f3f4f6;
                --color-gray-200: #e5e7eb;
                --color-gray-300: #d1d5db;
                --color-gray-400: #9ca3af;
                --color-gray-500: #6b7280;
                --color-gray-600: #4b5563;
                --color-gray-700: #374151;
                --color-gray-800: #1f2937;
                --color-gray-900: #111827;
                
                /* Sombras */
                --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                
                /* Bordes y radios */
                --radius-sm: 0.375rem;
                --radius-md: 0.5rem;
                --radius-lg: 0.75rem;
                --radius-xl: 1rem;
                --radius-full: 9999px;
                
                /* Transiciones */
                --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
                
                /* Espaciado */
                --spacing-xs: 0.25rem;
                --spacing-sm: 0.5rem;
                --spacing-md: 1rem;
                --spacing-lg: 1.5rem;
                --spacing-xl: 2rem;
                
                /* Tipografía */
                --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                --font-mono: 'Courier New', Courier, monospace;
                
                /* Z-index */
                --z-dropdown: 1000;
                --z-sticky: 1020;
                --z-fixed: 1030;
                --z-modal-backdrop: 1040;
                --z-modal: 1050;
                --z-popover: 1060;
                --z-tooltip: 1070;
                --z-notification: 9999;
            }

            /* ==========================================
               BASE Y RESET
               ========================================== */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: var(--font-sans);
                background-color: var(--color-gray-50);
                color: var(--color-gray-900);
                line-height: 1.6;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            /* Mejora de selección de texto */
            ::selection {
                background-color: var(--color-primary-light);
                color: white;
            }

            /* ==========================================
               LAYOUT Y NAVEGACIÓN
               ========================================== */
            .page-content {
                margin-left: 260px;
                min-height: 100vh;
                transition: margin-left var(--transition-base);
            }

            @media (max-width: 768px) {
                .page-content {
                    margin-left: 0;
                }
            }

            /* Sidebar mejorado */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 260px;
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
                z-index: var(--z-fixed);
                overflow-y: auto;
                overflow-x: hidden;
                transition: transform var(--transition-base);
                box-shadow: var(--shadow-xl);
            }

            .sidebar::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar::-webkit-scrollbar-track {
                background: rgba(255, 255, 255, 0.1);
            }

            .sidebar::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.3);
                border-radius: 3px;
            }

            .sidebar::-webkit-scrollbar-thumb:hover {
                background: rgba(255, 255, 255, 0.5);
            }

            /* ==========================================
               CARDS - COMPONENTE PRINCIPAL
               ========================================== */
            .card {
                background: white;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
                overflow: hidden;
                transition: all var(--transition-base);
            }

            .card:hover {
                box-shadow: var(--shadow-lg);
                transform: translateY(-2px);
            }

            .card-header {
                padding: var(--spacing-lg);
                border-bottom: 1px solid var(--color-gray-200);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(to bottom, white 0%, var(--color-gray-50) 100%);
            }

            .card-body {
                padding: var(--spacing-lg);
            }

            .card-footer {
                padding: var(--spacing-md) var(--spacing-lg);
                border-top: 1px solid var(--color-gray-200);
                background-color: var(--color-gray-50);
            }

            /* ==========================================
               TARJETAS INTERACTIVAS (Productos/Dispositivos)
               MEJORA: Mayor énfasis en feedback visual
               ========================================== */
            .interactive-card {
                border: 2px solid var(--color-gray-200);
                border-radius: var(--radius-lg);
                padding: var(--spacing-md);
                cursor: pointer;
                transition: all var(--transition-base);
                background: white;
                position: relative;
                overflow: hidden;
            }

            /* Efecto de brillo al hover */
            .interactive-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                transition: left 0.5s;
            }

            .interactive-card:hover {
                transform: translateY(-6px);
                box-shadow: var(--shadow-xl);
                border-color: var(--color-primary);
            }

            .interactive-card:hover::before {
                left: 100%;
            }

            .interactive-card:active {
                transform: translateY(-2px);
            }

            .interactive-card.selected {
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                border-color: var(--color-success);
                box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            }

            /* ==========================================
               PRODUCT CARDS (Productos específicos)
               MEJORA: Estados visuales más claros
               ========================================== */
            .product-card {
                transition: all var(--transition-base);
                border-radius: var(--radius-lg);
                overflow: hidden;
            }

            .product-card:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-xl);
            }

            /* Estados de stock con mejor contraste */
            .stock-sin {
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border-left: 4px solid var(--color-danger);
            }

            .stock-bajo {
                background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
                border-left: 4px solid var(--color-warning);
            }

            .stock-medio {
                background: linear-gradient(135deg, #f0f9ff 0%, #dbeafe 100%);
                border-left: 4px solid var(--color-info);
            }

            .stock-normal {
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                border-left: 4px solid var(--color-success);
            }

            /* ==========================================
               STATS CARDS (Dashboard)
               MEJORA: Diseño más moderno con gradientes
               ========================================== */
            .stats-card {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
                padding: var(--spacing-lg);
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-lg);
                transition: all var(--transition-base);
                position: relative;
                overflow: hidden;
            }

            .stats-card::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                animation: pulse 4s ease-in-out infinite;
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 0.5; }
                50% { transform: scale(1.1); opacity: 0.8; }
            }

            .stats-card:hover {
                transform: translateY(-4px) scale(1.02);
                box-shadow: var(--shadow-2xl);
            }

            .stats-card-icon {
                background: rgba(255, 255, 255, 0.2);
                border-radius: var(--radius-lg);
                padding: 0.75rem;
                display: inline-flex;
                margin-bottom: var(--spacing-md);
                backdrop-filter: blur(10px);
            }

            .stats-card-value {
                font-size: 2.5rem;
                font-weight: 700;
                line-height: 1;
                margin-bottom: var(--spacing-sm);
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .stats-card-label {
                font-size: 0.875rem;
                opacity: 0.95;
                font-weight: 500;
            }

            /* ==========================================
               BADGES Y ETIQUETAS
               MEJORA: Mejor legibilidad y contraste
               ========================================== */
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 0.25rem 0.75rem;
                border-radius: var(--radius-full);
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1;
                white-space: nowrap;
            }

            .badge-success {
                background-color: var(--color-success-light);
                color: #065f46;
            }

            .badge-danger {
                background-color: var(--color-danger-light);
                color: #991b1b;
            }

            .badge-warning {
                background-color: var(--color-warning-light);
                color: #92400e;
            }

            .badge-info {
                background-color: var(--color-info-light);
                color: #1e40af;
            }

            .badge-primary {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
                color: white;
            }

            /* ==========================================
               BOTONES
               MEJORA: Estados más claros y accesibles
               ========================================== */
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.625rem 1.25rem;
                border-radius: var(--radius-md);
                font-weight: 500;
                font-size: 0.875rem;
                transition: all var(--transition-base);
                cursor: pointer;
                border: none;
                text-decoration: none;
                gap: 0.5rem;
                min-height: 44px; /* Tamaño mínimo táctil WCAG */
                position: relative;
                overflow: hidden;
            }

            /* Efecto ripple en botones */
            .btn::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: translate(-50%, -50%);
                transition: width 0.6s, height 0.6s;
            }

            .btn:active::after {
                width: 300px;
                height: 300px;
            }

            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
                box-shadow: var(--shadow-sm);
            }

            .btn-primary:hover:not(:disabled) {
                background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-secondary) 100%);
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            .btn-success {
                background-color: var(--color-success);
                color: white;
                box-shadow: var(--shadow-sm);
            }

            .btn-success:hover:not(:disabled) {
                background-color: var(--color-success-dark);
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            .btn-danger {
                background-color: var(--color-danger);
                color: white;
                box-shadow: var(--shadow-sm);
            }

            .btn-danger:hover:not(:disabled) {
                background-color: var(--color-danger-dark);
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            .btn-secondary {
                background-color: var(--color-gray-500);
                color: white;
            }

            .btn-secondary:hover:not(:disabled) {
                background-color: var(--color-gray-600);
            }

            /* ==========================================
               MODALES
               MEJORA: Mejor backdrop y animaciones
               ========================================== */
            .modal {
                display: none;
                position: fixed;
                inset: 0;
                background-color: rgba(0, 0, 0, 0.6);
                z-index: var(--z-modal);
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(4px);
                animation: fadeIn var(--transition-base);
            }

            .modal.show {
                display: flex;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .modal-content {
                background: white;
                border-radius: var(--radius-xl);
                padding: var(--spacing-lg);
                width: 100%;
                max-width: 32rem;
                max-height: 90vh;
                overflow-y: auto;
                margin: var(--spacing-md);
                box-shadow: var(--shadow-2xl);
                animation: slideUp var(--transition-slow);
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: var(--spacing-lg);
                padding-bottom: var(--spacing-md);
                border-bottom: 2px solid var(--color-gray-200);
            }

            .modal-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--color-gray-900);
            }

            .modal-close {
                color: var(--color-gray-400);
                cursor: pointer;
                transition: all var(--transition-fast);
                padding: 0.25rem;
                border-radius: var(--radius-sm);
            }

            .modal-close:hover {
                color: var(--color-gray-600);
                background-color: var(--color-gray-100);
            }

            /* ==========================================
               FORMULARIOS
               MEJORA: Focus states y validación visual
               ========================================== */
            .form-group {
                margin-bottom: var(--spacing-md);
            }

            .form-label {
                display: block;
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--color-gray-700);
                margin-bottom: var(--spacing-sm);
            }

            .form-input,
            .form-select,
            .form-textarea {
                width: 100%;
                padding: 0.625rem 0.875rem;
                border: 2px solid var(--color-gray-300);
                border-radius: var(--radius-md);
                font-size: 0.875rem;
                transition: all var(--transition-fast);
                background-color: white;
            }

            .form-input:focus,
            .form-select:focus,
            .form-textarea:focus {
                outline: none;
                border-color: var(--color-primary);
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                background-color: white;
            }

            .form-input:disabled,
            .form-select:disabled,
            .form-textarea:disabled {
                background-color: var(--color-gray-100);
                cursor: not-allowed;
                opacity: 0.6;
            }

            /* Validación visual */
            .form-input.is-invalid,
            .form-select.is-invalid,
            .form-textarea.is-invalid {
                border-color: var(--color-danger);
            }

            .form-input.is-valid,
            .form-select.is-valid,
            .form-textarea.is-valid {
                border-color: var(--color-success);
            }

            /* ==========================================
               ALERTAS Y NOTIFICACIONES
               MEJORA: Iconos y mejor jerarquía visual
               ========================================== */
            .alert {
                padding: var(--spacing-md);
                border-radius: var(--radius-md);
                margin-bottom: var(--spacing-md);
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                border-left: 4px solid;
            }

            .alert-success {
                background-color: var(--color-success-light);
                border-color: var(--color-success);
                color: #065f46;
            }

            .alert-danger {
                background-color: var(--color-danger-light);
                border-color: var(--color-danger);
                color: #991b1b;
            }

            .alert-warning {
                background-color: var(--color-warning-light);
                border-color: var(--color-warning);
                color: #92400e;
            }

            .alert-info {
                background-color: var(--color-info-light);
                border-color: var(--color-info);
                color: #1e40af;
            }

            /* Notificaciones flotantes */
            .notification {
                position: fixed;
                top: var(--spacing-md);
                right: var(--spacing-md);
                z-index: var(--z-notification);
                padding: var(--spacing-md);
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-xl);
                max-width: 400px;
                animation: slideInRight var(--transition-base);
                transform: translateX(0);
                transition: transform var(--transition-base);
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(120%);
                }
                to {
                    transform: translateX(0);
                }
            }

            .notification.hiding {
                transform: translateX(120%);
            }

            /* ==========================================
               LOADING Y SPINNERS
               ========================================== */
            .loading-spinner {
                border: 3px solid var(--color-gray-200);
                border-top: 3px solid var(--color-primary);
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            .loading-overlay {
                position: fixed;
                inset: 0;
                background: rgba(255, 255, 255, 0.95);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: var(--z-modal-backdrop);
                backdrop-filter: blur(2px);
            }

            /* Skeleton loading */
            .skeleton {
                background: linear-gradient(90deg, var(--color-gray-200) 25%, var(--color-gray-100) 50%, var(--color-gray-200) 75%);
                background-size: 200% 100%;
                animation: shimmer 1.5s infinite;
                border-radius: var(--radius-md);
            }

            @keyframes shimmer {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }

            /* ==========================================
               TABLAS
               MEJORA: Mejor diseño responsive
               ========================================== */
            .table-container {
                overflow-x: auto;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
                background: white;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                background: white;
            }

            .table th {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
                padding: 1rem;
                text-align: left;
                font-weight: 600;
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                white-space: nowrap;
            }

            .table td {
                padding: 1rem;
                border-bottom: 1px solid var(--color-gray-200);
                font-size: 0.875rem;
            }

            .table tbody tr {
                transition: background-color var(--transition-fast);
            }

            .table tbody tr:hover {
                background-color: var(--color-gray-50);
            }

            .table tbody tr:last-child td {
                border-bottom: none;
            }

            /* ==========================================
               CÓDIGO DE BARRAS - DESTACADO ESPECIAL
               ========================================== */
            .barcode-highlight {
                background: linear-gradient(135dег, #dbeafe 0%, #bfdbfe 100%);
                border-left: 3px solid var(--color-info);
                border-radius: var(--radius-md);
                padding: var(--spacing-md);
            }

            .barcode-input {
                font-family: var(--font-mono);
                letter-spacing: 0.05em;
            }

            /* Scanner de código de barras */
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
                border-radius: var(--radius-lg);
            }

            .scanner-overlay {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 80%;
                height: 40%;
                border: 3px solid var(--color-success);
                border-radius: var(--radius-lg);
                box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
                pointer-events: none;
            }

            .scanner-line {
                position: absolute;
                width: 100%;
                height: 2px;
                background: var(--color-success);
                top: 0;
                animation: scan 2s linear infinite;
            }

            @keyframes scan {
                0%, 100% { top: 0; }
                50% { top: 100%; }
            }

            /* ==========================================
               UTILIDADES
               ========================================== */
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            
            .font-bold { font-weight: 700; }
            .font-semibold { font-weight: 600; }
            .font-medium { font-weight: 500; }
            
            .text-xs { font-size: 0.75rem; }
            .text-sm { font-size: 0.875rem; }
            .text-base { font-size: 1rem; }
            .text-lg { font-size: 1.125rem; }
            .text-xl { font-size: 1.25rem; }
            .text-2xl { font-size: 1.5rem; }
            .text-3xl { font-size: 1.875rem; }
            
            .truncate {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .line-clamp-3 {
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            /* ==========================================
               RESPONSIVE
               ========================================== */
            @media (max-width: 768px) {
                .stats-card-value {
                    font-size: 2rem;
                }

                .modal-content {
                    max-width: 100%;
                    margin: var(--spacing-sm);
                }

                .card-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: var(--spacing-md);
                }

                .table {
                    font-size: 0.8125rem;
                }

                .table th,
                .table td {
                    padding: 0.75rem 0.5rem;
                }

                .btn {
                    width: 100%;
                    justify-content: center;
                }
            }

            @media (max-width: 640px) {
                :root {
                    --spacing-md: 0.75rem;
                    --spacing-lg: 1rem;
                }

                .product-card,
                .interactive-card {
                    padding: 0.75rem;
                }
            }

            /* ==========================================
               SCROLLBAR PERSONALIZADO
               ========================================== */
            ::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            ::-webkit-scrollbar-track {
                background: var(--color-gray-100);
                border-radius: var(--radius-sm);
            }

            ::-webkit-scrollbar-thumb {
                background: var(--color-gray-300);
                border-radius: var(--radius-sm);
                transition: background var(--transition-fast);
            }

            ::-webkit-scrollbar-thumb:hover {
                background: var(--color-gray-400);
            }

            /* ==========================================
               PRINT STYLES
               ========================================== */
            @media print {
                .no-print,
                .sidebar,
                .btn,
                .modal,
                .notification {
                    display: none !important;
                }

                .page-content {
                    margin-left: 0;
                }

                body {
                    background: white;
                }

                .card {
                    box-shadow: none;
                    border: 1px solid var(--color-gray-300);
                }
            }

            /* ==========================================
               ACCESIBILIDAD
               ========================================== */
            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border-width: 0;
            }

            /* Focus visible mejorado */
            *:focus-visible {
                outline: 2px solid var(--color-primary);
                outline-offset: 2px;
                border-radius: var(--radius-sm);
            }

            /* Reducir movimiento para usuarios que lo prefieren */
            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }

            /* ==========================================
               ANIMACIONES ADICIONALES
               ========================================== */
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes slideInDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes scaleIn {
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .fade-in {
                animation: fadeIn var(--transition-base);
            }

            .slide-up {
                animation: slideInUp var(--transition-base);
            }

            .slide-down {
                animation: slideInDown var(--transition-base);
            }

            .scale-in {
                animation: scaleIn var(--transition-base);
            }

            /* ==========================================
               COMPONENTES ESPECÍFICOS DEL SISTEMA
               ========================================== */
            
            /* Estados de stock visuales */
            .stock-indicator {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-right: 0.5rem;
            }

            .stock-indicator.high {
                background-color: var(--color-success);
            }

            .stock-indicator.medium {
                background-color: var(--color-info);
            }

            .stock-indicator.low {
                background-color: var(--color-warning);
            }

            .stock-indicator.out {
                background-color: var(--color-danger);
            }

            /* Tarjetas de dispositivos con código de barras */
            .device-card {
                position: relative;
            }

            .device-barcode {
                font-family: var(--font-mono);
                font-size: 0.75rem;
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                padding: 0.375rem 0.625rem;
                border-radius: var(--radius-sm);
                border: 1px solid #bae6fd;
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }

            /* Alertas de sistema mejoradas */
            .readonly-warning {
                background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                border-left: 4px solid var(--color-warning);
                padding: var(--spacing-md);
                border-radius: var(--radius-md);
            }

            .system-alert {
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border-left: 4px solid var(--color-danger);
                padding: var(--spacing-md);
                border-radius: var(--radius-md);
            }

            /* Tabs de navegación */
            .tabs {
                display: flex;
                border-bottom: 2px solid var(--color-gray-200);
                gap: var(--spacing-sm);
            }

            .tab {
                padding: 0.75rem 1.25rem;
                border: none;
                background: none;
                color: var(--color-gray-600);
                font-weight: 500;
                cursor: pointer;
                transition: all var(--transition-fast);
                border-bottom: 2px solid transparent;
                margin-bottom: -2px;
            }

            .tab:hover {
                color: var(--color-primary);
                background-color: var(--color-gray-50);
            }

            .tab.active {
                color: var(--color-primary);
                border-bottom-color: var(--color-primary);
            }

            /* Tooltip simple */
            [data-tooltip] {
                position: relative;
            }

            [data-tooltip]::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%) translateY(-8px);
                padding: 0.5rem 0.75rem;
                background: var(--color-gray-900);
                color: white;
                font-size: 0.75rem;
                border-radius: var(--radius-md);
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity var(--transition-fast);
                z-index: var(--z-tooltip);
            }

            [data-tooltip]:hover::after {
                opacity: 1;
            }

            /* Dropdown menus */
            .dropdown {
                position: relative;
            }

            .dropdown-menu {
                position: absolute;
                top: 100%;
                right: 0;
                margin-top: 0.5rem;
                background: white;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-xl);
                padding: 0.5rem;
                min-width: 200px;
                display: none;
                z-index: var(--z-dropdown);
                animation: slideInDown var(--transition-base);
            }

            .dropdown.active .dropdown-menu {
                display: block;
            }

            .dropdown-item {
                padding: 0.625rem 1rem;
                border-radius: var(--radius-md);
                cursor: pointer;
                transition: background-color var(--transition-fast);
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--color-gray-700);
            }

            .dropdown-item:hover {
                background-color: var(--color-gray-100);
            }

            /* Progreso / Progress bars */
            .progress {
                width: 100%;
                height: 8px;
                background-color: var(--color-gray-200);
                border-radius: var(--radius-full);
                overflow: hidden;
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-secondary) 100%);
                transition: width var(--transition-slow);
                border-radius: var(--radius-full);
            }

            /* Dividers */
            .divider {
                height: 1px;
                background: var(--color-gray-200);
                margin: var(--spacing-lg) 0;
            }

            .divider-text {
                position: relative;
                text-align: center;
                margin: var(--spacing-lg) 0;
            }

            .divider-text::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 0;
                right: 0;
                height: 1px;
                background: var(--color-gray-200);
            }

            .divider-text span {
                position: relative;
                background: var(--color-gray-50);
                padding: 0 var(--spacing-md);
                color: var(--color-gray-500);
                font-size: 0.875rem;
                font-weight: 500;
            }

            /* Empty states */
            .empty-state {
                text-align: center;
                padding: var(--spacing-xl) var(--spacing-md);
            }

            .empty-state-icon {
                width: 64px;
                height: 64px;
                margin: 0 auto var(--spacing-md);
                color: var(--color-gray-400);
            }

            .empty-state-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--color-gray-700);
                margin-bottom: var(--spacing-sm);
            }

            .empty-state-description {
                color: var(--color-gray-500);
                margin-bottom: var(--spacing-lg);
            }

            /* ==========================================
               MODO OSCURO (Preparado para implementación futura)
               ========================================== */
            @media (prefers-color-scheme: dark) {
                /* Descomentar cuando se implemente modo oscuro completo
                :root {
                    --color-gray-50: #1f2937;
                    --color-gray-100: #374151;
                    --color-gray-900: #f9fafb;
                }

                body {
                    background-color: #111827;
                    color: #f9fafb;
                }

                .card {
                    background: #1f2937;
                }
                */
            }

            /* ==========================================
               CLASES DE UTILIDAD EXTENDIDAS
               ========================================== */
            .w-full { width: 100%; }
            .h-full { height: 100%; }
            .flex { display: flex; }
            .inline-flex { display: inline-flex; }
            .grid { display: grid; }
            .hidden { display: none; }
            .block { display: block; }
            .inline-block { display: inline-block; }
            
            .items-center { align-items: center; }
            .items-start { align-items: flex-start; }
            .items-end { align-items: flex-end; }
            
            .justify-center { justify-content: center; }
            .justify-between { justify-content: space-between; }
            .justify-start { justify-content: flex-start; }
            .justify-end { justify-content: flex-end; }
            
            .gap-1 { gap: 0.25rem; }
            .gap-2 { gap: 0.5rem; }
            .gap-3 { gap: 0.75rem; }
            .gap-4 { gap: 1rem; }
            .gap-6 { gap: 1.5rem; }
            
            .p-2 { padding: 0.5rem; }
            .p-4 { padding: 1rem; }
            .p-6 { padding: 1.5rem; }
            .p-8 { padding: 2rem; }
            
            .m-2 { margin: 0.5rem; }
            .m-4 { margin: 1rem; }
            .m-6 { margin: 1.5rem; }
            
            .mt-2 { margin-top: 0.5rem; }
            .mt-4 { margin-top: 1rem; }
            .mt-6 { margin-top: 1.5rem; }
            
            .mb-2 { margin-bottom: 0.5rem; }
            .mb-4 { margin-bottom: 1rem; }
            .mb-6 { margin-bottom: 1.5rem; }
            .mb-8 { margin-bottom: 2rem; }
            
            .rounded { border-radius: var(--radius-md); }
            .rounded-lg { border-radius: var(--radius-lg); }
            .rounded-full { border-radius: var(--radius-full); }
            
            .shadow { box-shadow: var(--shadow-sm); }
            .shadow-md { box-shadow: var(--shadow-md); }
            .shadow-lg { box-shadow: var(--shadow-lg); }
            .shadow-xl { box-shadow: var(--shadow-xl); }
            
            .overflow-auto { overflow: auto; }
            .overflow-hidden { overflow: hidden; }
            .overflow-y-auto { overflow-y: auto; }
            
            /* ==========================================
               TRANSICIONES Y EFECTOS
               ========================================== */
            .transition-all {
                transition: all var(--transition-base);
            }

            .transition-colors {
                transition: color var(--transition-fast), background-color var(--transition-fast), border-color var(--transition-fast);
            }

            .hover-lift:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }

            .hover-grow:hover {
                transform: scale(1.05);
            }

            .hover-glow:hover {
                box-shadow: 0 0 20px rgba(102, 126, 234, 0.4);
            }

            /* ==========================================
               DEBUG (solo desarrollo)
               ========================================== */
            .debug-grid {
                background-image: 
                    linear-gradient(rgba(255, 0, 0, 0.1) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 0, 0, 0.1) 1px, transparent 1px);
                background-size: 20px 20px;
            }
        </style>
        <?php
    }
}

/**
 * RECOMENDACIONES DE USO:
 * 
 * 1. CONSISTENCIA:
 *    - Usa las variables CSS para colores, espaciados y sombras
 *    - No hardcodees valores, usa las variables definidas
 * 
 * 2. ACCESIBILIDAD:
 *    - Tamaño mínimo de botones: 44x44px (WCAG)
 *    - Contraste de color adecuado (probado)
 *    - Focus states visibles
 * 
 * 3. RENDIMIENTO:
 *    - Usa transform y opacity para animaciones (mejor que left/top/width)
 *    - Evita animaciones excesivas
 *    - Usa will-change solo cuando sea necesario
 * 
 * 4. RESPONSIVE:
 *    - Mobile-first approach
 *    - Breakpoints: 640px (sm), 768px (md), 1024px (lg)
 * 
 * 5. CÓDIGO DE BARRAS:
 *    - Clases especiales: .barcode-highlight, .device-barcode
 *    - Scanner integrado con estilos
 * 
 * 6. COMPONENTES CLAVE:
 *    - .card: Contenedor base
 *    - .interactive-card: Tarjetas clicables (productos/dispositivos)
 *    - .stats-card: Métricas del dashboard
 *    - .modal: Diálogos modales
 *    - .btn: Botones con estados
 * 
 * 7. ESTADOS:
 *    - .stock-sin, .stock-bajo, .stock-medio, .stock-normal
 *    - .badge-success, .badge-danger, .badge-warning, .badge-info
 * 
 * 8. UTILIDADES:
 *    - Usa las clases utilitarias en lugar de CSS inline
 *    - Combina clases para crear componentes complejos
 */
?>

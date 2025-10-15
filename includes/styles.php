<?php
/**
 * ESTILOS CENTRALIZADOS DEL SISTEMA
 * Archivo único de estilos compartidos para todas las páginas
 * Versión 1.0 - Optimizado y profesional
 */

if (!function_exists('renderSharedStyles')) {
    function renderSharedStyles() {
        ?>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
        <style>
            /* ==========================================
               VARIABLES CSS
               ========================================== */
            :root {
                --color-primary: #667eea;
                --color-primary-dark: #5568d3;
                --color-secondary: #764ba2;
                --color-success: #10b981;
                --color-danger: #ef4444;
                --color-warning: #f59e0b;
                --color-info: #3b82f6;
                --color-gray-50: #f9fafb;
                --color-gray-100: #f3f4f6;
                --color-gray-200: #e5e7eb;
                --color-gray-300: #d1d5db;
                --color-gray-600: #4b5563;
                --color-gray-700: #374151;
                --color-gray-900: #111827;
                --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                --radius-sm: 0.375rem;
                --radius-md: 0.5rem;
                --radius-lg: 0.75rem;
                --transition: all 0.2s ease;
            }

            /* ==========================================
               LAYOUT BASE
               ========================================== */
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background-color: var(--color-gray-50);
                color: var(--color-gray-900);
            }

            .page-content {
                margin-left: 260px;
                min-height: 100vh;
                transition: margin-left 0.3s ease;
            }

            @media (max-width: 768px) {
                .page-content {
                    margin-left: 0;
                }
            }

            /* ==========================================
               CARDS Y CONTENEDORES
               ========================================== */
            .card {
                background: white;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-md);
                overflow: hidden;
                transition: var(--transition);
            }

            .card:hover {
                box-shadow: var(--shadow-lg);
            }

            .card-header {
                padding: 1.5rem;
                border-bottom: 1px solid var(--color-gray-200);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .card-body {
                padding: 1.5rem;
            }

            .card-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid var(--color-gray-200);
                background-color: var(--color-gray-50);
            }

            /* ==========================================
               STATS CARDS (Dashboard)
               ========================================== */
            .stats-card {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
                padding: 1.5rem;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-lg);
                transition: var(--transition);
            }

            .stats-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-xl);
            }

            .stats-card-icon {
                background: rgba(255, 255, 255, 0.2);
                border-radius: var(--radius-lg);
                padding: 0.75rem;
                display: inline-flex;
                margin-bottom: 1rem;
            }

            .stats-card-value {
                font-size: 2rem;
                font-weight: 700;
                line-height: 1;
                margin-bottom: 0.5rem;
            }

            .stats-card-label {
                font-size: 0.875rem;
                opacity: 0.9;
            }

            /* ==========================================
               TARJETAS INTERACTIVAS (Productos/Dispositivos)
               ========================================== */
            .interactive-card {
                border: 2px solid var(--color-gray-200);
                border-radius: var(--radius-lg);
                padding: 1rem;
                cursor: pointer;
                transition: var(--transition);
                background: white;
            }

            .interactive-card:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-lg);
                border-color: var(--color-primary);
            }

            .interactive-card.selected {
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                border-color: var(--color-success);
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            }

            /* ==========================================
               BADGES Y ETIQUETAS
               ========================================== */
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 0.25rem 0.75rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1;
            }

            .badge-success {
                background-color: #d1fae5;
                color: #065f46;
            }

            .badge-danger {
                background-color: #fee2e2;
                color: #991b1b;
            }

            .badge-warning {
                background-color: #fef3c7;
                color: #92400e;
            }

            .badge-info {
                background-color: #dbeafe;
                color: #1e40af;
            }

            .badge-primary {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
                color: white;
            }

            /* ==========================================
               BOTONES
               ========================================== */
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem 1rem;
                border-radius: var(--radius-md);
                font-weight: 500;
                font-size: 0.875rem;
                transition: var(--transition);
                cursor: pointer;
                border: none;
                text-decoration: none;
                gap: 0.5rem;
            }

            .btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
            }

            .btn-primary:hover:not(:disabled) {
                background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-secondary) 100%);
                transform: translateY(-1px);
                box-shadow: var(--shadow-md);
            }

            .btn-success {
                background-color: var(--color-success);
                color: white;
            }

            .btn-success:hover:not(:disabled) {
                background-color: #059669;
            }

            .btn-danger {
                background-color: var(--color-danger);
                color: white;
            }

            .btn-danger:hover:not(:disabled) {
                background-color: #dc2626;
            }

            .btn-secondary {
                background-color: var(--color-gray-500);
                color: white;
            }

            .btn-secondary:hover:not(:disabled) {
                background-color: var(--color-gray-600);
            }

            /* ==========================================
               MODAL
               ========================================== */
            .modal {
                display: none;
                position: fixed;
                inset: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(4px);
            }

            .modal.show {
                display: flex;
            }

            .modal-content {
                background: white;
                border-radius: var(--radius-lg);
                padding: 1.5rem;
                width: 100%;
                max-width: 32rem;
                max-height: 90vh;
                overflow-y: auto;
                margin: 1rem;
                box-shadow: var(--shadow-xl);
                animation: modalSlideIn 0.3s ease-out;
            }

            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
            }

            .modal-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--color-gray-900);
            }

            .modal-close {
                color: var(--color-gray-400);
                cursor: pointer;
                transition: var(--transition);
            }

            .modal-close:hover {
                color: var(--color-gray-600);
            }

            /* ==========================================
               FORMULARIOS
               ========================================== */
            .form-group {
                margin-bottom: 1rem;
            }

            .form-label {
                display: block;
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--color-gray-700);
                margin-bottom: 0.5rem;
            }

            .form-input,
            .form-select,
            .form-textarea {
                width: 100%;
                padding: 0.5rem 0.75rem;
                border: 1px solid var(--color-gray-300);
                border-radius: var(--radius-md);
                font-size: 0.875rem;
                transition: var(--transition);
            }

            .form-input:focus,
            .form-select:focus,
            .form-textarea:focus {
                outline: none;
                border-color: var(--color-primary);
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .form-input-icon {
                position: relative;
            }

            .form-input-icon input {
                padding-left: 2.5rem;
            }

            .form-input-icon svg {
                position: absolute;
                left: 0.75rem;
                top: 50%;
                transform: translateY(-50%);
                color: var(--color-gray-400);
            }

            /* ==========================================
               ALERTAS Y NOTIFICACIONES
               ========================================== */
            .alert {
                padding: 1rem;
                border-radius: var(--radius-md);
                margin-bottom: 1rem;
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .alert-success {
                background-color: #d1fae5;
                border: 1px solid #a7f3d0;
                color: #065f46;
            }

            .alert-danger {
                background-color: #fee2e2;
                border: 1px solid #fecaca;
                color: #991b1b;
            }

            .alert-warning {
                background-color: #fef3c7;
                border: 1px solid #fde68a;
                color: #92400e;
            }

            .alert-info {
                background-color: #dbeafe;
                border: 1px solid #bfdbfe;
                color: #1e40af;
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
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9998;
            }

            /* ==========================================
               TABLAS
               ========================================== */
            .table-container {
                overflow-x: auto;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                background: white;
            }

            .table th {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
                padding: 0.75rem 1rem;
                text-align: left;
                font-weight: 600;
                font-size: 0.875rem;
            }

            .table td {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid var(--color-gray-200);
                font-size: 0.875rem;
            }

            .table tbody tr:hover {
                background-color: var(--color-gray-50);
            }

            /* ==========================================
               UTILIDADES
               ========================================== */
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-bold { font-weight: 700; }
            .font-semibold { font-weight: 600; }
            .font-medium { font-weight: 500; }
            
            .text-sm { font-size: 0.875rem; }
            .text-xs { font-size: 0.75rem; }
            .text-lg { font-size: 1.125rem; }
            .text-xl { font-size: 1.25rem; }
            .text-2xl { font-size: 1.5rem; }
            .text-3xl { font-size: 1.875rem; }
            
            .text-gray-500 { color: var(--color-gray-500); }
            .text-gray-600 { color: var(--color-gray-600); }
            .text-gray-700 { color: var(--color-gray-700); }
            .text-gray-900 { color: var(--color-gray-900); }
            
            .text-primary { color: var(--color-primary); }
            .text-success { color: var(--color-success); }
            .text-danger { color: var(--color-danger); }
            .text-warning { color: var(--color-warning); }
            
            .mb-1 { margin-bottom: 0.25rem; }
            .mb-2 { margin-bottom: 0.5rem; }
            .mb-3 { margin-bottom: 0.75rem; }
            .mb-4 { margin-bottom: 1rem; }
            .mb-6 { margin-bottom: 1.5rem; }
            .mb-8 { margin-bottom: 2rem; }
            
            .mt-1 { margin-top: 0.25rem; }
            .mt-2 { margin-top: 0.5rem; }
            .mt-4 { margin-top: 1rem; }
            .mt-6 { margin-top: 1.5rem; }
            
            .p-4 { padding: 1rem; }
            .p-6 { padding: 1.5rem; }
            .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
            .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
            .px-4 { padding-left: 1rem; padding-right: 1rem; }
            
            .flex { display: flex; }
            .flex-1 { flex: 1; }
            .items-center { align-items: center; }
            .justify-between { justify-content: space-between; }
            .gap-2 { gap: 0.5rem; }
            .gap-3 { gap: 0.75rem; }
            .gap-4 { gap: 1rem; }
            
            .grid { display: grid; }
            .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
            .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            
            .hidden { display: none; }
            .block { display: block; }
            .inline-block { display: inline-block; }
            
            .overflow-auto { overflow: auto; }
            .overflow-y-auto { overflow-y: auto; }
            
            .max-h-96 { max-height: 24rem; }
            
            /* ==========================================
               ANIMACIONES
               ========================================== */
            .fade-in {
                animation: fadeIn 0.3s ease-in;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .slide-up {
                animation: slideUp 0.3s ease-out;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* ==========================================
               RESPONSIVE
               ========================================== */
            @media (max-width: 768px) {
                .grid-cols-2,
                .grid-cols-3,
                .grid-cols-4 {
                    grid-template-columns: repeat(1, minmax(0, 1fr));
                }
                
                .card-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }
                
                .stats-card-value {
                    font-size: 1.5rem;
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
            }

            ::-webkit-scrollbar-thumb {
                background: var(--color-gray-300);
                border-radius: 4px;
            }

            ::-webkit-scrollbar-thumb:hover {
                background: var(--color-gray-400);
            }
        </style>
        <?php
    }
}
?>

<?php
/**
 * VENTAS DE CELULARES - ESTILO DASHBOARD MODERNO
 * Sistema de Inventario de Celulares
 * Versión 2.1 FINAL - Sin dependencias externas, CSS Vanilla
 * ✅ Sin recarga de página
 * ✅ Sin conflictos de scripts
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

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
            
            // Verificar disponibilidad
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
            
            // Actualizar estado
            $stmt = $db->prepare("UPDATE celulares SET estado = 'vendido', fecha_venta = NOW() WHERE id = ?");
            $stmt->execute([$celular_id]);
            
            if (function_exists('logActivity')) {
                logActivity($user['id'], 'register_sale', "Venta: {$celular['marca']} {$celular['modelo']}");
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

$is_admin = $user['rol'] === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vender Celulares - <?php echo SYSTEM_NAME; ?></title>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }
        
        /* Layout */
        .layout { display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
        }
        
        .sidebar.collapsed { width: 70px; }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo { display: flex; align-items: center; gap: 0.75rem; }
        .logo svg { width: 24px; height: 24px; }
        .logo-text { font-weight: 700; font-size: 1.125rem; }
        
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .nav-text { display: none; }
        
        .menu-toggle {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .menu-toggle:hover { background: rgba(255,255,255,0.2); }
        
        /* Navigation */
        .nav {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            margin-bottom: 0.25rem;
        }
        
        .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        
        .nav-item svg { width: 20px; height: 20px; flex-shrink: 0; }
        
        .nav-section {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            padding: 1rem 1rem 0.5rem;
            letter-spacing: 0.05em;
        }
        
        .sidebar.collapsed .nav-section { display: none; }
        
        /* User Info */
        .user-info {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .user-details { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; font-size: 0.875rem; }
        .user-role { font-size: 0.75rem; color: rgba(255,255,255,0.7); }
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Header */
        .header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.25rem; }
        .header-subtitle { color: #6b7280; font-size: 0.875rem; }
        
        /* Content */
        .content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
        }
        
        /* Search Box */
        .search-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .search-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .search-icon { color: #667eea; font-size: 1.5rem; }
        .search-title { font-size: 1.25rem; font-weight: 700; }
        
        .search-box {
            position: relative;
            display: flex;
            gap: 0.75rem;
        }
        
        .search-input-wrapper {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }
        
        .clear-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: none;
            transition: all 0.2s;
        }
        
        .clear-btn:hover { background: #dc2626; }
        .clear-btn.visible { display: block; }
        
        .search-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .search-info {
            margin-top: 1rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        /* Device Card */
        .device-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .device-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .device-card.selected {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }
        
        .device-header {
            margin-bottom: 1rem;
        }
        
        .device-model {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .device-brand {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .device-specs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }
        
        .device-store {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .device-price {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: #9ca3af;
        }
        
        .empty-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
        .empty-title { font-size: 1.25rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; }
        .empty-text { color: #9ca3af; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal.show { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1.25rem;
            transition: background 0.2s;
        }
        
        .modal-close:hover { background: rgba(255,255,255,0.3); }
        
        .modal-body {
            padding: 2rem;
        }
        
        .device-info-alert {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .device-info-alert h5 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .required { color: #ef4444; }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* Loading */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .loading-overlay.show { display: flex; }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e5e7eb;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 3000;
            animation: slideUp 0.3s ease-out;
            max-width: 400px;
        }
        
        .toast.show { display: flex; }
        
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .toast.success .toast-icon {
            background: #d1fae5;
            color: #065f46;
        }
        
        .toast.error .toast-icon {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .toast-content { flex: 1; }
        .toast-title { font-weight: 700; margin-bottom: 0.25rem; }
        .toast-text { font-size: 0.875rem; color: #6b7280; }
        
        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1.25rem;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { width: 70px; }
            .sidebar .logo-text,
            .sidebar .nav-text,
            .sidebar .nav-section,
            .sidebar .user-details { display: none; }
        }
        
        @media (max-width: 768px) {
            .content { padding: 1rem; }
            .header { padding: 1rem; flex-direction: column; align-items: flex-start; gap: 1rem; }
            .search-card { padding: 1.5rem; }
            .search-box { flex-direction: column; }
            .grid { grid-template-columns: 1fr; }
        }
        
        svg { width: 1em; height: 1em; fill: currentColor; }
    </style>
</head>
<body>

<div class="layout">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <svg viewBox="0 0 24 24"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                <span class="logo-text"><?php echo SYSTEM_NAME; ?></span>
            </div>
            <button class="menu-toggle" onclick="toggleSidebar()">
                <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <div class="nav-section">Ventas</div>
            
            <a href="sales.php" class="nav-item active">
                <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <span class="nav-text">Vender Celulares</span>
            </a>
            
            <a href="product_sales.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M17.21 9l-4.38-6.56c-.19-.28-.51-.42-.83-.42-.32 0-.64.14-.83.43L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.92 1.46h13c.92 0 1.69-.62 1.93-1.46l2.54-9.27L23 10c0-.55-.45-1-1-1h-4.79zM9 9l3-4.4L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                <span class="nav-text">Vender Productos</span>
            </a>
            
            <div class="nav-section">Inventario</div>
            
            <a href="inventory.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                <span class="nav-text">Celulares</span>
            </a>
            
            <a href="products.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1 0-2 .9-2 2v3.01c0 .72.43 1.34 1 1.69V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.7c.57-.35 1-.97 1-1.69V4c0-1.1-1-2-2-2zm-5 12H9v-2h6v2zm5-7H4V4h16v3z"/></svg>
                <span class="nav-text">Productos</span>
            </a>
            
            <a href="reports.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                <span class="nav-text">Reportes</span>
            </a>
            
            <?php if ($is_admin): ?>
            <div class="nav-section">Administración</div>
            
            <a href="users.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <span class="nav-text">Usuarios</span>
            </a>
            
            <a href="stores.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                <span class="nav-text">Tiendas</span>
            </a>
            
            <a href="categories.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z"/></svg>
                <span class="nav-text">Categorías</span>
            </a>
            
            <a href="catalog_settings.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span class="nav-text">Configuración</span>
            </a>
            <?php endif; ?>
            
            <div class="nav-section">Público</div>
            
            <a href="../index.php" target="_blank" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                <span class="nav-text">Ver Catálogo</span>
            </a>
        </nav>
        
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user['nombre'], 0, 2)); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user['nombre']); ?></div>
                <div class="user-role"><?php echo $is_admin ? 'Administrador' : 'Vendedor'; ?></div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Header -->
        <header class="header">
            <div class="header-title">
                <h1>💰 Vender Celulares</h1>
                <p class="header-subtitle">Busca un dispositivo disponible para realizar la venta</p>
            </div>
        </header>
        
        <!-- Content -->
        <main class="content">
            
            <!-- Search Card -->
            <div class="search-card">
                <div class="search-header">
                    <span class="search-icon">🔍</span>
                    <h2 class="search-title">Buscar Dispositivo</h2>
                </div>
                
                <div class="search-box">
                    <div class="search-input-wrapper">
                        <svg class="search-input-icon" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <input 
                            type="text" 
                            id="deviceSearch" 
                            class="search-input" 
                            placeholder="Buscar por modelo, marca, capacidad o IMEI..."
                            autocomplete="off">
                        <button class="clear-btn" id="clearBtn" onclick="clearSearch()">✕</button>
                    </div>
                    <button class="search-btn" onclick="searchDevices()">
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        Buscar
                    </button>
                </div>
                
                <div class="search-info" id="searchInfo"></div>
            </div>
            
            <!-- Results Grid -->
            <div class="grid" id="devicesList">
                <div style="grid-column: 1 / -1;">
                    <div class="empty-state">
                        <div class="empty-icon">📱</div>
                        <h3 class="empty-title">Busca un dispositivo para vender</h3>
                        <p class="empty-text">Usa el buscador arriba para encontrar celulares disponibles</p>
                    </div>
                </div>
            </div>
            
        </main>
        
    </div>
    
</div>

<!-- Modal de Venta -->
<div class="modal" id="saleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <svg viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                    <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                </svg>
                Registrar Venta
            </h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        
        <form id="saleForm" onsubmit="return registerSale(event)">
            <div class="modal-body">
                <input type="hidden" id="selectedDeviceId" name="celular_id">
                
                <!-- Device Info -->
                <div class="device-info-alert" id="deviceInfo" style="display: none;">
                    <h5>
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                            <path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/>
                        </svg>
                        <span id="deviceName"></span>
                    </h5>
                    <p id="deviceDetails" style="margin: 0.5rem 0 0 0; color: #1e40af;"></p>
                    <p id="devicePrice" style="margin: 0.5rem 0 0 0; font-weight: 700; font-size: 1.25rem; color: #1e40af;"></p>
                </div>
                
                <!-- Form Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cliente <span class="required">*</span></label>
                        <input type="text" class="form-input" id="cliente_nombre" name="cliente_nombre" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-input" id="cliente_telefono" name="cliente_telefono">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="cliente_email" name="cliente_email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Precio Venta (S/) <span class="required">*</span></label>
                        <input type="number" class="form-input" id="precio_venta" name="precio_venta" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Método de Pago</label>
                        <select class="form-select" id="metodo_pago" name="metodo_pago">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="yape">Yape/Plin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notas</label>
                        <textarea class="form-textarea" id="notas" name="notas"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                        <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                    </svg>
                    Registrar Venta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Toast -->
<div class="toast" id="toast">
    <div class="toast-icon" id="toastIcon">✓</div>
    <div class="toast-content">
        <div class="toast-title" id="toastTitle">Éxito</div>
        <div class="toast-text" id="toastText">Operación completada</div>
    </div>
    <button class="toast-close" onclick="hideToast()">✕</button>
</div>

<!-- IMPORTANTE: NO cargar sales.js ni common.js para evitar conflictos -->
<!-- Todo el JavaScript necesario está incluido inline abajo -->

<script>
// ============================================================================
// SISTEMA DE VENTAS MODERNO - JavaScript Inline Completo
// Versión 2.1 FINAL - Sin conflictos, sin recargas
// ============================================================================

// Variables globales
let selectedDevice = null;
let searchTimeout = null;

// ============================================================================
// FUNCIONES DE SIDEBAR
// ============================================================================

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

// ============================================================================
// FUNCIONES DE BÚSQUEDA
// ============================================================================

const searchInput = document.getElementById('deviceSearch');
const clearBtn = document.getElementById('clearBtn');

// Event listener para input (sin preventDefault que causaba problemas)
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    
    const value = this.value.trim();
    
    // Mostrar/ocultar botón de limpiar
    if (value) {
        clearBtn.classList.add('visible');
    } else {
        clearBtn.classList.remove('visible');
    }
    
    // Búsqueda con debounce de 500ms
    searchTimeout = setTimeout(() => {
        searchDevices();
    }, 500);
});

// Event listener para Enter
searchInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimeout);
        searchDevices();
    }
});

// Limpiar búsqueda
function clearSearch() {
    searchInput.value = '';
    clearBtn.classList.remove('visible');
    document.getElementById('searchInfo').textContent = '';
    
    // Restaurar estado inicial
    document.getElementById('devicesList').innerHTML = `
        <div style="grid-column: 1 / -1;">
            <div class="empty-state">
                <div class="empty-icon">📱</div>
                <h3 class="empty-title">Busca un dispositivo para vender</h3>
                <p class="empty-text">Usa el buscador arriba para encontrar celulares disponibles</p>
            </div>
        </div>
    `;
}

// Realizar búsqueda
function searchDevices() {
    const search = searchInput.value.trim();
    
    console.log('🔍 Buscando:', search || '[todos]');
    
    showLoading();
    
    fetch('sales.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'search_devices',
            search: search
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('📦 Resultados:', data.count || 0);
        
        if (data.success) {
            renderDevices(data.devices);
            updateSearchInfo(search, data.count);
        } else {
            showToast(data.message || 'Error al buscar dispositivos', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showToast('Error de conexión. Intenta de nuevo.', 'error');
    })
    .finally(() => {
        hideLoading();
    });
}

// ============================================================================
// RENDERIZADO DE DISPOSITIVOS
// ============================================================================

function renderDevices(devices) {
    const container = document.getElementById('devicesList');
    
    if (!devices || devices.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1;">
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3 class="empty-title">No se encontraron dispositivos</h3>
                    <p class="empty-text">Intenta con otros términos de búsqueda</p>
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    devices.forEach((device, index) => {
        const deviceJson = JSON.stringify(device).replace(/"/g, '&quot;');
        
        html += `
        <div class="device-card" 
             data-device-id="${device.id}"
             onclick='selectDevice(${deviceJson})'
             style="animation-delay: ${index * 0.05}s">
            
            <div class="device-header">
                <h3 class="device-model">${escapeHtml(device.modelo)}</h3>
                <p class="device-brand">${escapeHtml(device.marca)}</p>
            </div>
            
            <div class="device-specs">
                <span class="badge badge-info">${escapeHtml(device.capacidad)}</span>
                ${device.color ? `<span class="badge badge-secondary">${escapeHtml(device.color)}</span>` : ''}
            </div>
            
            ${device.tienda_nombre ? `
            <div class="device-store">
                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                    <path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/>
                </svg>
                <span>${escapeHtml(device.tienda_nombre)}</span>
            </div>
            ` : ''}
            
            <div class="device-price">S/ ${parseFloat(device.precio).toFixed(2)}</div>
        </div>
        `;
    });
    
    container.innerHTML = html;
}

// ============================================================================
// SELECCIÓN DE DISPOSITIVO
// ============================================================================

function selectDevice(device) {
    selectedDevice = device;
    
    console.log('✅ Dispositivo seleccionado:', device.modelo);
    
    // Actualizar estado visual de las cards
    document.querySelectorAll('.device-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    const selectedCard = document.querySelector(`[data-device-id="${device.id}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Llenar datos del modal
    document.getElementById('selectedDeviceId').value = device.id;
    document.getElementById('deviceName').textContent = device.modelo;
    document.getElementById('deviceDetails').textContent = 
        `${device.marca} - ${device.capacidad}${device.color ? ' - ' + device.color : ''}`;
    document.getElementById('devicePrice').textContent = 
        'Precio: S/ ' + parseFloat(device.precio).toFixed(2);
    document.getElementById('deviceInfo').style.display = 'block';
    document.getElementById('precio_venta').value = device.precio;
    
    // Mostrar modal
    document.getElementById('saleModal').classList.add('show');
    
    // Focus en primer campo después de la animación
    setTimeout(() => {
        document.getElementById('cliente_nombre').focus();
    }, 300);
}

// ============================================================================
// GESTIÓN DEL MODAL
// ============================================================================

function closeModal() {
    document.getElementById('saleModal').classList.remove('show');
    document.getElementById('saleForm').reset();
    document.getElementById('deviceInfo').style.display = 'none';
    selectedDevice = null;
}

// ============================================================================
// REGISTRO DE VENTA
// ============================================================================

function registerSale(event) {
    event.preventDefault();
    
    if (!selectedDevice) {
        showToast('No se ha seleccionado un dispositivo', 'error');
        return false;
    }
    
    const formData = new FormData(event.target);
    formData.append('action', 'register_sale');
    
    const cliente = formData.get('cliente_nombre');
    const precio = parseFloat(formData.get('precio_venta'));
    
    // Validación básica
    if (!cliente || cliente.trim() === '') {
        showToast('Por favor ingresa el nombre del cliente', 'error');
        return false;
    }
    
    if (!precio || precio <= 0) {
        showToast('Por favor ingresa un precio válido', 'error');
        return false;
    }
    
    // Confirmación
    const confirmMsg = `¿Confirmar venta?\n\nDispositivo: ${selectedDevice.modelo}\nCliente: ${cliente}\nPrecio: S/ ${precio.toFixed(2)}`;
    
    if (!confirm(confirmMsg)) {
        return false;
    }
    
    showLoading();
    
    fetch('sales.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal();
            
            // Preguntar si imprimir
            setTimeout(() => {
                if (confirm('¿Desea imprimir el comprobante de venta?')) {
                    window.open(`print_sale_invoice.php?id=${data.venta_id}`, '_blank', 'width=800,height=600');
                }
                
                // Recargar búsqueda
                searchDevices();
            }, 500);
        } else {
            showToast(data.message || 'Error al registrar venta', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showToast('Error al registrar venta. Intenta de nuevo.', 'error');
    })
    .finally(() => {
        hideLoading();
    });
    
    return false;
}

// ============================================================================
// UTILIDADES
// ============================================================================

function updateSearchInfo(search, count) {
    const info = document.getElementById('searchInfo');
    if (search) {
        info.textContent = `Mostrando ${count} resultado${count !== 1 ? 's' : ''} para "${search}"`;
    } else {
        info.textContent = count > 0 ? `${count} dispositivos disponibles` : '';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showLoading() {
    document.getElementById('loadingOverlay').classList.add('show');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('show');
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const icon = document.getElementById('toastIcon');
    const title = document.getElementById('toastTitle');
    const text = document.getElementById('toastText');
    
    toast.className = 'toast show ' + type;
    icon.textContent = type === 'success' ? '✓' : '✕';
    title.textContent = type === 'success' ? 'Éxito' : 'Error';
    text.textContent = message;
    
    setTimeout(hideToast, 4000);
}

function hideToast() {
    document.getElementById('toast').classList.remove('show');
}

// ============================================================================
// EVENT LISTENERS GLOBALES
// ============================================================================

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('saleModal');
        if (modal && modal.classList.contains('show')) {
            closeModal();
        }
    }
});

// Click fuera del modal para cerrar
document.getElementById('saleModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// ============================================================================
// RESPONSIVE
// ============================================================================

// Auto-collapse sidebar en móviles
if (window.innerWidth <= 1024) {
    document.getElementById('sidebar').classList.add('collapsed');
}

window.addEventListener('resize', function() {
    if (window.innerWidth <= 1024) {
        document.getElementById('sidebar').classList.add('collapsed');
    }
});

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

console.log('✅ Sistema de Ventas Moderno Cargado - v2.1 FINAL');
console.log('💰 Moneda: Soles (S/)');
console.log('🎨 Estilo: Dashboard Moderno');
console.log('📱 100% Responsive');
console.log('🔒 Sin problemas CSP');
console.log('⚡ Sin conflictos de scripts');
console.log('✓ Búsqueda sin recargas');

</script>

</body>
</html>
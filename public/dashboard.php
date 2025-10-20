<?php
/**
 * DASHBOARD MODERNO SIN CSP
 * Sistema de Inventario de Celulares
 * Versión 2.1 - Sin dependencias externas
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Obtener estadísticas
$stats = [
    'ventas_hoy' => ['cantidad' => 0, 'ingresos' => 0],
    'ventas_mes' => ['cantidad' => 0, 'ingresos' => 0],
    'celulares_disponibles' => 0,
    'productos_stock' => 0,
    'productos_bajo_stock' => 0
];

try {
    $hoy = date('Y-m-d');
    $mes_actual = date('Y-m');
    
    // Ventas de hoy
    if ($user['rol'] === 'admin') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ?
        ");
        $stmt->execute([$hoy]);
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE(fecha_venta) = ? AND tienda_id = ?
        ");
        $stmt->execute([$hoy, $user['tienda_id']]);
    }
    $stats['ventas_hoy'] = $stmt->fetch();
    
    // Ventas del mes
    if ($user['rol'] === 'admin') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?
        ");
        $stmt->execute([$mes_actual]);
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(precio_venta), 0) as ingresos 
            FROM ventas 
            WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND tienda_id = ?
        ");
        $stmt->execute([$mes_actual, $user['tienda_id']]);
    }
    $stats['ventas_mes'] = $stmt->fetch();
    
    // Celulares disponibles
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("SELECT COUNT(*) as total FROM celulares WHERE estado = 'disponible'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM celulares WHERE estado = 'disponible' AND tienda_id = ?");
        $stmt->execute([$user['tienda_id']]);
    }
    $stats['celulares_disponibles'] = $stmt->fetchColumn();
    
    // Stock de productos
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("SELECT COALESCE(SUM(cantidad_actual), 0) as total FROM stock_productos");
    } else {
        $stmt = $db->prepare("SELECT COALESCE(SUM(cantidad_actual), 0) as total FROM stock_productos WHERE tienda_id = ?");
        $stmt->execute([$user['tienda_id']]);
    }
    $stats['productos_stock'] = $stmt->fetchColumn();
    
    // Productos con stock bajo
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("
            SELECT COUNT(DISTINCT s.producto_id) as total 
            FROM stock_productos s
            JOIN productos p ON s.producto_id = p.id
            WHERE s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0
        ");
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.producto_id) as total 
            FROM stock_productos s
            JOIN productos p ON s.producto_id = p.id
            WHERE s.cantidad_actual <= p.minimo_stock AND s.cantidad_actual > 0 AND s.tienda_id = ?
        ");
        $stmt->execute([$user['tienda_id']]);
    }
    $stats['productos_bajo_stock'] = $stmt->fetchColumn();
    
    // Actividad reciente
    if ($user['rol'] === 'admin') {
        $stmt = $db->query("
            SELECT c.modelo, v.precio_venta, v.fecha_venta, v.cliente_nombre
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            ORDER BY v.fecha_venta DESC
            LIMIT 5
        ");
    } else {
        $stmt = $db->prepare("
            SELECT c.modelo, v.precio_venta, v.fecha_venta, v.cliente_nombre
            FROM ventas v
            JOIN celulares c ON v.celular_id = c.id
            WHERE v.tienda_id = ?
            ORDER BY v.fecha_venta DESC
            LIMIT 5
        ");
        $stmt->execute([$user['tienda_id']]);
    }
    $actividades = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
}

$is_admin = $user['rol'] === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    
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
        
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        
        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .notification-btn:hover { color: #1f2937; background: #f3f4f6; }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            font-weight: 600;
        }
        
        .user-menu { position: relative; }
        
        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: none;
            border: none;
            color: #374151;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .user-menu-btn:hover { background: #f3f4f6; }
        
        .user-menu-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 200px;
            display: none;
            z-index: 50;
        }
        
        .user-menu-dropdown.show { display: block; }
        
        .dropdown-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .dropdown-item:hover { background: #f3f4f6; }
        .dropdown-item:first-child { border-radius: 0.5rem 0.5rem 0 0; }
        .dropdown-item:last-child { border-radius: 0 0 0.5rem 0.5rem; }
        
        .dropdown-divider { height: 1px; background: #e5e7eb; margin: 0.5rem 0; }
        
        /* Content */
        .content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
        }
        
        /* Alert */
        .alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .alert-icon { color: #f59e0b; font-size: 1.25rem; }
        .alert-content { flex: 1; }
        .alert-title { font-weight: 600; color: #92400e; margin-bottom: 0.25rem; }
        .alert-text { color: #78350f; font-size: 0.875rem; }
        .alert-link { color: #92400e; text-decoration: underline; font-weight: 600; }
        
        /* Grid */
        .grid { display: grid; gap: 1.5rem; margin-bottom: 2rem; }
        .grid-cols-4 { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .grid-cols-3 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        
        /* Stat Card */
        .stat-card {
            padding: 1.5rem;
            border-radius: 1rem;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s;
        }
        
        .stat-card:hover { transform: translateY(-4px); }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .stat-value { font-size: 2rem; font-weight: 700; }
        
        .stat-icon {
            background: rgba(255,255,255,0.2);
            padding: 1rem;
            border-radius: 0.75rem;
        }
        
        .stat-footer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .bg-yellow { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
        .bg-blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .bg-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .bg-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        
        /* Card */
        .card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-title { font-size: 1.125rem; font-weight: 700; }
        .card-icon { color: #3b82f6; }
        
        /* Quick Actions */
        .quick-actions { display: flex; flex-direction: column; gap: 0.75rem; }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid;
        }
        
        .quick-action:hover { transform: translateX(4px); }
        
        .action-icon {
            padding: 0.75rem;
            border-radius: 0.75rem;
            color: white;
        }
        
        .action-details { flex: 1; }
        .action-title { font-weight: 600; color: #1f2937; }
        .action-subtitle { font-size: 0.75rem; color: #6b7280; }
        
        .action-green {
            background: linear-gradient(to right, #f0fdf4, #dcfce7);
            border-color: #86efac;
        }
        
        .action-green:hover { background: linear-gradient(to right, #dcfce7, #bbf7d0); }
        .action-green .action-icon { background: #10b981; }
        
        .action-blue {
            background: linear-gradient(to right, #eff6ff, #dbeafe);
            border-color: #93c5fd;
        }
        
        .action-blue:hover { background: linear-gradient(to right, #dbeafe, #bfdbfe); }
        .action-blue .action-icon { background: #3b82f6; }
        
        .action-purple {
            background: linear-gradient(to right, #faf5ff, #f3e8ff);
            border-color: #d8b4fe;
        }
        
        .action-purple:hover { background: linear-gradient(to right, #f3e8ff, #e9d5ff); }
        .action-purple .action-icon { background: #8b5cf6; }
        
        .action-orange {
            background: linear-gradient(to right, #fff7ed, #ffedd5);
            border-color: #fed7aa;
        }
        
        .action-orange:hover { background: linear-gradient(to right, #ffedd5, #fde68a); }
        .action-orange .action-icon { background: #f97316; }
        
        /* Activity */
        .activity-list { display: flex; flex-direction: column; gap: 1rem; }
        
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.75rem;
            transition: background 0.2s;
        }
        
        .activity-item:hover { background: #f3f4f6; }
        
        .activity-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.75rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .activity-details { flex: 1; }
        .activity-title { font-weight: 600; color: #1f2937; margin-bottom: 0.25rem; }
        .activity-subtitle { font-size: 0.875rem; color: #6b7280; }
        
        .activity-price { text-align: right; }
        .activity-amount { font-weight: 700; color: #10b981; font-size: 1.125rem; }
        .activity-time { font-size: 0.75rem; color: #6b7280; }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }
        
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        
        /* System Info Card */
        .system-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .system-info { display: flex; flex-direction: column; gap: 0.5rem; }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
        }
        
        .info-label { color: rgba(255,255,255,0.8); }
        .info-value { font-weight: 600; }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #10b981;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 100;
            animation: slideUp 0.3s ease-out;
        }
        
        .toast.show { display: flex; }
        
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .toast-icon { font-size: 1.5rem; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 700; margin-bottom: 0.25rem; }
        .toast-text { font-size: 0.875rem; }
        
        .toast-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.25rem;
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
            .header { padding: 1rem; }
            .stat-value { font-size: 1.5rem; }
        }
        
        /* Icons - SVG Inline */
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
            <a href="dashboard.php" class="nav-item active">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <div class="nav-section">Ventas</div>
            
            <a href="sales.php" class="nav-item">
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
                <h1>Dashboard</h1>
                <p class="header-subtitle">Bienvenido de nuevo, <?php echo htmlspecialchars($user['nombre']); ?> 👋</p>
            </div>
            
            <div class="header-actions">
                <button class="notification-btn">
                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                    <?php if ($stats['productos_bajo_stock'] > 0): ?>
                    <span class="notification-badge"><?php echo $stats['productos_bajo_stock']; ?></span>
                    <?php endif; ?>
                </button>
                
                <div class="user-menu">
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <div class="user-avatar" style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <?php echo strtoupper(substr($user['nombre'], 0, 2)); ?>
                        </div>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M7 10l5 5 5-5z"/></svg>
                    </button>
                    
                    <div class="user-menu-dropdown" id="userMenuDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 8px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            Mi Perfil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item" style="color: #ef4444;">
                            <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 8px;"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                            Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <main class="content">
            
            <!-- Alert -->
            <?php if ($stats['productos_bajo_stock'] > 0): ?>
            <div class="alert">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <div class="alert-title">Alerta de Stock Bajo</div>
                    <div class="alert-text">
                        Tienes <strong><?php echo $stats['productos_bajo_stock']; ?></strong> producto(s) con stock bajo.
                        <a href="products.php?stock=bajo" class="alert-link">Ver ahora</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-4">
                <div class="stat-card bg-yellow">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Ventas de Hoy</div>
                            <div class="stat-value" id="ventasHoy">S/ <?php echo number_format($stats['ventas_hoy']['ingresos'], 2); ?></div>
                        </div>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" style="width: 32px; height: 32px;"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        <?php echo $stats['ventas_hoy']['cantidad']; ?> transacciones
                    </div>
                </div>
                
                <div class="stat-card bg-blue">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Ventas del Mes</div>
                            <div class="stat-value">S/ <?php echo number_format($stats['ventas_mes']['ingresos'], 0); ?></div>
                        </div>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" style="width: 32px; height: 32px;"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
                        <?php echo $stats['ventas_mes']['cantidad']; ?> transacciones
                    </div>
                </div>
                
                <div class="stat-card bg-green">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Celulares</div>
                            <div class="stat-value"><?php echo number_format($stats['celulares_disponibles']); ?></div>
                        </div>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" style="width: 32px; height: 32px;"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Disponibles
                    </div>
                </div>
                
                <div class="stat-card bg-purple">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Stock Total</div>
                            <div class="stat-value"><?php echo number_format($stats['productos_stock']); ?></div>
                        </div>
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" style="width: 32px; height: 32px;"><path d="M20 2H4c-1 0-2 .9-2 2v3.01c0 .72.43 1.34 1 1.69V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.7c.57-.35 1-.97 1-1.69V4c0-1.1-1-2-2-2zm-5 12H9v-2h6v2zm5-7H4V4h16v3z"/></svg>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        Productos
                    </div>
                </div>
            </div>
            
            <!-- Grid Layout -->
            <div class="grid grid-cols-3">
                
                <!-- Quick Actions -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <svg viewBox="0 0 24 24" class="card-icon" style="width: 20px; height: 20px;"><path d="M13 10h-2v3H8v2h3v3h2v-3h3v-2h-3z"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                            <h3 class="card-title">Accesos Rápidos</h3>
                        </div>
                        
                        <div class="quick-actions">
                            <a href="sales.php" class="quick-action action-green">
                                <div class="action-icon">
                                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                </div>
                                <div class="action-details">
                                    <div class="action-title">Vender Celular</div>
                                    <div class="action-subtitle">Nueva venta</div>
                                </div>
                            </a>
                            
                            <a href="product_sales.php" class="quick-action action-blue">
                                <div class="action-icon">
                                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path d="M17.21 9l-4.38-6.56c-.19-.28-.51-.42-.83-.42-.32 0-.64.14-.83.43L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.92 1.46h13c.92 0 1.69-.62 1.93-1.46l2.54-9.27L23 10c0-.55-.45-1-1-1h-4.79zM9 9l3-4.4L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                                </div>
                                <div class="action-details">
                                    <div class="action-title">Vender Producto</div>
                                    <div class="action-subtitle">Accesorios</div>
                                </div>
                            </a>
                            
                            <a href="inventory.php" class="quick-action action-purple">
                                <div class="action-icon">
                                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                                </div>
                                <div class="action-details">
                                    <div class="action-title">Inventario</div>
                                    <div class="action-subtitle">Ver celulares</div>
                                </div>
                            </a>
                            
                            <a href="reports.php" class="quick-action action-orange">
                                <div class="action-icon">
                                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                                </div>
                                <div class="action-details">
                                    <div class="action-title">Reportes</div>
                                    <div class="action-subtitle">Analíticas</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- System Info -->
                    <div class="system-card">
                        <div class="card-header" style="color: white;">
                            <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                            <h3 class="card-title">Sistema</h3>
                        </div>
                        <div class="system-info">
                            <div class="info-row">
                                <span class="info-label">Estado:</span>
                                <span class="info-value"><span class="status-dot"></span>Activo</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Versión:</span>
                                <span class="info-value"><?php echo SYSTEM_VERSION; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Moneda:</span>
                                <span class="info-value">Soles (S/)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity -->
                <div style="grid-column: span 2;">
                    <div class="card">
                        <div class="card-header">
                            <svg viewBox="0 0 24 24" class="card-icon" style="width: 20px; height: 20px;"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                            <h3 class="card-title">Actividad Reciente</h3>
                        </div>
                        
                        <?php if (empty($actividades)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>Sin actividad reciente</p>
                        </div>
                        <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($actividades as $act): 
                                $tiempo = time() - strtotime($act['fecha_venta']);
                                if ($tiempo < 3600) {
                                    $tiempo_texto = floor($tiempo / 60) . ' min';
                                } elseif ($tiempo < 86400) {
                                    $tiempo_texto = floor($tiempo / 3600) . ' h';
                                } else {
                                    $tiempo_texto = floor($tiempo / 86400) . ' días';
                                }
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo htmlspecialchars($act['modelo']); ?></div>
                                    <div class="activity-subtitle">
                                        <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; display: inline; margin-right: 4px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                        <?php echo htmlspecialchars($act['cliente_nombre']); ?>
                                    </div>
                                </div>
                                <div class="activity-price">
                                    <div class="activity-amount">S/ <?php echo number_format($act['precio_venta'], 0); ?></div>
                                    <div class="activity-time">Hace <?php echo $tiempo_texto; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="margin-top: 1.5rem; text-align: center;">
                            <a href="reports.php" style="color: #667eea; font-weight: 600; text-decoration: none; font-size: 0.875rem;">
                                Ver todos los reportes →
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
            
        </main>
        
    </div>
    
</div>

<!-- Toast -->
<?php if (isset($_GET['welcome'])): ?>
<div class="toast show" id="welcomeToast">
    <div class="toast-icon">✓</div>
    <div class="toast-content">
        <div class="toast-title">¡Bienvenido!</div>
        <div class="toast-text">Hola <?php echo htmlspecialchars($user['nombre']); ?> 👋</div>
    </div>
    <button class="toast-close" onclick="closeToast()">✕</button>
</div>
<?php endif; ?>

<!-- Common JS -->
<script src="../assets/js/common.js"></script>

<script>
// Toggle Sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

// Toggle User Menu
function toggleUserMenu() {
    document.getElementById('userMenuDropdown').classList.toggle('show');
}

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    const userMenu = document.querySelector('.user-menu');
    if (userMenu && !userMenu.contains(e.target)) {
        document.getElementById('userMenuDropdown').classList.remove('show');
    }
});

// Close Toast
function closeToast() {
    const toast = document.getElementById('welcomeToast');
    if (toast) {
        toast.style.display = 'none';
    }
}

// Auto-close toast
if (document.getElementById('welcomeToast')) {
    setTimeout(closeToast, 3000);
}

// Animate numbers on load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Dashboard Moderno cargado');
    console.log('💰 Moneda: Soles (S/)');
    console.log('🎨 Framework: CSS Vanilla + JavaScript Puro');
    console.log('📱 Responsive: Sí');
    console.log('🔒 CSP: Sin problemas');
    
    // Animar el valor de ventas de hoy
    const ventasHoyEl = document.getElementById('ventasHoy');
    if (ventasHoyEl) {
        const targetValue = <?php echo $stats['ventas_hoy']['ingresos']; ?>;
        if (targetValue > 0) {
            animateValue(ventasHoyEl, 0, targetValue, 1500);
        }
    }
    
    function animateValue(element, start, end, duration) {
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function: easeOutQuart
            const eased = 1 - Math.pow(1 - progress, 4);
            const current = start + (end - start) * eased;
            
            element.textContent = 'S/ ' + current.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }
});

// Responsive: Auto-collapse sidebar en móviles
if (window.innerWidth <= 1024) {
    document.getElementById('sidebar').classList.add('collapsed');
}

window.addEventListener('resize', function() {
    if (window.innerWidth <= 1024) {
        document.getElementById('sidebar').classList.add('collapsed');
    }
});
</script>

</body>
</html>
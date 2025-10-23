<?php
/**
 * VENTAS DE CELULARES - VERSIÓN AUTOCONTENIDA EXPERTO
 * Sistema de Inventario de Celulares
 * Versión 10.3 FINAL - BÚSQUEDA MANUAL (SIN AUTO-BÚSQUEDA)
 * 
 * ✅ Sin sales.js externo
 * ✅ JavaScript inline optimizado
 * ✅ CSS inline moderno
 * ✅ Búsqueda MANUAL (Enter o botón Buscar)
 * ✅ SIN búsqueda automática mientras escribes
 * ✅ Enter busca inmediatamente
 * ✅ SIN recarga de página
 * ✅ 100% profesional
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

// ==========================================
// PROCESAMIENTO AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
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
                $query .= " AND (c.modelo LIKE ? OR c.marca LIKE ? OR c.capacidad LIKE ? OR c.imei1 LIKE ? OR c.color LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, array_fill(0, 5, $searchParam));
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
                throw new Exception('Datos incompletos o inválidos');
            }
            
            $db->beginTransaction();
            
            // Verificar disponibilidad
            $stmt = $db->prepare("SELECT * FROM celulares WHERE id = ? AND estado = 'disponible' FOR UPDATE");
            $stmt->execute([$celular_id]);
            $celular = $stmt->fetch();
            
            if (!$celular) {
                throw new Exception('Celular no disponible');
            }
            
            // Verificar permisos de tienda
            if ($user['rol'] !== 'admin' && $celular['tienda_id'] != $user['tienda_id']) {
                throw new Exception('No tienes permisos para vender este dispositivo');
            }
            
            // Registrar venta
            $stmt = $db->prepare("
                INSERT INTO ventas (celular_id, cliente_nombre, cliente_telefono, cliente_email, 
                                   precio_venta, metodo_pago, notas, vendedor_id, tienda_id, fecha_venta)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $celular_id, $cliente_nombre, $cliente_telefono, $cliente_email,
                $precio_venta, $metodo_pago, $notas, $user['id'], $celular['tienda_id']
            ]);
            
            $venta_id = $db->lastInsertId();
            
            // Actualizar estado del celular
            $stmt = $db->prepare("UPDATE celulares SET estado = 'vendido', fecha_venta = NOW() WHERE id = ?");
            $stmt->execute([$celular_id]);
            
            // Registrar actividad
            if (function_exists('logActivity')) {
                logActivity($user['id'], 'register_sale', "Venta: {$celular['marca']} {$celular['modelo']} - Cliente: {$cliente_nombre} - S/ {$precio_venta}");
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => '¡Venta registrada exitosamente!',
                'venta_id' => $venta_id
            ]);
            exit;
        }
        
        throw new Exception('Acción no válida');
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        if (function_exists('logError')) {
            logError("Error en sales.php: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

$is_admin = $user['rol'] === 'admin';

// Obtener estadísticas rápidas
$stats = ['disponibles' => 0, 'ventas_hoy' => 0];
try {
    if ($is_admin) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM celulares WHERE estado = 'disponible'");
        $stats['disponibles'] = $stmt->fetch()['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM ventas WHERE DATE(fecha_venta) = CURDATE()");
        $stats['ventas_hoy'] = $stmt->fetch()['count'];
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM celulares WHERE estado = 'disponible' AND tienda_id = ?");
        $stmt->execute([$user['tienda_id']]);
        $stats['disponibles'] = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM ventas WHERE DATE(fecha_venta) = CURDATE() AND tienda_id = ?");
        $stmt->execute([$user['tienda_id']]);
        $stats['ventas_hoy'] = $stmt->fetch()['count'];
    }
} catch (Exception $e) {
    // Silenciar errores de estadísticas
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vender Celulares - <?php echo SYSTEM_NAME; ?></title>
    <meta name="description" content="Sistema de ventas de celulares - Registra ventas rápidamente">
    
    <style>
        /* ============================================ */
        /* SISTEMA DE DISEÑO PROFESIONAL */
        /* ============================================ */
        
        :root {
            --color-primary: #667eea;
            --color-primary-dark: #5568d3;
            --color-secondary: #764ba2;
            --color-success: #10b981;
            --color-success-dark: #059669;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --color-info: #3b82f6;
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            --gradient-success: linear-gradient(135deg, var(--color-success) 0%, var(--color-success-dark) 100%);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--color-gray-50);
            color: var(--color-gray-900);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* ============================================ */
        /* LAYOUT PRINCIPAL */
        /* ============================================ */
        
        .layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: var(--shadow-xl);
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .nav {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }
        
        .nav-section {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            padding: 1rem 1rem 0.5rem;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: var(--transition-base);
            margin-bottom: 0.25rem;
            font-size: 0.9375rem;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
        }
        
        .nav-item svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
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
        
        .user-name {
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        .header-title h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--color-gray-900);
            margin-bottom: 0.25rem;
        }
        
        .header-subtitle {
            color: var(--color-gray-500);
            font-size: 0.9375rem;
        }
        
        .header-stats {
            display: flex;
            gap: 1rem;
        }
        
        .stat-pill {
            padding: 0.5rem 1rem;
            background: var(--color-gray-100);
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-gray-700);
            white-space: nowrap;
        }
        
        .stat-pill.primary {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: var(--color-primary);
        }
        
        .stat-pill.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }
        
        /* Content Area */
        .content {
            flex: 1;
            padding: 2rem;
        }
        
        /* ============================================ */
        /* SEARCH CARD */
        /* ============================================ */
        
        .search-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--color-gray-200);
        }
        
        .search-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .search-icon {
            font-size: 1.5rem;
        }
        
        .search-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-gray-900);
        }
        
        .search-box {
            display: flex;
            gap: 0.75rem;
            align-items: stretch;
        }
        
        .search-input-wrapper {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            border: 2px solid var(--color-gray-200);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition-base);
            background: white;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-input::placeholder {
            color: var(--color-gray-400);
        }
        
        .search-input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-gray-400);
            pointer-events: none;
        }
        
        .clear-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: var(--color-danger);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: none;
            transition: var(--transition-base);
            font-size: 1rem;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
        }
        
        .clear-btn:hover {
            background: #dc2626;
        }
        
        .clear-btn.visible {
            display: flex;
        }
        
        .search-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-base);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-md);
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .search-info {
            margin-top: 1rem;
            color: var(--color-gray-500);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-hint {
            margin-top: 0.5rem;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
            border-left: 3px solid var(--color-info);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--color-gray-600);
        }
        
        /* ============================================ */
        /* DEVICE CARDS GRID */
        /* ============================================ */
        
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .device-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition-base);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .device-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--color-primary);
        }
        
        .device-card:hover::before {
            transform: scaleX(1);
        }
        
        .device-card.selected {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
            border-color: var(--color-success);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .device-card.selected::before {
            background: var(--gradient-success);
            transform: scaleX(1);
        }
        
        .device-header {
            margin-bottom: 1rem;
        }
        
        .device-model {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--color-gray-900);
            margin-bottom: 0.25rem;
        }
        
        .device-brand {
            color: var(--color-gray-600);
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
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }
        
        .badge-secondary {
            background: var(--color-gray-100);
            color: var(--color-gray-700);
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }
        
        .device-store {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-gray-600);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: var(--color-gray-50);
            border-radius: var(--radius-md);
        }
        
        .device-price {
            font-size: 2rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            padding-top: 1rem;
            border-top: 2px solid var(--color-gray-100);
        }
        
        /* ============================================ */
        /* EMPTY STATE */
        /* ============================================ */
        
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: var(--color-gray-400);
        }
        
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-gray-700);
            margin-bottom: 0.5rem;
        }
        
        .empty-text {
            color: var(--color-gray-500);
            font-size: 1rem;
        }
        
        /* ============================================ */
        /* MODAL */
        /* ============================================ */
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            padding: 1rem;
        }
        
        .modal.show {
            display: flex;
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: var(--gradient-primary);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
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
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1.25rem;
            transition: var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .device-info-alert {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
            border-left: 4px solid var(--color-info);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }
        
        .device-info-alert h5 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-gray-900);
        }
        
        .device-info-alert p {
            color: var(--color-gray-600);
            font-size: 0.9375rem;
        }
        
        /* ============================================ */
        /* FORMS */
        /* ============================================ */
        
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
            color: var(--color-gray-700);
            font-size: 0.9375rem;
        }
        
        .required {
            color: var(--color-danger);
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--color-gray-200);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition-base);
            background: white;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--color-gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* ============================================ */
        /* BUTTONS */
        /* ============================================ */
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition-base);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9375rem;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: var(--color-gray-200);
            color: var(--color-gray-700);
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: var(--color-gray-300);
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* ============================================ */
        /* LOADING & NOTIFICATIONS */
        /* ============================================ */
        
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
        
        .loading-overlay.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--color-gray-200);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 3000;
            max-width: 400px;
            border-left: 4px solid var(--color-info);
        }
        
        .toast.show {
            display: flex;
            animation: toastSlideIn 0.3s ease-out;
        }
        
        @keyframes toastSlideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .toast.success {
            border-left-color: var(--color-success);
        }
        
        .toast.success .toast-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }
        
        .toast.error {
            border-left-color: var(--color-danger);
        }
        
        .toast.error .toast-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--color-gray-900);
        }
        
        .toast-text {
            font-size: 0.875rem;
            color: var(--color-gray-600);
        }
        
        .toast-close {
            background: none;
            border: none;
            color: var(--color-gray-400);
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1.25rem;
            transition: var(--transition-base);
        }
        
        .toast-close:hover {
            color: var(--color-gray-600);
        }
        
        /* ============================================ */
        /* UTILITIES */
        /* ============================================ */
        
        .hidden {
            display: none !important;
        }
        
        svg {
            width: 1em;
            height: 1em;
            fill: currentColor;
        }
        
        /* ============================================ */
        /* ANIMATIONS */
        /* ============================================ */
        
        .animate-in {
            animation: fadeInUp 0.5s ease-out;
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
        
        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content {
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-stats {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .devices-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="layout">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <svg viewBox="0 0 24 24"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                <span><?php echo SYSTEM_NAME; ?></span>
            </div>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">Ventas</div>
            
            <a href="sales.php" class="nav-item active">
                <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <span>Vender Celulares</span>
            </a>
            
            <a href="product_sales.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M17.21 9l-4.38-6.56c-.19-.28-.51-.42-.83-.42-.32 0-.64.14-.83.43L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.92 1.46h13c.92 0 1.69-.62 1.93-1.46l2.54-9.27L23 10c0-.55-.45-1-1-1h-4.79zM9 9l3-4.4L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                <span>Vender Productos</span>
            </a>
            
            <div class="nav-section">Inventario</div>
            
            <a href="inventory.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                <span>Celulares</span>
            </a>
            
            <a href="products.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1 0-2 .9-2 2v3.01c0 .72.43 1.34 1 1.69V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.7c.57-.35 1-.97 1-1.69V4c0-1.1-1-2-2-2zm-5 12H9v-2h6v2zm5-7H4V4h16v3z"/></svg>
                <span>Productos</span>
            </a>
            
            <a href="reports.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                <span>Reportes</span>
            </a>
            
            <?php if ($is_admin): ?>
            <div class="nav-section">Administración</div>
            
            <a href="users.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <span>Usuarios</span>
            </a>
            
            <a href="stores.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                <span>Tiendas</span>
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user['nombre'], 0, 2)); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($user['nombre']); ?></div>
                <div class="user-role"><?php echo $is_admin ? 'Administrador' : 'Vendedor'; ?></div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-title">
                    <h1>💰 Vender Celulares</h1>
                    <p class="header-subtitle">Busca un dispositivo disponible para realizar la venta</p>
                </div>
                <div class="header-stats">
                    <div class="stat-pill primary">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                        <?php echo $stats['disponibles']; ?> disponibles
                    </div>
                    <div class="stat-pill success">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                        <?php echo $stats['ventas_hoy']; ?> ventas hoy
                    </div>
                </div>
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
                
                <!-- 🔥 FORMULARIO CORREGIDO - PREVIENE RECARGA -->
                <form class="search-box" onsubmit="return handleSearchSubmit(event)">
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
                        <button type="button" class="clear-btn" id="clearBtn" title="Limpiar búsqueda">✕</button>
                    </div>
                    <button type="submit" class="search-btn">
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        Buscar
                    </button>
                </form>
                
                <div class="search-info" id="searchInfo"></div>
                
                <div class="search-hint">
                    💡 <strong>Tip:</strong> Presiona <kbd>Enter</kbd> o haz clic en el botón <strong>Buscar</strong> para realizar la búsqueda.
                </div>
            </div>
            
            <!-- Results Grid -->
            <div class="devices-grid" id="devicesList">
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
                    <p id="deviceDetails"></p>
                    <p id="devicePrice" style="font-weight: 700; font-size: 1.25rem; margin-top: 0.5rem;"></p>
                </div>
                
                <!-- Form Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cliente <span class="required">*</span></label>
                        <input type="text" class="form-input" id="cliente_nombre" name="cliente_nombre" required placeholder="Nombre completo">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-input" id="cliente_telefono" name="cliente_telefono" placeholder="999 999 999">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="cliente_email" name="cliente_email" placeholder="cliente@ejemplo.com">
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
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea class="form-textarea" id="notas" name="notas" placeholder="Observaciones adicionales sobre la venta..."></textarea>
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

<script>
// ============================================================================
// SISTEMA DE VENTAS MODERNO - JAVASCRIPT INLINE COMPLETO v10.3
// Sin dependencias externas - Todo autocontenido
// ✅ CORREGIDO: Sin recarga de página
// ✅ BÚSQUEDA MANUAL: Solo con Enter o botón Buscar
// ============================================================================

(function() {
    'use strict';
    
    // Variables globales
    let selectedDevice = null;
    let searchTimeout = null;
    
    // ========================================================================
    // 🔥 FUNCIÓN PARA MANEJAR EL SUBMIT DEL FORMULARIO DE BÚSQUEDA
    // ========================================================================
    
    window.handleSearchSubmit = function(event) {
        event.preventDefault(); // 🔥 PREVIENE LA RECARGA
        event.stopPropagation(); // 🔥 EXTRA SEGURIDAD
        
        console.log('🚀 Submit del formulario - Ejecutando búsqueda');
        searchDevices();
        
        return false; // 🔥 TRIPLE SEGURIDAD
    };
    
    // ========================================================================
    // FUNCIONES DE BÚSQUEDA - OPTIMIZADO
    // ========================================================================
    
    window.searchDevices = function() {
        const searchInput = document.getElementById('deviceSearch');
        const searchTerm = searchInput ? searchInput.value.trim() : '';
        
        console.log('🔍 Buscando:', searchTerm || '[todos]');
        
        const clearBtn = document.getElementById('clearBtn');
        if (clearBtn) {
            clearBtn.classList.toggle('visible', searchTerm.length > 0);
        }
        
        showLoading();
        
        fetch('sales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'search_devices',
                search: searchTerm
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
                updateSearchInfo(searchTerm, data.count);
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
    };
    
    function clearSearch() {
        const searchInput = document.getElementById('deviceSearch');
        const clearBtn = document.getElementById('clearBtn');
        const searchInfo = document.getElementById('searchInfo');
        
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        
        if (clearBtn) {
            clearBtn.classList.remove('visible');
        }
        
        if (searchInfo) {
            searchInfo.textContent = '';
        }
        
        renderDevices([]);
    }
    
    function updateSearchInfo(searchTerm, count) {
        const info = document.getElementById('searchInfo');
        if (!info) return;
        
        if (searchTerm) {
            info.innerHTML = `✅ Mostrando <strong>${count}</strong> resultado${count !== 1 ? 's' : ''} para "<strong>${escapeHtml(searchTerm)}</strong>"`;
        } else {
            info.textContent = count > 0 ? `📱 ${count} dispositivos disponibles` : '';
        }
    }
    
    // ========================================================================
    // RENDERIZADO DE DISPOSITIVOS
    // ========================================================================
    
    function renderDevices(devices) {
        const container = document.getElementById('devicesList');
        if (!container) return;
        
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
            <div class="device-card animate-in" 
                 style="animation-delay: ${index * 0.05}s"
                 data-device-id="${device.id}"
                 onclick='selectDevice(${deviceJson})'>
                
                <div class="device-header">
                    <h3 class="device-model">${escapeHtml(device.modelo)}</h3>
                    <p class="device-brand">${escapeHtml(device.marca)}</p>
                </div>
                
                <div class="device-specs">
                    <span class="badge badge-info">
                        <svg viewBox="0 0 24 24" style="width:12px;height:12px">
                            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        ${escapeHtml(device.capacidad)}
                    </span>
                    ${device.color ? `<span class="badge badge-secondary">${escapeHtml(device.color)}</span>` : ''}
                    ${device.condicion ? `<span class="badge badge-success">${escapeHtml(device.condicion)}</span>` : ''}
                </div>
                
                ${device.tienda_nombre ? `
                <div class="device-store">
                    <svg viewBox="0 0 24 24" style="width:16px;height:16px">
                        <path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/>
                    </svg>
                    <span>${escapeHtml(device.tienda_nombre)}</span>
                </div>
                ` : ''}
                
                <div class="device-price">S/ ${formatPrice(device.precio)}</div>
            </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // ========================================================================
    // SELECCIÓN DE DISPOSITIVO
    // ========================================================================
    
    window.selectDevice = function(device) {
        selectedDevice = device;
        
        console.log('✅ Dispositivo seleccionado:', device.modelo);
        
        // Actualizar estado visual
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
            'Precio: S/ ' + formatPrice(device.precio);
        document.getElementById('deviceInfo').style.display = 'block';
        document.getElementById('precio_venta').value = device.precio;
        
        // Mostrar modal
        document.getElementById('saleModal').classList.add('show');
        
        // Focus en primer campo
        setTimeout(() => {
            document.getElementById('cliente_nombre').focus();
        }, 300);
    };
    
    // ========================================================================
    // GESTIÓN DEL MODAL
    // ========================================================================
    
    window.closeModal = function() {
        document.getElementById('saleModal').classList.remove('show');
        document.getElementById('saleForm').reset();
        document.getElementById('deviceInfo').style.display = 'none';
        selectedDevice = null;
    };
    
    // ========================================================================
    // REGISTRO DE VENTA
    // ========================================================================
    
    window.registerSale = function(event) {
        event.preventDefault();
        
        if (!selectedDevice) {
            showToast('No se ha seleccionado un dispositivo', 'error');
            return false;
        }
        
        const formData = new FormData(event.target);
        formData.append('action', 'register_sale');
        
        const cliente = formData.get('cliente_nombre');
        const precio = parseFloat(formData.get('precio_venta'));
        
        if (!cliente || cliente.trim() === '') {
            showToast('Por favor ingresa el nombre del cliente', 'error');
            return false;
        }
        
        if (!precio || precio <= 0) {
            showToast('Por favor ingresa un precio válido', 'error');
            return false;
        }
        
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
                
                setTimeout(() => {
                    if (confirm('¿Desea imprimir el comprobante de venta?')) {
                        window.open(`print_sale_invoice.php?id=${data.venta_id}`, '_blank', 'width=800,height=600');
                    }
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
    };
    
    // ========================================================================
    // UTILIDADES
    // ========================================================================
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatPrice(price) {
        return parseFloat(price).toFixed(2);
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
    
    window.hideToast = hideToast;
    
    // ========================================================================
    // EVENT LISTENERS GLOBALES
    // ========================================================================
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Sistema de Ventas v10.3 - BÚSQUEDA MANUAL');
        console.log('💰 Moneda: Soles (S/)');
        console.log('🎨 Diseño: Moderno y Profesional');
        console.log('🔥 CORREGIDO: Prevención total de recarga');
        console.log('');
        console.log('💡 Características:');
        console.log('   - Búsqueda MANUAL (Enter o botón Buscar)');
        console.log('   - SIN búsqueda automática mientras escribes');
        console.log('   - Enter: Buscar inmediatamente');
        console.log('   - Esc: Cerrar modal');
        console.log('✅ Prevención de recarga: ACTIVA');
        console.log('🚀 Sistema completamente inicializado');
        
        const searchInput = document.getElementById('deviceSearch');
        const clearBtn = document.getElementById('clearBtn');
        
        // ⭐ MOSTRAR/OCULTAR BOTÓN DE LIMPIAR
        searchInput.addEventListener('input', function() {
            const value = this.value.trim();
            clearBtn.classList.toggle('visible', value.length > 0);
        });
        
        // ⭐ ENTER busca inmediatamente - CON PREVENCIÓN DE SUBMIT
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // 🔥 CRÍTICO
                e.stopPropagation(); // 🔥 EXTRA SEGURIDAD
                clearTimeout(searchTimeout);
                
                console.log(`⚡ Enter - Búsqueda inmediata`);
                searchDevices();
                
                return false; // 🔥 TRIPLE SEGURIDAD
            }
        });
        
        // Click en botón limpiar - CON PREVENCIÓN
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault(); // 🔥 PREVENIR CUALQUIER SUBMIT
            e.stopPropagation();
            clearSearch();
        });
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('saleModal');
                if (modal && modal.classList.contains('show')) {
                    closeModal();
                }
            }
        });
        
        // Click fuera del modal
        document.getElementById('saleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    });
    
})();
</script>

</body>
</html>

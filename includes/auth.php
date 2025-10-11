<?php
/**
 * Sistema de Autenticación y Permisos
 * Versión Simple - 2 Roles (Admin y Vendedor)
 * VERSIÓN FINAL CORREGIDA - Sin errores de sintaxis
 */

// ============================================================================
// FUNCIONES DE AUTENTICACIÓN
// ============================================================================

/**
 * Hash de contraseña seguro
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verificar contraseña
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validar formato de email
 */
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Rechazar emails obviamente falsos
    $blacklist = ['test@test.com', 'ejemplo@ejemplo.com', 'example@example.com'];
    if (in_array(strtolower($email), $blacklist)) {
        return false;
    }
    
    return true;
}

// ============================================================================
// CLASE DE AUTENTICACIÓN
// ============================================================================

class Auth {
    private $db;
    
    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            logError("Error al inicializar Auth: " . $e->getMessage());
            throw new Exception("Sistema no disponible temporalmente");
        }
    }
    
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, t.nombre as tienda_nombre 
                FROM usuarios u 
                LEFT JOIN tiendas t ON u.tienda_id = t.id 
                WHERE u.username = ? AND u.activo = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado o inactivo'];
            }
            
            // Verificar si el usuario está bloqueado
            if ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
                $tiempo_restante = ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
                return ['success' => false, 'message' => "Usuario bloqueado. Intenta en $tiempo_restante minutos"];
            }
            
            if (!verifyPassword($password, $user['password'])) {
                // Incrementar intentos fallidos
                $intentos = $user['intentos_login'] + 1;
                
                if ($intentos >= 5) {
                    // Bloquear por 30 minutos
                    $update = $this->db->prepare("
                        UPDATE usuarios 
                        SET intentos_login = ?, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 30 MINUTE) 
                        WHERE id = ?
                    ");
                    $update->execute([$intentos, $user['id']]);
                    
                    logActivity($user['id'], 'login_blocked', 'Usuario bloqueado por intentos fallidos');
                    return ['success' => false, 'message' => 'Usuario bloqueado por múltiples intentos fallidos. Intenta en 30 minutos'];
                } else {
                    $update = $this->db->prepare("UPDATE usuarios SET intentos_login = ? WHERE id = ?");
                    $update->execute([$intentos, $user['id']]);
                    
                    $intentos_restantes = 5 - $intentos;
                    return ['success' => false, 'message' => "Contraseña incorrecta. Te quedan $intentos_restantes intentos"];
                }
            }
            
            // Login exitoso - resetear intentos
            $update = $this->db->prepare("
                UPDATE usuarios 
                SET intentos_login = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() 
                WHERE id = ?
            ");
            $update->execute([$user['id']]);
            
            // Regenerar session ID para prevenir session fixation
            session_regenerate_id(true);
            
            // Guardar en sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['tienda_id'] = $user['tienda_id'];
            $_SESSION['tienda_nombre'] = $user['tienda_nombre'];
            $_SESSION['last_activity'] = time();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
            
            // Registrar actividad
            logActivity($user['id'], 'login', 'Inicio de sesión exitoso');
            
            return ['success' => true, 'user' => $user];
            
        } catch(Exception $e) {
            logError("Error en login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en el sistema'];
        }
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout', 'Cierre de sesión');
        }
        
        // Destruir todas las variables de sesión
        $_SESSION = array();
        
        // Destruir la cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verificar timeout de sesión (30 minutos)
        $timeout = 1800;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            $this->logout();
            return false;
        }
        
        // Verificar que el user agent no haya cambiado (prevenir session hijacking)
        $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $current_user_agent) {
            logError("Posible session hijacking detectado para user_id: " . $_SESSION['user_id']);
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
}

// ============================================================================
// INICIALIZAR INSTANCIA GLOBAL DE AUTH
// ============================================================================

// CORREGIDO: Inicializar solo si no existe
if (!isset($auth)) {
    $auth = new Auth();
}

// ============================================================================
// FUNCIONES DE PERMISOS
// ============================================================================

/**
 * Verificar si el usuario tiene un permiso específico
 */
function hasPermission($permission) {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    
    $rol = $_SESSION['rol'];
    
    // Definir permisos por rol
    $permissions = [
        'admin' => [
            'admin', 'view_all_inventory', 'view_all_sales', 
            'add_devices', 'edit_devices', 'delete_devices',
            'manage_users', 'manage_stores', 'view_reports',
            'manage_products', 'manage_stock', 'manage_settings'
        ],
        'vendedor' => [
            'view_own_inventory', 'sell_devices', 'view_own_sales',
            'add_devices_own_store', 'view_products'
        ]
    ];
    
    $user_permissions = $permissions[$rol] ?? [];
    return in_array($permission, $user_permissions);
}

/**
 * Requerir que el usuario esté logueado
 */
function requireLogin() {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        $redirect_url = 'login.php';
        
        // Agregar parámetro de timeout si la sesión expiró
        if (basename($_SERVER['PHP_SELF']) != 'login.php') {
            $redirect_url .= '?timeout=1';
        }
        
        header('Location: ' . $redirect_url);
        exit();
    }
}

/**
 * Requerir acceso a una página específica
 */
function requirePageAccess($page) {
    requireLogin();
    
    $user = getCurrentUser();
    
    if (!$user) {
        header('Location: login.php');
        exit();
    }
    
    // Definir páginas solo para admin
    $admin_only = [
        'users.php',
        'stores.php',
        'catalog_settings.php',
        'activity_logs.php'
    ];
    
    if (in_array($page, $admin_only) && $user['rol'] !== 'admin') {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Verificar si puede acceder a un dispositivo/tienda
 */
function canAccessDevice($tienda_id, $action = 'view') {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    // Admin puede todo
    if ($user['rol'] === 'admin') {
        return true;
    }
    
    // Vendedor solo puede en su tienda
    if ($user['tienda_id'] == $tienda_id) {
        return true;
    }
    
    return false;
}

/**
 * Obtener datos del usuario actual
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nombre' => $_SESSION['nombre'],
        'email' => $_SESSION['email'] ?? '',
        'rol' => $_SESSION['rol'],
        'tienda_id' => $_SESSION['tienda_id'],
        'tienda_nombre' => $_SESSION['tienda_nombre'] ?? ''
    ];
}

// ============================================================================
// SISTEMA DE LOGS DE ACTIVIDAD
// ============================================================================

/**
 * Registrar actividad del usuario
 */
function logActivity($user_id, $action, $description = '') {
    try {
        $db = getDB();
        
        // Verificar si la tabla existe
        $table_check = $db->query("SHOW TABLES LIKE 'activity_logs'");
        if ($table_check->rowCount() === 0) {
            throw new Exception('Tabla activity_logs no existe');
        }
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
        
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $ip_address,
            $user_agent
        ]);
        
    } catch(Exception $e) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $message = "[$timestamp] User:$user_id | IP:$ip | Action:$action | Description:$description";
        logError("ACTIVITY: " . $message);
    }
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

/**
 * Generar token CSRF
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validar fortaleza de contraseña - MEJORADO
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Debe tener al menos 8 caracteres";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Debe contener al menos una letra mayúscula";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Debe contener al menos una letra minúscula";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Debe contener al menos un número";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Debe contener al menos un carácter especial (!@#$%^&*)";
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * Generar contraseña segura aleatoria
 */
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charLength - 1)];
    }
    
    // Asegurar que cumple con los requisitos
    if (validatePasswordStrength($password) === true) {
        return $password;
    }
    
    // Si no cumple, intentar de nuevo
    return generateSecurePassword($length);
}

/**
 * Verificar si IP está en lista negra
 */
function isBlacklistedIP($ip) {
    // Implementar sistema de blacklist si es necesario
    return false;
}

/**
 * Limpiar sesiones antiguas
 */
function cleanupOldSessions() {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        logError("Error limpiando sesiones: " . $e->getMessage());
        return 0;
    }
}

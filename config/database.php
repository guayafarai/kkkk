<?php
/**
 * Configuración de Base de Datos - PROTEGIDA CON .ENV
 * Sistema de Inventario de Celulares
 * VERSIÓN CORREGIDA - Sin función env() duplicada
 */

// ===================================================================
// CARGAR VARIABLES DE ENTORNO
// ===================================================================

$projectRoot = dirname(__DIR__);

// OPCIÓN A: Con Composer (si instalaste vlucas/phpdotenv)
if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
    
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
    
} 
// OPCIÓN B: Con SimpleDotenv (sin Composer)
else if (file_exists(__DIR__ . '/../includes/dotenv.php')) {
    require_once __DIR__ . '/../includes/dotenv.php';
    
    $dotenv = SimpleDotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

// ===================================================================
// ✅ CORREGIDO: La función env() ahora solo está en dotenv.php
// No se redefine aquí para evitar duplicación
// ===================================================================

// Verificar que env() esté disponible
if (!function_exists('env')) {
    // Si por alguna razón no se cargó dotenv.php, definir una versión básica
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        return $value;
    }
}

// ===================================================================
// CONFIGURACIÓN SEGURA - USA VARIABLES DE ENTORNO
// ===================================================================

// Base de datos
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'chamotvs_ventasdb'));
define('DB_USER', env('DB_USER', 'chamotvs_ventasuser'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Seguridad
define('JWT_SECRET', env('JWT_SECRET', 'CHANGE_THIS_SECRET_KEY'));
define('SESSION_NAME', env('SESSION_NAME', 'phone_inventory_session'));

// Sistema
define('SYSTEM_NAME', env('SYSTEM_NAME', 'Mobile Service'));
define('SYSTEM_VERSION', env('SYSTEM_VERSION', '1.0.0'));
define('TIMEZONE', env('TIMEZONE', 'America/Lima'));

// Modo desarrollo
define('DEVELOPMENT_MODE', env('APP_ENV', 'production') === 'development');

// Configurar zona horaria
date_default_timezone_set(TIMEZONE);

// ===================================================================
// FUNCIONES AUXILIARES GLOBALES
// ===================================================================

if (!function_exists('logError')) {
    function logError($message) {
        $logDir = __DIR__ . '/../logs';
        $logFile = $logDir . '/error.log';
        
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $formatted = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        @error_log($formatted, 3, $logFile);
    }
}

// Validación de seguridad
if (DB_PASS === '' && env('APP_ENV') === 'production') {
    logError('⚠️ ERROR CRÍTICO: Contraseña de base de datos vacía en producción');
    die('⚠️ ERROR: Configuración de base de datos incompleta. Verifica el archivo .env');
}

if (JWT_SECRET === 'CHANGE_THIS_SECRET_KEY') {
    logError('⚠️ WARNING: JWT_SECRET sin cambiar. Sistema inseguro.');
}

// ===================================================================
// CLASE DE CONEXIÓN A BASE DE DATOS
// ===================================================================

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $exception) {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] Error de conexión a BD\n";
            
            if (DEVELOPMENT_MODE || env('APP_DEBUG') === true) {
                $errorMessage .= $exception->getMessage() . "\n";
                error_log($errorMessage);
                die("⚠️ Error de conexión: " . $exception->getMessage());
            }
            
            logError($errorMessage);
            die("⚠️ Error al conectar con la base de datos. Por favor contacta al administrador.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ===================================================================
// FUNCIONES AUXILIARES ADICIONALES
// ===================================================================

if (!function_exists('getDB')) {
    function getDB() {
        return Database::getInstance()->getConnection();
    }
}

if (!function_exists('setSecurityHeaders')) {
    function setSecurityHeaders() {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: same-origin');
        header("Permissions-Policy: geolocation=(), microphone=()");
        
        if (env('APP_ENV') === 'production') {
            header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com");
        }
    }
}

if (!function_exists('startSecureSession')) {
    function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = env('APP_ENV') === 'production' && isset($_SERVER['HTTPS']);
            
            session_name(SESSION_NAME);
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $isSecure,
                'use_strict_mode' => true,
                'cookie_samesite' => 'Lax',
                'gc_maxlifetime' => 3600
            ]);
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// ===================================================================
// VERIFICACIÓN DE SEGURIDAD AL CARGAR
// ===================================================================

if (env('APP_DEBUG') === true && php_sapi_name() === 'cli') {
    echo "✅ Configuración cargada correctamente\n";
    echo "   - DB_HOST: " . DB_HOST . "\n";
    echo "   - DB_NAME: " . DB_NAME . "\n";
    echo "   - DB_USER: " . DB_USER . "\n";
    echo "   - Entorno: " . env('APP_ENV', 'production') . "\n";
    echo "   - DEVELOPMENT_MODE: " . (DEVELOPMENT_MODE ? 'true' : 'false') . "\n";
}

if (function_exists('logError')) {
    logError("Sistema inicializado correctamente - Entorno: " . env('APP_ENV', 'production'));
}

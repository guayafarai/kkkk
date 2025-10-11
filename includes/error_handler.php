<?php
/**
 * Manejador centralizado de errores para el sistema
 * Incluir después de database.php
 * VERSIÓN FINAL CORREGIDA
 */

// Definir modo desarrollo ANTES de usarlo
if (!defined('DEVELOPMENT_MODE')) {
    define('DEVELOPMENT_MODE', env('APP_ENV', 'production') === 'development');
}

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', DEVELOPMENT_MODE ? 1 : 0);
ini_set('log_errors', 1);

// Crear directorio de logs si no existe
$log_dir = dirname(__DIR__) . '/logs';
if (!file_exists($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

ini_set('error_log', $log_dir . '/php_errors.log');

/**
 * Manejador personalizado de errores PHP
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING', 
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    
    $error_type = $error_types[$errno] ?? 'UNKNOWN';
    
    $log_message = sprintf(
        "[%s] %s: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $error_type,
        $errstr,
        $errfile,
        $errline
    );
    
    error_log($log_message);
    
    // En desarrollo, mostrar errores detallados
    if (DEVELOPMENT_MODE) {
        echo "<div style='background: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin: 10px 0; font-family: monospace;'>";
        echo "<strong>[$error_type]</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline<br>";
        echo "</div>";
    }
    
    // No detener la ejecución para errores menores
    if ($errno !== E_ERROR && $errno !== E_USER_ERROR && $errno !== E_CORE_ERROR) {
        return true;
    }
    
    return false;
}

/**
 * Manejador de excepciones no capturadas
 */
function customExceptionHandler($exception) {
    $log_message = sprintf(
        "[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    
    error_log($log_message);
    
    // Mostrar página de error genérica en producción
    if (!DEVELOPMENT_MODE) {
        http_response_code(500);
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Error del Sistema</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0; 
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-container { 
                    max-width: 600px; 
                    background: white; 
                    padding: 40px; 
                    border-radius: 12px; 
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
                    text-align: center; 
                }
                .error-icon { 
                    font-size: 64px; 
                    margin-bottom: 20px; 
                }
                h1 { 
                    color: #2c3e50; 
                    margin-bottom: 15px;
                    font-size: 28px;
                    font-weight: 600;
                }
                p { 
                    color: #7f8c8d; 
                    line-height: 1.6; 
                    margin-bottom: 10px;
                    font-size: 16px;
                }
                .btn { 
                    display: inline-block; 
                    padding: 12px 24px; 
                    background: #667eea; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 6px; 
                    margin: 10px 5px;
                    transition: all 0.3s;
                    font-weight: 500;
                }
                .btn:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                .error-code {
                    font-family: 'Courier New', monospace;
                    background: #f8f9fa;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    color: #6c757d;
                    margin-top: 30px;
                    border: 1px solid #dee2e6;
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <div class='error-icon'>⚠️</div>
                <h1>Error del Sistema</h1>
                <p>Lo sentimos, ha ocurrido un error inesperado.</p>
                <p>Nuestro equipo ha sido notificado y estamos trabajando para solucionarlo.</p>
                <div style='margin-top: 30px;'>
                    <a href='javascript:history.back()' class='btn'>Volver Atrás</a>
                    <a href='/public/dashboard.php' class='btn'>Ir al Dashboard</a>
                </div>
                <div class='error-code'>Error ID: " . uniqid('ERR-') . "</div>
            </div>
        </body>
        </html>";
        exit();
    } else {
        // En desarrollo, mostrar detalles completos
        http_response_code(500);
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Excepción No Capturada</title>
            <style>
                body { 
                    font-family: 'Courier New', monospace; 
                    background: #1a1a1a; 
                    color: #f0f0f0; 
                    padding: 20px; 
                    margin: 0;
                    line-height: 1.6;
                }
                .exception-container {
                    background: #2d2d2d;
                    border-left: 4px solid #f44336;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 4px;
                }
                h2 { 
                    color: #ff6b6b; 
                    margin-top: 0;
                    font-size: 24px;
                }
                .error-detail {
                    background: #1a1a1a;
                    padding: 12px;
                    margin: 10px 0;
                    border-radius: 4px;
                    overflow-x: auto;
                }
                .error-label {
                    color: #4ecdc4;
                    font-weight: bold;
                    display: inline-block;
                    min-width: 100px;
                }
                .stack-trace {
                    background: #1a1a1a;
                    padding: 15px;
                    margin-top: 15px;
                    border-radius: 4px;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    font-size: 12px;
                    line-height: 1.5;
                    border: 1px solid #444;
                }
                .help-text {
                    background: #2a5298;
                    padding: 15px;
                    margin-top: 20px;
                    border-radius: 4px;
                    color: #fff;
                }
            </style>
        </head>
        <body>
            <div class='exception-container'>
                <h2>⚠️ Excepción No Capturada</h2>
                <div class='error-detail'>
                    <span class='error-label'>Tipo:</span> " . get_class($exception) . "
                </div>
                <div class='error-detail'>
                    <span class='error-label'>Mensaje:</span> " . htmlspecialchars($exception->getMessage()) . "
                </div>
                <div class='error-detail'>
                    <span class='error-label'>Archivo:</span> " . htmlspecialchars($exception->getFile()) . "
                </div>
                <div class='error-detail'>
                    <span class='error-label'>Línea:</span> " . $exception->getLine() . "
                </div>
                <div class='stack-trace'>
                    <span class='error-label'>Stack Trace:</span>
" . htmlspecialchars($exception->getTraceAsString()) . "
                </div>
                <div class='help-text'>
                    <strong>💡 Modo Desarrollo Activo</strong><br>
                    Este error completo solo se muestra porque APP_ENV=development<br>
                    En producción, se mostrará un mensaje genérico al usuario.
                </div>
            </div>
        </body>
        </html>";
    }
    
    exit();
}

/**
 * Manejador de errores fatales
 */
function shutdownHandler() {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log_message = sprintf(
            "[%s] FATAL ERROR: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        error_log($log_message);
        
        if (!DEVELOPMENT_MODE) {
            http_response_code(500);
            echo "<!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Error Crítico</title>
                <style>
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        margin: 0; 
                        padding: 20px;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .error-container { 
                        max-width: 500px; 
                        background: white; 
                        padding: 40px; 
                        border-radius: 12px; 
                        box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
                        text-align: center; 
                    }
                    .error-icon { font-size: 64px; margin-bottom: 20px; }
                    h1 { color: #e74c3c; margin-bottom: 15px; font-size: 24px; }
                    p { color: #7f8c8d; line-height: 1.6; }
                    .btn {
                        display: inline-block;
                        padding: 12px 24px;
                        background: #667eea;
                        color: white;
                        text-decoration: none;
                        border-radius: 6px;
                        margin-top: 20px;
                        transition: all 0.3s;
                    }
                    .btn:hover {
                        background: #5568d3;
                        transform: translateY(-2px);
                    }
                </style>
            </head>
            <body>
                <div class='error-container'>
                    <div class='error-icon'>💥</div>
                    <h1>Error Crítico del Sistema</h1>
                    <p>El sistema ha encontrado un error crítico y no puede continuar.</p>
                    <p>Por favor contacta al administrador del sistema.</p>
                    <a href='/' class='btn'>Volver al Inicio</a>
                </div>
            </body>
            </html>";
        } else {
            echo "<div style='background: #000; color: #f00; padding: 20px; font-family: monospace;'>";
            echo "<h2>💥 FATAL ERROR</h2>";
            echo "<p><strong>Message:</strong> {$error['message']}</p>";
            echo "<p><strong>File:</strong> {$error['file']}</p>";
            echo "<p><strong>Line:</strong> {$error['line']}</p>";
            echo "</div>";
        }
    }
}

/**
 * Función para registrar eventos del sistema
 */
function logSystemEvent($event, $details = '', $level = 'INFO') {
    $log_file = dirname(__DIR__) . '/logs/system.log';
    
    $log_entry = sprintf(
        "[%s] [%s] %s | %s | IP: %s | User: %s\n",
        date('Y-m-d H:i:s'),
        $level,
        $event,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        $_SESSION['username'] ?? 'guest'
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Función de utilidad para debugging (solo en desarrollo)
 */
function debug($data, $label = 'DEBUG') {
    if (DEVELOPMENT_MODE) {
        echo "<div style='background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 10px 0; font-family: monospace; border-radius: 4px;'>";
        echo "<strong style='color: #1976d2; font-size: 14px;'>🐛 $label:</strong><br>";
        echo "<pre style='margin: 10px 0; color: #424242; overflow-x: auto;'>" . htmlspecialchars(print_r($data, true)) . "</pre>";
        echo "</div>";
    }
}

/**
 * Función para sanitizar output de errores
 */
function sanitizeErrorOutput($string) {
    // Remover rutas absolutas que puedan revelar estructura del servidor
    $string = preg_replace('#/home/[^/]+/#', '/.../', $string);
    $string = preg_replace('#C:\\\\[^\\\\]+\\\\#', 'C:\\...\\', $string);
    $string = preg_replace('#/var/www/[^/]+/#', '/web/.../', $string);
    
    return $string;
}

/**
 * Registrar error de seguridad
 */
function logSecurityEvent($event, $severity = 'WARNING') {
    $log_file = dirname(__DIR__) . '/logs/security.log';
    
    $log_entry = sprintf(
        "[%s] [%s] %s | IP: %s | User-Agent: %s | User: %s\n",
        date('Y-m-d H:i:s'),
        $severity,
        $event,
        $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        substr($_SERVER['HTTP_USER_AGENT'] ?? 'N/A', 0, 100),
        $_SESSION['username'] ?? 'guest'
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Si es crítico, también enviar a error_log
    if ($severity === 'CRITICAL') {
        error_log("SECURITY ALERT: $event");
    }
}

// Registrar manejadores de errores
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');
register_shutdown_function('shutdownHandler');

// Registrar evento de carga del sistema
logSystemEvent('error_handler_load', 'Error handler initialized - Mode: ' . (DEVELOPMENT_MODE ? 'DEV' : 'PROD'));

// Mostrar información de debug si está activado
if (DEVELOPMENT_MODE && php_sapi_name() !== 'cli') {
    echo "<!-- 🔧 Error Handler Loaded - Development Mode Active -->\n";
    echo "<!-- Environment: " . env('APP_ENV', 'production') . " -->\n";
    echo "<!-- Debug Mode: " . (env('APP_DEBUG') ? 'ON' : 'OFF') . " -->\n";
}

<?php
/**
 * DOTENV SIMPLE - Sin dependencias
 * Para HostingPlatino sin Composer
 * VERSIÓN 2.1 CORREGIDA
 * 
 * CORRECCIONES:
 * ✅ Validación de nombres de variables permite minúsculas
 * ✅ Mejor manejo de errores
 * ✅ Seguridad mejorada
 */

class SimpleDotenv {
    protected $path;
    protected $vars = [];
    protected $errors = [];

    public function __construct($path) {
        $this->path = $path;
    }

    public static function createImmutable($path) {
        return new self($path);
    }

    /**
     * Cargar archivo .env con validación
     */
    public function load() {
        $envFile = $this->path . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception("Archivo .env no encontrado en: {$envFile}");
        }

        if (!is_readable($envFile)) {
            throw new Exception("Archivo .env no es legible. Verifica permisos.");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            throw new Exception("Error leyendo archivo .env");
        }
        
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            
            // Ignorar comentarios y líneas vacías
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parsear línea KEY=VALUE
            if (strpos($line, '=') === false) {
                $this->errors[] = "Línea " . ($lineNumber + 1) . ": Formato inválido (falta '=')";
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // ✅ CORREGIDO: Validación permite mayúsculas, minúsculas y guiones bajos
            if (!$this->isValidVariableName($name)) {
                $this->errors[] = "Línea " . ($lineNumber + 1) . ": Nombre de variable inválido '$name'";
                continue;
            }

            // Remover comillas externas preservando espacios internos
            $value = $this->parseValue($value);

            // Guardar en $_ENV y $_SERVER solo si no existe
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                
                // putenv con validación
                if ($this->isValidVariableName($name)) {
                    putenv("{$name}={$value}");
                }
                
                $this->vars[$name] = $value;
            }
        }

        // Registrar errores si los hay
        if (!empty($this->errors)) {
            error_log("Advertencias al cargar .env:\n" . implode("\n", $this->errors));
        }

        return $this;
    }

    /**
     * Cargar de forma segura sin lanzar excepciones
     */
    public function safeLoad() {
        try {
            $this->load();
        } catch (Exception $e) {
            error_log('Error cargando .env: ' . $e->getMessage());
            
            // En desarrollo, mostrar el error
            if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
                echo "<!-- Error cargando .env: " . htmlspecialchars($e->getMessage()) . " -->\n";
            }
        }
        return $this;
    }
    
    /**
     * ✅ CORREGIDO: Validar nombre de variable de entorno
     * Ahora permite mayúsculas, minúsculas, números y guiones bajos
     */
    private function isValidVariableName($name) {
        // Permite mayúsculas, minúsculas y guiones bajos
        // Debe empezar con letra o guion bajo
        // Acepta tanto APP_ENV como app_env
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
    }

    /**
     * Parsear valor removiendo comillas y escapando
     */
    private function parseValue($value) {
        $value = trim($value);
        
        // Remover comillas dobles externas
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
            // Procesar secuencias de escape en comillas dobles
            $value = $this->processEscapeSequences($value);
        }
        // Remover comillas simples externas (sin procesar escapes)
        elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }
        
        return $value;
    }

    /**
     * Procesar secuencias de escape comunes
     */
    private function processEscapeSequences($value) {
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\"' => '"',
            '\\\\' => '\\'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }
    
    /**
     * Obtener variables cargadas
     */
    public function getVars() {
        return $this->vars;
    }

    /**
     * Obtener errores encontrados
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Verificar si una variable existe
     */
    public function has($key) {
        return array_key_exists($key, $this->vars);
    }

    /**
     * Obtener valor de variable
     */
    public function get($key, $default = null) {
        return $this->vars[$key] ?? $default;
    }
}

/**
 * Función helper para obtener variables de entorno
 * Solo se define si no existe previamente
 */
if (!function_exists('env')) {
    /**
     * Obtener valor de variable de entorno
     * 
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    function env($key, $default = null) {
        // Buscar en $_ENV, $_SERVER y getenv() en ese orden
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convertir strings booleanos a valores reales
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

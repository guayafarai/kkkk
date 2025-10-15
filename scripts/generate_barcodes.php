<?php
/**
 * SCRIPT: Generar Códigos de Barras Faltantes
 * Genera códigos automáticamente para productos sin código
 * 
 * INSTRUCCIONES:
 * 1. Guarda este archivo como: scripts/generate_missing_barcodes.php
 * 2. Ejecútalo desde el navegador: http://tu-dominio/scripts/generate_missing_barcodes.php
 * 3. O desde terminal: php scripts/generate_missing_barcodes.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de ruta base
$base_dir = dirname(__DIR__);

// Incluir archivos necesarios
require_once $base_dir . '/config/database.php';
require_once $base_dir . '/includes/auth.php';

// Verificar autenticación (solo para web)
if (php_sapi_name() !== 'cli') {
    startSecureSession();
    requireLogin();
    
    $user = getCurrentUser();
    if (!$user || !hasPermission('admin')) {
        die('❌ Error: Solo administradores pueden ejecutar este script');
    }
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Generar Códigos de Barras</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:20px;background:#f5f5f5}";
echo ".success{background:#d4edda;color:#155724;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #c3e6cb}";
echo ".error{background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #f5c6cb}";
echo ".info{background:#d1ecf1;color:#0c5460;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #bee5eb}";
echo ".warning{background:#fff3cd;color:#856404;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #ffeeba}";
echo ".code{background:#272822;color:#f8f8f2;padding:3px 8px;border-radius:3px;font-family:monospace;font-size:14px}";
echo "h1{color:#333}h2{color:#666;margin-top:30px}table{width:100%;border-collapse:collapse;margin:20px 0;background:white}";
echo "th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}th{background:#6366f1;color:white}";
echo "tr:hover{background:#f5f5f5}.btn{display:inline-block;padding:10px 20px;background:#6366f1;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 0 0}";
echo ".btn:hover{background:#4f46e5}</style></head><body>";

echo "<h1>🔖 Generador de Códigos de Barras Faltantes</h1>";

try {
    $db = getDB();
    
    // PASO 1: Buscar productos sin código
    echo "<div class='info'><strong>📋 Paso 1:</strong> Buscando productos sin código de barras...</div>";
    
    $stmt = $db->query("
        SELECT id, nombre, tipo, marca, modelo_compatible 
        FROM productos 
        WHERE (codigo_producto IS NULL OR codigo_producto = '')
        AND activo = 1
        ORDER BY tipo, nombre
    ");
    
    $productos_sin_codigo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_sin_codigo = count($productos_sin_codigo);
    
    if ($total_sin_codigo === 0) {
        echo "<div class='success'><strong>✅ ¡Excelente!</strong> Todos los productos activos ya tienen código de barras.</div>";
        
        // Mostrar estadísticas
        $stats = $db->query("
            SELECT 
                tipo,
                COUNT(*) as total,
                SUM(CASE WHEN codigo_producto IS NOT NULL AND codigo_producto != '' THEN 1 ELSE 0 END) as con_codigo
            FROM productos 
            WHERE activo = 1
            GROUP BY tipo
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>📊 Estadísticas</h2>";
        echo "<table><tr><th>Tipo</th><th>Total</th><th>Con Código</th><th>%</th></tr>";
        foreach ($stats as $stat) {
            $porcentaje = ($stat['con_codigo'] / $stat['total']) * 100;
            echo "<tr>";
            echo "<td>" . strtoupper($stat['tipo']) . "</td>";
            echo "<td>{$stat['total']}</td>";
            echo "<td>{$stat['con_codigo']}</td>";
            echo "<td>" . number_format($porcentaje, 1) . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<a href='../pages/products.php' class='btn'>← Volver a Productos</a>";
        echo "</body></html>";
        exit;
    }
    
    echo "<div class='warning'><strong>⚠️ Encontrados:</strong> {$total_sin_codigo} productos sin código de barras</div>";
    
    // Mostrar productos encontrados
    echo "<h2>📦 Productos que necesitan código:</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Marca</th></tr>";
    foreach ($productos_sin_codigo as $prod) {
        echo "<tr>";
        echo "<td>{$prod['id']}</td>";
        echo "<td>" . htmlspecialchars($prod['nombre']) . "</td>";
        echo "<td>" . strtoupper($prod['tipo']) . "</td>";
        echo "<td>" . htmlspecialchars($prod['marca'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // PASO 2: Generar códigos
    echo "<div class='info'><strong>🔄 Paso 2:</strong> Generando códigos de barras...</div>";
    
    $generados = 0;
    $errores = 0;
    $codigos_generados = [];
    
    $db->beginTransaction();
    
    foreach ($productos_sin_codigo as $producto) {
        try {
            // Determinar prefijo según tipo
            $prefix = ($producto['tipo'] === 'accesorio') ? 'ACC' : 'REP';
            
            // Generar código único
            $intentos = 0;
            do {
                if ($intentos > 10) {
                    throw new Exception("No se pudo generar código único para producto ID: {$producto['id']}");
                }
                
                // Formato: TIPO + YYYYMMDD + 4 dígitos aleatorios
                $codigo = $prefix . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                
                // Verificar que no exista
                $check = $db->prepare("SELECT id FROM productos WHERE codigo_producto = ?");
                $check->execute([$codigo]);
                
                $intentos++;
            } while ($check->fetch());
            
            // Actualizar producto
            $update = $db->prepare("UPDATE productos SET codigo_producto = ? WHERE id = ?");
            $update->execute([$codigo, $producto['id']]);
            
            $codigos_generados[] = [
                'id' => $producto['id'],
                'nombre' => $producto['nombre'],
                'codigo' => $codigo,
                'tipo' => $producto['tipo']
            ];
            
            $generados++;
            
        } catch (Exception $e) {
            $errores++;
            echo "<div class='error'>❌ Error en producto ID {$producto['id']}: " . $e->getMessage() . "</div>";
        }
    }
    
    if ($errores === 0) {
        $db->commit();
        echo "<div class='success'><strong>✅ ¡Proceso completado!</strong> Se generaron {$generados} códigos de barras exitosamente.</div>";
    } else {
        $db->rollBack();
        echo "<div class='error'><strong>❌ Proceso cancelado.</strong> Se encontraron {$errores} errores. No se guardó ningún cambio.</div>";
        echo "</body></html>";
        exit;
    }
    
    // PASO 3: Mostrar resultados
    echo "<h2>✨ Códigos Generados</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Producto</th><th>Tipo</th><th>Código Generado</th></tr>";
    
    foreach ($codigos_generados as $item) {
        echo "<tr>";
        echo "<td>{$item['id']}</td>";
        echo "<td>" . htmlspecialchars($item['nombre']) . "</td>";
        echo "<td>" . strtoupper($item['tipo']) . "</td>";
        echo "<td><span class='code'>{$item['codigo']}</span></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Registrar actividad si está disponible
    if (function_exists('logActivity') && isset($user)) {
        logActivity($user['id'], 'generate_barcodes', "Generados {$generados} códigos de barras automáticamente");
    }
    
    // PASO 4: Verificación final
    echo "<div class='info'><strong>🔍 Paso 3:</strong> Verificación final...</div>";
    
    $verificacion = $db->query("
        SELECT COUNT(*) as total
        FROM productos 
        WHERE activo = 1 
        AND (codigo_producto IS NULL OR codigo_producto = '')
    ")->fetch();
    
    if ($verificacion['total'] == 0) {
        echo "<div class='success'><strong>✅ Verificado:</strong> Todos los productos activos ahora tienen código de barras.</div>";
    } else {
        echo "<div class='warning'><strong>⚠️ Atención:</strong> Aún quedan {$verificacion['total']} productos sin código.</div>";
    }
    
    // Estadísticas finales
    echo "<h2>📊 Resumen Final</h2>";
    echo "<table>";
    echo "<tr><th>Concepto</th><th>Cantidad</th></tr>";
    echo "<tr><td>Productos procesados</td><td>{$total_sin_codigo}</td></tr>";
    echo "<tr><td>Códigos generados exitosamente</td><td><strong style='color:green'>{$generados}</strong></td></tr>";
    echo "<tr><td>Errores encontrados</td><td><strong style='color:red'>{$errores}</strong></td></tr>";
    echo "</table>";
    
    echo "<a href='../pages/products.php' class='btn'>✅ Ver Productos Actualizados</a>";
    echo "<a href='javascript:location.reload()' class='btn' style='background:#64748b'>🔄 Ejecutar de Nuevo</a>";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "<div class='error'><strong>❌ Error crítico:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre style='background:#f8f8f8;padding:15px;border-radius:5px;overflow:auto'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

echo "</body></html>";
?>

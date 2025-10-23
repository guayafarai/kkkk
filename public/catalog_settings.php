<?php
/**
 * CONFIGURACIÓN DEL CATÁLOGO PÚBLICO
 * Sistema de Inventario de Celulares
 * Versión funcional completa
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

// Solo administradores pueden acceder
if (!hasPermission('admin')) {
    header('Location: dashboard.php');
    exit();
}

$user = getCurrentUser();
$db = getDB();
$message = '';
$error = '';

// Procesar guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    // Verificar token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf_token)) {
        $error = 'Token CSRF inválido. Recarga la página e intenta nuevamente.';
    } else {
        try {
        $db->beginTransaction();
        
        // Lista de todas las configuraciones posibles
        $all_configs = [
            'catalogo_activo',
            'catalogo_titulo',
            'catalogo_descripcion',
            'catalogo_meta_description',
            'catalogo_meta_keywords',
            'catalogo_items_por_pagina',
            'catalogo_email',
            'catalogo_telefono',
            'catalogo_whatsapp',
            'catalogo_horario',
            'catalogo_direccion',
            'catalogo_mensaje_whatsapp',
            'catalogo_color_principal',
            'catalogo_color_secundario',
            'catalogo_mostrar_celulares',
            'catalogo_mostrar_productos',
            'catalogo_mostrar_precios',
            'catalogo_mostrar_stock',
            'catalogo_facebook',
            'catalogo_instagram',
            'catalogo_twitter'
        ];
        
        foreach ($all_configs as $key) {
            // Para checkboxes, si no viene en POST, el valor es '0'
            if (in_array($key, ['catalogo_activo', 'catalogo_mostrar_celulares', 'catalogo_mostrar_productos', 'catalogo_mostrar_precios', 'catalogo_mostrar_stock'])) {
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                // Para otros campos, obtener el valor del POST
                $value = isset($_POST[$key]) ? sanitize($_POST[$key]) : '';
            }
            
            // Actualizar o insertar configuración
            $check_stmt = $db->prepare("SELECT id FROM configuracion_catalogo WHERE clave = ?");
            $check_stmt->execute([$key]);
            
            if ($check_stmt->fetch()) {
                // Actualizar existente
                $stmt = $db->prepare("UPDATE configuracion_catalogo SET valor = ?, actualizado_por = ?, fecha_actualizacion = NOW() WHERE clave = ?");
                $stmt->execute([$value, $user['id'], $key]);
            } else {
                // Insertar nuevo
                $stmt = $db->prepare("INSERT INTO configuracion_catalogo (clave, valor, tipo, descripcion, actualizado_por) VALUES (?, ?, 'text', '', ?)");
                $stmt->execute([$key, $value, $user['id']]);
            }
        }
        
        $db->commit();
        logActivity($user['id'], 'update_catalog_config', 'Configuración del catálogo actualizada');
        $message = 'Configuración guardada correctamente';
        
    } catch(Exception $e) {
        $db->rollback();
        logError("Error al guardar configuración: " . $e->getMessage());
        $error = 'Error al guardar la configuración: ' . $e->getMessage();
    }
    }
}
// Obtener configuración actual
try {
    $config_stmt = $db->query("SELECT * FROM configuracion_catalogo ORDER BY clave");
    $configs = $config_stmt->fetchAll();
    
    // Convertir a array asociativo
    $config_array = [];
    foreach ($configs as $conf) {
        $config_array[$conf['clave']] = [
            'valor' => $conf['valor'],
            'tipo' => $conf['tipo'],
            'descripcion' => $conf['descripcion']
        ];
    }
    
    // Valores por defecto si no existen
    $defaults = [
        'catalogo_activo' => '1',
        'catalogo_titulo' => 'Catálogo de Celulares y Accesorios',
        'catalogo_descripcion' => 'Los mejores celulares y accesorios de calidad',
        'catalogo_meta_description' => 'Encuentra los mejores celulares, accesorios y repuestos para tu móvil',
        'catalogo_meta_keywords' => 'celulares, smartphones, accesorios, repuestos, móviles',
        'catalogo_items_por_pagina' => '20',
        'catalogo_email' => '',
        'catalogo_telefono' => '',
        'catalogo_whatsapp' => '',
        'catalogo_horario' => 'Lun-Vie: 9am-6pm',
        'catalogo_direccion' => '',
        'catalogo_mensaje_whatsapp' => 'Hola, me interesa información sobre sus productos',
        'catalogo_color_principal' => '#667eea',
        'catalogo_color_secundario' => '#764ba2',
        'catalogo_mostrar_celulares' => '1',
        'catalogo_mostrar_productos' => '1',
        'catalogo_mostrar_precios' => '1',
        'catalogo_mostrar_stock' => '1',
        'catalogo_facebook' => '',
        'catalogo_instagram' => '',
        'catalogo_twitter' => ''
    ];
    
    foreach ($defaults as $key => $default_value) {
        if (!isset($config_array[$key])) {
            $config_array[$key] = [
                'valor' => $default_value,
                'tipo' => 'text',
                'descripcion' => ''
            ];
        }
    }
    
} catch(Exception $e) {
    logError("Error al obtener configuración: " . $e->getMessage());
    $config_array = [];
    $error = 'Error al cargar la configuración';
}

// Función helper para obtener configuración
function getCatalogConfig($clave, $default = '') {
    global $config_array;
    return $config_array[$clave]['valor'] ?? $default;
}

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Catálogo - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .config-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid #3b82f6;
        }
        
        .preview-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px dashed #f59e0b;
        }
        
        .color-preview {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .color-preview:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <?php renderNavbar('catalog_settings'); ?>
    
    <main class="page-content">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-900">Configuración del Catálogo Público</h2>
                <p class="text-gray-600">Personaliza la apariencia y contenido del catálogo visible al público</p>
                <div class="mt-2">
                    <a href="../index.php" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Ver catálogo público
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <!-- Configuración General -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="config-section p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">⚙️ Configuración General</h3>
                        <p class="text-sm text-blue-700">Ajustes básicos del catálogo público</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="catalogo_activo" value="1" 
                                       <?php echo getCatalogConfig('catalogo_activo', '1') == '1' ? 'checked' : ''; ?>
                                       class="mr-2 rounded">
                                <span class="font-medium">Catálogo Activo</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1">Activar/desactivar el catálogo público</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Título del Catálogo</label>
                            <input type="text" name="catalogo_titulo" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_titulo')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                            <input type="text" name="catalogo_descripcion" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_descripcion')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Items por Página</label>
                            <input type="number" name="catalogo_items_por_pagina" min="5" max="100"
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_items_por_pagina', '20')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="config-section p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">📞 Información de Contacto</h3>
                        <p class="text-sm text-blue-700">Datos de contacto mostrados en el catálogo</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="catalogo_email" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_email')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="text" name="catalogo_telefono" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_telefono')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp (sin +)</label>
                            <input type="text" name="catalogo_whatsapp" placeholder="51999999999"
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_whatsapp')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Ejemplo: 51999999999 (código país + número)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Horario de Atención</label>
                            <input type="text" name="catalogo_horario" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_horario')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                            <input type="text" name="catalogo_direccion" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_direccion')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje de WhatsApp</label>
                            <textarea name="catalogo_mensaje_whatsapp" rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars(getCatalogConfig('catalogo_mensaje_whatsapp')); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Apariencia y Colores -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="config-section p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">🎨 Apariencia y Colores</h3>
                        <p class="text-sm text-blue-700">Personaliza los colores del catálogo</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color Principal</label>
                            <div class="flex gap-2 items-center">
                                <input type="color" name="catalogo_color_principal" id="color_principal"
                                       value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_color_principal', '#667eea')); ?>"
                                       class="h-12 w-20 border border-gray-300 rounded cursor-pointer">
                                <input type="text" id="color_principal_text" readonly
                                       value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_color_principal', '#667eea')); ?>"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color Secundario</label>
                            <div class="flex gap-2 items-center">
                                <input type="color" name="catalogo_color_secundario" id="color_secundario"
                                       value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_color_secundario', '#764ba2')); ?>"
                                       class="h-12 w-20 border border-gray-300 rounded cursor-pointer">
                                <input type="text" id="color_secundario_text" readonly
                                       value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_color_secundario', '#764ba2')); ?>"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vista previa de colores -->
                    <div class="preview-box p-4 rounded-lg mt-4">
                        <p class="text-sm font-medium text-yellow-800 mb-2">Vista Previa:</p>
                        <div id="colorPreview" class="p-4 rounded-lg text-white text-center font-bold">
                            Catálogo de Productos
                        </div>
                    </div>
                </div>

                <!-- Opciones de Visualización -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="config-section p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">👁️ Opciones de Visualización</h3>
                        <p class="text-sm text-blue-700">Controla qué se muestra en el catálogo</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="catalogo_mostrar_celulares" value="1" 
                                       <?php echo getCatalogConfig('catalogo_mostrar_celulares', '1') == '1' ? 'checked' : ''; ?>
                                       class="mr-2 rounded">
                                <span class="font-medium">Mostrar Celulares</span>
                            </label>
                        </div>
                        
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="catalogo_mostrar_productos" value="1" 
                                       <?php echo getCatalogConfig('catalogo_mostrar_productos', '1') == '1' ? 'checked' : ''; ?>
                                       class="mr-2 rounded">
                                <span class="font-medium">Mostrar Productos/Accesorios</span>
                            </label>
                        </div>
                        
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="catalogo_mostrar_precios" value="1" 
                                       <?php echo getCatalogConfig('catalogo_mostrar_precios', '1') == '1' ? 'checked' : ''; ?>
                                       class="mr-2 rounded">
                                <span class="font-medium">Mostrar Precios</span>
                            </label>
                        </div>
                        
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="catalogo_mostrar_stock" value="1" 
                                       <?php echo getCatalogConfig('catalogo_mostrar_stock', '1') == '1' ? 'checked' : ''; ?>
                                       class="mr-2 rounded">
                                <span class="font-medium">Mostrar Stock</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Redes Sociales -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="config-section p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">🔗 Redes Sociales</h3>
                        <p class="text-sm text-blue-700">Enlaces a redes sociales (dejar vacío para ocultar)</p>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Facebook</label>
                            <input type="url" name="catalogo_facebook" placeholder="https://facebook.com/tuempresa"
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_facebook')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input type="url" name="catalogo_instagram" placeholder="https://instagram.com/tuempresa"
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_instagram')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Twitter</label>
                            <input type="url" name="catalogo_twitter" placeholder="https://twitter.com/tuempresa"
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_twitter')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- SEO -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="config-section p-4 rounded-lg mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">🔍 SEO (Optimización para Buscadores)</h3>
                        <p class="text-sm text-blue-700">Mejora la visibilidad en motores de búsqueda</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                            <textarea name="catalogo_meta_description" rows="2" maxlength="160"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars(getCatalogConfig('catalogo_meta_description')); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Máximo 160 caracteres</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Keywords</label>
                            <input type="text" name="catalogo_meta_keywords" 
                                   value="<?php echo htmlspecialchars(getCatalogConfig('catalogo_meta_keywords')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Separadas por comas</p>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end gap-4 sticky bottom-4 bg-white p-4 rounded-lg shadow-lg">
                    <a href="dashboard.php" class="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" name="save_config" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Actualizar campo de texto al cambiar color
        document.getElementById('color_principal').addEventListener('input', function() {
            document.getElementById('color_principal_text').value = this.value;
            updateColorPreview();
        });

        document.getElementById('color_secundario').addEventListener('input', function() {
            document.getElementById('color_secundario_text').value = this.value;
            updateColorPreview();
        });

        // Actualizar vista previa de colores
        function updateColorPreview() {
            const color1 = document.getElementById('color_principal').value;
            const color2 = document.getElementById('color_secundario').value;
            const preview = document.getElementById('colorPreview');
            preview.style.background = `linear-gradient(135deg, ${color1} 0%, ${color2} 100%)`;
        }

        // Inicializar vista previa
        updateColorPreview();

        // Confirmación antes de salir si hay cambios
        let formChanged = false;
        document.querySelectorAll('input, textarea, select').forEach(input => {
            input.addEventListener('change', () => { formChanged = true; });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.querySelector('form').addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>
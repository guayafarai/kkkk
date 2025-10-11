<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

// Solo admin puede acceder
if (!hasPermission('admin')) {
    header('Location: dashboard.php?error=access_denied');
    exit();
}

$user = getCurrentUser();
$db = getDB();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_config') {
            $stmt = $db->prepare("
                UPDATE configuracion_empresa SET
                    nombre_empresa = ?,
                    ruc = ?,
                    direccion = ?,
                    telefono = ?,
                    email = ?,
                    sitio_web = ?,
                    slogan = ?,
                    mostrar_logo = ?,
                    mostrar_slogan = ?,
                    incluir_terminos = ?,
                    terminos_condiciones = ?,
                    mensaje_agradecimiento = ?,
                    footer_nota = ?,
                    tamano_papel = ?,
                    mostrar_qr = ?,
                    facebook = ?,
                    instagram = ?,
                    whatsapp = ?
                WHERE id = 1
            ");
            
            $result = $stmt->execute([
                sanitize($_POST['nombre_empresa']),
                sanitize($_POST['ruc']),
                sanitize($_POST['direccion']),
                sanitize($_POST['telefono']),
                sanitize($_POST['email']),
                sanitize($_POST['sitio_web']),
                sanitize($_POST['slogan']),
                isset($_POST['mostrar_logo']) ? 1 : 0,
                isset($_POST['mostrar_slogan']) ? 1 : 0,
                isset($_POST['incluir_terminos']) ? 1 : 0,
                sanitize($_POST['terminos_condiciones']),
                sanitize($_POST['mensaje_agradecimiento']),
                sanitize($_POST['footer_nota']),
                $_POST['tamano_papel'],
                isset($_POST['mostrar_qr']) ? 1 : 0,
                sanitize($_POST['facebook']),
                sanitize($_POST['instagram']),
                sanitize($_POST['whatsapp'])
            ]);
            
            if ($result) {
                logActivity($user['id'], 'update_company_config', 'Configuración de empresa actualizada');
                echo json_encode(['success' => true, 'message' => 'Configuración actualizada correctamente']);
            } else {
                throw new Exception('Error al actualizar la configuración');
            }
        }
        
        if ($_POST['action'] === 'upload_logo') {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al subir el archivo');
            }
            
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['logo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Solo se permiten imágenes JPG, PNG o GIF');
            }
            
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($_FILES['logo']['size'] > $maxSize) {
                throw new Exception('El archivo no debe superar 2MB');
            }
            
            $uploadDir = dirname(__DIR__) . '/uploads/logos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $newFilename = 'logo_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                $logoUrl = '/uploads/logos/' . $newFilename;
                
                $stmt = $db->prepare("UPDATE configuracion_empresa SET logo_url = ? WHERE id = 1");
                $stmt->execute([$logoUrl]);
                
                logActivity($user['id'], 'upload_logo', 'Logo de empresa actualizado');
                echo json_encode(['success' => true, 'logo_url' => $logoUrl]);
            } else {
                throw new Exception('Error al guardar el archivo');
            }
        }
        
    } catch(Exception $e) {
        logError("Error en configuración de empresa: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Obtener configuración actual
try {
    $config_stmt = $db->query("SELECT * FROM configuracion_empresa WHERE id = 1");
    $config = $config_stmt->fetch();
    
    if (!$config) {
        // Crear configuración por defecto si no existe
        $db->exec("
            INSERT INTO configuracion_empresa (nombre_empresa, direccion, telefono, email) 
            VALUES ('Mobile Service', 'Lima, Perú', '+51 999 999 999', 'ventas@mobileservice.com')
        ");
        $config_stmt = $db->query("SELECT * FROM configuracion_empresa WHERE id = 1");
        $config = $config_stmt->fetch();
    }
} catch(Exception $e) {
    logError("Error al obtener configuración: " . $e->getMessage());
    $config = null;
}

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .preview-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .logo-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
        }
        .paper-preview {
            border: 2px solid #e5e7eb;
            padding: 10px;
            background: white;
            margin: 10px auto;
        }
        .paper-58mm {
            width: 58mm;
        }
        .paper-80mm {
            width: 80mm;
        }
        .paper-a4 {
            width: 210mm;
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <?php renderNavbar('company_config'); ?>
    
    <main class="page-content">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-3xl font-bold text-gray-900">Configuración de Empresa</h2>
                <p class="text-gray-600">Personaliza la información que aparecerá en las notas de venta</p>
            </div>

            <?php if ($config): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Formulario de configuración -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Información Básica -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Información Básica</h3>
                        <form id="configForm" class="space-y-4">
                            <input type="hidden" name="action" value="update_config">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre de la Empresa *
                                    </label>
                                    <input type="text" name="nombre_empresa" required
                                           value="<?php echo htmlspecialchars($config['nombre_empresa']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        RUC / NIT
                                    </label>
                                    <input type="text" name="ruc"
                                           value="<?php echo htmlspecialchars($config['ruc'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Dirección
                                </label>
                                <textarea name="direccion" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                ><?php echo htmlspecialchars($config['direccion'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Teléfono
                                    </label>
                                    <input type="text" name="telefono"
                                           value="<?php echo htmlspecialchars($config['telefono'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Email
                                    </label>
                                    <input type="email" name="email"
                                           value="<?php echo htmlspecialchars($config['email'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Sitio Web
                                    </label>
                                    <input type="url" name="sitio_web"
                                           value="<?php echo htmlspecialchars($config['sitio_web'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Slogan
                                </label>
                                <input type="text" name="slogan"
                                       value="<?php echo htmlspecialchars($config['slogan'] ?? ''); ?>"
                                       placeholder="Ej: Tu mejor opción en celulares"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                        </form>
                    </div>

                    <!-- Logo -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Logo de la Empresa</h3>
                        <div class="space-y-4">
                            <?php if ($config['logo_url']): ?>
                            <div class="text-center">
                                <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" 
                                     alt="Logo" class="logo-preview mx-auto border rounded-lg p-2">
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Subir nuevo logo (JPG, PNG, GIF - máx. 2MB)
                                </label>
                                <input type="file" id="logoUpload" accept="image/*" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <button type="button" onclick="uploadLogo()" 
                                        class="mt-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                    Subir Logo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Notas de Venta -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Configuración de Notas de Venta</h3>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" name="mostrar_logo" id="mostrar_logo"
                                       <?php echo $config['mostrar_logo'] ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                                <label for="mostrar_logo" class="ml-2 text-sm text-gray-700">
                                    Mostrar logo en las notas de venta
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="mostrar_slogan" id="mostrar_slogan"
                                       <?php echo $config['mostrar_slogan'] ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                                <label for="mostrar_slogan" class="ml-2 text-sm text-gray-700">
                                    Mostrar slogan en las notas de venta
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="incluir_terminos" id="incluir_terminos"
                                       <?php echo $config['incluir_terminos'] ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                                <label for="incluir_terminos" class="ml-2 text-sm text-gray-700">
                                    Incluir términos y condiciones
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="mostrar_qr" id="mostrar_qr"
                                       <?php echo $config['mostrar_qr'] ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                                <label for="mostrar_qr" class="ml-2 text-sm text-gray-700">
                                    Mostrar código QR
                                </label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Tamaño de papel ⭐ NUEVO
                                </label>
                                <select name="tamano_papel" id="tamano_papel" class="w-full px-3 py-2 border border-gray-300 rounded-lg" onchange="updatePreview()">
                                    <option value="ticket58" <?php echo ($config['tamano_papel'] ?? '') === 'ticket58' ? 'selected' : ''; ?>>
                                        🎫 Ticket Térmico 58mm (Recomendado para impresoras portátiles)
                                    </option>
                                    <option value="ticket" <?php echo $config['tamano_papel'] === 'ticket' ? 'selected' : ''; ?>>
                                        🎫 Ticket Térmico 80mm
                                    </option>
                                    <option value="a4" <?php echo $config['tamano_papel'] === 'a4' ? 'selected' : ''; ?>>
                                        📄 A4 / Carta (210mm)
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    💡 58mm es ideal para impresoras térmicas portátiles y puntos de venta móviles
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Términos y Condiciones
                                </label>
                                <textarea name="terminos_condiciones" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                ><?php echo htmlspecialchars($config['terminos_condiciones'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Mensaje de Agradecimiento
                                </label>
                                <textarea name="mensaje_agradecimiento" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                ><?php echo htmlspecialchars($config['mensaje_agradecimiento'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Texto del Footer
                                </label>
                                <input type="text" name="footer_nota"
                                       value="<?php echo htmlspecialchars($config['footer_nota'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                        </div>
                    </div>

                    <!-- Redes Sociales -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Redes Sociales</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Facebook
                                </label>
                                <input type="text" name="facebook"
                                       value="<?php echo htmlspecialchars($config['facebook'] ?? ''); ?>"
                                       placeholder="https://facebook.com/tupagina"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Instagram
                                </label>
                                <input type="text" name="instagram"
                                       value="<?php echo htmlspecialchars($config['instagram'] ?? ''); ?>"
                                       placeholder="@tuinstagram"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    WhatsApp
                                </label>
                                <input type="text" name="whatsapp"
                                       value="<?php echo htmlspecialchars($config['whatsapp'] ?? ''); ?>"
                                       placeholder="+51 999 999 999"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                        </div>
                    </div>

                    <!-- Botón Guardar -->
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="window.location='dashboard.php'" 
                                class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                            Cancelar
                        </button>
                        <button type="button" onclick="saveConfig()" 
                                class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            Guardar Cambios
                        </button>
                    </div>
                </div>

                <!-- Vista Previa -->
                <div class="lg:col-span-1">
                    <div class="sticky top-6">
                        <div class="preview-card text-white p-6 rounded-lg shadow-lg">
                            <h3 class="text-xl font-semibold mb-4">Vista Previa de Nota</h3>
                            <div id="previewContainer" class="bg-white text-gray-900 p-4 rounded-lg text-sm paper-preview paper-58mm">
                                <div class="text-center mb-3">
                                    <?php if ($config['logo_url'] && $config['mostrar_logo']): ?>
                                    <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" 
                                         alt="Logo" class="h-12 mx-auto mb-2">
                                    <?php endif; ?>
                                    <h4 class="font-bold text-base"><?php echo htmlspecialchars($config['nombre_empresa']); ?></h4>
                                    <?php if ($config['slogan'] && $config['mostrar_slogan']): ?>
                                    <p class="text-xs text-gray-600 italic"><?php echo htmlspecialchars($config['slogan']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($config['ruc']): ?>
                                    <p class="text-xs">RUC: <?php echo htmlspecialchars($config['ruc']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs"><?php echo htmlspecialchars($config['direccion'] ?? ''); ?></p>
                                    <p class="text-xs"><?php echo htmlspecialchars($config['telefono'] ?? ''); ?></p>
                                </div>
                                
                                <div class="border-t border-b py-2 my-3">
                                    <p class="text-center font-bold text-xs">NOTA DE VENTA</p>
                                    <p class="text-xs text-center">Nº 001-00001234</p>
                                    <p class="text-xs text-center"><?php echo date('d/m/Y H:i'); ?></p>
                                </div>
                                
                                <div class="text-xs mb-3">
                                    <p><strong>Cliente:</strong> Juan Pérez</p>
                                    <p><strong>Teléfono:</strong> 999 999 999</p>
                                </div>
                                
                                <div class="text-xs mb-3">
                                    <p class="font-bold mb-1">PRODUCTO</p>
                                    <p>iPhone 13 Pro - 256GB</p>
                                    <p>IMEI: 123456789012345</p>
                                    <div class="flex justify-between mt-2 font-bold">
                                        <span>TOTAL:</span>
                                        <span>$899.00</span>
                                    </div>
                                </div>
                                
                                <?php if ($config['incluir_terminos'] && $config['terminos_condiciones']): ?>
                                <div class="text-xs border-t pt-2 mt-3">
                                    <p class="font-bold mb-1">Términos:</p>
                                    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($config['terminos_condiciones'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="text-center mt-3 text-xs">
                                    <p class="font-semibold"><?php echo htmlspecialchars($config['mensaje_agradecimiento'] ?? ''); ?></p>
                                    <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($config['footer_nota'] ?? ''); ?></p>
                                </div>
                            </div>
                            <p class="text-xs text-purple-200 mt-3 text-center">
                                📱 Esta es una vista previa simplificada
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <p class="text-yellow-800">No se pudo cargar la configuración. Por favor, contacta al soporte técnico.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updatePreview() {
            const tamano = document.getElementById('tamano_papel').value;
            const preview = document.getElementById('previewContainer');
            
            // Remover todas las clases de tamaño
            preview.classList.remove('paper-58mm', 'paper-80mm', 'paper-a4');
            
            // Agregar la clase correspondiente
            if (tamano === 'ticket58') {
                preview.classList.add('paper-58mm');
            } else if (tamano === 'ticket') {
                preview.classList.add('paper-80mm');
            } else if (tamano === 'a4') {
                preview.classList.add('paper-a4');
            }
        }

        function saveConfig() {
            const form = document.getElementById('configForm');
            const formData = new FormData(form);
            
            // Agregar checkboxes
            formData.set('mostrar_logo', document.getElementById('mostrar_logo').checked ? '1' : '0');
            formData.set('mostrar_slogan', document.getElementById('mostrar_slogan').checked ? '1' : '0');
            formData.set('incluir_terminos', document.getElementById('incluir_terminos').checked ? '1' : '0');
            formData.set('mostrar_qr', document.getElementById('mostrar_qr').checked ? '1' : '0');
            
            // Agregar tamaño de papel
            formData.set('tamano_papel', document.getElementById('tamano_papel').value);
            
            fetch('company_config.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error al guardar los cambios', 'error');
            });
        }

        function uploadLogo() {
            const fileInput = document.getElementById('logoUpload');
            const file = fileInput.files[0];
            
            if (!file) {
                showNotification('Por favor selecciona un archivo', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_logo');
            formData.append('logo', file);
            
            fetch('company_config.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ Logo subido correctamente', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error al subir el logo', 'error');
            });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColors = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            };
            
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${bgColors[type]} text-white`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
    </script>
</body>
</html>
                                    
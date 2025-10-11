<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

startSecureSession();
requireLogin();

$user = getCurrentUser();
$db = getDB();

$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$venta_id) {
    die('ID de venta no válido');
}

try {
    $venta_stmt = $db->prepare("
        SELECT vp.*, 
               p.nombre as producto_nombre, p.codigo_producto, p.marca, p.modelo_compatible, p.descripcion,
               c.nombre as categoria_nombre,
               t.nombre as tienda_nombre, t.direccion as tienda_direccion, t.telefono as tienda_telefono,
               u.nombre as vendedor_nombre
        FROM ventas_productos vp
        LEFT JOIN productos p ON vp.producto_id = p.id
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        LEFT JOIN tiendas t ON vp.tienda_id = t.id
        LEFT JOIN usuarios u ON vp.vendedor_id = u.id
        WHERE vp.id = ?
    ");
    $venta_stmt->execute([$venta_id]);
    $venta = $venta_stmt->fetch();
    
    if (!$venta) {
        die('Venta no encontrada');
    }
    
    if (!hasPermission('admin') && $venta['tienda_id'] != $user['tienda_id']) {
        die('No tienes permisos para ver esta venta');
    }
    
    $config_stmt = $db->query("SELECT * FROM configuracion_empresa WHERE id = 1");
    $config = $config_stmt->fetch();
    
    if (!$config) {
        die('Configuración de empresa no encontrada');
    }
    
} catch(Exception $e) {
    logError("Error al obtener datos para impresión: " . $e->getMessage());
    die('Error al cargar los datos de la venta');
}

$numero_nota = str_pad($venta_id, 8, '0', STR_PAD_LEFT);
$serie = '002';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Venta #<?php echo $numero_nota; ?> - Productos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 9px;
            line-height: 1.3;
            color: #000;
            background: #fff;
        }
        
        .container {
            width: 58mm;
            margin: 0 auto;
            padding: 2mm;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px dashed #000;
        }
        
        .logo {
            max-width: 30mm;
            max-height: 30mm;
            margin: 0 auto 2mm;
            display: block;
        }
        
        .company-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1mm;
            word-wrap: break-word;
        }
        
        .slogan {
            font-size: 8px;
            font-style: italic;
            margin-bottom: 1mm;
        }
        
        .company-info {
            font-size: 8px;
            line-height: 1.2;
        }
        
        .invoice-title {
            text-align: center;
            margin: 2mm 0;
            padding: 2mm 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }
        
        .invoice-title h2 {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        
        .invoice-number {
            font-size: 9px;
            font-weight: bold;
        }
        
        .section {
            margin: 2mm 0;
            font-size: 8px;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 1mm;
            border-bottom: 1px solid #000;
        }
        
        .row {
            margin-bottom: 1mm;
            display: flex;
            justify-content: space-between;
        }
        
        .label {
            font-weight: bold;
        }
        
        .value {
            text-align: right;
            word-wrap: break-word;
            max-width: 30mm;
        }
        
        .product-box {
            border: 1px solid #000;
            padding: 2mm;
            margin: 2mm 0;
        }
        
        .product-name {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 1mm;
            text-align: center;
            word-wrap: break-word;
        }
        
        .price-table {
            margin: 2mm 0;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
            padding: 1mm 0;
        }
        
        .price-row.separator {
            border-top: 1px dashed #000;
            margin-top: 1mm;
            padding-top: 1mm;
        }
        
        .total-box {
            margin: 3mm 0;
            padding: 2mm;
            border: 2px solid #000;
            text-align: center;
        }
        
        .total-label {
            font-size: 9px;
            font-weight: bold;
        }
        
        .total-amount {
            font-size: 14px;
            font-weight: bold;
            margin-top: 1mm;
        }
        
        .footer {
            margin-top: 3mm;
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 2mm;
            font-size: 8px;
        }
        
        .footer-text {
            margin: 1mm 0;
        }
        
        .separator {
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-small {
            font-size: 7px;
        }
        
        .code-box {
            background: #f5f5f5;
            padding: 1mm;
            margin: 1mm 0;
            font-family: 'Courier New', monospace;
            font-size: 7px;
            word-break: break-all;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                width: 58mm;
                padding: 0;
                margin: 0;
            }
            
            @page {
                size: 58mm auto;
                margin: 0;
            }
        }
        
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #5568d3;
        }
        
        .close-button {
            position: fixed;
            top: 10px;
            left: 10px;
            padding: 8px 16px;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
        }
        
        .close-button:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <button onclick="window.close()" class="close-button no-print">← Cerrar</button>
    <button onclick="window.print()" class="print-button no-print">🖨️ Imprimir</button>
    
    <div class="container">
        <!-- ENCABEZADO -->
        <div class="header">
            <?php if ($config['mostrar_logo'] && $config['logo_url']): ?>
                <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="Logo" class="logo">
            <?php endif; ?>
            
            <div class="company-name"><?php echo htmlspecialchars($config['nombre_empresa']); ?></div>
            
            <?php if ($config['mostrar_slogan'] && $config['slogan']): ?>
                <div class="slogan"><?php echo htmlspecialchars($config['slogan']); ?></div>
            <?php endif; ?>
            
            <div class="company-info">
                <?php if ($config['ruc']): ?>
                    <div>RUC: <?php echo htmlspecialchars($config['ruc']); ?></div>
                <?php endif; ?>
                
                <?php if ($config['direccion']): ?>
                    <div><?php echo htmlspecialchars($config['direccion']); ?></div>
                <?php endif; ?>
                
                <?php if ($config['telefono']): ?>
                    <div>Tel: <?php echo htmlspecialchars($config['telefono']); ?></div>
                <?php endif; ?>
                
                <?php if ($config['email']): ?>
                    <div><?php echo htmlspecialchars($config['email']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- TÍTULO -->
        <div class="invoice-title">
            <h2>NOTA DE VENTA</h2>
            <div style="font-size: 8px;">PRODUCTOS</div>
            <div class="invoice-number">Nº <?php echo $serie; ?>-<?php echo $numero_nota; ?></div>
            <div class="text-small" style="margin-top: 1mm;">
                <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?>
            </div>
        </div>
        
        <!-- TIENDA -->
        <?php if ($venta['tienda_nombre']): ?>
        <div class="section">
            <div class="section-title">TIENDA</div>
            <div><?php echo htmlspecialchars($venta['tienda_nombre']); ?></div>
            <?php if ($venta['tienda_telefono']): ?>
                <div class="text-small">Tel: <?php echo htmlspecialchars($venta['tienda_telefono']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="separator"></div>
        
        <!-- CLIENTE -->
        <div class="section">
            <div class="section-title">CLIENTE</div>
            <div class="row">
                <span class="label">Nombre:</span>
                <span class="value"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></span>
            </div>
            <?php if ($venta['cliente_telefono']): ?>
            <div class="row">
                <span class="label">Tel:</span>
                <span class="value"><?php echo htmlspecialchars($venta['cliente_telefono']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="separator"></div>
        
        <!-- PRODUCTO -->
        <div class="section">
            <div class="section-title">PRODUCTO</div>
            <div class="product-box">
                <div class="product-name">
                    <?php echo htmlspecialchars($venta['producto_nombre']); ?>
                </div>
                
                <?php if ($venta['codigo_producto']): ?>
                <div class="code-box">
                    COD: <?php echo htmlspecialchars($venta['codigo_producto']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($venta['marca']): ?>
                <div class="row">
                    <span class="label">Marca:</span>
                    <span><?php echo htmlspecialchars($venta['marca']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($venta['categoria_nombre']): ?>
                <div class="row">
                    <span class="label">Categoría:</span>
                    <span><?php echo htmlspecialchars($venta['categoria_nombre']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($venta['modelo_compatible']): ?>
                <div class="row">
                    <span class="label">Compatible:</span>
                    <span class="value"><?php echo htmlspecialchars($venta['modelo_compatible']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <!-- DESGLOSE -->
        <div class="section">
            <div class="section-title">DESGLOSE</div>
            <div class="price-table">
                <div class="price-row">
                    <span>Cantidad:</span>
                    <span><?php echo $venta['cantidad']; ?> und.</span>
                </div>
                <div class="price-row">
                    <span>Precio Unit.:</span>
                    <span>$<?php echo number_format($venta['precio_unitario'], 2); ?></span>
                </div>
                <div class="price-row separator">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($venta['precio_unitario'] * $venta['cantidad'], 2); ?></span>
                </div>
                <?php if ($venta['descuento'] > 0): ?>
                <div class="price-row" style="color: #666;">
                    <span>Descuento:</span>
                    <span>-$<?php echo number_format($venta['descuento'], 2); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <!-- PAGO -->
        <div class="section">
            <div class="row">
                <span class="label">Pago:</span>
                <span><?php echo ucfirst(htmlspecialchars($venta['metodo_pago'])); ?></span>
            </div>
            <?php if ($venta['vendedor_nombre']): ?>
            <div class="row">
                <span class="label">Vendedor:</span>
                <span><?php echo htmlspecialchars($venta['vendedor_nombre']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TOTAL -->
        <div class="total-box">
            <div class="total-label">TOTAL PAGADO</div>
            <div class="total-amount">$<?php echo number_format($venta['precio_total'], 2); ?></div>
        </div>
        
        <!-- NOTAS -->
        <?php if ($venta['notas']): ?>
        <div class="separator"></div>
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div class="text-small"><?php echo nl2br(htmlspecialchars($venta['notas'])); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- TÉRMINOS -->
        <?php if ($config['incluir_terminos'] && $config['terminos_condiciones']): ?>
        <div class="separator"></div>
        <div class="section text-small">
            <div class="section-title">TÉRMINOS</div>
            <div><?php echo nl2br(htmlspecialchars($config['terminos_condiciones'])); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- CÓDIGO QR -->
        <?php if ($config['mostrar_qr']): ?>
        <div class="text-center" style="margin: 2mm 0;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=VENTA-PROD-<?php echo $numero_nota; ?>-<?php echo $venta['codigo_producto']; ?>" 
                 alt="QR" style="max-width: 25mm;">
        </div>
        <?php endif; ?>
        
        <!-- FOOTER -->
        <div class="footer">
            <?php if ($config['mensaje_agradecimiento']): ?>
            <div class="footer-text" style="font-weight: bold;">
                <?php echo htmlspecialchars($config['mensaje_agradecimiento']); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($config['footer_nota']): ?>
            <div class="footer-text text-small">
                <?php echo htmlspecialchars($config['footer_nota']); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($config['whatsapp'] || $config['facebook'] || $config['instagram']): ?>
            <div class="separator"></div>
            <div class="text-small">
                <?php if ($config['whatsapp']): ?>
                    <div>WhatsApp: <?php echo htmlspecialchars($config['whatsapp']); ?></div>
                <?php endif; ?>
                <?php if ($config['facebook']): ?>
                    <div>FB: <?php echo htmlspecialchars($config['facebook']); ?></div>
                <?php endif; ?>
                <?php if ($config['instagram']): ?>
                    <div>IG: <?php echo htmlspecialchars($config['instagram']); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="separator"></div>
            <div class="text-small" style="margin-top: 2mm;">
                Sistema <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Imprimir automáticamente al cargar (opcional)
        // window.onload = function() { window.print(); }
        
        // Cerrar después de imprimir
        window.onafterprint = function() {
            // window.close();
        }
    </script>
</body>
</html>
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
        SELECT v.*, 
               c.modelo, c.marca, c.capacidad, c.color, c.imei1, c.imei2, c.condicion,
               t.nombre as tienda_nombre, t.direccion as tienda_direccion, t.telefono as tienda_telefono,
               u.nombre as vendedor_nombre
        FROM ventas v
        LEFT JOIN celulares c ON v.celular_id = c.id
        LEFT JOIN tiendas t ON v.tienda_id = t.id
        LEFT JOIN usuarios u ON v.vendedor_id = u.id
        WHERE v.id = ?
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
$serie = '001';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Venta #<?php echo $numero_nota; ?></title>
    <style>
        @charset "UTF-8";
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            /* FUENTE ARIAL BOLD - MÁXIMA LEGIBILIDAD EN TÉRMICAS */
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: bold;
            line-height: 1.5;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .container {
            width: 58mm;
            margin: 0;
            padding: 2mm;
        }
        
        /* ENCABEZADO */
        .header {
            text-align: center;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
        }
        
        .logo {
            max-width: 25mm;
            max-height: 25mm;
            margin: 0 auto 2mm;
            display: block;
        }
        
        .company-name {
            font-size: 14px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 1mm;
            letter-spacing: 0.5px;
        }
        
        .slogan {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        
        .company-info {
            font-size: 10px;
            font-weight: bold;
            line-height: 1.4;
        }
        
        /* SEPARADORES - MÁS GRUESOS */
        .separator {
            border: none;
            border-top: 2px solid #000;
            margin: 2mm 0;
            height: 2px;
        }
        
        .separator-dots {
            border: none;
            border-top: 2px dashed #000;
            margin: 2mm 0;
            height: 2px;
        }
        
        /* TÍTULO */
        .invoice-title {
            text-align: center;
            margin: 3mm 0;
            padding: 3mm 0;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
            background: #f0f0f0;
        }
        
        .invoice-title h2 {
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 1mm;
            letter-spacing: 1px;
        }
        
        .invoice-number {
            font-size: 13px;
            font-weight: 900;
        }
        
        /* SECCIONES */
        .section {
            margin: 3mm 0;
            font-size: 11px;
        }
        
        .section-title {
            font-weight: 900;
            font-size: 12px;
            margin-bottom: 1.5mm;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #000;
            color: #fff;
            padding: 1mm 2mm;
            border-radius: 1mm;
        }
        
        /* FILAS DE DATOS */
        .row {
            margin-bottom: 1.5mm;
            overflow: hidden;
        }
        
        .label {
            float: left;
            font-weight: 900;
            width: 45%;
        }
        
        .value {
            float: right;
            text-align: right;
            width: 55%;
            font-weight: bold;
        }
        
        /* PRODUCTO */
        .product-box {
            border: 3px solid #000;
            padding: 2mm;
            margin: 2mm 0;
            background: #f5f5f5;
        }
        
        .product-name {
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 2mm;
            text-align: center;
            text-transform: uppercase;
            background: #000;
            color: #fff;
            padding: 1mm;
        }
        
        /* IMEI */
        .imei-box {
            background: #000;
            color: #fff;
            padding: 2mm;
            margin: 2mm 0;
            font-size: 10px;
            font-weight: 900;
            word-break: break-all;
            text-align: center;
        }
        
        /* TOTAL */
        .total-box {
            margin: 4mm 0;
            padding: 3mm;
            border: 4px solid #000;
            text-align: center;
            background: #000;
            color: #fff;
        }
        
        .total-label {
            font-size: 11px;
            font-weight: 900;
            margin-bottom: 1mm;
            letter-spacing: 1px;
        }
        
        .total-amount {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
        }
        
        /* FOOTER */
        .footer {
            margin-top: 4mm;
            text-align: center;
            border-top: 2px solid #000;
            padding-top: 2mm;
            font-size: 9px;
            font-weight: bold;
        }
        
        .footer-text {
            margin: 2mm 0;
        }
        
        /* UTILIDADES */
        .text-center {
            text-align: center;
        }
        
        .text-bold {
            font-weight: 900;
        }
        
        .clear {
            clear: both;
        }
        
        /* QR CODE */
        .qr-container {
            text-align: center;
            margin: 3mm 0;
            padding: 2mm;
            background: #fff;
            border: 2px solid #000;
        }
        
        .qr-container img {
            max-width: 25mm;
            height: auto;
        }
        
        /* IMPRESIÓN */
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
            
            /* Forzar elementos negros */
            .separator,
            .invoice-title,
            .product-box,
            .total-box,
            .section-title,
            .imei-box {
                border-color: #000 !important;
                background-color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .total-box,
            .section-title,
            .imei-box,
            .product-name {
                color: #fff !important;
            }
        }
        
        /* BOTONES */
        .print-button,
        .close-button {
            position: fixed;
            padding: 12px 24px;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            z-index: 1000;
            font-family: Arial, sans-serif;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .print-button {
            top: 20px;
            right: 20px;
            background: #667eea;
        }
        
        .print-button:hover {
            background: #5568d3;
        }
        
        .close-button {
            top: 20px;
            left: 20px;
            background: #6b7280;
        }
        
        .close-button:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <button onclick="window.close()" class="close-button no-print">✕ Cerrar</button>
    <button onclick="window.print()" class="print-button no-print">🖨 Imprimir</button>
    
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
            </div>
        </div>
        
        <!-- TÍTULO -->
        <div class="invoice-title">
            <h2>NOTA DE VENTA</h2>
            <div class="invoice-number">N° <?php echo $serie; ?>-<?php echo $numero_nota; ?></div>
            <div style="font-size: 10px; margin-top: 1mm; font-weight: bold;">
                <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?>
            </div>
        </div>
        
        <!-- TIENDA -->
        <?php if ($venta['tienda_nombre']): ?>
        <div class="section">
            <div class="section-title">TIENDA</div>
            <div class="text-bold text-center"><?php echo htmlspecialchars($venta['tienda_nombre']); ?></div>
            <?php if ($venta['tienda_telefono']): ?>
                <div class="text-center" style="font-size: 10px; margin-top: 1mm;">
                    Tel: <?php echo htmlspecialchars($venta['tienda_telefono']); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="separator-dots"></div>
        <?php endif; ?>
        
        <!-- CLIENTE -->
        <div class="section">
            <div class="section-title">CLIENTE</div>
            <div class="row">
                <div class="label">Nombre:</div>
                <div class="value"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></div>
            </div>
            <div class="clear"></div>
            <?php if ($venta['cliente_telefono']): ?>
            <div class="row">
                <div class="label">Telefono:</div>
                <div class="value"><?php echo htmlspecialchars($venta['cliente_telefono']); ?></div>
            </div>
            <div class="clear"></div>
            <?php endif; ?>
        </div>
        
        <div class="separator-dots"></div>
        
        <!-- PRODUCTO -->
        <div class="section">
            <div class="section-title">DISPOSITIVO</div>
            <div class="product-box">
                <div class="product-name">
                    <?php echo htmlspecialchars($venta['marca'] . ' ' . $venta['modelo']); ?>
                </div>
                
                <div class="row">
                    <div class="label">Capacidad:</div>
                    <div class="value"><?php echo htmlspecialchars($venta['capacidad']); ?></div>
                </div>
                <div class="clear"></div>
                
                <?php if ($venta['color']): ?>
                <div class="row">
                    <div class="label">Color:</div>
                    <div class="value"><?php echo htmlspecialchars($venta['color']); ?></div>
                </div>
                <div class="clear"></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="label">Estado:</div>
                    <div class="value"><?php echo ucfirst(htmlspecialchars($venta['condicion'])); ?></div>
                </div>
                <div class="clear"></div>
                
                <div class="imei-box">
                    IMEI: <?php echo htmlspecialchars($venta['imei1']); ?>
                </div>
                
                <?php if ($venta['imei2']): ?>
                <div class="imei-box">
                    IMEI2: <?php echo htmlspecialchars($venta['imei2']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="separator-dots"></div>
        
        <!-- PAGO -->
        <div class="section">
            <div class="row">
                <div class="label">Pago:</div>
                <div class="value"><?php echo ucfirst(htmlspecialchars($venta['metodo_pago'])); ?></div>
            </div>
            <div class="clear"></div>
            <?php if ($venta['vendedor_nombre']): ?>
            <div class="row">
                <div class="label">Vendedor:</div>
                <div class="value"><?php echo htmlspecialchars($venta['vendedor_nombre']); ?></div>
            </div>
            <div class="clear"></div>
            <?php endif; ?>
        </div>
        
        <!-- TOTAL -->
        <div class="total-box">
            <div class="total-label">TOTAL PAGADO</div>
            <div class="total-amount">$<?php echo number_format($venta['precio_venta'], 2); ?></div>
        </div>
        
        <!-- NOTAS -->
        <?php if ($venta['notas']): ?>
        <div class="separator-dots"></div>
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div style="font-size: 10px; font-weight: bold;">
                <?php echo nl2br(htmlspecialchars($venta['notas'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TÉRMINOS -->
        <?php if ($config['incluir_terminos'] && $config['terminos_condiciones']): ?>
        <div class="separator-dots"></div>
        <div class="section" style="font-size: 9px;">
            <div class="section-title">TERMINOS</div>
            <div style="font-weight: bold;">
                <?php echo nl2br(htmlspecialchars($config['terminos_condiciones'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- CÓDIGO QR -->
        <?php if ($config['mostrar_qr']): ?>
        <div class="qr-container">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=VENTA-<?php echo $numero_nota; ?>-IMEI-<?php echo $venta['imei1']; ?>" 
                 alt="QR">
        </div>
        <?php endif; ?>
        
        <!-- FOOTER -->
        <div class="footer">
            <?php if ($config['mensaje_agradecimiento']): ?>
            <div class="footer-text text-bold" style="font-size: 11px;">
                <?php echo htmlspecialchars($config['mensaje_agradecimiento']); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($config['footer_nota']): ?>
            <div class="footer-text" style="font-size: 9px;">
                <?php echo htmlspecialchars($config['footer_nota']); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($config['whatsapp'] || $config['facebook'] || $config['instagram']): ?>
            <div class="separator-dots"></div>
            <div style="font-size: 9px; font-weight: bold;">
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
            <div style="font-size: 8px; margin-top: 2mm; font-weight: bold;">
                <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>
            </div>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            document.body.style.fontWeight = 'bold';
        }
    </script>
</body>
</html>

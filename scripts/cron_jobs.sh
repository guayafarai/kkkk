#!/bin/bash

#===============================================================================
# SCRIPT DE TAREAS PROGRAMADAS - SISTEMA INVENTARIO CELULARES
# VERSIÓN FINAL CORREGIDA
#===============================================================================

# Configuración de rutas (AJUSTAR SEGÚN TU INSTALACIÓN)
PROJECT_ROOT="/var/www/html/phone_inventory"
PHP_PATH="/usr/bin/php"
LOG_DIR="$PROJECT_ROOT/logs"
BACKUP_DIR="$PROJECT_ROOT/backups"

# Crear directorios si no existen
mkdir -p "$LOG_DIR"
mkdir -p "$BACKUP_DIR"

# Función para logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_DIR/cron.log"
}

#===============================================================================
# TAREAS DISPONIBLES
#===============================================================================

# 1. BACKUP AUTOMÁTICO DE BASE DE DATOS
backup_database() {
    log_message "Iniciando backup automático de base de datos"
    
    cd "$PROJECT_ROOT"
    $PHP_PATH scripts/backup.php
    
    if [ $? -eq 0 ]; then
        log_message "Backup completado exitosamente"
    else
        log_message "ERROR: Falló el backup de base de datos"
    fi
}

# 2. LIMPIAR LOGS ANTIGUOS (más de 30 días) - CORREGIDO
cleanup_logs() {
    log_message "Iniciando limpieza de logs antiguos"
    
    # Comprimir logs de más de 7 días
    compressed=0
    find "$LOG_DIR" -name "*.log" -mtime +7 -type f | while read logfile; do
        if [ -s "$logfile" ]; then
            gzip "$logfile"
            compressed=$((compressed + 1))
        fi
    done
    
    # CORREGIDO: Contar ANTES de eliminar
    deleted_count=$(find "$LOG_DIR" -name "*.log.gz" -mtime +30 -type f | wc -l)
    
    # Eliminar archivos
    find "$LOG_DIR" -name "*.log.gz" -mtime +30 -type f -delete
    
    log_message "Limpieza completada. Archivos eliminados: $deleted_count"
}

# 3. LIMPIEZA DE BACKUPS ANTIGUOS (más de 60 días) - CORREGIDO
cleanup_backups() {
    log_message "Iniciando limpieza de backups antiguos"
    
    # CORREGIDO: Contar ANTES de eliminar
    deleted_count=$(find "$BACKUP_DIR" -name "backup_*.sql*" -mtime +60 -type f | wc -l)
    
    # Eliminar backups
    find "$BACKUP_DIR" -name "backup_*.sql*" -mtime +60 -type f -delete
    
    log_message "Limpieza de backups completada. Archivos eliminados: $deleted_count"
}

# 4. VERIFICAR SALUD DEL SISTEMA
check_system_health() {
    log_message "Verificando salud del sistema"
    
    # Verificar espacio en disco
    disk_usage=$(df "$PROJECT_ROOT" | tail -1 | awk '{print $5}' | sed 's/%//')
    
    if [ "$disk_usage" -gt 90 ]; then
        log_message "ALERTA: Espacio en disco crítico: ${disk_usage}%"
    elif [ "$disk_usage" -gt 80 ]; then
        log_message "WARNING: Espacio en disco alto: ${disk_usage}%"
    fi
    
    # Verificar tamaño de logs
    log_size=$(du -sm "$LOG_DIR" 2>/dev/null | cut -f1)
    
    if [ -n "$log_size" ] && [ "$log_size" -gt 100 ]; then
        log_message "WARNING: Logs ocupan ${log_size}MB - considerar limpieza"
    fi
    
    # Verificar permisos
    if [ ! -w "$LOG_DIR" ]; then
        log_message "ERROR: Sin permisos de escritura en directorio de logs"
    fi
    
    if [ ! -w "$BACKUP_DIR" ]; then
        log_message "ERROR: Sin permisos de escritura en directorio de backups"
    fi
    
    log_message "Verificación de salud completada - Disco: ${disk_usage}%"
}

# 5. OPTIMIZAR BASE DE DATOS
optimize_database() {
    log_message "Iniciando optimización de base de datos"
    
    cd "$PROJECT_ROOT"
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $tables = ["usuarios", "tiendas", "celulares", "ventas", "logs_actividad", "activity_logs", "productos", "stock_productos", "movimientos_stock"];
        
        $optimized = 0;
        foreach($tables as $table) {
            try {
                $db->exec("OPTIMIZE TABLE `$table`");
                $optimized++;
                echo "Optimizada tabla: $table\n";
            } catch(Exception $e) {
                echo "Error optimizando $table: " . $e->getMessage() . "\n";
            }
        }
        echo "Optimización completada: $optimized tablas\n";
    } catch(Exception $e) {
        echo "Error en optimización: " . $e->getMessage() . "\n";
        exit(1);
    }
    '
    
    if [ $? -eq 0 ]; then
        log_message "Optimización de base de datos completada"
    else
        log_message "ERROR: Falló la optimización de base de datos"
    fi
}

# 6. GENERAR REPORTE DIARIO
generate_daily_report() {
    log_message "Generando reporte diario"
    
    cd "$PROJECT_ROOT"
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $yesterday = date("Y-m-d", strtotime("-1 day"));
        
        // Ventas del día anterior
        $stmt = $db->prepare("SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos FROM ventas WHERE DATE(fecha_venta) = ?");
        $stmt->execute([$yesterday]);
        $ventas = $stmt->fetch();
        
        // Nuevos dispositivos registrados
        $stmt = $db->prepare("SELECT COUNT(*) as nuevos FROM celulares WHERE DATE(fecha_registro) = ?");
        $stmt->execute([$yesterday]);
        $nuevos = $stmt->fetch();
        
        // Usuarios activos
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as usuarios FROM activity_logs WHERE DATE(created_at) = ?");
        $stmt->execute([$yesterday]);
        $usuarios = $stmt->fetch();
        
        // Generar reporte
        $report = "═══════════════════════════════════════════\n";
        $report .= "Reporte Diario - $yesterday\n";
        $report .= "═══════════════════════════════════════════\n\n";
        $report .= "📊 VENTAS\n";
        $report .= "  • Cantidad: " . $ventas["ventas"] . "\n";
        $report .= "  • Ingresos: $" . number_format($ventas["ingresos"], 2) . "\n\n";
        $report .= "📱 INVENTARIO\n";
        $report .= "  • Nuevos dispositivos: " . $nuevos["nuevos"] . "\n\n";
        $report .= "👥 ACTIVIDAD\n";
        $report .= "  • Usuarios activos: " . $usuarios["usuarios"] . "\n\n";
        $report .= "═══════════════════════════════════════════\n";
        
        file_put_contents("logs/daily_report_$yesterday.txt", $report);
        echo "Reporte generado exitosamente\n";
        
    } catch(Exception $e) {
        echo "Error generando reporte: " . $e->getMessage() . "\n";
        exit(1);
    }
    '
    
    if [ $? -eq 0 ]; then
        log_message "Reporte diario generado correctamente"
    else
        log_message "ERROR: Falló la generación del reporte diario"
    fi
}

# 7. ENVIAR ALERTAS - SINTAXIS CORREGIDA
send_alerts() {
    log_message "Verificando alertas del sistema"
    
    cd "$PROJECT_ROOT"
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $alerts_found = false;
        
        // Verificar dispositivos con stock bajo
        $stmt = $db->query("
            SELECT modelo, COUNT(*) as stock 
            FROM celulares 
            WHERE estado = \"disponible\" 
            GROUP BY modelo 
            HAVING stock < 5
            ORDER BY stock ASC
        ");
        $low_stock = $stmt->fetchAll();
        
        if (!empty($low_stock)) {
            $alert = "⚠️  ALERTA DE STOCK BAJO\n";
            $alert .= "═══════════════════════════════════════════\n";
            foreach($low_stock as $item) {
                $alert .= "• " . $item["modelo"] . ": " . $item["stock"] . " unidades\n";
            }
            $alert .= "═══════════════════════════════════════════\n";
            file_put_contents("logs/stock_alert_" . date("Y-m-d") . ".txt", $alert);
            echo "Alerta de stock generada: " . count($low_stock) . " modelos\n";
            $alerts_found = true;
        }
        
        // Verificar usuarios bloqueados
        $stmt = $db->query("
            SELECT COUNT(*) as bloqueados 
            FROM usuarios 
            WHERE bloqueado_hasta > NOW()
        ");
        $bloqueados = $stmt->fetch();
        
        if ($bloqueados["bloqueados"] > 0) {
            $alert = "🔒 ALERTA DE SEGURIDAD\n";
            $alert .= "═══════════════════════════════════════════\n";
            $alert .= "Usuarios bloqueados: " . $bloqueados["bloqueados"] . "\n";
            $alert .= "Motivo: Múltiples intentos fallidos de login\n";
            $alert .= "═══════════════════════════════════════════\n";
            file_put_contents("logs/security_alert_" . date("Y-m-d") . ".txt", $alert);
            echo "Alerta de seguridad generada\n";
            $alerts_found = true;
        }
        
        if (!$alerts_found) {
            echo "✅ Sin alertas - Sistema normal\n";
        }
        
    } catch(Exception $e) {
        echo "Error verificando alertas: " . $e->getMessage() . "\n";
    }
    '
    
    log_message "Verificación de alertas completada"
}

#===============================================================================
# FUNCIÓN PRINCIPAL
#===============================================================================

main() {
    case "$1" in
        "backup")
            backup_database
            ;;
        "cleanup-logs")
            cleanup_logs
            ;;
        "cleanup-backups")
            cleanup_backups
            ;;
        "health-check")
            check_system_health
            ;;
        "optimize")
            optimize_database
            ;;
        "daily-report")
            generate_daily_report
            ;;
        "alerts")
            send_alerts
            ;;
        "full-maintenance")
            log_message "Iniciando mantenimiento completo"
            backup_database
            optimize_database
            cleanup_logs
            cleanup_backups
            check_system_health
            generate_daily_report
            send_alerts
            log_message "Mantenimiento completo finalizado"
            ;;
        *)
            echo "Uso: $0 {backup|cleanup-logs|cleanup-backups|health-check|optimize|daily-report|alerts|full-maintenance}"
            echo ""
            echo "Tareas disponibles:"
            echo "  backup           - Crear respaldo de base de datos"
            echo "  cleanup-logs     - Limpiar logs antiguos"
            echo "  cleanup-backups  - Limpiar backups antiguos"
            echo "  health-check     - Verificar salud del sistema"
            echo "  optimize         - Optimizar base de datos"
            echo "  daily-report     - Generar reporte diario"
            echo "  alerts           - Verificar y enviar alertas"
            echo "  full-maintenance - Ejecutar todas las tareas"
            echo ""
            echo "Ejemplos de configuración cron:"
            echo "# Backup diario a las 2:00 AM"
            echo "0 2 * * * $0 backup"
            echo ""
            echo "# Limpieza semanal los domingos a las 3:00 AM"
            echo "0 3 * * 0 $0 cleanup-logs"
            echo ""
            echo "# Verificación de salud cada 6 horas"
            echo "0 */6 * * * $0 health-check"
            echo ""
            echo "# Mantenimiento completo mensual"
            echo "0 1 1 * * $0 full-maintenance"
            exit 1
            ;;
    esac
}

#===============================================================================
# PRUEBAS DEL SISTEMA
#===============================================================================

run_tests() {
    echo "🧪 Ejecutando pruebas del sistema..."
    log_message "Iniciando pruebas del sistema"
    
    # Prueba de conectividad a BD
    echo "- Probando conexión a base de datos..."
    cd "$PROJECT_ROOT"
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
        $result = $stmt->fetch();
        echo "Conexión BD: ✅ OK - " . $result["total"] . " usuarios encontrados\n";
    } catch(Exception $e) {
        echo "Conexión BD: ❌ ERROR - " . $e->getMessage() . "\n";
        exit(1);
    }
    '
    
    if [ $? -ne 0 ]; then
        echo "❌ Prueba de BD falló"
        return 1
    fi
    
    # Prueba de permisos
    echo "- Probando permisos de escritura..."
    if [ -w "$LOG_DIR" ]; then
        echo "Permisos logs: ✅ OK"
    else
        echo "Permisos logs: ❌ ERROR"
        return 1
    fi
    
    if [ -w "$BACKUP_DIR" ]; then
        echo "Permisos backups: ✅ OK"
    else
        echo "Permisos backups: ❌ ERROR"
        return 1
    fi
    
    # Prueba de espacio en disco
    echo "- Verificando espacio en disco..."
    disk_usage=$(df "$PROJECT_ROOT" | tail -1 | awk '{print $5}' | sed 's/%//')
    echo "Uso de disco: ${disk_usage}%"
    
    if [ "$disk_usage" -gt 90 ]; then
        echo "⚠️  WARNING: Espacio en disco crítico"
    fi
    
    # Verificar que PHP puede ejecutar scripts
    echo "- Verificando PHP..."
    php_version=$($PHP_PATH -v | head -1)
    echo "PHP: ✅ $php_version"
    
    # Verificar que las tablas existen
    echo "- Verificando tablas de BD..."
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $required_tables = ["usuarios", "tiendas", "celulares", "ventas", "activity_logs", "productos", "stock_productos"];
        
        $missing = [];
        foreach($required_tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE \"$table\"");
            if($stmt->rowCount() == 0) {
                $missing[] = $table;
            }
        }
        
        if(!empty($missing)) {
            echo "❌ Tablas faltantes: " . implode(", ", $missing) . "\n";
            exit(1);
        }
        
        echo "Tablas: ✅ OK (" . count($required_tables) . " tablas verificadas)\n";
    } catch(Exception $e) {
        echo "Error verificando tablas: " . $e->getMessage() . "\n";
        exit(1);
    }
    '
    
    if [ $? -ne 0 ]; then
        echo "❌ Verificación de tablas falló"
        return 1
    fi
    
    # Verificar archivo .env
    echo "- Verificando configuración .env..."
    if [ -f "$PROJECT_ROOT/.env" ]; then
        echo "Archivo .env: ✅ Encontrado"
        
        # Verificar permisos del .env
        env_perms=$(stat -c %a "$PROJECT_ROOT/.env" 2>/dev/null || stat -f %A "$PROJECT_ROOT/.env" 2>/dev/null)
        if [ "$env_perms" = "600" ]; then
            echo "Permisos .env: ✅ OK (600)"
        else
            echo "Permisos .env: ⚠️  WARNING ($env_perms) - Recomendado: 600"
        fi
    else
        echo "Archivo .env: ❌ NO ENCONTRADO"
        echo "Copia .env.example a .env y configúralo"
        return 1
    fi
    
    # Verificar procedimientos almacenados
    echo "- Verificando procedimientos almacenados..."
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $required_procedures = ["generar_codigos_barras_masivo", "AjustarInventario", "LimpiarLogsAntiguos"];
        
        $missing = [];
        foreach($required_procedures as $proc) {
            $stmt = $db->query("SHOW PROCEDURE STATUS WHERE Name = \"$proc\"");
            if($stmt->rowCount() == 0) {
                $missing[] = $proc;
            }
        }
        
        if(!empty($missing)) {
            echo "⚠️  Procedimientos faltantes: " . implode(", ", $missing) . "\n";
        } else {
            echo "Procedimientos: ✅ OK\n";
        }
    } catch(Exception $e) {
        echo "Error verificando procedimientos: " . $e->getMessage() . "\n";
    }
    '
    
    echo ""
    echo "🏁 Todas las pruebas completadas exitosamente"
    log_message "Pruebas del sistema completadas - Todo OK"
    return 0
}

#===============================================================================
# CONFIGURACIÓN AUTOMÁTICA DE CRON JOBS
#===============================================================================

install_cron_jobs() {
    echo "📅 Instalando cron jobs automáticos..."
    
    # Crear archivo temporal con los cron jobs
    cat > /tmp/phone_inventory_crons << EOF
# ===============================================
# Sistema de Inventario de Celulares - Tareas Automáticas
# Generado: $(date)
# ===============================================

# Backup diario a las 2:00 AM
0 2 * * * $PROJECT_ROOT/scripts/cron_jobs.sh backup >/dev/null 2>&1

# Limpieza de logs semanal (domingos 3:00 AM)
0 3 * * 0 $PROJECT_ROOT/scripts/cron_jobs.sh cleanup-logs >/dev/null 2>&1

# Verificación de salud cada 6 horas
0 */6 * * * $PROJECT_ROOT/scripts/cron_jobs.sh health-check >/dev/null 2>&1

# Optimización de BD semanal (lunes 1:00 AM)
0 1 * * 1 $PROJECT_ROOT/scripts/cron_jobs.sh optimize >/dev/null 2>&1

# Reporte diario a las 8:00 AM
0 8 * * * $PROJECT_ROOT/scripts/cron_jobs.sh daily-report >/dev/null 2>&1

# Verificar alertas cada 4 horas
0 */4 * * * $PROJECT_ROOT/scripts/cron_jobs.sh alerts >/dev/null 2>&1

# Limpieza de backups mensual (día 1, 4:00 AM)
0 4 1 * * $PROJECT_ROOT/scripts/cron_jobs.sh cleanup-backups >/dev/null 2>&1

# Mantenimiento completo trimestral
0 2 1 */3 * $PROJECT_ROOT/scripts/cron_jobs.sh full-maintenance >/dev/null 2>&1

EOF

    # Instalar cron jobs
    crontab /tmp/phone_inventory_crons
    rm /tmp/phone_inventory_crons
    
    echo "✅ Cron jobs instalados correctamente"
    echo ""
    echo "Para verificar: crontab -l"
    echo "Para editar: crontab -e"
    echo "Para desinstalar: crontab -r"
}

# Función para desinstalar cron jobs
uninstall_cron_jobs() {
    echo "⚠️  Esto eliminará TODOS los cron jobs del usuario actual"
    echo "¿Estás seguro? (y/N): "
    read -r response
    
    if [[ "$response" =~ ^[Yy]$ ]]; then
        crontab -r
        echo "✅ Cron jobs eliminados"
    else
        echo "Operación cancelada"
    fi
}

#===============================================================================
# MANEJO DE PARÁMETROS ESPECIALES
#===============================================================================

if [ "$1" = "install-crons" ]; then
    install_cron_jobs
    exit 0
elif [ "$1" = "uninstall-crons" ]; then
    uninstall_cron_jobs
    exit 0
elif [ "$1" = "test" ]; then
    run_tests
    exit $?
fi

#===============================================================================
# EJECUTAR FUNCIÓN PRINCIPAL
#===============================================================================

main "$@"
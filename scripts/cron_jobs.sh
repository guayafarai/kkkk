#!/bin/bash

#===============================================================================
# SCRIPT DE TAREAS PROGRAMADAS - SISTEMA INVENTARIO CELULARES
# VERSIÓN CORREGIDA v1.1
#
# CORRECCIONES:
# ✅ Contadores corregidos (se cuentan antes de eliminar)
# ✅ Verificación de comandos disponibles
# ✅ Mejor manejo de errores
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
# VERIFICACIÓN DE DEPENDENCIAS
#===============================================================================

check_dependencies() {
    local missing=0
    
    # Verificar PHP
    if ! command -v "$PHP_PATH" >/dev/null 2>&1; then
        echo "ERROR: PHP no encontrado en $PHP_PATH"
        missing=1
    fi
    
    # Verificar gzip (opcional)
    if ! command -v gzip >/dev/null 2>&1; then
        echo "WARNING: gzip no disponible - la compresión de logs no funcionará"
    fi
    
    # Verificar find
    if ! command -v find >/dev/null 2>&1; then
        echo "ERROR: find no disponible"
        missing=1
    fi
    
    return $missing
}

#===============================================================================
# TAREAS DISPONIBLES
#===============================================================================

# 1. BACKUP AUTOMÁTICO DE BASE DE DATOS
backup_database() {
    log_message "Iniciando backup automático de base de datos"
    
    cd "$PROJECT_ROOT" || {
        log_message "ERROR: No se pudo acceder al directorio $PROJECT_ROOT"
        return 1
    }
    
    $PHP_PATH scripts/backup.php
    
    if [ $? -eq 0 ]; then
        log_message "Backup completado exitosamente"
        return 0
    else
        log_message "ERROR: Falló el backup de base de datos"
        return 1
    fi
}

# 2. LIMPIAR LOGS ANTIGUOS - ✅ CORREGIDO
cleanup_logs() {
    log_message "Iniciando limpieza de logs antiguos"
    
    local compressed=0
    local deleted=0
    
    # Comprimir logs de más de 7 días (si gzip está disponible)
    if command -v gzip >/dev/null 2>&1; then
        while IFS= read -r logfile; do
            if [ -s "$logfile" ]; then
                gzip "$logfile" && compressed=$((compressed + 1))
            fi
        done < <(find "$LOG_DIR" -name "*.log" -mtime +7 -type f 2>/dev/null)
        
        log_message "Logs comprimidos: $compressed"
    fi
    
    # ✅ CORREGIDO: Contar ANTES de eliminar
    deleted=$(find "$LOG_DIR" -name "*.log.gz" -mtime +30 -type f 2>/dev/null | wc -l)
    
    # Eliminar archivos comprimidos antiguos
    find "$LOG_DIR" -name "*.log.gz" -mtime +30 -type f -delete 2>/dev/null
    
    log_message "Limpieza completada. Archivos eliminados: $deleted"
    return 0
}

# 3. LIMPIEZA DE BACKUPS ANTIGUOS - ✅ CORREGIDO
cleanup_backups() {
    log_message "Iniciando limpieza de backups antiguos"
    
    # ✅ CORREGIDO: Contar ANTES de eliminar
    local deleted
    deleted=$(find "$BACKUP_DIR" -name "backup_*.sql*" -mtime +60 -type f 2>/dev/null | wc -l)
    
    # Eliminar backups antiguos
    find "$BACKUP_DIR" -name "backup_*.sql*" -mtime +60 -type f -delete 2>/dev/null
    
    log_message "Limpieza de backups completada. Archivos eliminados: $deleted"
    return 0
}

# 4. VERIFICAR SALUD DEL SISTEMA
check_system_health() {
    log_message "Verificando salud del sistema"
    
    # Verificar espacio en disco
    local disk_usage
    disk_usage=$(df "$PROJECT_ROOT" 2>/dev/null | tail -1 | awk '{print $5}' | sed 's/%//')
    
    if [ -z "$disk_usage" ]; then
        log_message "WARNING: No se pudo determinar el uso de disco"
        disk_usage=0
    fi
    
    if [ "$disk_usage" -gt 90 ]; then
        log_message "ALERTA: Espacio en disco crítico: ${disk_usage}%"
    elif [ "$disk_usage" -gt 80 ]; then
        log_message "WARNING: Espacio en disco alto: ${disk_usage}%"
    fi
    
    # Verificar tamaño de logs
    local log_size
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
    return 0
}

# 5. OPTIMIZAR BASE DE DATOS
optimize_database() {
    log_message "Iniciando optimización de base de datos"
    
    cd "$PROJECT_ROOT" || {
        log_message "ERROR: No se pudo acceder al directorio $PROJECT_ROOT"
        return 1
    }
    
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
        return 0
    else
        log_message "ERROR: Falló la optimización de base de datos"
        return 1
    fi
}

# 6. GENERAR REPORTE DIARIO
generate_daily_report() {
    log_message "Generando reporte diario"
    
    cd "$PROJECT_ROOT" || {
        log_message "ERROR: No se pudo acceder al directorio $PROJECT_ROOT"
        return 1
    }
    
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $yesterday = date("Y-m-d", strtotime("-1 day"));
        
        $stmt = $db->prepare("SELECT COUNT(*) as ventas, COALESCE(SUM(precio_venta), 0) as ingresos FROM ventas WHERE DATE(fecha_venta) = ?");
        $stmt->execute([$yesterday]);
        $ventas = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT COUNT(*) as nuevos FROM celulares WHERE DATE(fecha_registro) = ?");
        $stmt->execute([$yesterday]);
        $nuevos = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as usuarios FROM activity_logs WHERE DATE(created_at) = ?");
        $stmt->execute([$yesterday]);
        $usuarios = $stmt->fetch();
        
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
        return 0
    else
        log_message "ERROR: Falló la generación del reporte diario"
        return 1
    fi
}

# 7. ENVIAR ALERTAS
send_alerts() {
    log_message "Verificando alertas del sistema"
    
    cd "$PROJECT_ROOT" || {
        log_message "ERROR: No se pudo acceder al directorio $PROJECT_ROOT"
        return 1
    }
    
    $PHP_PATH -r '
    require_once "config/database.php";
    try {
        $db = getDB();
        $alerts_found = false;
        
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
    return 0
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
# EJECUTAR FUNCIÓN PRINCIPAL
#===============================================================================

# Verificar dependencias antes de ejecutar
if [ "$1" != "" ] && [ "$1" != "test" ] && [ "$1" != "install-crons" ] && [ "$1" != "uninstall-crons" ]; then
    if ! check_dependencies; then
        echo "ERROR: Faltan dependencias críticas"
        exit 1
    fi
fi

main "$@"

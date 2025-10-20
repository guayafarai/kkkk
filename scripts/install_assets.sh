#!/bin/bash

#===============================================================================
# SCRIPT DE INSTALACIÓN DE ASSETS LOCALES
# Descarga jQuery, Bootstrap, Font Awesome y AdminLTE localmente
# para evitar problemas con CSP
#===============================================================================

echo "🚀 Instalando assets locales para evitar problemas CSP..."
echo "=========================================================="

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # Sin color

# Directorio base
PROJECT_ROOT="/home/chamotvs/ventas.chamotv.xyz"
ASSETS_DIR="$PROJECT_ROOT/public/assets"

# Crear directorios
echo -e "${YELLOW}📁 Creando directorios...${NC}"
mkdir -p "$ASSETS_DIR/js"
mkdir -p "$ASSETS_DIR/fontawesome/css"
mkdir -p "$ASSETS_DIR/fontawesome/webfonts"
mkdir -p "$ASSETS_DIR/adminlte"

cd "$ASSETS_DIR"

# Descargar jQuery
echo -e "${YELLOW}📥 Descargando jQuery 3.6.0...${NC}"
wget -q https://code.jquery.com/jquery-3.6.0.min.js -O js/jquery.min.js
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ jQuery descargado${NC}"
else
    echo -e "${RED}❌ Error descargando jQuery${NC}"
fi

# Descargar Bootstrap 5
echo -e "${YELLOW}📥 Descargando Bootstrap 5.3.0...${NC}"
wget -q https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js -O js/bootstrap.bundle.min.js
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Bootstrap JS descargado${NC}"
else
    echo -e "${RED}❌ Error descargando Bootstrap${NC}"
fi

# Descargar Font Awesome
echo -e "${YELLOW}📥 Descargando Font Awesome 6.5.1...${NC}"
wget -q https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css -O fontawesome/css/all.min.css

# Descargar fuentes de Font Awesome
echo -e "${YELLOW}📥 Descargando fuentes de Font Awesome...${NC}"
wget -q https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2 -O fontawesome/webfonts/fa-solid-900.woff2
wget -q https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-regular-400.woff2 -O fontawesome/webfonts/fa-regular-400.woff2
wget -q https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.woff2 -O fontawesome/webfonts/fa-brands-400.woff2

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Font Awesome descargado${NC}"
else
    echo -e "${RED}❌ Error descargando Font Awesome${NC}"
fi

# Descargar AdminLTE 4
echo -e "${YELLOW}📥 Descargando AdminLTE 4 RC5...${NC}"
wget -q https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc.5/dist/css/adminlte.min.css -O adminlte/adminlte.min.css
wget -q https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc.5/dist/js/adminlte.min.js -O adminlte/adminlte.min.js

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ AdminLTE descargado${NC}"
else
    echo -e "${RED}❌ Error descargando AdminLTE${NC}"
fi

# Ajustar rutas en Font Awesome CSS
echo -e "${YELLOW}🔧 Ajustando rutas de fuentes...${NC}"
sed -i 's|../webfonts/|../fontawesome/webfonts/|g' fontawesome/css/all.min.css

# Establecer permisos
echo -e "${YELLOW}🔐 Estableciendo permisos...${NC}"
chmod -R 755 "$ASSETS_DIR"

# Verificar instalación
echo ""
echo "=========================================================="
echo -e "${GREEN}✅ INSTALACIÓN COMPLETADA${NC}"
echo "=========================================================="
echo ""
echo "📁 Archivos instalados:"
echo "   ✓ jQuery: $ASSETS_DIR/js/jquery.min.js"
echo "   ✓ Bootstrap: $ASSETS_DIR/js/bootstrap.bundle.min.js"
echo "   ✓ Font Awesome CSS: $ASSETS_DIR/fontawesome/css/all.min.css"
echo "   ✓ Font Awesome Fonts: $ASSETS_DIR/fontawesome/webfonts/"
echo "   ✓ AdminLTE CSS: $ASSETS_DIR/adminlte/adminlte.min.css"
echo "   ✓ AdminLTE JS: $ASSETS_DIR/adminlte/adminlte.min.js"
echo ""
echo "🎉 Ahora recarga tu dashboard: https://ventas.chamotv.xyz/public/dashboard.php"
echo ""

# Verificar tamaños
echo "📊 Tamaños de archivos:"
du -h "$ASSETS_DIR/js/jquery.min.js" 2>/dev/null
du -h "$ASSETS_DIR/js/bootstrap.bundle.min.js" 2>/dev/null
du -h "$ASSETS_DIR/fontawesome/css/all.min.css" 2>/dev/null
du -h "$ASSETS_DIR/adminlte/adminlte.min.css" 2>/dev/null
du -h "$ASSETS_DIR/adminlte/adminlte.min.js" 2>/dev/null

echo ""
echo "💡 Tip: Si aún hay problemas de CSP, edita config/database.php"
echo "   y comenta la línea Content-Security-Policy"
echo ""
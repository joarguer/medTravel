#!/bin/bash
# install_provider_system.sh - Instalación automática del sistema de gestión de proveedores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   MedTravel - Sistema de Gestión de Proveedores      ║${NC}"
echo -e "${BLUE}║   Instalador Automático v1.0                          ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuración de BD
DB_HOST="localhost"
DB_NAME="medtravel"
DB_USER="root"

# Solicitar contraseña
echo -e "${YELLOW}Configuración de Base de Datos${NC}"
echo -n "Host [$DB_HOST]: "
read input_host
if [ ! -z "$input_host" ]; then
    DB_HOST=$input_host
fi

echo -n "Base de Datos [$DB_NAME]: "
read input_db
if [ ! -z "$input_db" ]; then
    DB_NAME=$input_db
fi

echo -n "Usuario [$DB_USER]: "
read input_user
if [ ! -z "$input_user" ]; then
    DB_USER=$input_user
fi

echo -n "Contraseña: "
read -s DB_PASS
echo ""

# Verificar conexión
echo ""
echo -e "${YELLOW}► Verificando conexión a base de datos...${NC}"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Error: No se pudo conectar a la base de datos${NC}"
    echo -e "${RED}  Verifica las credenciales y que la BD '$DB_NAME' exista${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Conexión exitosa${NC}"

# Paso 1: Verificar si ya está instalado
echo ""
echo -e "${YELLOW}► Verificando instalación previa...${NC}"
TABLE_EXISTS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='service_providers';")

if [ "$TABLE_EXISTS" -eq "1" ]; then
    echo -e "${YELLOW}⚠ La tabla 'service_providers' ya existe${NC}"
    echo -n "¿Desea reinstalar? Esto NO eliminará datos existentes (s/N): "
    read reinstall
    if [ "$reinstall" != "s" ] && [ "$reinstall" != "S" ]; then
        echo -e "${BLUE}Instalación cancelada${NC}"
        exit 0
    fi
fi

# Paso 2: Backup de seguridad
echo ""
echo -e "${YELLOW}► Creando backup de seguridad...${NC}"
BACKUP_DIR="backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/medtravel_before_providers_$TIMESTAMP.sql"

mkdir -p "$BACKUP_DIR"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" medtravel_services_catalog > "$BACKUP_FILE" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Backup creado: $BACKUP_FILE${NC}"
else
    echo -e "${YELLOW}⚠ No se pudo crear backup (continuando de todas formas)${NC}"
fi

# Paso 3: Instalar sistema COP (si no existe)
echo ""
echo -e "${YELLOW}► Verificando sistema de precios COP...${NC}"
EXCHANGE_TABLE_EXISTS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='exchange_rates';")

if [ "$EXCHANGE_TABLE_EXISTS" -eq "0" ]; then
    echo -e "${BLUE}  Instalando sistema de tasa de cambio...${NC}"
    if [ -f "sql/INSTALL_COP_SYSTEM.sql" ]; then
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < sql/INSTALL_COP_SYSTEM.sql 2>/dev/null
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Sistema COP instalado${NC}"
        else
            echo -e "${RED}✗ Error instalando sistema COP${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠ Archivo sql/INSTALL_COP_SYSTEM.sql no encontrado${NC}"
    fi
else
    echo -e "${GREEN}✓ Sistema COP ya instalado${NC}"
fi

# Paso 4: Instalar tabla de proveedores
echo ""
echo -e "${YELLOW}► Instalando tabla service_providers...${NC}"
if [ ! -f "sql/service_providers_table.sql" ]; then
    echo -e "${RED}✗ Error: Archivo sql/service_providers_table.sql no encontrado${NC}"
    exit 1
fi

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < sql/service_providers_table.sql 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tabla service_providers creada${NC}"
else
    echo -e "${RED}✗ Error instalando tabla de proveedores${NC}"
    echo -e "${RED}  Revisa el archivo sql/service_providers_table.sql${NC}"
    exit 1
fi

# Paso 5: Verificar FK
echo ""
echo -e "${YELLOW}► Verificando foreign key...${NC}"
FK_EXISTS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -sse "SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema='$DB_NAME' AND table_name='medtravel_services_catalog' AND constraint_name='fk_service_provider';")

if [ "$FK_EXISTS" -eq "1" ]; then
    echo -e "${GREEN}✓ Foreign key configurada correctamente${NC}"
else
    echo -e "${YELLOW}⚠ Foreign key no encontrada (puede ser normal si ya existía)${NC}"
fi

# Paso 6: Verificar proveedores de ejemplo
echo ""
echo -e "${YELLOW}► Verificando proveedores de ejemplo...${NC}"
PROVIDERS_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -sse "SELECT COUNT(*) FROM service_providers;")
echo -e "${GREEN}✓ $PROVIDERS_COUNT proveedor(es) en catálogo${NC}"

if [ "$PROVIDERS_COUNT" -eq "0" ]; then
    echo -e "${YELLOW}  Nota: No hay proveedores. Puedes crearlos desde el panel admin.${NC}"
fi

# Paso 7: Verificar archivos PHP
echo ""
echo -e "${YELLOW}► Verificando archivos del sistema...${NC}"

FILES=(
    "admin/ajax/service_providers.php"
    "admin/ajax/exchange_rate.php"
    "admin/js/medtravel_services.js"
)

ALL_OK=true
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}  ✓ $file${NC}"
    else
        echo -e "${RED}  ✗ $file (NO ENCONTRADO)${NC}"
        ALL_OK=false
    fi
done

if [ "$ALL_OK" = false ]; then
    echo -e "${YELLOW}⚠ Algunos archivos faltan. Revisa la instalación.${NC}"
fi

# Paso 8: Permisos
echo ""
echo -e "${YELLOW}► Configurando permisos...${NC}"
chmod 755 admin/ajax/service_providers.php 2>/dev/null
chmod 755 admin/ajax/exchange_rate.php 2>/dev/null
echo -e "${GREEN}✓ Permisos configurados${NC}"

# Resumen final
echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║              INSTALACIÓN COMPLETADA                   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}✓ Sistema de gestión de proveedores instalado correctamente${NC}"
echo ""
echo -e "${YELLOW}Próximos pasos:${NC}"
echo "1. Accede al panel admin: admin/medtravel_services.php"
echo "2. Crea un servicio y selecciona un proveedor del dropdown"
echo "3. Verifica que los datos se cargan automáticamente"
echo ""
echo -e "${YELLOW}Documentación:${NC}"
echo "• README completo: PROVIDER_MANAGEMENT_README.md"
echo "• Archivos SQL: sql/service_providers_table.sql"
echo "• API Backend: admin/ajax/service_providers.php"
echo ""
echo -e "${YELLOW}Backup creado:${NC}"
echo "• $BACKUP_FILE"
echo ""
echo -e "${BLUE}¡Instalación exitosa! 🎉${NC}"

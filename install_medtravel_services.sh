#!/bin/bash
# Script para instalar MedTravel Services Catalog y su integración
# Asegúrate de editar las credenciales de base de datos

# Configuración de base de datos
DB_USER="root"
DB_PASS=""  # Agregar tu contraseña aquí
DB_NAME="medtravel"
DB_HOST="localhost"

echo "=========================================="
echo "Instalando MedTravel Services Catalog"
echo "=========================================="

# Ejecutar SQL del catálogo de servicios
echo "1. Creando tabla medtravel_services_catalog..."
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/medtravel_services_catalog.sql

if [ $? -eq 0 ]; then
    echo "✅ Catálogo de servicios instalado exitosamente"
else
    echo "❌ Error al instalar catálogo de servicios"
    exit 1
fi

echo ""
echo "2. Creando integración con Travel Packages..."
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/package_services_integration.sql

if [ $? -eq 0 ]; then
    echo "✅ Integración instalada exitosamente"
else
    echo "❌ Error al instalar integración"
    exit 1
fi

echo ""
echo "=========================================="
echo "✅ Instalación completada exitosamente!"
echo "=========================================="
echo ""
echo "Accede al panel admin:"
echo "- MedTravel Services: admin/medtravel_services.php"
echo "- Travel Packages: admin/paquetes.php"
echo ""

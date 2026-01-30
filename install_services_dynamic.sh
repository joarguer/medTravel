#!/bin/bash
# Script de instalación rápida para Services Dynamic Management

echo "==================================================="
echo "Services Page - Dynamic Management Installation"
echo "==================================================="
echo ""

# Verificar directorio de imágenes
echo "1. Verificando/Creando directorio de imágenes..."
if [ ! -d "img/services" ]; then
    mkdir -p img/services
    chmod 777 img/services
    echo "✓ Directorio img/services creado"
else
    echo "✓ Directorio img/services ya existe"
fi

echo ""
echo "2. Siguientes pasos:"
echo "   - Ejecutar SQL: sql/services_coordination_table.sql"
echo "   - O si ya existe: sql/ALTER_services_add_header_image.sql"
echo ""
echo "3. Acceder al panel admin:"
echo "   - URL: admin/services_edit.php"
echo "   - Subir imagen del header"
echo "   - Configurar iconos y textos"
echo ""
echo "==================================================="
echo "Instalación preparada correctamente"
echo "==================================================="

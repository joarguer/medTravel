#!/bin/bash
# Script para agregar el campo legal_name a la tabla providers
# Ejecutar desde: /medtravel/sql/

echo "==========================================="
echo "Agregando campo legal_name a providers"
echo "==========================================="

mysql -u root -p123456 bolsacar_medtravel < ALTER_providers_add_legal_name.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Campo legal_name agregado exitosamente"
    echo ""
    echo "Verificando estructura de la tabla..."
    mysql -u root -p123456 bolsacar_medtravel -e "DESCRIBE providers" | grep -E "Field|legal_name"
else
    echo ""
    echo "❌ Error al ejecutar el script SQL"
    echo "Por favor verifica la conexión a la base de datos"
fi

echo ""
echo "==========================================="

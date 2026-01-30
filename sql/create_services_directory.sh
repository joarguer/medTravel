#!/bin/bash
# Script para crear el directorio de servicios con permisos correctos

# Crear directorio si no existe
mkdir -p img/services

# Establecer permisos
chmod 755 img/services

echo "Directorio img/services creado con permisos 755"

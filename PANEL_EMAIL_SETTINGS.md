# 🎛️ Panel de Administración de Email SMTP

**Fecha:** 29 enero 2026  
**Ubicación:** `admin/email_settings.php`  
**Base de Datos:** Tabla `email_settings`

---

## 📋 Descripción

Sistema de administración de cuentas de email SMTP desde el panel de administración, con almacenamiento encriptado en base de datos.

### ✨ Características

- ✅ Interfaz gráfica para administrar credenciales SMTP
- 🔐 Contraseñas encriptadas con AES-256-CBC
- 🧪 Pruebas de conexión en tiempo real
- 📧 Envío de emails de prueba
- 📊 Registro de pruebas (fecha y estado)
- 🔄 Actualización sin necesidad de editar archivos PHP
- 🎨 Interfaz responsiva y amigable

---

## 🚀 Instalación

### Paso 1: Crear la Tabla en la Base de Datos

```bash
# Opción 1: Desde línea de comandos
mysql -u usuario -p nombre_bd < sql/email_settings_table.sql

# Opción 2: Desde phpMyAdmin
# Importar el archivo: sql/email_settings_table.sql
```

### Paso 2: Verificar la Creación

Verificar que la tabla `email_settings` existe con 4 registros por defecto:
- patientcare@medtravel.com.co
- info@medtravel.com.co
- noreply@medtravel.com.co
- providers@medtravel.com.co

### Paso 3: Acceder al Panel

URL: `http://tu-dominio.com/admin/email_settings.php`

---

## 💻 Cómo Usar

### 1. Configurar Cuentas de Email

1. Acceder a **admin/email_settings.php**
2. Hacer clic en **"Editar"** en cualquier cuenta
3. Completar los campos:
   - **Dirección Email**: Email completo (ej: patientcare@medtravel.com.co)
   - **Nombre para Mostrar**: Nombre que aparecerá al enviar
   - **Usuario SMTP**: Generalmente el email completo
   - **Contraseña SMTP**: La contraseña del email
   - **Responder A**: Email de respuesta (opcional)
   - **Descripción**: Notas sobre el uso de esta cuenta
   - **Estado**: Activa/Inactiva

4. Click en **"Guardar Cambios"**

### 2. Probar Conexión SMTP

Click en el botón **"Probar Conexión"** de cada cuenta:
- ✅ Verde: Conexión exitosa
- ❌ Rojo: Error de conexión (revisar credenciales)

### 3. Enviar Email de Prueba

1. Click en **"Enviar Test"**
2. Ingresar email de destino
3. Verificar que el email llegue correctamente

### 4. Probar Todas las Cuentas

Click en **"Probar Todas las Cuentas"** en la parte superior para verificar todas de una vez.

---

## 🔒 Seguridad

### Encriptación de Contraseñas

Las contraseñas se almacenan encriptadas usando:
- **Algoritmo**: AES-256-CBC
- **Clave**: Definida en `EMAIL_ENCRYPTION_KEY`
- **Vector de Inicialización**: Aleatorio por cada contraseña

### Cambiar la Clave de Encriptación

⚠️ **IMPORTANTE**: Cambiar en AMBOS archivos:

1. **admin/ajax/email_settings.php**:
```php
define('ENCRYPTION_KEY', 'TU_CLAVE_SEGURA_AQUI');
```

2. **admin/include/email_config.php**:
```php
define('EMAIL_ENCRYPTION_KEY', 'TU_CLAVE_SEGURA_AQUI');
```

⚠️ Si cambias la clave después de guardar contraseñas, deberás volver a configurarlas.

---

## 🗂️ Estructura de Archivos

```
admin/
├── email_settings.php              # Interfaz de administración
├── ajax/
│   └── email_settings.php          # Backend API
├── include/
│   ├── email_config.php            # Sistema de email (actualizado)
│   └── email_credentials.php       # Fallback (opcional, mantener para compatibilidad)
└── ...

sql/
└── email_settings_table.sql        # Creación de tabla
```

---

## 📊 Tabla de Base de Datos

### Estructura: `email_settings`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | ID único |
| account_type | VARCHAR(50) | Tipo: patientcare, info, noreply, providers |
| email_address | VARCHAR(255) | Dirección de email |
| display_name | VARCHAR(255) | Nombre para mostrar |
| smtp_host | VARCHAR(255) | Servidor SMTP (mail.medtravel.com.co) |
| smtp_port | INT | Puerto (465) |
| smtp_secure | VARCHAR(10) | Tipo de encriptación (ssl/tls) |
| smtp_username | VARCHAR(255) | Usuario SMTP |
| smtp_password | TEXT | Contraseña encriptada |
| reply_to | VARCHAR(255) | Email de respuesta |
| is_active | TINYINT | 1 = activa, 0 = inactiva |
| description | TEXT | Descripción del uso |
| last_test_date | DATETIME | Fecha última prueba |
| last_test_status | VARCHAR(50) | success / failed |
| created_at | TIMESTAMP | Fecha de creación |
| updated_at | TIMESTAMP | Fecha de actualización |

---

## 🔌 API Backend

### Endpoints Disponibles

**URL Base**: `admin/ajax/email_settings.php`

#### 1. Listar Cuentas
```javascript
$.post('ajax/email_settings.php', {
    action: 'list'
}, function(response) {
    // response.data = array de cuentas
});
```

#### 2. Obtener Cuenta Específica
```javascript
$.post('ajax/email_settings.php', {
    action: 'get',
    id: 1
}, function(response) {
    // response.data = datos de la cuenta
});
```

#### 3. Actualizar Cuenta
```javascript
$.post('ajax/email_settings.php', {
    action: 'update',
    account_id: 1,
    email_address: 'test@medtravel.com.co',
    display_name: 'Test Account',
    smtp_username: 'test@medtravel.com.co',
    smtp_password: 'password123',
    reply_to: 'info@medtravel.com.co',
    description: 'Descripción',
    is_active: 1
}, function(response) {
    // response.ok = true/false
});
```

#### 4. Probar Conexión
```javascript
$.post('ajax/email_settings.php', {
    action: 'test_connection',
    id: 1
}, function(response) {
    // response.ok = true/false
    // response.message = resultado
});
```

#### 5. Enviar Email de Prueba
```javascript
$.post('ajax/email_settings.php', {
    action: 'send_test_email',
    id: 1,
    test_email: 'destino@example.com'
}, function(response) {
    // response.ok = true/false
});
```

#### 6. Probar Todas las Cuentas
```javascript
$.post('ajax/email_settings.php', {
    action: 'test_all'
}, function(response) {
    // response.data.success = cantidad exitosas
    // response.data.failed = cantidad fallidas
});
```

---

## 🔄 Integración con Sistema Existente

El sistema de email (`email_config.php`) ahora:

1. **Prioridad 1**: Lee credenciales desde la base de datos
2. **Prioridad 2**: Si no hay en BD, usa `email_credentials.php` (fallback)

### Uso en Código

```php
require_once('admin/include/email_config.php');

// La función getMailer() automáticamente carga desde BD
$result = sendEmail(
    'cliente@example.com',
    'Asunto',
    '<h1>Contenido HTML</h1>',
    'patientcare'  // Lee credenciales desde BD
);
```

---

## 🧪 Testing

### Checklist de Pruebas

- [ ] Crear tabla con SQL
- [ ] Acceder a admin/email_settings.php
- [ ] Verificar que aparecen las 4 cuentas por defecto
- [ ] Editar cuenta "patientcare"
- [ ] Agregar contraseña real
- [ ] Guardar cambios
- [ ] Probar conexión (debe ser exitosa)
- [ ] Enviar email de prueba
- [ ] Verificar recepción del email
- [ ] Repetir para las otras 3 cuentas
- [ ] Probar "Probar Todas las Cuentas"
- [ ] Verificar que el envío de cotizaciones funcione

---

## 🐛 Troubleshooting

### Error: "Tabla email_settings no existe"
**Solución**: Ejecutar `sql/email_settings_table.sql`

### Error: "Contraseña no configurada"
**Solución**: 
1. Ir a admin/email_settings.php
2. Editar la cuenta
3. Ingresar la contraseña SMTP
4. Guardar

### Error: "Authentication failed"
**Solución**:
1. Verificar que el usuario SMTP sea correcto (generalmente el email completo)
2. Verificar que la contraseña sea correcta
3. Verificar en cPanel que la cuenta existe

### Las contraseñas no se desencriptan correctamente
**Solución**:
1. Verificar que `ENCRYPTION_KEY` y `EMAIL_ENCRYPTION_KEY` sean iguales
2. Eliminar y volver a guardar las contraseñas

### Error: "Cannot modify header information"
**Solución**: Verificar que no haya salida antes de `header()` en email_settings.php

---

## 🔮 Mejoras Futuras

### Posibles Expansiones

1. **Multi-servidor**: Configurar múltiples servidores SMTP
2. **Estadísticas**: Dashboard con emails enviados por cuenta
3. **Límites**: Control de cuota de envío por cuenta
4. **Templates**: Gestionar plantillas de email desde el panel
5. **Logs**: Visualizar historial de emails enviados
6. **Alertas**: Notificaciones si una cuenta falla repetidamente
7. **Backup**: Exportar/importar configuraciones
8. **API Externa**: Integración con SendGrid, Mailgun, etc.

---

## 📞 Soporte

Para problemas con la configuración:
1. Verificar logs del sistema
2. Probar conexión desde test_smtp.php (legacy)
3. Revisar configuración en cPanel
4. Verificar firewall/puertos del servidor

---

## ✅ Ventajas del Nuevo Sistema

| Antes | Ahora |
|-------|-------|
| ❌ Editar archivos PHP manualmente | ✅ Interfaz gráfica |
| ❌ Contraseñas en texto plano | ✅ Encriptación AES-256 |
| ❌ Sin forma de probar | ✅ Tests integrados |
| ❌ Requiere acceso SSH/FTP | ✅ Todo desde el navegador |
| ❌ Sin historial de cambios | ✅ Registro de pruebas |
| ❌ Error prone | ✅ Validación automática |

---

**Sistema listo para uso en producción** 🚀

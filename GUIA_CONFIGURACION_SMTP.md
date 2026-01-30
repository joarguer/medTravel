# 📧 Guía de Configuración SMTP - MedTravel

**Fecha:** 29 enero 2026  
**Sistema:** Sistema de email profesional con PHPMailer  
**Servidor:** mail.medtravel.com.co (cPanel)

---

## 🎯 Objetivo

Configurar el envío de emails profesionales para:
- ✉️ Cotizaciones a clientes (patientcare@)
- 📬 Información general (info@)
- 🔔 Notificaciones automáticas (noreply@)
- 🏥 Comunicación con proveedores (providers@)

---

## 📁 Archivos del Sistema

### 1. **admin/include/email_config.php**
**Propósito:** Configuración y funciones de envío  
**Funciones principales:**
- `getMailer($account_type)` - Crea instancia PHPMailer configurada
- `sendEmail($to, $subject, $body, $account_type, $options)` - Enviar email simple
- `sendBulkEmail($recipients, $subject, $body, $account_type)` - Envío masivo
- `validateEmailConfig($account_type)` - Validar configuración

### 2. **admin/include/email_credentials.php** ⚠️
**Propósito:** Credenciales privadas (NO incluir en Git)  
**Seguridad:**
```bash
chmod 600 admin/include/email_credentials.php
```

### 3. **admin/ajax/paquetes.php**
**Propósito:** Envío de cotizaciones  
**Endpoint:** `action=send_quote`  
**Cuenta usada:** patientcare@medtravel.com.co

### 4. **admin/test_smtp.php**
**Propósito:** Página de pruebas y diagnóstico  
**Acceso:** http://tu-dominio.com/admin/test_smtp.php

---

## ⚙️ Configuración Paso a Paso

### Paso 1: Editar Credenciales

Abrir: `admin/include/email_credentials.php`

```php
// PATIENTCARE - Para cotizaciones
define('SMTP_PATIENTCARE_USER', 'patientcare@medtravel.com.co');
define('SMTP_PATIENTCARE_PASS', 'TU_CONTRASEÑA_AQUI'); // ⚠️ CONFIGURAR

// INFO - Para información general
define('SMTP_INFO_USER', 'info@medtravel.com.co');
define('SMTP_INFO_PASS', 'TU_CONTRASEÑA_AQUI'); // ⚠️ CONFIGURAR

// NOREPLY - Para notificaciones
define('SMTP_NOREPLY_USER', 'noreply@medtravel.com.co');
define('SMTP_NOREPLY_PASS', 'TU_CONTRASEÑA_AQUI'); // ⚠️ CONFIGURAR

// PROVIDERS - Para proveedores
define('SMTP_PROVIDERS_USER', 'providers@medtravel.com.co');
define('SMTP_PROVIDERS_PASS', 'TU_CONTRASEÑA_AQUI'); // ⚠️ CONFIGURAR
```

### Paso 2: Configurar cPanel (si aplica)

1. Acceder a cPanel de medtravel.com.co
2. Ir a "Cuentas de Email"
3. Crear/verificar las cuentas:
   - patientcare@medtravel.com.co
   - info@medtravel.com.co
   - noreply@medtravel.com.co
   - providers@medtravel.com.co
4. Anotar las contraseñas

### Paso 3: Asegurar el Archivo

```bash
# Terminal / SSH
cd /ruta/al/proyecto/admin/include
chmod 600 email_credentials.php
```

### Paso 4: Verificar .gitignore

Verificar que `.gitignore` incluye:
```
admin/include/email_credentials.php
```

### Paso 5: Probar la Configuración

1. Acceder a: `http://tu-dominio.com/admin/test_smtp.php`
2. Verificar que todos los tests pasen
3. Enviar email de prueba

---

## 🧪 Tests Disponibles

### Test 1: PHPMailer Disponible
✅ Verifica que la librería esté instalada

### Test 2: Credenciales Configuradas
✅ Verifica que todas las contraseñas estén configuradas

### Test 3: Configuración SMTP
📋 Muestra servidor, puerto y tipo de encriptación

### Test 4: Conexión SMTP
🔌 Intenta conectar con cada cuenta

### Test 5: Envío Real
📤 Envía un email de prueba al destinatario que elijas

---

## 💻 Uso en Código

### Ejemplo Básico
```php
require_once('admin/include/email_config.php');

// Enviar email simple
$result = sendEmail(
    'cliente@example.com',           // Destinatario
    'Asunto del mensaje',            // Asunto
    '<h1>Hola</h1><p>Contenido</p>', // Cuerpo HTML
    'patientcare'                    // Cuenta a usar
);

if($result) {
    echo "Email enviado exitosamente";
}
```

### Ejemplo con Opciones
```php
$options = array(
    'cc' => array('supervisor@medtravel.com.co'),
    'bcc' => array('archivo@medtravel.com.co'),
    'attachments' => array('/ruta/al/archivo.pdf')
);

sendEmail(
    'cliente@example.com',
    'Cotización Adjunta',
    $html_body,
    'patientcare',
    $options
);
```

### Envío Masivo
```php
$recipients = array(
    'cliente1@example.com',
    'cliente2@example.com',
    'cliente3@example.com'
);

$results = sendBulkEmail(
    $recipients,
    'Newsletter MedTravel',
    $html_body,
    'info'
);

echo "Enviados: {$results['success']}, Fallidos: {$results['failed']}";
```

---

## 🔒 Seguridad

### ✅ Buenas Prácticas
- ✔️ Archivo de credenciales con permisos 600
- ✔️ Credenciales fuera del código principal
- ✔️ .gitignore configurado
- ✔️ Logs de envíos activados
- ✔️ Validación de emails antes de enviar

### ❌ NO Hacer
- ❌ Subir credenciales a Git
- ❌ Poner contraseñas en archivos públicos
- ❌ Usar permisos 777 en archivos de config
- ❌ Hardcodear contraseñas en el código
- ❌ Desactivar validación SSL/TLS

---

## 🐛 Troubleshooting

### Error: "SMTP connect() failed"
**Solución:**
1. Verificar que el puerto sea correcto (465 para SSL, 587 para TLS)
2. Verificar firewall del servidor
3. Confirmar que cPanel permite conexiones SMTP externas

### Error: "Authentication failed"
**Solución:**
1. Verificar usuario y contraseña en email_credentials.php
2. Asegurar que la cuenta existe en cPanel
3. Probar login manual en webmail

### Error: "Could not instantiate mail function"
**Solución:**
1. Verificar que PHPMailer esté instalado
2. Verificar rutas en email_config.php
3. Verificar permisos de archivos

### Emails no llegan
**Solución:**
1. Revisar carpeta de SPAM del destinatario
2. Verificar logs: `admin/logs/`
3. Verificar SPF/DKIM del dominio en cPanel
4. Probar con test_smtp.php

---

## 📊 Configuración del Dominio

### SPF Record (Recommended)
```
v=spf1 a mx ip4:TU_IP_SERVIDOR ~all
```

### DKIM (Recommended)
Configurar en cPanel → Email Deliverability

### DMARC (Optional)
```
v=DMARC1; p=quarantine; rua=mailto:postmaster@medtravel.com.co
```

---

## 📈 Monitoreo

### Logs del Sistema
Los logs se guardan automáticamente en:
- `admin/logs/` (si existe)
- `error_log` del servidor

### Ver Logs Recientes
```bash
tail -f admin/logs/email.log
```

### Estadísticas de Envío
Usar el panel de cPanel → Email Deliverability

---

## 🔄 Usos Futuros

### Módulos que Usarán el Sistema

1. **Cotizaciones (Actual)**
   - Cuenta: patientcare@
   - Archivo: admin/ajax/paquetes.php
   - Función: sendQuoteEmail()

2. **Notificaciones Internas** (Futuro)
   - Cuenta: noreply@
   - Uso: Alertas al administrador

3. **Comunicación con Médicos** (Futuro)
   - Cuenta: providers@
   - Uso: Verificaciones, solicitudes

4. **Newsletter** (Futuro)
   - Cuenta: info@
   - Uso: Marketing, novedades

5. **Recordatorios de Citas** (Futuro)
   - Cuenta: noreply@
   - Uso: Confirmaciones automáticas

---

## 📞 Soporte

Para problemas de configuración:
1. Revisar test_smtp.php
2. Verificar logs del servidor
3. Contactar soporte de hosting si es problema del servidor
4. Verificar documentación de cPanel

---

## 📝 Checklist de Implementación

- [ ] Crear cuentas de email en cPanel
- [ ] Configurar email_credentials.php
- [ ] Asignar permisos 600 al archivo
- [ ] Agregar a .gitignore
- [ ] Ejecutar test_smtp.php
- [ ] Verificar Test 1: PHPMailer
- [ ] Verificar Test 2: Credenciales
- [ ] Verificar Test 3: Configuración
- [ ] Verificar Test 4: Conexión SMTP
- [ ] Enviar Test 5: Email de prueba
- [ ] Probar envío de cotización real
- [ ] Configurar SPF/DKIM (opcional)
- [ ] Documentar para el equipo

---

**✅ Sistema listo para producción una vez completado el checklist**

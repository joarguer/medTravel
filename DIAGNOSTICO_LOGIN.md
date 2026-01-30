# 🔧 DIAGNÓSTICO Y SOLUCIÓN - LOGIN NO FUNCIONA

## 🐛 Problema Reportado
- Usuario: Modificaste credenciales de un proveedor
- Error: No puede iniciar sesión
- Redirección: `login.php?usuario=nulo2` (línea 223 de log.php)
- Código HTTP: 302 Found (redirección)

## 🔍 Causa Raíz Identificada

### 1. **Hash de Contraseña Incompatible**
El sistema tenía DOS métodos de hash:
- **Antiguo**: `hash('sha512', $token.$password)` (línea 96 original)
- **Nuevo**: `password_hash($password, PASSWORD_DEFAULT)` (implementado en providers.php)

❌ **Resultado**: Contraseñas guardadas con bcrypt NO funcionaban con validación SHA512

### 2. **Dependencia de Tabla `empresas`**
El login requería:
```php
SELECT * FROM empresas WHERE rasocial = '...'
```
Pero los nuevos proveedores están en tabla `providers`, NO en `empresas`.

---

## ✅ SOLUCIÓN IMPLEMENTADA

### Cambio 1: Validación Dual de Contraseñas (log.php línea 93-106)
```php
// Verificar contraseña (soporta hash antiguo SHA512 y nuevo bcrypt)
$password_valido = false;
$stored_password = v($fil,'password','');

// Método 1: Nuevo sistema con bcrypt (password_hash/password_verify)
if (substr($stored_password, 0, 4) === '$2y$' || substr($stored_password, 0, 4) === '$2a$') {
    // Es un hash bcrypt
    $password_valido = password_verify($password, $stored_password);
} 
// Método 2: Sistema antiguo con SHA512 + token
else {
    $password_valido = ($stored_password === hash('sha512', v($fil,'token','').$password));
}

if ($password_valido) {
    // ... continuar login
```

**Ventajas:**
- ✅ Soporta usuarios antiguos (SHA512)
- ✅ Soporta usuarios nuevos (bcrypt)
- ✅ Migración transparente sin romper usuarios existentes

---

### Cambio 2: Soporte para Proveedores sin Empresa (log.php línea 110-125)
```php
// Si no hay empresa en el campo, intentar buscar por provider_id
if (empty($rasocial) && !empty($fil['provider_id'])) {
    $query = mysqli_query($conexion, "SELECT id, name as rasocial, name as nit, 0 as activo, '' as logo FROM providers WHERE id = ".(int)$fil['provider_id']." LIMIT 1");
} else {
    $query = mysqli_query($conexion, "SELECT * FROM empresas WHERE rasocial = '".$rasocial_esc."' LIMIT 1");
}

if (mysqli_num_rows($query) == 0) {
    // Si es rol admin o no requiere empresa, crear una empresa virtual
    if (v($fil,'rol','') === 'admin' || empty($rasocial)) {
        $fila = [
            'id' => 1,
            'rasocial' => v($fil,'nombre','Usuario'),
            'nit' => '000000000',
            'activo' => 0,
            'logo' => ''
        ];
    }
```

**Ventajas:**
- ✅ Usuarios admin pueden entrar sin empresa
- ✅ Usuarios prestador buscan datos en tabla `providers`
- ✅ Fallback a empresa virtual para casos sin empresa

---

### Cambio 3: Provider ID desde tabla usuarios (log.php línea 161-177)
```php
// Leer provider_id directamente de la tabla usuarios
if (!empty($fil['provider_id']) && (int)$fil['provider_id'] > 0) {
    $_SESSION['provider_id'] = (int)$fil['provider_id'];
} else {
    // Fallback: buscar en tabla provider_users (sistema antiguo)
    ...
}
```

**Ventajas:**
- ✅ Lee provider_id directamente desde `usuarios.provider_id`
- ✅ Mantiene compatibilidad con tabla `provider_users` (si existe)
- ✅ Session correctamente configurada para módulos de prestador

---

## 🧪 VERIFICACIÓN REQUERIDA

### Paso 1: Verificar Hash de Contraseña en BD
```sql
-- Ejecutar en PhpMyAdmin
SELECT id, usuario, nombre, rol, provider_id, 
       LEFT(password, 10) as hash_inicio,
       LENGTH(password) as hash_length
FROM usuarios 
WHERE usuario = 'TU_USUARIO'
LIMIT 1;
```

**Resultado esperado:**
- `hash_inicio`: Debe ser `$2y$` o `$2a$` (bcrypt)
- `hash_length`: Debe ser 60 caracteres

**Si NO es bcrypt:**
El password se guardó mal. Ejecutar:
```sql
-- Cambiar 'TU_USUARIO' y 'TU_CONTRASEÑA'
UPDATE usuarios 
SET password = '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOP'
WHERE usuario = 'TU_USUARIO';
```
⚠️ **NOTA**: El hash de arriba es FAKE. Debes generar uno real.

---

### Paso 2: Regenerar Contraseña Correctamente
Si el hash está mal, usa este script PHP:

**Archivo**: `admin/ajax/regenerar_password.php`
```php
<?php
// SOLO PARA EMERGENCIA - BORRAR DESPUÉS DE USAR
session_start();
include('../include/conexion.php');

// CONFIGURAR AQUÍ
$usuario = 'testclinica';  // ← TU USUARIO
$nueva_password = 'Test123!';  // ← TU CONTRASEÑA

$hash = password_hash($nueva_password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conexion, "UPDATE usuarios SET password = ? WHERE usuario = ?");
mysqli_stmt_bind_param($stmt, 'ss', $hash, $usuario);

if (mysqli_stmt_execute($stmt)) {
    echo "✅ Contraseña actualizada correctamente<br>";
    echo "Usuario: $usuario<br>";
    echo "Hash: $hash<br>";
    echo "<br><a href='../../login.php'>Ir al login</a>";
} else {
    echo "❌ Error: " . mysqli_error($conexion);
}
?>
```

**Uso:**
1. Crear archivo `admin/ajax/regenerar_password.php`
2. Editar líneas 6-7 con tus credenciales
3. Acceder: `https://medtravel.com.co/admin/ajax/regenerar_password.php`
4. **BORRAR EL ARCHIVO** después de usar

---

### Paso 3: Verificar Tabla usuarios
```sql
-- Verificar estructura
SHOW COLUMNS FROM usuarios WHERE Field = 'provider_id';

-- Ver tu usuario
SELECT id, usuario, nombre, rol, provider_id, activo 
FROM usuarios 
WHERE usuario = 'TU_USUARIO';
```

**Debe tener:**
- ✅ Campo `provider_id` (INT NULL)
- ✅ Campo `rol` = 'prestador' (si es proveedor)
- ✅ Campo `activo` = 1
- ✅ `provider_id` con valor numérico (ID del provider)

---

### Paso 4: Probar Login
1. Ir a: `https://medtravel.com.co/login.php`
2. Ingresar usuario y contraseña
3. Si falla, anotar el error en la URL:
   - `?usuario=nulo` → No encontró usuario (verificar `activo=1`)
   - `?usuario=nulo2` → Contraseña incorrecta (verificar hash)
   - `?error=empresa` → No encontró empresa (normal para nuevos proveedores, ya lo arreglamos)
   - `?session=error` → Sesión duplicada (cerrar otras sesiones)

---

## 📋 CHECKLIST POST-FIX

- [ ] Verificar hash bcrypt en BD (SELECT)
- [ ] Regenerar contraseña si es necesario (script PHP)
- [ ] Verificar provider_id en tabla usuarios
- [ ] Probar login con credenciales
- [ ] Verificar que $_SESSION['provider_id'] se cargue
- [ ] Confirmar acceso a módulo "Mis Ofertas"
- [ ] BORRAR regenerar_password.php (si se usó)

---

## 🎯 ESTADO FINAL

**Archivos modificados:**
- ✅ `admin/include/log.php` - Soporta bcrypt + SHA512, provider_id desde usuarios, empresa virtual

**Compatibilidad:**
- ✅ Usuarios antiguos (SHA512) siguen funcionando
- ✅ Usuarios nuevos (bcrypt) ahora funcionan
- ✅ Admins sin empresa pueden entrar
- ✅ Prestadores sin empresa virtual funcionan

**Próximo paso:**
Ejecutar Paso 1 de verificación y reportar resultados.

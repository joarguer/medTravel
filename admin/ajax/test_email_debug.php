<?php
// Test de diagnóstico para email_settings.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "1. Sesión iniciada<br>";

if(!isset($_SESSION['id_usuario'])){
    die("ERROR: No hay sesión de usuario");
}

echo "2. Usuario en sesión: " . $_SESSION['id_usuario'] . "<br>";

require_once('../include/conexion.php');
echo "3. Conexión incluida<br>";

if(!$conexion) {
    die("ERROR: No hay conexión a BD");
}

echo "4. Conexión establecida<br>";

// Verificar tabla
$table_check = mysqli_query($conexion, "SHOW TABLES LIKE 'email_settings'");
echo "5. Query de tabla ejecutada<br>";

if(mysqli_num_rows($table_check) == 0) {
    die("ERROR: Tabla no existe");
}

echo "6. Tabla existe<br>";

// Probar query
$query = "SELECT id, account_type, email_address FROM email_settings LIMIT 1";
$result = mysqli_query($conexion, $query);

if(!$result) {
    die("ERROR en query: " . mysqli_error($conexion));
}

echo "7. Query exitosa<br>";

$row = mysqli_fetch_assoc($result);
echo "8. Datos: <pre>" . print_r($row, true) . "</pre>";

// Probar encriptación
define('ENCRYPTION_KEY', 'MedTravel2026SecureKey!@#$%');
echo "9. Constante definida<br>";

// Test de encriptación
function testEncrypt($password) {
    $key = ENCRYPTION_KEY;
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

$test = testEncrypt("test123");
echo "10. Test encriptación: " . substr($test, 0, 50) . "...<br>";

echo "<br><strong style='color:green;'>✅ TODO FUNCIONA CORRECTAMENTE</strong>";
?>

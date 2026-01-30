<?php
// Test completo de paquetes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>TEST DE PAQUETES.PHP</h2>";

echo "<h3>1. Iniciando sesión</h3>";
session_start();
if(!isset($_SESSION['id_usuario'])){
    $_SESSION['id_usuario'] = 1;
    echo "Sesión simulada con usuario ID=1<br>";
}
echo "Sesión OK - Usuario: " . $_SESSION['id_usuario'] . "<br>";

echo "<h3>2. Cargando conexión</h3>";
require_once('../include/conexion.php');
echo "Conexión OK<br>";

echo "<h3>3. Cargando email_config.php</h3>";
require_once('../include/email_config.php');
echo "Email config cargado<br>";

// Verificar que las funciones existen
if(function_exists('sendEmail')) {
    echo "4. Función sendEmail existe<br>";
} else {
    die("ERROR: Función sendEmail no existe");
}

if(function_exists('getMailer')) {
    echo "5. Función getMailer existe<br>";
} else {
    die("ERROR: Función getMailer no existe");
}

// Verificar PHPMailer
if(class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "6. PHPMailer disponible<br>";
} else {
    die("ERROR: PHPMailer no disponible");
}

// Test básico de carga de cuentas
try {
    $accounts = loadEmailAccountsFromDB($conexion);
    echo "7. Cuentas cargadas: " . count($accounts) . "<br>";
    
    if(isset($accounts['patientcare'])) {
        echo "8. Cuenta patientcare encontrada<br>";
    }
} catch(Exception $e) {
    die("ERROR cargando cuentas: " . $e->getMessage());
}

echo "<h3>9. Simulando POST send_quote</h3>";
$_POST['action'] = 'send_quote';
$_POST['package_id'] = '1';
$_POST['email'] = 'test@test.com';
$_POST['subject'] = 'Test';
$_POST['message'] = 'Test';
$_POST['include_details'] = '1';
echo "POST configurado<br>";

echo "<h3>10. Incluyendo paquetes.php</h3>";
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<strong style='color:red;'>PHP ERROR [$errno]:</strong> $errstr en $errfile línea $errline<br>";
});
ob_start();
try {
    @include('paquetes.php');
    $output = ob_get_clean();
    if(empty($output)) {
        echo "<strong style='color:orange;'>Sin output capturado</strong><br>";
    } else {
        echo "Resultado:<br><pre style='background:#f0f0f0;padding:10px;'>" . htmlspecialchars($output) . "</pre>";
    }
} catch(Throwable $e) {
    ob_end_clean();
    echo "<strong style='color:red;'>ERROR FATAL:</strong> " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
restore_error_handler();

echo "<br><strong style='color:green;'>✅ Test completado</strong>";
?>

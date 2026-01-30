<?php
// Test de depuración para paquetes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/test_error.log');

session_start();

// Simular sesión válida si no existe
if(!isset($_SESSION['id_usuario'])) {
    $_SESSION['id_usuario'] = 1; // Usuario de prueba
    echo "⚠️ Sesión simulada\n";
}

echo "=== TEST DE PAQUETES.PHP ===\n\n";

// Test 1: Conexión
echo "1. Probando conexión a BD...\n";
require_once('../include/conexion.php');
if($conexion) {
    echo "   ✓ Conexión exitosa\n\n";
} else {
    die("   ✗ Error de conexión: " . mysqli_connect_error() . "\n");
}

// Test 2: Cargar email_config.php
echo "2. Cargando email_config.php...\n";
try {
    require_once('../include/email_config.php');
    echo "   ✓ email_config.php cargado\n\n";
} catch (Exception $e) {
    die("   ✗ Error cargando email_config.php: " . $e->getMessage() . "\n");
}

// Test 3: Verificar función sendEmail existe
echo "3. Verificando función sendEmail...\n";
if(function_exists('sendEmail')) {
    echo "   ✓ Función sendEmail disponible\n\n";
} else {
    die("   ✗ Función sendEmail NO existe\n");
}

// Test 4: Verificar cuenta 'patientcare'
echo "4. Verificando cuenta 'patientcare'...\n";
$query = "SELECT * FROM email_settings WHERE account_type = 'patientcare' AND is_active = 1";
$result = mysqli_query($conexion, $query);
if($result && mysqli_num_rows($result) > 0) {
    $account = mysqli_fetch_assoc($result);
    echo "   ✓ Cuenta encontrada: " . $account['email_address'] . "\n";
    echo "   Password configurado: " . (empty($account['smtp_password']) ? "NO" : "SI") . "\n\n";
} else {
    echo "   ⚠️ Cuenta 'patientcare' no encontrada o inactiva\n\n";
}

// Test 5: Simular llamada a sendQuoteEmail (sin enviar email)
echo "5. Probando estructura de sendQuoteEmail...\n";
$_POST['action'] = 'send_quote';
$_POST['package_id'] = 0; // ID inexistente para probar la lógica
$_POST['email'] = 'test@test.com';
$_POST['subject'] = 'Test';
$_POST['custom_message'] = '';
$_POST['include_details'] = 1;

try {
    // Incluir el archivo completo
    echo "   Incluyendo paquetes.php...\n";
    ob_start(); // Capturar salida
    include('paquetes.php');
    $output = ob_get_clean();
    
    echo "   Output capturado: " . substr($output, 0, 200) . "\n";
    echo "   ✓ paquetes.php ejecutado\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";

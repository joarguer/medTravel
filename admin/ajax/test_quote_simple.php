<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../include/conexion.php');
require_once('../include/email_config.php');

echo "=== TEST ENVÍO COTIZACIÓN PAQUETE ===\n\n";

// Simular envío de cotización
$package_id = 1;
$email = 'joarguer@gmail.com';

// Obtener datos del paquete
$query = "SELECT p.*, c.nombre, c.apellido FROM travel_packages p 
          INNER JOIN clientes c ON p.client_id = c.id WHERE p.id = ?";
$stmt = mysqli_prepare($conexion, $query);
mysqli_stmt_bind_param($stmt, 'i', $package_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$package = mysqli_fetch_assoc($result);

if(!$package) {
    die("Paquete no encontrado\n");
}

echo "Paquete encontrado: " . $package['package_name'] . "\n";
echo "Cliente: " . $package['nombre'] . " " . $package['apellido'] . "\n";
echo "Email destino: $email\n\n";

// Email simple
$subject = "Cotización de Paquete Turístico Médico - MedTravel";
$body = "<h1>Prueba de Cotización</h1><p>Este es un email de prueba.</p>";

echo "Intentando enviar con cuenta 'patientcare'...\n";

try {
    $sent = sendEmail($email, $subject, $body, 'patientcare', [], $conexion);
    
    echo "\n";
    echo "Resultado sendEmail: " . ($sent ? "TRUE" : "FALSE") . "\n";
    
    if($sent) {
        echo "✓ Email enviado exitosamente\n";
    } else {
        echo "✗ sendEmail retornó false\n";
    }
    
} catch(Exception $e) {
    echo "✗ EXCEPCIÓN: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIN TEST ===\n";
?>

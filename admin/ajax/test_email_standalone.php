<?php
// test_email_standalone.php - Prueba de email SIN validación de sesión
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PRUEBA INDEPENDIENTE DE EMAIL ===\n\n";

// Conectar a BD
require_once('../include/conexion.php');
require_once('../include/email_config.php');

// Verificar cuenta patientcare
echo "1. Cargando configuración de email...\n";
$accounts = loadEmailAccountsFromDB($conexion);

if(!isset($accounts['patientcare'])) {
    die("ERROR: No existe cuenta 'patientcare'\n");
}

$account = $accounts['patientcare'];
echo "   ✓ Cuenta patientcare encontrada\n";
echo "   Email: {$account['from_email']}\n";
echo "   Host: {$account['smtp_host']}:{$account['smtp_port']}\n";
echo "   Secure: {$account['smtp_secure']}\n";
echo "   Password: " . (empty($account['password']) ? 'VACÍA' : 'OK') . "\n\n";

// Preparar email simple
$to = 'joarguer@gmail.com';
$subject = 'TEST MedTravel - ' . date('H:i:s');
$body = '<html><body>';
$body .= '<h2 style="color: #0f766e;">Test MedTravel</h2>';
$body .= '<p>Este es un email de prueba enviado el ' . date('d/m/Y H:i:s') . '</p>';
$body .= '<p>Si recibe este mensaje, el sistema de email está funcionando correctamente.</p>';
$body .= '</body></html>';

echo "2. Intentando enviar a: $to\n\n";

try {
    $result = sendEmail($to, $subject, $body, 'patientcare', array(), $conexion);
    
    if($result === true) {
        echo "✓✓✓ EMAIL ENVIADO EXITOSAMENTE ✓✓✓\n";
    } else {
        echo "✗✗✗ ERROR AL ENVIAR ✗✗✗\n";
        print_r($result);
    }
    
} catch(Exception $e) {
    echo "EXCEPCIÓN: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n3. Revisar logs del sistema para más detalles\n";
?>

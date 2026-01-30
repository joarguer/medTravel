<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/smtp_debug.log');

echo "=== TEST DETALLADO SMTP ===\n\n";

require_once('../include/conexion.php');
require_once('../include/email_config.php');

// Test 1: Cargar configuración
echo "1. Cargando configuración de 'patientcare'...\n";
$accounts = loadEmailAccountsFromDB($conexion);

if(isset($accounts['patientcare'])) {
    $acc = $accounts['patientcare'];
    echo "   Email: " . $acc['from_email'] . "\n";
    echo "   Host: " . $acc['smtp_host'] . "\n";
    echo "   Puerto: " . $acc['smtp_port'] . "\n";
    echo "   Seguridad: " . $acc['smtp_secure'] . "\n";
    echo "   Usuario: " . $acc['username'] . "\n";
    echo "   Password: " . (empty($acc['password']) ? "VACIO" : "[" . strlen($acc['password']) . " caracteres]") . "\n\n";
} else {
    die("   ✗ Cuenta no encontrada\n");
}

// Test 2: Intentar crear mailer
echo "2. Creando instancia de PHPMailer...\n";
try {
    $mailer = getMailer('patientcare', $conexion);
    echo "   ✓ PHPMailer creado\n\n";
} catch(Exception $e) {
    die("   ✗ Error: " . $e->getMessage() . "\n");
}

// Test 3: Intentar enviar email
echo "3. Intentando enviar email de prueba...\n";
echo "   Destinatario: joarguer@gmail.com\n";
echo "   Asunto: Test SMTP MedTravel\n\n";

try {
    $result = sendEmail(
        'joarguer@gmail.com',
        'Test SMTP MedTravel',
        '<h1>Test</h1><p>Este es un email de prueba desde MedTravel.</p>',
        'patientcare',
        array('alt_body' => 'Test de email'),
        $conexion
    );
    
    echo "\n";
    echo "Resultado: " . ($result === true ? "TRUE" : json_encode($result)) . "\n";
    
    if($result === true) {
        echo "✓ Email enviado\n";
    } else {
        echo "✗ Email NO enviado\n";
        if(is_array($result)) {
            echo "Detalles del error:\n";
            print_r($result);
        }
    }
    
} catch(Exception $e) {
    echo "\n✗ EXCEPCIÓN CAPTURADA:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Revise el archivo logs/smtp_debug.log para ver los logs SMTP detallados ===\n";
echo "\n=== FIN TEST ===\n";
?>

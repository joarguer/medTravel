<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Configuración SMTP - MedTravel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .test-container { max-width: 800px; margin: 0 auto; }
        .status-box { padding: 15px; border-radius: 8px; margin: 10px 0; }
        .status-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .status-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .status-warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; }
        .status-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 class="mb-4">🔧 Test de Configuración SMTP</h1>
        
        <?php
        session_start();
        if(!isset($_SESSION['id_usuario'])){
            echo '<div class="alert alert-danger">⛔ Debes iniciar sesión como administrador</div>';
            exit;
        }
        
        require_once('include/email_config.php');
        require_once('include/email_credentials.php');
        
        // Test 1: Verificar que PHPMailer está disponible
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 1:</strong> PHPMailer Disponible</div>';
        echo '<div class="card-body">';
        if(class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo '<div class="status-box status-success">✅ PHPMailer está disponible</div>';
        } else {
            echo '<div class="status-box status-error">❌ PHPMailer NO está disponible</div>';
        }
        echo '</div></div>';
        
        // Test 2: Verificar configuración de credenciales
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 2:</strong> Credenciales Configuradas</div>';
        echo '<div class="card-body">';
        
        $accounts = ['patientcare', 'info', 'noreply', 'providers'];
        $credentials_ok = true;
        
        foreach($accounts as $account) {
            $constant = 'SMTP_' . strtoupper($account) . '_PASS';
            if(defined($constant) && !empty(constant($constant))) {
                echo '<div class="status-box status-success">✅ ' . $account . '@medtravel.com.co - Configurada</div>';
            } else {
                echo '<div class="status-box status-warning">⚠️ ' . $account . '@medtravel.com.co - Sin contraseña</div>';
                $credentials_ok = false;
            }
        }
        echo '</div></div>';
        
        // Test 3: Configuración SMTP
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>Test 3:</strong> Configuración SMTP</div>';
        echo '<div class="card-body">';
        echo '<div class="status-box status-info">';
        echo '📧 <strong>Servidor:</strong> ' . SMTP_HOST . '<br>';
        echo '🔌 <strong>Puerto:</strong> ' . SMTP_PORT . '<br>';
        echo '🔒 <strong>Seguridad:</strong> ' . SMTP_SECURE . '<br>';
        echo '</div>';
        echo '</div></div>';
        
        // Test 4: Intentar conexión SMTP (solo si hay credenciales)
        if($credentials_ok) {
            echo '<div class="card mb-3">';
            echo '<div class="card-header"><strong>Test 4:</strong> Conexión SMTP</div>';
            echo '<div class="card-body">';
            
            foreach($accounts as $account) {
                echo '<h6>' . ucfirst($account) . '</h6>';
                try {
                    $mail = getMailer($account);
                    // Intentar conectar
                    $mail->SMTPDebug = 0;
                    if($mail->smtpConnect()) {
                        echo '<div class="status-box status-success">✅ Conexión exitosa</div>';
                        $mail->smtpClose();
                    } else {
                        echo '<div class="status-box status-error">❌ No se pudo conectar</div>';
                    }
                } catch(Exception $e) {
                    echo '<div class="status-box status-error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            echo '</div></div>';
        }
        
        // Formulario de prueba de envío
        if($credentials_ok) {
            echo '<div class="card mb-3">';
            echo '<div class="card-header"><strong>Test 5:</strong> Enviar Email de Prueba</div>';
            echo '<div class="card-body">';
            
            if(isset($_POST['test_send'])) {
                $test_email = trim($_POST['test_email']);
                $test_account = $_POST['test_account'];
                
                if(!empty($test_email)) {
                    try {
                        $subject = "Test de Email SMTP - MedTravel";
                        $body = '
                        <h2>✅ Test Exitoso</h2>
                        <p>Este es un email de prueba del sistema SMTP de MedTravel.</p>
                        <p><strong>Cuenta usada:</strong> ' . $test_account . '@medtravel.com.co</p>
                        <p><strong>Servidor:</strong> ' . SMTP_HOST . ':' . SMTP_PORT . '</p>
                        <p><strong>Fecha:</strong> ' . date('Y-m-d H:i:s') . '</p>
                        ';
                        
                        $result = sendEmail($test_email, $subject, $body, $test_account);
                        
                        if($result) {
                            echo '<div class="status-box status-success">✅ Email enviado exitosamente a ' . htmlspecialchars($test_email) . '</div>';
                        } else {
                            echo '<div class="status-box status-error">❌ Error al enviar el email</div>';
                        }
                    } catch(Exception $e) {
                        echo '<div class="status-box status-error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            }
            
            echo '
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email de destino:</label>
                    <input type="email" name="test_email" class="form-control" required placeholder="tu@email.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cuenta de envío:</label>
                    <select name="test_account" class="form-select">
                        <option value="patientcare">patientcare@medtravel.com.co</option>
                        <option value="info">info@medtravel.com.co</option>
                        <option value="noreply">noreply@medtravel.com.co</option>
                        <option value="providers">providers@medtravel.com.co</option>
                    </select>
                </div>
                <button type="submit" name="test_send" class="btn btn-primary">📤 Enviar Email de Prueba</button>
            </form>';
            
            echo '</div></div>';
        }
        
        // Instrucciones de configuración
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><strong>📋 Instrucciones de Configuración</strong></div>';
        echo '<div class="card-body">';
        echo '<ol>';
        echo '<li>Editar el archivo: <code>admin/include/email_credentials.php</code></li>';
        echo '<li>Completar las contraseñas de las cuentas de email</li>';
        echo '<li>Asignar permisos restrictivos: <code>chmod 600 email_credentials.php</code></li>';
        echo '<li>Agregar a .gitignore si usas Git</li>';
        echo '<li>Refrescar esta página para verificar</li>';
        echo '</ol>';
        echo '<div class="alert alert-warning mt-3">';
        echo '<strong>⚠️ Configuración cPanel:</strong><br>';
        echo 'Si usas cPanel, las credenciales son:<br>';
        echo '• Servidor: mail.medtravel.com.co<br>';
        echo '• Puerto: 465 (SSL) o 587 (TLS)<br>';
        echo '• Usuario: La dirección de email completa<br>';
        echo '• Contraseña: La contraseña de la cuenta de email';
        echo '</div>';
        echo '</div></div>';
        ?>
        
        <div class="text-center mt-4">
            <a href="paquetes.php" class="btn btn-secondary">← Volver a Paquetes</a>
            <button onclick="location.reload()" class="btn btn-primary">🔄 Refrescar Tests</button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// admin/ajax/email_settings.php - API para gestión de configuración de email

// Activar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar en pantalla, solo en logs
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
session_start();

// Importar PHPMailer al inicio
require_once('../include/mailer/PHPMailer.php');
require_once('../include/mailer/SMTP.php');
require_once('../include/mailer/Exception.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Validación de sesión
if(!isset($_SESSION['id_usuario'])){
    error_log("email_settings.php: Sesión no válida");
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

require_once('../include/conexion.php');

if(!$conexion) {
    error_log("email_settings.php: Error de conexión a BD");
    echo json_encode(['ok' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

// Verificar que la tabla existe
$table_check = mysqli_query($conexion, "SHOW TABLES LIKE 'email_settings'");
if($table_check === false) {
    error_log("email_settings.php: Error verificando tabla: " . mysqli_error($conexion));
    echo json_encode(['ok' => false, 'message' => 'Error verificando tabla: ' . mysqli_error($conexion)]);
    exit;
}

if(mysqli_num_rows($table_check) == 0) {
    error_log("email_settings.php: Tabla email_settings no existe");
    echo json_encode([
        'ok' => false, 
        'message' => 'La tabla email_settings no existe. Por favor ejecuta el archivo SQL: sql/email_settings_table.sql',
        'error' => 'TABLE_NOT_EXISTS'
    ]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Clave de encriptación (debería estar en un archivo de configuración seguro)
define('ENCRYPTION_KEY', 'MedTravel2026SecureKey!@#$%'); // Cambiar en producción

try {
    switch($action) {
        case 'list':
            listAccounts($conexion);
            break;
        
        case 'get':
            getAccount($conexion);
            break;
        
        case 'update':
            updateAccount($conexion);
            break;
        
        case 'test_connection':
            testConnection($conexion);
            break;
        
        case 'send_test_email':
            sendTestEmail($conexion);
            break;
        
        case 'test_all':
            testAllAccounts($conexion);
            break;
        
        default:
            echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    error_log("Error en email_settings.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// ===================================================================
// FUNCIONES
// ===================================================================

/**
 * Listar todas las cuentas de email
 */
function listAccounts($conexion) {
    $query = "SELECT 
        id, account_type, email_address, display_name, smtp_host, smtp_port, 
        smtp_secure, smtp_username, reply_to, is_active, description,
        last_test_date, last_test_status, created_at, updated_at
    FROM email_settings 
    ORDER BY 
        FIELD(account_type, 'patientcare', 'info', 'noreply', 'providers')";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        echo json_encode(['ok' => false, 'message' => 'Error al consultar: ' . mysqli_error($conexion)]);
        return;
    }
    
    $accounts = array();
    while($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $accounts]);
}

/**
 * Obtener una cuenta específica
 */
function getAccount($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    $query = "SELECT * FROM email_settings WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($account = mysqli_fetch_assoc($result)) {
        // No enviar la contraseña desencriptada
        $account['smtp_password'] = ''; // Dejar vacío por seguridad
        echo json_encode(['ok' => true, 'data' => $account]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Cuenta no encontrada']);
    }
}

/**
 * Actualizar cuenta de email
 */
function updateAccount($conexion) {
    $id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
    $email_address = isset($_POST['email_address']) ? trim($_POST['email_address']) : '';
    $display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
    $smtp_username = isset($_POST['smtp_username']) ? trim($_POST['smtp_username']) : '';
    $smtp_password = isset($_POST['smtp_password']) ? trim($_POST['smtp_password']) : '';
    $reply_to = isset($_POST['reply_to']) ? trim($_POST['reply_to']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if($id === 0 || empty($email_address) || empty($display_name) || empty($smtp_username)) {
        echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
        return;
    }
    
    // Si se proporcionó nueva contraseña, encriptarla
    if(!empty($smtp_password)) {
        $encrypted_password = encryptPassword($smtp_password);
        $query = "UPDATE email_settings SET 
            email_address = ?,
            display_name = ?,
            smtp_username = ?,
            smtp_password = ?,
            reply_to = ?,
            description = ?,
            is_active = ?
        WHERE id = ?";
        
        $stmt = mysqli_prepare($conexion, $query);
        mysqli_stmt_bind_param($stmt, 'ssssssii', 
            $email_address, $display_name, $smtp_username, $encrypted_password, 
            $reply_to, $description, $is_active, $id);
    } else {
        // No actualizar contraseña si no se proporcionó
        $query = "UPDATE email_settings SET 
            email_address = ?,
            display_name = ?,
            smtp_username = ?,
            reply_to = ?,
            description = ?,
            is_active = ?
        WHERE id = ?";
        
        $stmt = mysqli_prepare($conexion, $query);
        mysqli_stmt_bind_param($stmt, 'sssssii', 
            $email_address, $display_name, $smtp_username, 
            $reply_to, $description, $is_active, $id);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        echo json_encode(['ok' => true, 'message' => 'Cuenta actualizada exitosamente']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conexion)]);
    }
}

/**
 * Probar conexión SMTP de una cuenta
 */
function testConnection($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if($id === 0) {
        echo json_encode(['ok' => false, 'message' => 'ID no válido']);
        return;
    }
    
    // Obtener datos de la cuenta
    $account = getAccountData($conexion, $id);
    if(!$account) {
        echo json_encode(['ok' => false, 'message' => 'Cuenta no encontrada']);
        return;
    }
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $account['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $account['smtp_username'];
        $mail->Password = decryptPassword($account['smtp_password']);
        $mail->SMTPSecure = $account['smtp_secure'];
        $mail->Port = $account['smtp_port'];
        $mail->Timeout = 10;
        $mail->SMTPDebug = 0;
        
        // Intentar conectar
        $connected = $mail->smtpConnect();
        
        if($connected) {
            $mail->smtpClose();
            
            // Actualizar registro de prueba exitosa
            updateTestStatus($conexion, $id, 'success');
            
            echo json_encode([
                'ok' => true, 
                'message' => '✅ Conexión exitosa con ' . $account['email_address']
            ]);
        } else {
            updateTestStatus($conexion, $id, 'failed');
            echo json_encode([
                'ok' => false, 
                'message' => '❌ No se pudo conectar con el servidor SMTP'
            ]);
        }
        
    } catch(Exception $e) {
        updateTestStatus($conexion, $id, 'failed');
        echo json_encode([
            'ok' => false, 
            'message' => '❌ Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Enviar email de prueba
 */
function sendTestEmail($conexion) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $test_email = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
    
    if($id === 0 || empty($test_email)) {
        echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
        return;
    }
    
    $account = getAccountData($conexion, $id);
    if(!$account) {
        echo json_encode(['ok' => false, 'message' => 'Cuenta no encontrada']);
        return;
    }
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $account['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $account['smtp_username'];
        $mail->Password = decryptPassword($account['smtp_password']);
        $mail->SMTPSecure = $account['smtp_secure'];
        $mail->Port = $account['smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($account['email_address'], $account['display_name']);
        $mail->addAddress($test_email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Test Email - MedTravel SMTP Configuration';
        $mail->Body = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5;">
                <div style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                    <h1 style="margin: 0;">✅ Test Exitoso</h1>
                </div>
                <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                    <h2>Configuración SMTP Funcionando Correctamente</h2>
                    <p>Este es un email de prueba enviado desde el panel de administración de MedTravel.</p>
                    
                    <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;">
                        <p style="margin: 5px 0;"><strong>Cuenta:</strong> ' . $account['email_address'] . '</p>
                        <p style="margin: 5px 0;"><strong>Nombre:</strong> ' . $account['display_name'] . '</p>
                        <p style="margin: 5px 0;"><strong>Servidor:</strong> ' . $account['smtp_host'] . ':' . $account['smtp_port'] . '</p>
                        <p style="margin: 5px 0;"><strong>Fecha:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    </div>
                    
                    <p>Si recibiste este email, significa que el sistema de envío de emails está configurado correctamente.</p>
                    
                    <p style="color: #999; font-size: 12px; margin-top: 30px;">
                        Este es un email automático generado por el sistema de pruebas de MedTravel.
                    </p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = 'Test exitoso - Configuración SMTP de MedTravel funcionando correctamente.';
        
        if($mail->send()) {
            updateTestStatus($conexion, $id, 'success');
            echo json_encode([
                'ok' => true, 
                'message' => '✅ Email de prueba enviado exitosamente a ' . $test_email
            ]);
        } else {
            echo json_encode([
                'ok' => false, 
                'message' => '❌ Error al enviar el email'
            ]);
        }
        
    } catch(Exception $e) {
        updateTestStatus($conexion, $id, 'failed');
        echo json_encode([
            'ok' => false, 
            'message' => '❌ Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Probar todas las cuentas
 */
function testAllAccounts($conexion) {
    $query = "SELECT id FROM email_settings WHERE is_active = 1";
    $result = mysqli_query($conexion, $query);
    
    $success = 0;
    $failed = 0;
    
    while($row = mysqli_fetch_assoc($result)) {
        $account = getAccountData($conexion, $row['id']);
        
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $account['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $account['smtp_username'];
            $mail->Password = decryptPassword($account['smtp_password']);
            $mail->SMTPSecure = $account['smtp_secure'];
            $mail->Port = $account['smtp_port'];
            $mail->Timeout = 10;
            $mail->SMTPDebug = 0;
            
            if($mail->smtpConnect()) {
                $mail->smtpClose();
                updateTestStatus($conexion, $row['id'], 'success');
                $success++;
            } else {
                updateTestStatus($conexion, $row['id'], 'failed');
                $failed++;
            }
        } catch(Exception $e) {
            updateTestStatus($conexion, $row['id'], 'failed');
            $failed++;
        }
    }
    
    echo json_encode([
        'ok' => true,
        'data' => [
            'success' => $success,
            'failed' => $failed
        ]
    ]);
}

// ===================================================================
// FUNCIONES AUXILIARES
// ===================================================================

/**
 * Obtener datos de cuenta por ID
 */
function getAccountData($conexion, $id) {
    $query = "SELECT * FROM email_settings WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Actualizar estado de prueba
 */
function updateTestStatus($conexion, $id, $status) {
    $query = "UPDATE email_settings SET 
        last_test_date = NOW(),
        last_test_status = ?
    WHERE id = ?";
    
    $stmt = mysqli_prepare($conexion, $query);
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
}

/**
 * Encriptar contraseña
 */
function encryptPassword($password) {
    $key = ENCRYPTION_KEY;
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Desencriptar contraseña
 */
function decryptPassword($encrypted) {
    $key = ENCRYPTION_KEY;
    list($encrypted_data, $iv) = explode('::', base64_decode($encrypted), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
}
?>

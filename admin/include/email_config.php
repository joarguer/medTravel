<?php
/**
 * Configuración de Email SMTP para MedTravel
 * Última actualización: 29 enero 2026
 * 
 * IMPORTANTE: Las credenciales ahora se administran desde el panel admin
 * en admin/email_settings.php y se almacenan encriptadas en la base de datos.
 */

// Incluir PHPMailer
require_once(__DIR__ . '/mailer/PHPMailer.php');
require_once(__DIR__ . '/mailer/SMTP.php');
require_once(__DIR__ . '/mailer/Exception.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Clave de encriptación (debe coincidir con ajax/email_settings.php)
if(!defined('EMAIL_ENCRYPTION_KEY')) {
    define('EMAIL_ENCRYPTION_KEY', 'MedTravel2026SecureKey!@#$%');
}

/**
 * Desencriptar contraseña de email
 */
function decryptEmailPassword($encrypted) {
    if(empty($encrypted)) return '';
    
    try {
        $key = EMAIL_ENCRYPTION_KEY;
        list($encrypted_data, $iv) = explode('::', base64_decode($encrypted), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    } catch(Exception $e) {
        error_log("Error desencriptando contraseña: " . $e->getMessage());
        return '';
    }
}

/**
 * Cargar configuración de email desde la base de datos
 * 
 * @param object $conexion Conexión a la base de datos
 * @return array Configuraciones de todas las cuentas activas
 */
function loadEmailAccountsFromDB($conexion = null) {
    if($conexion === null) {
        require_once(__DIR__ . '/conexion.php');
    }
    
    $query = "SELECT * FROM email_settings WHERE is_active = 1";
    $result = mysqli_query($conexion, $query);
    
    $accounts = array();
    while($row = mysqli_fetch_assoc($result)) {
        $accounts[$row['account_type']] = array(
            'username' => $row['smtp_username'],
            'password' => decryptEmailPassword($row['smtp_password']),
            'from_email' => $row['email_address'],
            'from_name' => $row['display_name'],
            'reply_to' => $row['reply_to'] ?: $row['email_address'],
            'smtp_host' => $row['smtp_host'],
            'smtp_port' => $row['smtp_port'],
            'smtp_secure' => $row['smtp_secure'],
            'description' => $row['description']
        );
    }
    
    // Si no hay cuentas en BD, intentar cargar desde archivo (fallback)
    if(empty($accounts) && file_exists(__DIR__ . '/email_credentials.php')) {
        require_once(__DIR__ . '/email_credentials.php');
        $accounts = array(
            'patientcare' => array(
                'username' => defined('SMTP_PATIENTCARE_USER') ? SMTP_PATIENTCARE_USER : '',
                'password' => defined('SMTP_PATIENTCARE_PASS') ? SMTP_PATIENTCARE_PASS : '',
                'from_email' => defined('SMTP_PATIENTCARE_USER') ? SMTP_PATIENTCARE_USER : '',
                'from_name' => 'MedTravel Patient Care',
                'reply_to' => defined('SMTP_PATIENTCARE_USER') ? SMTP_PATIENTCARE_USER : '',
                'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : 'mail.medtravel.com.co',
                'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 465,
                'smtp_secure' => defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl',
                'description' => 'Para cotizaciones y comunicación con pacientes'
            )
        );
    }
    
    return $accounts;
}

// Cargar cuentas de email (se cachea en variable global)
$EMAIL_ACCOUNTS = null;

/**
 * Crear instancia configurada de PHPMailer
 * 
 * @param string $account_type Tipo de cuenta: 'patientcare', 'info', 'noreply', 'providers'
 * @param object $conexion Conexión a BD (opcional)
 * @return PHPMailer
 */
function getMailer($account_type = 'patientcare', $conexion = null) {
    global $EMAIL_ACCOUNTS;
    
    // Cargar cuentas si no están en caché
    if($EMAIL_ACCOUNTS === null) {
        $EMAIL_ACCOUNTS = loadEmailAccountsFromDB($conexion);
    }
    
    if (!isset($EMAIL_ACCOUNTS[$account_type])) {
        throw new Exception("Cuenta de email '$account_type' no configurada");
    }
    
    $account = $EMAIL_ACCOUNTS[$account_type];
    
    // Validar que tenga contraseña
    if(empty($account['password'])) {
        throw new Exception("Contraseña no configurada para '$account_type'. Configure en admin/email_settings.php");
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = isset($account['smtp_host']) ? $account['smtp_host'] : 'mail.medtravel.com.co';
        $mail->SMTPAuth = true;
        $mail->Username = $account['username'];
        $mail->Password = $account['password'];
        $mail->SMTPSecure = isset($account['smtp_secure']) ? $account['smtp_secure'] : 'ssl';
        $mail->Port = isset($account['smtp_port']) ? $account['smtp_port'] : 465;
        
        // Charset
        $mail->CharSet = 'UTF-8';
        
        // Remitente
        $mail->setFrom($account['from_email'], $account['from_name']);
        $mail->addReplyTo($account['reply_to'], $account['from_name']);
        
        // Configuración adicional
        $mail->isHTML(true);
        $mail->Timeout = 30;
        
        // Debug SMTP - capturar salida
        $mail->SMTPDebug = 2; // 0=off, 1=client, 2=client+server
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: " . trim($str));
        };
        
        return $mail;
        
    } catch (Exception $e) {
        error_log("Error configurando mailer: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Enviar email simple
 * 
 * @param string $to Email destino
 * @param string $subject Asunto
 * @param string $body Cuerpo del mensaje (HTML)
 * @param string $account_type Tipo de cuenta a usar
 * @param array $options Opciones adicionales (cc, bcc, attachments, etc.)
 * @param object $conexion Conexión a BD (opcional)
 * @return bool|array Retorna true si se envió, o array con detalles del error
 */
function sendEmail($to, $subject, $body, $account_type = 'patientcare', $options = array(), $conexion = null) {
    try {
        $mail = getMailer($account_type, $conexion);
        
        // Destinatario principal
        $mail->addAddress($to);
        
        // CC
        if (isset($options['cc']) && is_array($options['cc'])) {
            foreach ($options['cc'] as $cc_email) {
                $mail->addCC($cc_email);
            }
        }
        
        // BCC
        if (isset($options['bcc']) && is_array($options['bcc'])) {
            foreach ($options['bcc'] as $bcc_email) {
                $mail->addBCC($bcc_email);
            }
        }
        
        // Adjuntos
        if (isset($options['attachments']) && is_array($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                $mail->addAttachment($attachment);
            }
        }
        
        // Asunto y cuerpo
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Versión texto plano (opcional)
        if (isset($options['alt_body'])) {
            $mail->AltBody = $options['alt_body'];
        } else {
            $mail->AltBody = strip_tags($body);
        }
        
        // Enviar
        $result = $mail->send();
        
        // Capturar información de envío
        $smtp_log = array(
            'result' => $result,
            'error_info' => $mail->ErrorInfo,
            'last_message_id' => $mail->getLastMessageID()
        );
        
        // Log exitoso
        if ($result) {
            error_log("✓ Email enviado a: $to | Asunto: $subject | Cuenta: $account_type | MessageID: " . $mail->getLastMessageID());
            return true;
        } else {
            $error_msg = "✗ Email NO enviado a: $to | Error: " . $mail->ErrorInfo;
            error_log($error_msg);
            return array('success' => false, 'error' => $mail->ErrorInfo, 'smtp_error' => true, 'smtp_log' => $smtp_log);
        }
        
    } catch (Exception $e) {
        $error_details = array(
            'success' => false,
            'error' => $e->getMessage(),
            'error_info' => isset($mail) ? $mail->ErrorInfo : 'Mailer no inicializado',
            'account_type' => $account_type,
            'to' => $to
        );
        error_log("EXCEPCION enviando email a $to: " . json_encode($error_details));
        throw $e; // Re-lanzar para que paquetes.php lo capture
    }
}

/**
 * Enviar email a múltiples destinatarios
 * 
 * @param array $recipients Array de emails
 * @param string $subject Asunto
 * @param string $body Cuerpo del mensaje
 * @param string $account_type Tipo de cuenta
 * @return array ['success' => count, 'failed' => count, 'errors' => array]
 */
function sendBulkEmail($recipients, $subject, $body, $account_type = 'patientcare') {
    $results = array(
        'success' => 0,
        'failed' => 0,
        'errors' => array()
    );
    
    foreach ($recipients as $email) {
        if (sendEmail($email, $subject, $body, $account_type)) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = $email;
        }
    }
    
    return $results;
}

/**
 * Validar configuración de email
 * 
 * @param string $account_type
 * @param object $conexion Conexión a BD (opcional)
 * @return array ['valid' => bool, 'message' => string]
 */
function validateEmailConfig($account_type = 'patientcare', $conexion = null) {
    global $EMAIL_ACCOUNTS;
    
    // Cargar cuentas si no están en caché
    if($EMAIL_ACCOUNTS === null) {
        $EMAIL_ACCOUNTS = loadEmailAccountsFromDB($conexion);
    }
    
    if (!isset($EMAIL_ACCOUNTS[$account_type])) {
        return array('valid' => false, 'message' => 'Cuenta no configurada');
    }
    
    $account = $EMAIL_ACCOUNTS[$account_type];
    
    if (empty($account['password'])) {
        return array('valid' => false, 'message' => 'Password no configurado. Configure en admin/email_settings.php');
    }
    
    try {
        $mail = getMailer($account_type, $conexion);
        // Intentar conectar (sin enviar)
        $mail->smtpConnect();
        $mail->smtpClose();
        return array('valid' => true, 'message' => 'Configuración válida');
    } catch (Exception $e) {
        return array('valid' => false, 'message' => $e->getMessage());
    }
}
?>

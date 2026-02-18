<?php
require_once __DIR__ . '/admin/include/conexion.php';
require_once __DIR__ . '/admin/include/password_utils.php';
require_once __DIR__ . '/admin/include/email_config.php';
require_once __DIR__ . '/inc/email_template.php';

function usuarios_column_exists_reset($conexion, $column)
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE '{$columnEsc}'");
    $cache[$column] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$column];
}

function bind_params_local($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [];
    $bind[] = &$types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function token_is_expired($expiresAt)
{
    if (!$expiresAt) {
        return true;
    }
    $ts = strtotime((string)$expiresAt);
    if ($ts === false) {
        return true;
    }
    return ($ts < time());
}

function generate_secure_reset_token($bytes = 32)
{
    $bytes = max(16, (int)$bytes);
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $raw = openssl_random_pseudo_bytes($bytes);
        if ($raw !== false) {
            return bin2hex($raw);
        }
    }
    return bin2hex(hash('sha256', uniqid((string)mt_rand(), true), true));
}

function get_user_by_reset_token($conexion, $rawToken)
{
    if (!usuarios_column_exists_reset($conexion, 'password_reset_token') || !usuarios_column_exists_reset($conexion, 'password_reset_expires_at')) {
        return null;
    }

    $sql = "SELECT * FROM usuarios WHERE password_reset_token = ?";
    if (usuarios_column_exists_reset($conexion, 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $rawToken);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function find_user_for_resend($conexion, $email)
{
    $email = trim((string)$email);
    if ($email === '') {
        return null;
    }

    $conditions = [];
    $types = '';
    $params = [];

    if (usuarios_column_exists_reset($conexion, 'email')) {
        $conditions[] = 'email = ?';
        $types .= 's';
        $params[] = $email;
    }
    if (usuarios_column_exists_reset($conexion, 'usuario')) {
        $conditions[] = 'usuario = ?';
        $types .= 's';
        $params[] = $email;
    }

    if (empty($conditions)) {
        return null;
    }

    $sql = "SELECT * FROM usuarios WHERE (" . implode(' OR ', $conditions) . ")";
    if (usuarios_column_exists_reset($conexion, 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " ORDER BY id ASC LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    bind_params_local($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function build_resend_email_body($token)
{
    $setPasswordUrl = 'https://medtravel.com.co/set_password.php?token=' . urlencode($token);
    $contentHtml = ''
        . '<p style="margin:0 0 14px 0;">Use the secure access link below to create your password and enter your MedTravel patient portal.</p>'
        . '<p style="margin:0 0 14px 0;">Your patient portal lets you track each requested service status, receive updates, and coordinate your virtual evaluation appointment.</p>'
        . '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 14px 0;">'
        . '<tr><td style="padding:4px 0;">1. Click the button below.</td></tr>'
        . '<tr><td style="padding:4px 0;">2. Create your password.</td></tr>'
        . '<tr><td style="padding:4px 0;">3. Log in and track your request progress.</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 14px 0;">If you prefer, you can also copy this secure link:<br><a href="' . htmlspecialchars($setPasswordUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0b4ea2; text-decoration:none;">' . htmlspecialchars($setPasswordUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="margin:0;">Need help? Reply to this email and our coordination team will assist you.</p>';

    return renderMedTravelEmail(
        'Secure access link',
        'Set your password and track your request.',
        $contentHtml,
        'This is an automated message.',
        [
            'text' => 'Create your password',
            'url' => $setPasswordUrl,
        ]
    );
}

function send_resend_access_email($conexion, $toEmail, $token)
{
    $subject = 'MedTravel – Your secure access link';
    $body = build_resend_email_body($token);

    try {
        $result = sendEmail($toEmail, $subject, $body, 'patientcare', [], $conexion);
        return $result === true;
    } catch (Exception $e) {
        error_log('set_password resend email exception: ' . $e->getMessage());
        return false;
    }
}

function resend_secure_access_link($conexion, $email)
{
    $email = trim((string)$email);
    if ($email === '') {
        return;
    }

    $hasResetToken = usuarios_column_exists_reset($conexion, 'password_reset_token');
    $hasResetExpires = usuarios_column_exists_reset($conexion, 'password_reset_expires_at');
    $hasResetSentAt = usuarios_column_exists_reset($conexion, 'password_reset_sent_at');

    if (!$hasResetToken || !$hasResetExpires) {
        return;
    }

    $user = find_user_for_resend($conexion, $email);
    if (!$user || empty($user['id'])) {
        return;
    }

    $token = generate_secure_reset_token(32);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);
    if ($hasResetSentAt) {
        // Throttle at DB level to avoid race/timezone drift on concurrent requests.
        $updateSql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ?, password_reset_sent_at = NOW()
            WHERE id = ?
              AND (password_reset_sent_at IS NULL OR password_reset_sent_at <= DATE_SUB(NOW(), INTERVAL 3 MINUTE))
            LIMIT 1';
    } else {
        $updateSql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1';
    }

    $stmt = mysqli_prepare($conexion, $updateSql);
    if (!$stmt) {
        return;
    }

    $userId = (int)$user['id'];
    mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiresAt, $userId);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return;
    }
    if ($hasResetSentAt && $affected < 1) {
        // Within throttle window: return generic success without sending.
        return;
    }

    send_resend_access_email($conexion, $email, $token);
}

$token = isset($_REQUEST['token']) ? trim((string)$_REQUEST['token']) : '';
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$emailInput = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

$statusMessage = '';
$statusType = '';
$showPasswordForm = false;
$showResendForm = false;
$tokenState = 'invalid';
$userByToken = null;

$resetColumnsAvailable = usuarios_column_exists_reset($conexion, 'password_reset_token') && usuarios_column_exists_reset($conexion, 'password_reset_expires_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'resend_link') {
    if ($resetColumnsAvailable && filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        resend_secure_access_link($conexion, $emailInput);
    }
    $statusType = 'success';
    $statusMessage = 'If the email exists, we sent a new secure link.';
    $showResendForm = true;
    $tokenState = 'invalid';
} else {
    if (!$resetColumnsAvailable) {
        $statusType = 'danger';
        $statusMessage = 'Secure access is temporarily unavailable. Please contact support.';
        $showResendForm = false;
    } else {
        if ($token === '' || strlen($token) < 20) {
            $tokenState = 'invalid';
            $showResendForm = true;
        } else {
            $userByToken = get_user_by_reset_token($conexion, $token);
            if (!$userByToken) {
                $tokenState = 'invalid';
                $showResendForm = true;
            } elseif (token_is_expired($userByToken['password_reset_expires_at'] ?? null)) {
                $tokenState = 'expired';
                $showResendForm = true;
            } else {
                $tokenState = 'valid';
                $showPasswordForm = true;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_password') {
    if (!$resetColumnsAvailable) {
        $statusType = 'danger';
        $statusMessage = 'Secure access is temporarily unavailable. Please contact support.';
        $showPasswordForm = false;
        $showResendForm = false;
    } else {
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $passwordConfirm = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';

        if ($token === '' || strlen($token) < 20) {
            $tokenState = 'invalid';
            $showPasswordForm = false;
            $showResendForm = true;
        } else {
            $userByToken = get_user_by_reset_token($conexion, $token);
            if (!$userByToken) {
                $tokenState = 'invalid';
                $showPasswordForm = false;
                $showResendForm = true;
            } elseif (token_is_expired($userByToken['password_reset_expires_at'] ?? null)) {
                $tokenState = 'expired';
                $showPasswordForm = false;
                $showResendForm = true;
            } elseif ($password === '' || strlen($password) < 8) {
                $statusType = 'danger';
                $statusMessage = 'Password must be at least 8 characters.';
                $showPasswordForm = true;
            } elseif (!hash_equals($password, $passwordConfirm)) {
                $statusType = 'danger';
                $statusMessage = 'Passwords do not match.';
                $showPasswordForm = true;
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $fields = ['password = ?', 'password_reset_token = NULL', 'password_reset_expires_at = NULL'];
                $types = 's';
                $params = [$passwordHash];
                if (usuarios_column_exists_reset($conexion, 'password_reset_sent_at')) {
                    $fields[] = 'password_reset_sent_at = NULL';
                }

                if (usuarios_column_exists_reset($conexion, 'token')) {
                    $legacyToken = ensure_password_token($userByToken);
                    $fields[] = 'token = ?';
                    $types .= 's';
                    $params[] = $legacyToken;
                }
                if (usuarios_column_exists_reset($conexion, 'cambio_password')) {
                    $fields[] = 'cambio_password = 0';
                }
                if (usuarios_column_exists_reset($conexion, 'activo')) {
                    $fields[] = 'activo = 1';
                }

                $sql = 'UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = ? LIMIT 1';
                $stmt = mysqli_prepare($conexion, $sql);

                if (!$stmt) {
                    $statusType = 'danger';
                    $statusMessage = 'Could not update password. Try again later.';
                    $showPasswordForm = true;
                } else {
                    $userId = (int)$userByToken['id'];
                    $types .= 'i';
                    $params[] = $userId;
                    bind_params_local($stmt, $types, $params);

                    if (!mysqli_stmt_execute($stmt)) {
                        $statusType = 'danger';
                        $statusMessage = 'Could not update password. Try again later.';
                        $showPasswordForm = true;
                        mysqli_stmt_close($stmt);
                    } else {
                        mysqli_stmt_close($stmt);
                        header('Location: login.php?password_set=1');
                        exit;
                    }
                }
            }
        }
    }
}

$tokenInfoTitle = 'Link expired or invalid';
$tokenInfoText = 'This secure link is no longer valid. You can request a new one below.';
if ($tokenState === 'expired') {
    $tokenInfoText = 'For security reasons, access links expire after 24 hours. You can request a new one below.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Secure access link - MedTravel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h4 mb-3">Secure access link</h1>

                        <?php if ($statusMessage !== ''): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($statusType ?: 'info', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($showPasswordForm): ?>
                            <p class="text-muted">Create your password to access your MedTravel patient portal.</p>
                            <form method="post" action="set_password.php">
                                <input type="hidden" name="action" value="set_password">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New password</label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                </div>
                                <div class="mb-3">
                                    <label for="password_confirm" class="form-label">Confirm password</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Save password</button>
                            </form>
                        <?php else: ?>
                            <?php if ($showResendForm): ?>
                                <h2 class="h5 mb-2"><?php echo htmlspecialchars($tokenInfoTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($tokenInfoText, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-muted mb-3">Your patient portal lets you track each requested service status, receive updates, and coordinate your virtual evaluation appointment.</p>

                                <form method="post" action="set_password.php" class="mb-3">
                                    <input type="hidden" name="action" value="resend_link">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($emailInput, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Send me a new access link</button>
                                </form>
                                <p class="small text-muted mb-0">Need help? Reply to this email and our coordination team will assist you.</p>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline-primary w-100">Go to login</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

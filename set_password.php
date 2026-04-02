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

function usuarios_password_max_length($conexion)
{
    static $maxLen = null;
    if ($maxLen !== null) {
        return $maxLen;
    }
    $maxLen = 0;
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'password'");
    if (!$res) {
        return $maxLen;
    }
    $row = mysqli_fetch_assoc($res);
    $type = isset($row['Type']) ? (string)$row['Type'] : '';
    if (preg_match('/\((\d+)\)/', $type, $m)) {
        $maxLen = (int)$m[1];
    }
    return $maxLen;
}

function bind_params_local($stmt, $types, &$params)
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [];
    $bind[] = $stmt;
    $bind[] = &$types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function stmt_fetch_assoc_local($stmt)
{
    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) {
        return null;
    }

    $row = [];
    $bind = [$stmt];
    while ($field = mysqli_fetch_field($meta)) {
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }

    call_user_func_array('mysqli_stmt_bind_result', $bind);
    if (!mysqli_stmt_fetch($stmt)) {
        return null;
    }

    $out = [];
    foreach ($row as $k => $v) {
        $out[$k] = $v;
    }
    return $out;
}

function redirect_to_login_after_password_set()
{
    $target = 'login.php?password_set=1';
    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body>';
    echo '<script>window.location.href=' . json_encode($target) . ';</script>';
    echo '</body></html>';
    exit;
}

function build_password_storage_payload($plainPassword, $userByToken)
{
    $plainPassword = (string)$plainPassword;
    $userByToken = is_array($userByToken) ? $userByToken : [];

    // Prefer project canonical legacy hash/token flow when available.
    if (function_exists('hash_password_for_storage')) {
        $payload = hash_password_for_storage($plainPassword, $userByToken);
        return [
            'password' => (string)($payload['password'] ?? ''),
            'token' => (string)($payload['token'] ?? ensure_password_token($userByToken)),
        ];
    }

    // Fallback: bcrypt where available.
    if (function_exists('password_hash')) {
        return [
            'password' => (string)password_hash($plainPassword, PASSWORD_DEFAULT),
            'token' => (string)ensure_password_token($userByToken),
        ];
    }

    // Last-resort compatibility fallback.
    $token = (string)ensure_password_token($userByToken);
    if (function_exists('hash_password')) {
        return [
            'password' => (string)hash_password($plainPassword, $token),
            'token' => $token,
        ];
    }

    return [
        'password' => sha1($token . $plainPassword),
        'token' => $token,
    ];
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
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Throwable $e) {
            error_log('set_password random_bytes failed: ' . $e->getMessage());
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        try {
            $raw = openssl_random_pseudo_bytes($bytes);
            if ($raw !== false) {
                return bin2hex($raw);
            }
        } catch (Throwable $e) {
            error_log('set_password openssl_random_pseudo_bytes failed: ' . $e->getMessage());
        }
    }
    return hash('sha256', uniqid((string)mt_rand(), true) . microtime(true));
}

function get_user_by_reset_token($conexion, $rawToken)
{
    $hasResetToken = usuarios_column_exists_reset($conexion, 'password_reset_token');
    $hasResetExpiry = usuarios_column_exists_reset($conexion, 'password_reset_expires_at');
    $hasLegacyToken = usuarios_column_exists_reset($conexion, 'token');

    if (!$hasResetToken && !$hasLegacyToken) {
        return null;
    }

    if ($hasResetToken && $hasResetExpiry) {
        $sql = "SELECT * FROM usuarios WHERE password_reset_token = ?";
    } else {
        // Legacy fallback for environments that still use usuarios.token.
        $sql = "SELECT * FROM usuarios WHERE token = ?";
    }
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
    $row = stmt_fetch_assoc_local($stmt);
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

    $row = stmt_fetch_assoc_local($stmt);
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
    } catch (Throwable $e) {
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
    $hasLegacyToken = usuarios_column_exists_reset($conexion, 'token');

    $canUseSecureReset = ($hasResetToken && $hasResetExpires);
    $canUseLegacyReset = (!$canUseSecureReset && $hasLegacyToken);
    if (!$canUseSecureReset && !$canUseLegacyReset) {
        return;
    }

    $user = find_user_for_resend($conexion, $email);
    if (!$user || empty($user['id'])) {
        return;
    }

    $token = generate_secure_reset_token(32);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);
    if ($canUseSecureReset && $hasResetSentAt) {
        // Throttle at DB level to avoid race/timezone drift on concurrent requests.
        $updateSql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ?, password_reset_sent_at = NOW()
            WHERE id = ?
              AND (password_reset_sent_at IS NULL OR password_reset_sent_at <= DATE_SUB(NOW(), INTERVAL 3 MINUTE))
            LIMIT 1';
    } elseif ($canUseSecureReset) {
        $updateSql = 'UPDATE usuarios SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1';
    } elseif ($hasResetSentAt) {
        // Legacy fallback keeps token flow working without new reset columns.
        $updateSql = 'UPDATE usuarios SET token = ?, password_reset_sent_at = NOW()
            WHERE id = ?
              AND (password_reset_sent_at IS NULL OR password_reset_sent_at <= DATE_SUB(NOW(), INTERVAL 3 MINUTE))
            LIMIT 1';
    } else {
        $updateSql = 'UPDATE usuarios SET token = ? WHERE id = ? LIMIT 1';
    }

    $stmt = mysqli_prepare($conexion, $updateSql);
    if (!$stmt) {
        return;
    }

    $userId = (int)$user['id'];
    if ($canUseSecureReset) {
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiresAt, $userId);
    } else {
        mysqli_stmt_bind_param($stmt, 'si', $token, $userId);
    }
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
$tokenState = 'missing';
$userByToken = null;

$hasResetTokenCol = usuarios_column_exists_reset($conexion, 'password_reset_token');
$hasResetExpiryCol = usuarios_column_exists_reset($conexion, 'password_reset_expires_at');
$hasLegacyTokenCol = usuarios_column_exists_reset($conexion, 'token');
$canUseSecureReset = ($hasResetTokenCol && $hasResetExpiryCol);
$canUseLegacyReset = (!$canUseSecureReset && $hasLegacyTokenCol);
$resetColumnsAvailable = ($canUseSecureReset || $canUseLegacyReset);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'resend_link') {
    if ($resetColumnsAvailable && filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        try {
            resend_secure_access_link($conexion, $emailInput);
        } catch (Throwable $e) {
            error_log('set_password resend_link fatal-safe catch: ' . $e->getMessage());
        }
    }
    $statusType = 'success';
    $statusMessage = 'If the email exists, we sent a new secure link.';
    $showResendForm = true;
    $tokenState = 'missing';
} else {
    if (!$resetColumnsAvailable) {
        $statusType = 'danger';
        $statusMessage = 'Secure access is temporarily unavailable. Please contact support.';
        $showResendForm = false;
    } else {
        if ($token === '' || strlen($token) < 20) {
            $tokenState = 'missing';
            $showResendForm = true;
        } else {
            $userByToken = get_user_by_reset_token($conexion, $token);
            if (!$userByToken) {
                $tokenState = 'invalid';
                $showResendForm = true;
            } elseif ($canUseSecureReset && token_is_expired($userByToken['password_reset_expires_at'] ?? null)) {
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
            $tokenState = 'missing';
            $showPasswordForm = false;
            $showResendForm = true;
        } else {
            $userByToken = get_user_by_reset_token($conexion, $token);
            if (!$userByToken) {
                $tokenState = 'invalid';
                $showPasswordForm = false;
                $showResendForm = true;
            } elseif ($canUseSecureReset && token_is_expired($userByToken['password_reset_expires_at'] ?? null)) {
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
                try {
                    $storagePayload = build_password_storage_payload($password, $userByToken);
                    $passwordHash = (string)($storagePayload['password'] ?? '');
                    $tokenForUser = (string)($storagePayload['token'] ?? '');
                    if ($passwordHash === '') {
                        throw new Exception('empty_password_hash');
                    }
                    $passwordMaxLen = usuarios_password_max_length($conexion);
                    if ($passwordMaxLen > 0 && strlen($passwordHash) > $passwordMaxLen) {
                        $bcryptHash = function_exists('password_hash')
                            ? (string)password_hash($password, PASSWORD_DEFAULT)
                            : '';
                        if ($bcryptHash === '' || strlen($bcryptHash) > $passwordMaxLen) {
                            throw new Exception('password_hash_exceeds_column_length');
                        }
                        $passwordHash = $bcryptHash;
                        if ($tokenForUser === '') {
                            $tokenForUser = ensure_password_token($userByToken);
                        }
                        error_log('set_password: fallback to bcrypt due password column length limit=' . $passwordMaxLen);
                    }

                    $fields = ['password = ?'];
                    if (usuarios_column_exists_reset($conexion, 'password_reset_token')) {
                        $fields[] = 'password_reset_token = NULL';
                    }
                    if (usuarios_column_exists_reset($conexion, 'password_reset_expires_at')) {
                        $fields[] = 'password_reset_expires_at = NULL';
                    }
                    if (usuarios_column_exists_reset($conexion, 'password_reset_sent_at')) {
                        $fields[] = 'password_reset_sent_at = NULL';
                    }

                    if (usuarios_column_exists_reset($conexion, 'token')) {
                        $fields[] = 'token = ?';
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
                        if (usuarios_column_exists_reset($conexion, 'token')) {
                            $tokenToStore = $tokenForUser !== '' ? $tokenForUser : ensure_password_token($userByToken);
                            mysqli_stmt_bind_param($stmt, 'ssi', $passwordHash, $tokenToStore, $userId);
                        } else {
                            mysqli_stmt_bind_param($stmt, 'si', $passwordHash, $userId);
                        }

                        if (!mysqli_stmt_execute($stmt)) {
                            $statusType = 'danger';
                            $statusMessage = 'Could not update password. Try again later.';
                            $showPasswordForm = true;
                            error_log('set_password update execute failed: ' . mysqli_stmt_error($stmt));
                            mysqli_stmt_close($stmt);
                        } else {
                            mysqli_stmt_close($stmt);
                            redirect_to_login_after_password_set();
                        }
                    }
                } catch (Throwable $e) {
                    error_log('set_password set_password action fatal-safe catch: ' . $e->getMessage());
                    $statusType = 'danger';
                    $statusMessage = 'Could not update password. Try again later.';
                    $showPasswordForm = true;
                }
            }
        }
    }
}

$tokenInfoTitle = 'Request a secure access link';
$tokenInfoText = 'Enter your email below and we will send a secure link so you can create your password.';
if ($tokenState === 'invalid') {
    $tokenInfoTitle = 'Link expired or invalid';
    $tokenInfoText = 'This secure link is no longer valid. You can request a new one below.';
}
if ($tokenState === 'expired') {
    $tokenInfoTitle = 'Link expired';
    $tokenInfoText = 'For security reasons, access links expire after 24 hours. You can request a new one below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>MedTravel | Secure access link</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <meta content="" name="description" />
    <meta content="" name="author" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
    <link href="assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/global/css/components-md.min.css" rel="stylesheet" id="style_components" type="text/css" />
    <link href="assets/global/css/plugins-md.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/pages/css/lock.min.css" rel="stylesheet" type="text/css" />
    <style>
        .auth-terms-notice {
            margin: 0 0 15px 0;
            padding: 12px 14px;
            border: 1px solid #cfe2ff;
            border-radius: 4px;
            background: #f4f8ff;
            color: #355070;
            font-size: 13px;
            line-height: 1.55;
        }
        .auth-terms-notice strong {
            display: block;
            margin-bottom: 4px;
            color: #13357b;
            font-weight: 700;
        }
    </style>
    <link rel="shortcut icon" href="favicon.ico" />
</head>
<body>
    <div class="page-lock">
        <div class="page-logo">
            <a class="brand" href="index.php">
                <img src="img/site/logo_800_182.png" alt="MedTravel" style="max-width: 220px; height: auto;" />
            </a>
        </div>
        <div class="page-body">
            <div class="lock-head"> Secure access link </div>
            <div class="lock-body">
                <div class="pull-left lock-avatar-block">
                    <img src="assets/pages/media/profile/photo3.jpg" class="lock-avatar" alt="profile">
                </div>

                <div class="lock-form pull-left" style="width: calc(100% - 130px);">
                    <?php if ($statusMessage !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($statusType ?: 'info', ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 15px;">
                            <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($showPasswordForm): ?>
                        <div class="auth-terms-notice">
                            <strong>First-time access notice</strong>
                            After you create your password and sign in for the first time, you will be asked to review and accept the MedTravel Terms of Service to complete activation of your patient portal.
                        </div>
                        <h4 style="margin:0 0 12px 0;">Create your password</h4>
                        <form method="post" action="set_password.php">
                            <input type="hidden" name="action" value="set_password">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <input class="form-control placeholder-no-fix" type="password" id="password" name="password" required minlength="8" autocomplete="off" placeholder="New password" />
                            </div>
                            <div class="form-group">
                                <input class="form-control placeholder-no-fix" type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="off" placeholder="Confirm password" />
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn red uppercase">Save password</button>
                            </div>
                        </form>
                    <?php elseif ($showResendForm): ?>
                        <div class="auth-terms-notice">
                            <strong>First-time access notice</strong>
                            Once you create your password and sign in for the first time, MedTravel will ask you to review and accept the Terms of Service before your patient portal is fully activated.
                        </div>
                        <h4 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($tokenInfoTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p style="margin:0 0 10px 0; color:#9ca8b4;"><?php echo htmlspecialchars($tokenInfoText, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p style="margin:0 0 12px 0; color:#9ca8b4;">Your patient portal lets you track each requested service status, receive updates, and coordinate your virtual evaluation appointment.</p>
                        <form method="post" action="set_password.php">
                            <input type="hidden" name="action" value="resend_link">
                            <div class="form-group">
                                <input class="form-control placeholder-no-fix" type="email" id="email" name="email" required autocomplete="off" placeholder="Email" value="<?php echo htmlspecialchars($emailInput, ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn red uppercase">Send me a new access link</button>
                            </div>
                        </form>
                        <p style="margin-top:10px; color:#9ca8b4; font-size:12px;">Need help? Reply to this email and our coordination team will assist you.</p>
                    <?php else: ?>
                        <div class="form-actions">
                            <a href="login.php" class="btn red uppercase">Go to login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lock-bottom">
                <a href="login.php">Back to login</a>
            </div>
        </div>
        <div class="page-footer-custom"> <?php echo date('Y'); ?> &copy; MedTravel </div>
    </div>

    <script src="assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <script src="assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
    <script src="assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
    <script src="assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
    <script src="assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
    <script src="assets/global/scripts/app.min.js" type="text/javascript"></script>
    <script src="assets/pages/scripts/lock.min.js" type="text/javascript"></script>
</body>
</html>

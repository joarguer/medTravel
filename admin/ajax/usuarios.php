<?php
require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/roles.php';
require_once __DIR__ . '/../include/password_utils.php';
require_once __DIR__ . '/../include/email_config.php';

header('Content-Type: application/json; charset=utf-8');

require_login_ajax();

if (!user_can('users.view') && !can_manage_users()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'forbidden']);
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

function json_ok($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function json_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function can_manage_users() {
    return is_role_admin_session() || user_can(PERM_USERS_MANAGE) || user_can('users.manage') || user_can('users.edit') || user_can('users.create');
}

function table_exists($conexion, $table) {
    $table = mysqli_real_escape_string($conexion, $table);
    $q = mysqli_query($conexion, "SHOW TABLES LIKE '{$table}'");
    return $q && mysqli_num_rows($q) > 0;
}

function table_has_column($conexion, $table, $column) {
    $table = mysqli_real_escape_string($conexion, $table);
    $column = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    return $q && mysqli_num_rows($q) > 0;
}

function usuarios_has_column($conexion, $column) {
    return table_has_column($conexion, 'usuarios', $column);
}

function fetch_active_service_provider($conexion, $serviceProviderId) {
    if (!table_exists($conexion, 'service_providers')) return null;
    $stmt = mysqli_prepare($conexion, "SELECT id, provider_name FROM service_providers WHERE id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $serviceProviderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_active_medical_provider($conexion, $providerId) {
    if (!table_exists($conexion, 'providers')) return null;

    $hasKind = table_has_column($conexion, 'providers', 'kind');
    $hasActive = table_has_column($conexion, 'providers', 'is_active');

    $sql = "SELECT id, name FROM providers WHERE id = ?";
    if ($hasActive) {
        $sql .= " AND is_active = 1";
    }
    if ($hasKind) {
        $sql .= " AND (kind IS NULL OR kind <> 'partner')";
    }
    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = ($res && ($tmp = mysqli_fetch_assoc($res))) ? $tmp : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_roles($conexion) {
    $roles = [];

    if (table_exists($conexion, 'roles')) {
        $hasSlug = table_has_column($conexion, 'roles', 'slug');
        $sql = $hasSlug ? "SELECT id, name, slug FROM roles ORDER BY id ASC" : "SELECT id, name, '' AS slug FROM roles ORDER BY id ASC";
        $res = mysqli_query($conexion, $sql);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $roles[intval($r['id'])] = [
                    'id' => intval($r['id']),
                    'name' => $r['name'],
                    'slug' => isset($r['slug']) ? $r['slug'] : ''
                ];
            }
        }
    }

    if (empty($roles)) {
        foreach (get_available_roles() as $id => $name) {
            $roles[intval($id)] = [
                'id' => intval($id),
                'name' => $name,
                'slug' => ''
            ];
        }
    }

    return $roles;
}

function get_user_role_id($row) {
    if (isset($row['role_id']) && $row['role_id'] !== null && $row['role_id'] !== '') {
        return intval($row['role_id']);
    }
    return normalize_role_value($row['rol'] ?? null);
}

function is_medical_user_role($roleId) {
    return in_array(intval($roleId), [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true);
}

function is_complementary_user_role($roleId) {
    return intval($roleId) === ROLE_COMPLEMENTARY_ADMIN;
}

function is_global_admin_user_role($roleId) {
    return in_array(intval($roleId), [ROLE_ADMIN, ROLE_ADMINISTRATIVE], true);
}

function generate_temp_password($length = 14) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $charsLen = strlen($chars);
    $length = max(12, min(16, intval($length)));
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[mt_rand(0, $charsLen - 1)];
    }
    return $out;
}

switch ($action) {
    case 'list_roles':
        $roles = fetch_roles($conexion);
        json_ok(['data' => array_values($roles)]);
        break;

    case 'list_providers':
        $rows = [];
        if (table_exists($conexion, 'providers')) {
            $hasKind = table_has_column($conexion, 'providers', 'kind');
            $hasActive = table_has_column($conexion, 'providers', 'is_active');

            $sql = "SELECT id, name FROM providers WHERE 1=1";
            if ($hasActive) {
                $sql .= " AND is_active = 1";
            }
            if ($hasKind) {
                $sql .= " AND (kind IS NULL OR kind <> 'partner')";
            }
            $sql .= " ORDER BY name ASC";

            $res = mysqli_query($conexion, $sql);
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $rows[] = [
                        'id' => intval($r['id']),
                        'name' => $r['name']
                    ];
                }
            }
        }
        json_ok(['data' => $rows]);
        break;

    case 'list_service_providers':
        $rows = [];
        if (table_exists($conexion, 'service_providers')) {
            $stmt = mysqli_prepare($conexion, "SELECT id, provider_name FROM service_providers WHERE is_active = 1 ORDER BY provider_name ASC");
            if ($stmt) {
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    while ($r = mysqli_fetch_assoc($res)) {
                        $rows[] = [
                            'id' => intval($r['id']),
                            'provider_name' => $r['provider_name']
                        ];
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
        json_ok(['data' => $rows]);
        break;

    case 'list':
        $rows = [];
        $roles = fetch_roles($conexion);
        $hasServiceProviderId = usuarios_has_column($conexion, 'service_provider_id');

        if ($hasServiceProviderId) {
            $sql = "SELECT u.id, u.usuario, u.nombre, u.email, u.role_id, u.rol, u.provider_id, u.service_provider_id, u.empresa, u.activo,
                           p.name AS provider_name, p.kind AS provider_kind, sp.provider_name AS service_provider_name
                    FROM usuarios u
                    LEFT JOIN providers p ON p.id = u.provider_id
                    LEFT JOIN service_providers sp ON sp.id = u.service_provider_id
                    ORDER BY u.id DESC";
        } else {
            $sql = "SELECT u.id, u.usuario, u.nombre, u.email, u.role_id, u.rol, u.provider_id, u.empresa, u.activo,
                           p.name AS provider_name, p.kind AS provider_kind
                    FROM usuarios u
                    LEFT JOIN providers p ON p.id = u.provider_id
                    ORDER BY u.id DESC";
        }

        $res = mysqli_query($conexion, $sql);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $roleId = get_user_role_id($r);
                $providerName = $r['provider_name'] ?? '';
                $providerKind = $r['provider_kind'] ?? '';

                if ($hasServiceProviderId && !empty($r['service_provider_name'])) {
                    $providerName = $r['service_provider_name'];
                    $providerKind = 'partner';
                }

                $rows[] = [
                    'id' => intval($r['id']),
                    'usuario' => $r['usuario'],
                    'nombre' => $r['nombre'],
                    'email' => $r['email'],
                    'role_id' => $roleId,
                    'role_name' => ($roleId !== null && isset($roles[$roleId])) ? $roles[$roleId]['name'] : ($r['rol'] ?: ''),
                    'provider' => $providerName,
                    'provider_kind' => $providerKind,
                    'provider_id' => isset($r['provider_id']) ? intval($r['provider_id']) : null,
                    'service_provider_id' => ($hasServiceProviderId && isset($r['service_provider_id']) && $r['service_provider_id'] !== null) ? intval($r['service_provider_id']) : null,
                    'empresa' => $r['empresa'],
                    'activo' => intval($r['activo'])
                ];
            }
        }

        json_ok(['data' => $rows]);
        break;

    case 'get_user':
        if (!can_manage_users()) json_err('forbidden', 403);

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) json_err('invalid_input', 422);

        $hasServiceProviderId = usuarios_has_column($conexion, 'service_provider_id');
        if ($hasServiceProviderId) {
            $sql = "SELECT id, usuario, nombre, email, role_id, rol, provider_id, service_provider_id, activo FROM usuarios WHERE id = ? LIMIT 1";
        } else {
            $sql = "SELECT id, usuario, nombre, email, role_id, rol, provider_id, activo FROM usuarios WHERE id = ? LIMIT 1";
        }

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare_error', 500);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row) json_err('user_not_found', 404);

        $row['id'] = intval($row['id']);
        $row['role_id'] = get_user_role_id($row);
        $row['provider_id'] = isset($row['provider_id']) && $row['provider_id'] !== null ? intval($row['provider_id']) : null;
        if ($hasServiceProviderId) {
            $row['service_provider_id'] = isset($row['service_provider_id']) && $row['service_provider_id'] !== null ? intval($row['service_provider_id']) : null;
        } else {
            $row['service_provider_id'] = null;
        }
        $row['activo'] = intval($row['activo']);

        json_ok(['data' => $row]);
        break;

    case 'reset_password':
        $canResetPassword = is_role_admin_session() || user_can(PERM_USERS_MANAGE) || user_can('users.manage');
        if (!$canResetPassword) {
            json_err('forbidden', 403);
        }

        $userId = intval($_POST['user_id'] ?? $_REQUEST['user_id'] ?? 0);
        if ($userId <= 0) json_err('invalid_user_id', 422);

        $stmtUser = mysqli_prepare($conexion, "SELECT id, usuario, nombre, email, token, password FROM usuarios WHERE id = ? LIMIT 1");
        if (!$stmtUser) json_err('db_prepare_error', 500);
        mysqli_stmt_bind_param($stmtUser, 'i', $userId);
        if (!mysqli_stmt_execute($stmtUser)) json_err('db_error', 500);
        $resUser = mysqli_stmt_get_result($stmtUser);
        $user = $resUser ? mysqli_fetch_assoc($resUser) : null;
        mysqli_stmt_close($stmtUser);

        if (!$user) json_err('user_not_found', 404);

        $tempPassword = generate_temp_password(14);
        $hasTokenColumn = usuarios_has_column($conexion, 'token');

        if ($hasTokenColumn) {
            $newToken = ensure_password_token($user);
            $newHash = hash_password($tempPassword, $newToken);
        } else {
            // Fallback sin token: bcrypt para mantener compatibilidad de login.
            $newHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $newToken = '';
        }

        $hasCambioPassword = usuarios_has_column($conexion, 'cambio_password');
        if ($hasTokenColumn && $hasCambioPassword) {
            $stmtUpdate = mysqli_prepare($conexion, "UPDATE usuarios SET password = ?, token = ?, cambio_password = 1 WHERE id = ? LIMIT 1");
            if (!$stmtUpdate) json_err('db_prepare_error', 500);
            mysqli_stmt_bind_param($stmtUpdate, 'ssi', $newHash, $newToken, $userId);
        } elseif ($hasTokenColumn) {
            $stmtUpdate = mysqli_prepare($conexion, "UPDATE usuarios SET password = ?, token = ? WHERE id = ? LIMIT 1");
            if (!$stmtUpdate) json_err('db_prepare_error', 500);
            mysqli_stmt_bind_param($stmtUpdate, 'ssi', $newHash, $newToken, $userId);
        } elseif ($hasCambioPassword) {
            $stmtUpdate = mysqli_prepare($conexion, "UPDATE usuarios SET password = ?, cambio_password = 1 WHERE id = ? LIMIT 1");
            if (!$stmtUpdate) json_err('db_prepare_error', 500);
            mysqli_stmt_bind_param($stmtUpdate, 'si', $newHash, $userId);
        } else {
            $stmtUpdate = mysqli_prepare($conexion, "UPDATE usuarios SET password = ? WHERE id = ? LIMIT 1");
            if (!$stmtUpdate) json_err('db_prepare_error', 500);
            mysqli_stmt_bind_param($stmtUpdate, 'si', $newHash, $userId);
        }

        if (!mysqli_stmt_execute($stmtUpdate)) {
            mysqli_stmt_close($stmtUpdate);
            json_err('db_error', 500);
        }
        mysqli_stmt_close($stmtUpdate);

        $mailFailed = false;
        $mailError = '';
        $to = trim((string)($user['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $mailFailed = true;
            $mailError = 'invalid_to_email';
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'medtravel.com.co';
            $adminUrl = $scheme . '://' . $host . '/admin/';
            $safeName = htmlspecialchars(trim((string)($user['nombre'] ?? 'usuario')), ENT_QUOTES, 'UTF-8');
            $safeUser = htmlspecialchars(trim((string)($user['usuario'] ?? $to)), ENT_QUOTES, 'UTF-8');
            $safePass = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');

            $subject = 'Restablecimiento de contraseña - MedTravel';
            $body = '<html><body style="font-family: Arial, Helvetica, sans-serif; color:#333;">'
                . '<h2 style="color:#2980d9;">Restablecimiento de contraseña</h2>'
                . '<p>Hola ' . $safeName . ',</p>'
                . '<p>Se generó una contraseña temporal para tu cuenta.</p>'
                . '<ul>'
                . '<li><strong>Usuario:</strong> ' . $safeUser . '</li>'
                . '<li><strong>Contraseña temporal:</strong> ' . $safePass . '</li>'
                . '</ul>'
                . '<p>Ingresa aquí: <a href="' . $safeUrl . '">' . $safeUrl . '</a></p>'
                . '</body></html>';
            $altBody = "Restablecimiento de contraseña MedTravel\n"
                . "Usuario: " . ($user['usuario'] ?? $to) . "\n"
                . "Contraseña temporal: " . $tempPassword . "\n"
                . "Ingreso: " . $adminUrl;

            try {
                $sent = sendEmail($to, $subject, $body, 'patientcare', array('alt_body' => $altBody), $conexion);
                if ($sent !== true) {
                    $mailFailed = true;
                    if (is_array($sent) && isset($sent['error'])) {
                        $mailError = (string)$sent['error'];
                    } else {
                        $mailError = 'email_send_failed';
                    }
                }
            } catch (Exception $e) {
                $mailFailed = true;
                $mailError = $e->getMessage();
            }
        }

        if ($mailFailed) {
            $payload = [
                'ok' => true,
                'mail_failed' => true,
                'error' => $mailError !== '' ? $mailError : 'email_send_failed',
            ];
            if (is_role_admin_session()) {
                $payload['temp_password'] = $tempPassword;
            }
            json_ok($payload);
        }

        json_ok([
            'ok' => true,
            'mail_failed' => false,
        ]);
        break;

    case 'update_user':
        if (!can_manage_users()) json_err('forbidden', 403);

        $id = intval($_POST['id'] ?? 0);
        $email = trim((string)($_POST['email'] ?? ''));
        $usuario = trim((string)($_POST['usuario'] ?? ''));
        $roleId = intval($_POST['role_id'] ?? 0);
        $activo = intval($_POST['activo'] ?? 0) ? 1 : 0;
        $providerId = isset($_POST['provider_id']) && $_POST['provider_id'] !== '' ? intval($_POST['provider_id']) : null;
        $serviceProviderId = isset($_POST['service_provider_id']) && $_POST['service_provider_id'] !== '' ? intval($_POST['service_provider_id']) : null;

        if ($id <= 0) json_err('invalid_user_id', 422);
        if ($email === '' || strpos($email, ',') !== false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_err('invalid_email', 422);
        }
        if ($usuario === '') {
            json_err('invalid_username', 422);
        }
        if ($roleId <= 0) {
            json_err('role_required', 422);
        }

        $roles = fetch_roles($conexion);
        if (!isset($roles[$roleId])) {
            json_err('role_not_found', 422);
        }

        $hasRoleId = usuarios_has_column($conexion, 'role_id');
        $hasServiceProviderId = usuarios_has_column($conexion, 'service_provider_id');

        if (!$hasRoleId) {
            json_err('role_id_column_missing', 500);
        }

        // Unicidad email/usuario
        $stmtUnique = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE (email = ? OR usuario = ?) AND id <> ? LIMIT 1");
        if (!$stmtUnique) json_err('db_prepare_error', 500);
        mysqli_stmt_bind_param($stmtUnique, 'ssi', $email, $usuario, $id);
        if (!mysqli_stmt_execute($stmtUnique)) json_err('db_error', 500);
        $resUnique = mysqli_stmt_get_result($stmtUnique);
        if ($resUnique && mysqli_num_rows($resUnique) > 0) {
            mysqli_stmt_close($stmtUnique);
            json_err('email_or_username_already_exists', 422);
        }
        mysqli_stmt_close($stmtUnique);

        $isComplementaryRole = is_complementary_user_role($roleId);
        $isMedicalProviderRole = is_medical_user_role($roleId);
        $isAdminRole = is_global_admin_user_role($roleId);

        if ($isMedicalProviderRole) {
            if ($providerId === null || $providerId <= 0) {
                json_err('provider_required', 422);
            }
            $provider = fetch_active_medical_provider($conexion, $providerId);
            if (!$provider) {
                json_err('invalid_or_inactive_medical_provider', 422);
            }
            $serviceProviderId = null;
        } elseif ($isComplementaryRole) {
            if (!$hasServiceProviderId) {
                json_err('service_provider_column_missing', 422);
            }
            if ($serviceProviderId === null || $serviceProviderId <= 0) {
                json_err('service_provider_required', 422);
            }
            $serviceProvider = fetch_active_service_provider($conexion, $serviceProviderId);
            if (!$serviceProvider) {
                json_err('invalid_or_inactive_complementary_provider', 422);
            }
            $providerId = null;
        } elseif ($isAdminRole) {
            $providerId = null;
            $serviceProviderId = null;
        } else {
            $providerId = null;
            $serviceProviderId = null;
        }

        $rolText = (string)$roleId;
        $providerSql = ($providerId === null) ? 'NULL' : intval($providerId);
        $serviceProviderSql = ($serviceProviderId === null) ? 'NULL' : intval($serviceProviderId);

        if ($hasServiceProviderId) {
            $sql = "UPDATE usuarios
                    SET email = ?, usuario = ?, role_id = ?, rol = ?, activo = ?, provider_id = {$providerSql}, service_provider_id = {$serviceProviderSql}
                    WHERE id = ? LIMIT 1";
        } else {
            $sql = "UPDATE usuarios
                    SET email = ?, usuario = ?, role_id = ?, rol = ?, activo = ?, provider_id = {$providerSql}
                    WHERE id = ? LIMIT 1";
        }

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare_error', 500);
        mysqli_stmt_bind_param($stmt, 'ssisii', $email, $usuario, $roleId, $rolText, $activo, $id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            json_err('db_error', 500);
        }
        mysqli_stmt_close($stmt);

        json_ok(['message' => 'updated']);
        break;

    // Compatibilidad con flujo inline antiguo
    case 'update_role':
        if (!can_manage_users()) json_err('forbidden', 403);
        $id = intval($_POST['id'] ?? 0);
        $roleId = intval($_POST['role_id'] ?? 0);
        if ($id <= 0 || $roleId <= 0) json_err('invalid_input', 422);

        $roles = fetch_roles($conexion);
        if (!isset($roles[$roleId])) json_err('role_not_found', 422);

        $hasServiceProviderId = usuarios_has_column($conexion, 'service_provider_id');
        $serviceProviderId = isset($_POST['service_provider_id']) && $_POST['service_provider_id'] !== '' ? intval($_POST['service_provider_id']) : null;

        if (is_complementary_user_role($roleId)) {
            if (!$hasServiceProviderId) json_err('service_provider_column_missing', 422);
            if ($serviceProviderId === null || $serviceProviderId <= 0) json_err('service_provider_required', 422);
            if (!fetch_active_service_provider($conexion, $serviceProviderId)) json_err('invalid_or_inactive_complementary_provider', 422);
            $sql = "UPDATE usuarios SET role_id = ?, rol = ?, provider_id = NULL, service_provider_id = ? WHERE id = ? LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) json_err('db_prepare_error', 500);
            $rolText = (string)$roleId;
            mysqli_stmt_bind_param($stmt, 'isii', $roleId, $rolText, $serviceProviderId, $id);
            if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
            mysqli_stmt_close($stmt);
            json_ok();
        }

        if (is_medical_user_role($roleId)) {
            $providerId = isset($_POST['provider_id']) && $_POST['provider_id'] !== '' ? intval($_POST['provider_id']) : null;
            if ($providerId === null || $providerId <= 0) {
                $stmtGet = mysqli_prepare($conexion, "SELECT provider_id FROM usuarios WHERE id = ? LIMIT 1");
                if (!$stmtGet) json_err('db_prepare_error', 500);
                mysqli_stmt_bind_param($stmtGet, 'i', $id);
                if (!mysqli_stmt_execute($stmtGet)) json_err('db_error', 500);
                $resGet = mysqli_stmt_get_result($stmtGet);
                $rowGet = $resGet ? mysqli_fetch_assoc($resGet) : null;
                mysqli_stmt_close($stmtGet);
                if ($rowGet && isset($rowGet['provider_id']) && $rowGet['provider_id'] !== null) {
                    $providerId = intval($rowGet['provider_id']);
                }
            }
            if ($providerId === null || $providerId <= 0) json_err('provider_required', 422);
            if (!fetch_active_medical_provider($conexion, $providerId)) json_err('invalid_or_inactive_medical_provider', 422);

            if ($hasServiceProviderId) {
                $sql = "UPDATE usuarios SET role_id = ?, rol = ?, provider_id = ?, service_provider_id = NULL WHERE id = ? LIMIT 1";
                $stmt = mysqli_prepare($conexion, $sql);
                if (!$stmt) json_err('db_prepare_error', 500);
                $rolText = (string)$roleId;
                mysqli_stmt_bind_param($stmt, 'isii', $roleId, $rolText, $providerId, $id);
                if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
                mysqli_stmt_close($stmt);
                json_ok();
            }

            $sql = "UPDATE usuarios SET role_id = ?, rol = ?, provider_id = ? WHERE id = ? LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) json_err('db_prepare_error', 500);
            $rolText = (string)$roleId;
            mysqli_stmt_bind_param($stmt, 'isii', $roleId, $rolText, $providerId, $id);
            if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
            mysqli_stmt_close($stmt);
            json_ok();
        }

        if ($hasServiceProviderId) {
            $sql = "UPDATE usuarios SET role_id = ?, rol = ?, service_provider_id = NULL WHERE id = ? LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) json_err('db_prepare_error', 500);
            $rolText = (string)$roleId;
            mysqli_stmt_bind_param($stmt, 'isi', $roleId, $rolText, $id);
            if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
            mysqli_stmt_close($stmt);
            json_ok();
        }

        $sql = "UPDATE usuarios SET role_id = ?, rol = ? WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) json_err('db_prepare_error', 500);
        $rolText = (string)$roleId;
        mysqli_stmt_bind_param($stmt, 'isi', $roleId, $rolText, $id);
        if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
        mysqli_stmt_close($stmt);
        json_ok();
        break;

    case 'toggle_active':
        if (!can_manage_users()) json_err('forbidden', 403);
        $id = intval($_POST['id'] ?? 0);
        $val = isset($_POST['val']) ? intval($_POST['val']) : 0;
        if ($id <= 0) json_err('invalid_input', 422);
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET activo = ? WHERE id = ? LIMIT 1");
        if (!$stmt) json_err('db_prepare_error', 500);
        mysqli_stmt_bind_param($stmt, 'ii', $val, $id);
        if (!mysqli_stmt_execute($stmt)) json_err('db_error', 500);
        mysqli_stmt_close($stmt);
        json_ok();
        break;

    default:
        json_err('unknown_action');
}

<?php
/**
 * admin/ajax/provider_medical_staff.php
 * CRUD MVP para staff medico interno por prestador.
 *
 * Acciones:
 *   - list_staff
 *   - get_staff
 *   - save_staff
 *   - toggle_staff
 *
 * Nota: expone active_only para futura asignacion provider -> medical staff
 * sin acoplar aun booking_request_items a esta tabla.
 */

require_once '../include/conexion.php';
require_once '../include/roles.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (
    !user_can(PERM_PROVIDERS_MEDICAL_MANAGE) &&
    !user_can('providers.medical.edit') &&
    !user_can('providers.edit')
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'forbidden']);
    exit;
}

function pms_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function pms_err($message, $status = 400, $extra = [])
{
    http_response_code((int)$status);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra));
    exit;
}

function pms_table_ready($conexion)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $q = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_medical_staff'");
    $ready = ($q && mysqli_num_rows($q) > 0);
    return $ready;
}

function pms_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $q = mysqli_query($conexion, "SHOW COLUMNS FROM {$tableEsc} LIKE '{$columnEsc}'");
    $cache[$key] = ($q && mysqli_num_rows($q) > 0);
    return $cache[$key];
}

function pms_provider_exists($conexion, $providerId)
{
    $sql = 'SELECT id, name FROM providers WHERE id = ?';
    if (pms_table_has_column($conexion, 'providers', 'is_deleted')) {
        $sql .= ' AND is_deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function pms_clean_text($value, $max = 255)
{
    $text = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, (int)$max, 'UTF-8');
    }
    return substr($text, 0, (int)$max);
}

function pms_clean_email($value)
{
    $email = pms_clean_text($value, 190);
    if ($email === '') {
        return '';
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function pms_staff_row($conexion, $staffId, $providerId = 0)
{
    $sql = 'SELECT id, provider_id, full_name, specialty, professional_license, email, phone, clinic_name, notes, active, created_at, updated_at
            FROM provider_medical_staff
            WHERE id = ?';
    if ($providerId > 0) {
        $sql .= ' AND provider_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    if ($providerId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $staffId, $providerId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $staffId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    $row['active_label'] = ((int)($row['active'] ?? 0) === 1) ? 'Activo' : 'Inactivo';
    return $row;
}

if (!pms_table_ready($conexion)) {
    pms_err('provider_medical_staff_table_missing — run sql/2026_03_12_provider_medical_staff.sql', 503);
}

$action = isset($_POST['action']) ? trim((string)$_POST['action'])
    : (isset($_GET['action']) ? trim((string)$_GET['action']) : '');

switch ($action) {
    case 'list_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $activeOnly = (int)($_GET['active_only'] ?? $_POST['active_only'] ?? 0) === 1;
        if ($providerId <= 0) {
            pms_err('provider_id required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $sql = 'SELECT id, provider_id, full_name, specialty, professional_license, email, phone, clinic_name, notes, active, created_at, updated_at
                FROM provider_medical_staff
                WHERE provider_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY active DESC, full_name ASC, id DESC';

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            pms_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $rows = [];
        $activeCount = 0;
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $row['active_label'] = ((int)($row['active'] ?? 0) === 1) ? 'Activo' : 'Inactivo';
            if ((int)($row['active'] ?? 0) === 1) {
                $activeCount++;
            }
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        pms_ok([
            'provider' => $provider,
            'items' => $rows,
            'total' => count($rows),
            'active_total' => $activeCount,
        ]);
    }

    case 'get_staff': {
        $providerId = (int)($_GET['provider_id'] ?? $_POST['provider_id'] ?? 0);
        $staffId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($providerId <= 0 || $staffId <= 0) {
            pms_err('provider_id and id required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        if (!$row) {
            pms_err('staff_not_found', 404);
        }

        pms_ok(['item' => $row, 'provider' => $provider]);
    }

    case 'save_staff': {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? 0);
        if ($providerId <= 0) {
            pms_err('provider_id required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }

        $fullName = pms_clean_text($_POST['full_name'] ?? '', 180);
        if ($fullName === '') {
            pms_err('El nombre completo es obligatorio', 422);
        }

        $specialty = pms_clean_text($_POST['specialty'] ?? '', 180);
        $license = pms_clean_text($_POST['professional_license'] ?? '', 120);
        $email = pms_clean_email($_POST['email'] ?? '');
        if ($email === false) {
            pms_err('El correo no tiene un formato válido', 422);
        }
        $phone = pms_clean_text($_POST['phone'] ?? '', 80);
        $clinicName = pms_clean_text($_POST['clinic_name'] ?? '', 180);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($staffId > 0 && !pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
        }

        if ($staffId > 0) {
            $stmt = mysqli_prepare(
                $conexion,
                'UPDATE provider_medical_staff
                    SET full_name = ?, specialty = ?, professional_license = ?, email = ?, phone = ?, clinic_name = ?, notes = ?, active = ?, updated_at = NOW()
                  WHERE id = ? AND provider_id = ?
                  LIMIT 1'
            );
            if (!$stmt) {
                pms_err('db_prepare_failed', 500);
            }
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssiii',
                $fullName,
                $specialty,
                $license,
                $email,
                $phone,
                $clinicName,
                $notes,
                $active,
                $staffId,
                $providerId
            );
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if (!$ok) {
                pms_err('db_error: ' . $err, 500);
            }
            $savedId = $staffId;
            $message = 'Staff médico actualizado correctamente';
        } else {
            $stmt = mysqli_prepare(
                $conexion,
                'INSERT INTO provider_medical_staff
                    (provider_id, full_name, specialty, professional_license, email, phone, clinic_name, notes, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                pms_err('db_prepare_failed', 500);
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isssssssi',
                $providerId,
                $fullName,
                $specialty,
                $license,
                $email,
                $phone,
                $clinicName,
                $notes,
                $active
            );
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            $savedId = (int)mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
            if (!$ok) {
                pms_err('db_error: ' . $err, 500);
            }
            $message = 'Staff médico creado correctamente';
        }

        $saved = pms_staff_row($conexion, $savedId, $providerId);
        pms_ok([
            'item' => $saved,
            'message' => $message,
            'provider' => $provider,
        ]);
    }

    case 'toggle_staff': {
        $providerId = (int)($_POST['provider_id'] ?? $_GET['provider_id'] ?? 0);
        $staffId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $value = (int)($_POST['value'] ?? $_GET['value'] ?? -1);
        if ($providerId <= 0 || $staffId <= 0 || ($value !== 0 && $value !== 1)) {
            pms_err('provider_id, id and value are required');
        }

        $provider = pms_provider_exists($conexion, $providerId);
        if (!$provider) {
            pms_err('provider_not_found', 404);
        }
        if (!pms_staff_row($conexion, $staffId, $providerId)) {
            pms_err('staff_not_found', 404);
        }

        $stmt = mysqli_prepare(
            $conexion,
            'UPDATE provider_medical_staff
                SET active = ?, updated_at = NOW()
              WHERE id = ? AND provider_id = ?
              LIMIT 1'
        );
        if (!$stmt) {
            pms_err('db_prepare_failed', 500);
        }
        mysqli_stmt_bind_param($stmt, 'iii', $value, $staffId, $providerId);
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            pms_err('db_error: ' . $err, 500);
        }

        $row = pms_staff_row($conexion, $staffId, $providerId);
        pms_ok([
            'item' => $row,
            'message' => ($value === 1) ? 'Registro activado' : 'Registro desactivado',
            'provider' => $provider,
        ]);
    }

    default:
        pms_err('action_required', 400);
}

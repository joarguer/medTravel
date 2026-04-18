<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../include/conexion.php');
require_once('../include/roles.php');
require_once('../../inc/provider_public_links.php');

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function json_ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($message, $status = 400, $code = 'bad_request', $extra = []) {
    http_response_code((int)$status);
    echo json_encode(array_merge([
        'ok' => false,
        'error' => $message,
        'code' => $code,
    ], $extra));
    exit;
}

function table_has_column($conexion, $table, $column) {
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

function record_is_soft_deleted($conexion, $table, $id) {
    if (!table_has_column($conexion, $table, 'is_deleted')) {
        return false;
    }
    $sql = "SELECT id FROM {$table} WHERE id = ? AND is_deleted = 1 LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $found = ($res && mysqli_fetch_assoc($res)) ? true : false;
    mysqli_stmt_close($stmt);
    return $found;
}

function auth_is_dev_mode() {
    return defined('APP_ENV') && APP_ENV === 'dev';
}

function auth_dev_scope_log($action, $scope) {
    if (!auth_is_dev_mode()) {
        return;
    }
    $providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
    $serviceProviderId = isset($_SESSION['service_provider_id']) ? intval($_SESSION['service_provider_id']) : 0;
    error_log(
        '[MI_EMPRESA] action=' . (string)$action .
        ' role_id=' . (string)($scope['role_id'] ?? 'null') .
        ' provider_id=' . $providerId .
        ' service_provider_id=' . $serviceProviderId
    );
}

function resolve_company_scope() {
    global $conexion;
    $isAdmin = is_role_admin_session();
    $roleId = current_role_id();
    $providerId = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : 0;
    $serviceProviderId = isset($_SESSION['service_provider_id']) ? intval($_SESSION['service_provider_id']) : 0;

    $domain = 'none';
    $scopeId = 0;

    if (in_array((int)$roleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true) && $providerId > 0) {
        $domain = 'medical';
        $scopeId = $providerId;
    } elseif ((int)$roleId === ROLE_COMPLEMENTARY_ADMIN && $serviceProviderId > 0) {
        $domain = 'complementary';
        $scopeId = $serviceProviderId;
    } elseif (!$isAdmin) {
        // legacy guard - medical only: antes solo se permitía provider_id.
        if ($providerId > 0) {
            $domain = 'medical';
            $scopeId = $providerId;
        } elseif ($serviceProviderId > 0) {
            $domain = 'complementary';
            $scopeId = $serviceProviderId;
        }
    }

    $canEditSelf = (!$isAdmin && ($domain === 'medical' || $domain === 'complementary'));
    $canUploadLogo = ($canEditSelf && $domain === 'medical');
    $isLinkedMedicalStaff = ($domain === 'medical') ? is_provider_linked_medical_staff_session($conexion ?? null) : false;
    if ($isLinkedMedicalStaff) {
        $canEditSelf = false;
        $canUploadLogo = false;
    }

    return [
        'is_admin' => $isAdmin,
        'role_id' => $roleId,
        'domain' => $domain,
        'scope_id' => $scopeId,
        'can_edit_self' => $canEditSelf,
        'can_upload_logo' => $canUploadLogo,
        'is_linked_medical_staff' => $isLinkedMedicalStaff,
    ];
}

function fetch_medical_company($conexion, $providerId) {
    $sql = "SELECT * FROM providers WHERE id = ?";
    if (table_has_column($conexion, 'providers', 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('Error al preparar consulta', 500, 'db_prepare_error');
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_complementary_company($conexion, $serviceProviderId) {
    $sql = "SELECT * FROM service_providers WHERE id = ? AND is_active = 1";
    if (table_has_column($conexion, 'service_providers', 'is_deleted')) {
        $sql .= " AND is_deleted = 0";
    }
    $sql .= " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        json_err('Error al preparar consulta', 500, 'db_prepare_error');
    }
    mysqli_stmt_bind_param($stmt, 'i', $serviceProviderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_medical_verification($conexion, $providerId) {
    $out = [
        'status' => 'pending',
        'verification_level' => 'basic',
        'completion_percent' => 0,
        'verified_at' => null,
    ];

    $sql = "SELECT 
                COALESCE(pv.status,'pending') AS status,
                COALESCE(pv.verification_level,'basic') AS verification_level,
                pv.verified_at,
                COUNT(pvi.id) AS total_items,
                SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) AS checked_items
            FROM providers p
            LEFT JOIN provider_verification pv ON pv.provider_id = p.id
            LEFT JOIN provider_verification_items pvi ON pvi.provider_id = p.id
            WHERE p.id = ?
            GROUP BY pv.status, pv.verification_level, pv.verified_at";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return $out;
    }
    mysqli_stmt_bind_param($stmt, 'i', $providerId);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $total = isset($row['total_items']) ? intval($row['total_items']) : 0;
            $checked = isset($row['checked_items']) ? intval($row['checked_items']) : 0;
            $out['status'] = $row['status'];
            $out['verification_level'] = $row['verification_level'];
            $out['verified_at'] = $row['verified_at'];
            $out['completion_percent'] = ($total > 0) ? (int)round(($checked / $total) * 100) : 0;
        }
    }
    mysqli_stmt_close($stmt);
    return $out;
}

function build_company_payload($conexion, $scope, $row) {
    $domain = $scope['domain'];
    $scopeId = intval($scope['scope_id']);

    if ($domain === 'medical') {
        $logoUrl = '';
        $logoFile = isset($row['logo']) ? trim((string)$row['logo']) : '';
        if ($logoFile !== '') {
            $path = '../../img/providers/' . $scopeId . '/' . $logoFile;
            if (file_exists($path)) {
                $logoUrl = '../img/providers/' . $scopeId . '/' . $logoFile;
            }
        }

        $verification = fetch_medical_verification($conexion, $scopeId);

        return [
            'id' => $scopeId,
            'domain' => 'medical',
            'type_raw' => isset($row['type']) ? (string)$row['type'] : 'medico',
            'type_label' => ucfirst((string)($row['type'] ?? 'medico')),
            'name' => (string)($row['name'] ?? ''),
            'city' => (string)($row['city'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'website' => (string)($row['website'] ?? ''),
            'instagram_url' => (string)($row['instagram_url'] ?? ''),
            'facebook_url' => (string)($row['facebook_url'] ?? ''),
            'linkedin_url' => (string)($row['linkedin_url'] ?? ''),
            'youtube_url' => (string)($row['youtube_url'] ?? ''),
            'whatsapp_url' => (string)($row['whatsapp_url'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'logo' => $logoFile,
            'logo_url' => $logoUrl,
            'is_active' => isset($row['is_active']) ? intval($row['is_active']) : 0,
            'calendar_capacity' => max(1, (int)($row['calendar_capacity'] ?? 1)),
            'status' => $verification['status'],
            'verification_level' => $verification['verification_level'],
            'completion_percent' => $verification['completion_percent'],
            'verified_at' => $verification['verified_at'],
            'address_available' => true,
            'logo_available' => true,
        ];
    }

    return [
        'id' => $scopeId,
        'domain' => 'complementary',
        'type_raw' => (string)($row['provider_type'] ?? 'other'),
        'type_label' => ucfirst((string)($row['provider_type'] ?? 'other')),
        'name' => (string)($row['provider_name'] ?? ''),
        'city' => (string)($row['city'] ?? ''),
        'phone' => (string)($row['contact_phone'] ?? ''),
        'email' => (string)($row['contact_email'] ?? ''),
        'address' => '',
        'website' => (string)($row['website'] ?? ''),
        'instagram_url' => '',
        'facebook_url' => '',
        'linkedin_url' => '',
        'youtube_url' => '',
        'whatsapp_url' => '',
        'description' => (string)($row['notes'] ?? ''),
        'logo' => '',
        'logo_url' => '',
        'is_active' => isset($row['is_active']) ? intval($row['is_active']) : 0,
        'calendar_capacity' => max(1, (int)($row['calendar_capacity'] ?? 1)),
        'status' => ((isset($row['is_active']) && intval($row['is_active']) === 1) ? 'active' : 'inactive'),
        'verification_level' => null,
        'completion_percent' => null,
        'verified_at' => null,
        'address_available' => false,
        'logo_available' => false,
    ];
}

function load_scoped_company($conexion, $scope) {
    if ($scope['domain'] === 'medical') {
        if (record_is_soft_deleted($conexion, 'providers', intval($scope['scope_id']))) {
            json_err('registro eliminado', 410, 'record_deleted');
        }
        $row = fetch_medical_company($conexion, intval($scope['scope_id']));
        if (!$row) {
            json_err('Prestador no encontrado', 404, 'provider_not_found');
        }
        if (isset($row['is_active']) && intval($row['is_active']) !== 1) {
            json_err('Prestador inactivo', 403, 'provider_inactive');
        }
        return build_company_payload($conexion, $scope, $row);
    }

    if ($scope['domain'] === 'complementary') {
        if (record_is_soft_deleted($conexion, 'service_providers', intval($scope['scope_id']))) {
            json_err('registro eliminado', 410, 'record_deleted');
        }
        $row = fetch_complementary_company($conexion, intval($scope['scope_id']));
        if (!$row) {
            json_err('Proveedor complementario inválido o inactivo', 403, 'service_provider_invalid');
        }
        return build_company_payload($conexion, $scope, $row);
    }

    return null;
}

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
$tipo = isset($_REQUEST['tipo']) ? trim((string)$_REQUEST['tipo']) : '';
if ($action === '') {
    // Compatibilidad legacy con JS antiguo (tipo=actualizar_empresa/upload_logo).
    if ($tipo === 'actualizar_empresa') {
        $action = 'update_self_company';
    } elseif ($tipo === 'upload_logo') {
        $action = 'upload_logo';
    } else {
        $action = 'get_self_company';
    }
}

$scope = resolve_company_scope();
auth_dev_scope_log($action, $scope);

if ($scope['domain'] === 'none') {
    if ($scope['is_admin'] && $action === 'get_self_company') {
        // permitido: admin global puede abrir vista sin scope self.
    } else {
        $rid = intval($scope['role_id']);
        if (in_array($rid, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true)) {
            json_err('Scope médico requerido en sesión', 403, 'provider_scope_required');
        }
        if ($rid === ROLE_COMPLEMENTARY_ADMIN) {
            json_err('Scope complementario requerido en sesión', 403, 'service_provider_scope_required');
        }
        json_err('Acceso denegado', 403, 'forbidden');
    }
}

if ($action === 'get_self_company') {
    $data = null;
    if ($scope['domain'] !== 'none') {
        $data = load_scoped_company($conexion, $scope);
    }
    json_ok([
        'domain' => $scope['domain'],
        'role_id' => $scope['role_id'],
        'can_edit_self' => $scope['can_edit_self'],
        'can_upload_logo' => $scope['can_upload_logo'],
        'data' => $data,
    ]);
}

if ($action === 'update_self_company') {
    if (!$scope['can_edit_self']) {
        json_err('No puedes editar empresa en este perfil', 403, 'self_edit_forbidden');
    }

    // Valida que el scope exista/esté activo antes de actualizar.
    load_scoped_company($conexion, $scope);

    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
    $city = isset($_POST['city']) ? trim((string)$_POST['city']) : '';
    $address = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
    $phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $website = isset($_POST['website']) ? trim((string)$_POST['website']) : '';
    $instagramUrl = isset($_POST['instagram_url']) ? trim((string)$_POST['instagram_url']) : '';
    $facebookUrl = isset($_POST['facebook_url']) ? trim((string)$_POST['facebook_url']) : '';
    $linkedinUrl = isset($_POST['linkedin_url']) ? trim((string)$_POST['linkedin_url']) : '';
    $youtubeUrl = isset($_POST['youtube_url']) ? trim((string)$_POST['youtube_url']) : '';
    $whatsappUrl = isset($_POST['whatsapp_url']) ? trim((string)$_POST['whatsapp_url']) : '';
    $calendarCapacity = isset($_POST['calendar_capacity']) ? (int)$_POST['calendar_capacity'] : 1;
    if ($calendarCapacity < 1) {
        $calendarCapacity = 1;
    } elseif ($calendarCapacity > 50) {
        $calendarCapacity = 50;
    }

    if ($name === '') {
        json_err('El nombre es obligatorio', 422, 'name_required');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err('Email inválido', 422, 'invalid_email');
    }
    $publicLinkInputs = [
        'website' => $website,
        'instagram_url' => $instagramUrl,
        'facebook_url' => $facebookUrl,
        'linkedin_url' => $linkedinUrl,
        'youtube_url' => $youtubeUrl,
        'whatsapp_url' => $whatsappUrl,
    ];
    foreach ($publicLinkInputs as $field => $value) {
        $validation = mt_provider_public_link_validate($field, $value);
        if (!$validation['valid']) {
            json_err($validation['message'], 422, 'invalid_public_link', ['field' => $field]);
        }
        $publicLinkInputs[$field] = $validation['normalized'];
    }
    $website = $publicLinkInputs['website'];
    $instagramUrl = $publicLinkInputs['instagram_url'];
    $facebookUrl = $publicLinkInputs['facebook_url'];
    $linkedinUrl = $publicLinkInputs['linkedin_url'];
    $youtubeUrl = $publicLinkInputs['youtube_url'];
    $whatsappUrl = $publicLinkInputs['whatsapp_url'];

    $scopeId = intval($scope['scope_id']);

    if ($scope['domain'] === 'medical') {
        $hasSocialColumns = table_has_column($conexion, 'providers', 'instagram_url')
            && table_has_column($conexion, 'providers', 'facebook_url')
            && table_has_column($conexion, 'providers', 'linkedin_url')
            && table_has_column($conexion, 'providers', 'youtube_url')
            && table_has_column($conexion, 'providers', 'whatsapp_url');
        $hasCapacityColumn = table_has_column($conexion, 'providers', 'calendar_capacity');
        if ($hasSocialColumns && $hasCapacityColumn) {
            $sql = "UPDATE providers
                    SET name = ?,
                        description = ?,
                        city = ?,
                        address = ?,
                        phone = ?,
                        email = ?,
                        website = NULLIF(?, ''),
                        instagram_url = NULLIF(?, ''),
                        facebook_url = NULLIF(?, ''),
                        linkedin_url = NULLIF(?, ''),
                        youtube_url = NULLIF(?, ''),
                        whatsapp_url = NULLIF(?, ''),
                        calendar_capacity = ?
                    WHERE id = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                json_err('Error al preparar actualización', 500, 'db_prepare_error');
            }
            mysqli_stmt_bind_param($stmt, 'ssssssssssssii', $name, $description, $city, $address, $phone, $email, $website, $instagramUrl, $facebookUrl, $linkedinUrl, $youtubeUrl, $whatsappUrl, $calendarCapacity, $scopeId);
        } elseif ($hasSocialColumns) {
            $sql = "UPDATE providers
                    SET name = ?,
                        description = ?,
                        city = ?,
                        address = ?,
                        phone = ?,
                        email = ?,
                        website = NULLIF(?, ''),
                        instagram_url = NULLIF(?, ''),
                        facebook_url = NULLIF(?, ''),
                        linkedin_url = NULLIF(?, ''),
                        youtube_url = NULLIF(?, ''),
                        whatsapp_url = NULLIF(?, '')
                    WHERE id = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                json_err('Error al preparar actualización', 500, 'db_prepare_error');
            }
            mysqli_stmt_bind_param($stmt, 'ssssssssssssi', $name, $description, $city, $address, $phone, $email, $website, $instagramUrl, $facebookUrl, $linkedinUrl, $youtubeUrl, $whatsappUrl, $scopeId);
        } elseif ($hasCapacityColumn) {
            $sql = "UPDATE providers SET name = ?, description = ?, city = ?, address = ?, phone = ?, email = ?, website = NULLIF(?, ''), calendar_capacity = ? WHERE id = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                json_err('Error al preparar actualización', 500, 'db_prepare_error');
            }
            mysqli_stmt_bind_param($stmt, 'sssssssii', $name, $description, $city, $address, $phone, $email, $website, $calendarCapacity, $scopeId);
        } else {
            $sql = "UPDATE providers SET name = ?, description = ?, city = ?, address = ?, phone = ?, email = ?, website = NULLIF(?, '') WHERE id = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                json_err('Error al preparar actualización', 500, 'db_prepare_error');
            }
            mysqli_stmt_bind_param($stmt, 'sssssssi', $name, $description, $city, $address, $phone, $email, $website, $scopeId);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            json_err('Error de base de datos', 500, 'db_error', ['detail' => $err]);
        }
        mysqli_stmt_close($stmt);
    } elseif ($scope['domain'] === 'complementary') {
        $hasCapacityColumn = table_has_column($conexion, 'service_providers', 'calendar_capacity');
        if ($hasCapacityColumn) {
            $sql = "UPDATE service_providers SET provider_name = ?, notes = ?, city = ?, contact_phone = ?, contact_email = ?, website = ?, calendar_capacity = ? WHERE id = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                json_err('Error al preparar actualización', 500, 'db_prepare_error');
            }
            mysqli_stmt_bind_param($stmt, 'ssssssii', $name, $description, $city, $phone, $email, $website, $calendarCapacity, $scopeId);
        } else {
            $sql = "UPDATE service_providers SET provider_name = ?, notes = ?, city = ?, contact_phone = ?, contact_email = ?, website = ? WHERE id = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                json_err('Error al preparar actualización', 500, 'db_prepare_error');
            }
            mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $description, $city, $phone, $email, $website, $scopeId);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            json_err('Error de base de datos', 500, 'db_error', ['detail' => $err]);
        }
        mysqli_stmt_close($stmt);
    } else {
        json_err('Acceso denegado', 403, 'forbidden');
    }

    $data = load_scoped_company($conexion, $scope);
    json_ok([
        'message' => 'Datos actualizados correctamente',
        'domain' => $scope['domain'],
        'data' => $data,
    ]);
}

if ($action === 'upload_logo') {
    if (!$scope['can_upload_logo']) {
        json_err('Logo no disponible para este dominio', 403, 'logo_not_allowed');
    }

    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        json_err('Archivo requerido', 422, 'file_required');
    }

    $file = $_FILES['logo'];
    if ($file['size'] > 2 * 1024 * 1024) {
        json_err('Archivo excede 2MB', 422, 'file_too_large');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = $file['type'];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected) {
                $mime = $detected;
            }
        }
    }

    if (!isset($allowed[$mime])) {
        json_err('Tipo de archivo inválido', 422, 'invalid_file_type');
    }

    $scopeId = intval($scope['scope_id']);
    $uploadDir = '../../img/providers/' . $scopeId . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        json_err('Error creando carpeta de subida', 500, 'upload_dir_error');
    }

    $filename = 'logo_' . time() . '.' . $allowed[$mime];
    $path = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        json_err('Error guardando archivo', 500, 'upload_failed');
    }

    $stmt = mysqli_prepare($conexion, "UPDATE providers SET logo = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        @unlink($path);
        json_err('Error al preparar actualización', 500, 'db_prepare_error');
    }
    mysqli_stmt_bind_param($stmt, 'si', $filename, $scopeId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        @unlink($path);
        json_err('Error de base de datos', 500, 'db_error', ['detail' => $err]);
    }
    mysqli_stmt_close($stmt);

    json_ok([
        'message' => 'Logo actualizado correctamente',
        'filename' => $filename,
        'url' => '../img/providers/' . $scopeId . '/' . $filename,
    ]);
}

// -----------------------------------------------------------------------
// Checklist de documentación — solo dominio médico
// -----------------------------------------------------------------------

function me_table_exists($conexion, $table) {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $esc = mysqli_real_escape_string($conexion, $table);
    $r = mysqli_query($conexion, "SHOW TABLES LIKE '{$esc}'");
    $cache[$table] = ($r && mysqli_num_rows($r) > 0);
    return $cache[$table];
}

function me_checklist_canonical_items() {
    return [
        ['key' => 'business_registration', 'label' => 'Registro Empresarial',       'desc' => 'Certificado de cámara de comercio o registro de empresa',                    'category' => 'legal',      'required' => 1],
        ['key' => 'tax_id',                 'label' => 'RUT o Tax ID',               'desc' => 'Identificación tributaria vigente',                                          'category' => 'legal',      'required' => 1],
        ['key' => 'medical_license',        'label' => 'Licencia Médica',            'desc' => 'Licencia profesional médica vigente',                                        'category' => 'medical',    'required' => 1],
        ['key' => 'professional_certifications', 'label' => 'Certificaciones Profesionales', 'desc' => 'Certificados de especialización',                                    'category' => 'medical',    'required' => 0],
        ['key' => 'clinic_accreditation',   'label' => 'Acreditación de Clínica',   'desc' => 'Certificado de habilitación de secretaría de salud',                         'category' => 'medical',    'required' => 1],
        ['key' => 'facility_photos',        'label' => 'Fotos de Instalaciones',    'desc' => 'Mínimo 5 fotos de consultorios, quirófanos, áreas de recuperación',          'category' => 'facilities', 'required' => 1],
        ['key' => 'equipment_certification','label' => 'Certificación de Equipos',  'desc' => 'Documentos de calibración/certificación de equipos médicos',                 'category' => 'facilities', 'required' => 0],
        ['key' => 'owner_identity',         'label' => 'Identidad del Responsable', 'desc' => 'Cédula o pasaporte del director/dueño',                                      'category' => 'identity',   'required' => 1],
        ['key' => 'staff_credentials',      'label' => 'Credenciales del Personal', 'desc' => 'Lista de personal médico con sus licencias',                                 'category' => 'identity',   'required' => 0],
        ['key' => 'liability_insurance',    'label' => 'Seguro de Responsabilidad', 'desc' => 'Póliza de seguro de responsabilidad civil vigente',                          'category' => 'insurance',  'required' => 1],
        ['key' => 'malpractice_insurance',  'label' => 'Seguro contra Mala Praxis', 'desc' => 'Póliza de seguro médico profesional',                                        'category' => 'insurance',  'required' => 0],
    ];
}

if ($action === 'get_my_checklist') {
    if ($scope['domain'] !== 'medical') {
        json_err('No aplica a este dominio', 403, 'wrong_domain');
    }
    if (!me_table_exists($conexion, 'provider_verification_items')) {
        json_ok(['items' => [], 'checklist_unavailable' => true]);
    }

    $scopeId = (int)$scope['scope_id'];

    $cntStmt = mysqli_prepare($conexion, 'SELECT COUNT(*) FROM provider_verification_items WHERE provider_id = ?');
    if ($cntStmt) {
        mysqli_stmt_bind_param($cntStmt, 'i', $scopeId);
        mysqli_stmt_execute($cntStmt);
        $cntRes = mysqli_stmt_get_result($cntStmt);
        $count = (int)(mysqli_fetch_row($cntRes)[0] ?? 0);
        mysqli_stmt_close($cntStmt);
    } else {
        $count = 0;
    }

    if ($count === 0) {
        foreach (me_checklist_canonical_items() as $ci) {
            $k   = mysqli_real_escape_string($conexion, $ci['key']);
            $l   = mysqli_real_escape_string($conexion, $ci['label']);
            $d   = mysqli_real_escape_string($conexion, $ci['desc']);
            $cat = mysqli_real_escape_string($conexion, $ci['category']);
            $req = (int)$ci['required'];
            mysqli_query($conexion,
                "INSERT IGNORE INTO provider_verification_items
                 (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type)
                 VALUES ($scopeId, '$k', '$l', '$d', '$cat', $req, 'document')"
            );
        }
    }

    $hasDocTable = me_table_exists($conexion, 'provider_documents');
    if ($hasDocTable) {
        // JOIN solo por id — el scope ya está en WHERE pvi.provider_id = ?
        // No añadir pd.provider_id = ? al JOIN: evita falsos null si existe
        // algún documento con discrepancia de provider_id heredada.
        $sql = 'SELECT pvi.id, pvi.item_key, pvi.item_label, pvi.item_description,
                       pvi.item_category, pvi.is_required, pvi.is_checked, pvi.evidence_document_id,
                       pd.original_filename, pd.file_path, pd.uploaded_at,
                       pd.mime_type, pd.file_extension
                FROM provider_verification_items pvi
                LEFT JOIN provider_documents pd ON pd.id = pvi.evidence_document_id
                WHERE pvi.provider_id = ?
                ORDER BY pvi.item_category, pvi.item_key';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) { json_err('Error DB', 500, 'db_prepare_error'); }
        mysqli_stmt_bind_param($stmt, 'i', $scopeId);
    } else {
        $sql = 'SELECT id, item_key, item_label, item_description, item_category,
                       is_required, is_checked, evidence_document_id
                FROM provider_verification_items
                WHERE provider_id = ?
                ORDER BY item_category, item_key';
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) { json_err('Error DB', 500, 'db_prepare_error'); }
        mysqli_stmt_bind_param($stmt, 'i', $scopeId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $filePath = $row['file_path'] ?? null;
        $itemIdInt = (int)$row['id'];
        $items[] = [
            'id'                => $itemIdInt,
            'item_key'          => (string)($row['item_key'] ?? ''),
            'item_label'        => (string)($row['item_label'] ?? ''),
            'item_description'  => (string)($row['item_description'] ?? ''),
            'item_category'     => (string)($row['item_category'] ?? ''),
            'is_required'       => (int)($row['is_required'] ?? 0),
            'is_checked'        => (int)($row['is_checked'] ?? 0),
            'has_document'      => !empty($row['evidence_document_id']),
            'original_filename' => $row['original_filename'] ?? null,
            'uploaded_at'       => $row['uploaded_at'] ?? null,
            'mime_type'         => $row['mime_type'] ?? null,
            'file_extension'    => $row['file_extension'] ?? null,
            'view_url'          => $filePath ? 'ajax/download_provider_doc.php?item_id=' . $itemIdInt : null,
        ];
    }
    mysqli_stmt_close($stmt);
    json_ok(['items' => $items]);
}

if ($action === 'upload_my_document') {
    if ($scope['domain'] !== 'medical') {
        json_err('No aplica a este dominio', 403, 'wrong_domain');
    }
    if (!$scope['can_edit_self']) {
        json_err('Sin permiso de edición en este perfil', 403, 'edit_forbidden');
    }
    if (!me_table_exists($conexion, 'provider_documents') || !me_table_exists($conexion, 'provider_verification_items')) {
        json_err('Sistema de documentos no disponible', 503, 'tables_unavailable');
    }

    $scopeId = (int)$scope['scope_id'];
    $itemId  = (int)($_POST['item_id'] ?? 0);

    if ($itemId <= 0) {
        json_err('item_id requerido', 422, 'item_id_required');
    }

    $chk = mysqli_prepare($conexion, 'SELECT id FROM provider_verification_items WHERE id = ? AND provider_id = ? LIMIT 1');
    if (!$chk) { json_err('Error DB', 500, 'db_prepare_error'); }
    mysqli_stmt_bind_param($chk, 'ii', $itemId, $scopeId);
    mysqli_stmt_execute($chk);
    $chkRes  = mysqli_stmt_get_result($chk);
    $itemRow = $chkRes ? mysqli_fetch_assoc($chkRes) : null;
    mysqli_stmt_close($chk);
    if (!$itemRow) {
        json_err('Item no encontrado o no pertenece a este prestador', 403, 'item_not_found');
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        json_err('Archivo requerido', 422, 'file_required');
    }
    $file = $_FILES['document'];
    if ($file['size'] > 10 * 1024 * 1024) {
        json_err('El archivo excede 10MB', 422, 'file_too_large');
    }

    $allowedMimes = [
        'application/pdf'    => 'pdf',
        'image/jpeg'         => 'jpg',
        'image/png'          => 'png',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    $mime = $file['type'];
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $detected = finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
            if ($detected) { $mime = $detected; }
        }
    }
    if (!isset($allowedMimes[$mime])) {
        json_err('Formato no permitido. Use PDF, JPG, PNG, DOC', 422, 'invalid_file_type');
    }
    $ext      = $allowedMimes[$mime];
    $origName = basename($file['name']);

    $uploadDir = '../uploads/provider_documents/provider_' . $scopeId . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        json_err('Error creando directorio de subida', 500, 'upload_dir_error');
    }

    $filename = 'doc_' . $scopeId . '_' . $itemId . '_' . time() . '.' . $ext;
    $filePath = 'provider_' . $scopeId . '/' . $filename;
    $fullPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        json_err('Error guardando archivo', 500, 'upload_failed');
    }

    $userId   = (int)($_SESSION['id_usuario'] ?? 0);
    $fileSize = (int)$file['size'];

    $ins = mysqli_prepare($conexion,
        'INSERT INTO provider_documents
         (provider_id, document_type, file_path, filename, original_filename,
          file_size, mime_type, file_extension, uploaded_by, uploaded_at)
         VALUES (?, \'other\', ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$ins) {
        @unlink($fullPath);
        json_err('Error preparando inserción', 500, 'db_prepare_error');
    }
    mysqli_stmt_bind_param($ins, 'isssissi', $scopeId, $filePath, $filename, $origName, $fileSize, $mime, $ext, $userId);
    if (!mysqli_stmt_execute($ins)) {
        $dbErr = mysqli_stmt_error($ins);
        mysqli_stmt_close($ins);
        @unlink($fullPath);
        json_err('Error guardando metadata', 500, 'db_error', ['detail' => $dbErr]);
    }
    $docId = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($ins);

    // Reemplazar evidencia siempre resetea validación — nueva evidencia exige re-revisión.
    $upd = mysqli_prepare($conexion,
        "UPDATE provider_verification_items
         SET evidence_document_id = ?,
             evidence_type        = 'document',
             is_checked           = 0,
             checked_at           = NULL,
             checked_by           = NULL
         WHERE id = ? AND provider_id = ?"
    );
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'iii', $docId, $itemId, $scopeId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }

    json_ok([
        'message'           => 'Documento subido correctamente',
        'document_id'       => $docId,
        'original_filename' => $origName,
        'view_url'          => 'ajax/download_provider_doc.php?item_id=' . $itemId,
    ]);
}

json_err('Acción inválida', 400, 'invalid_action');

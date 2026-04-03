<?php
session_start();
include('../include/conexion.php');
require_once '../include/roles.php';
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

if (is_administrative_session()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

function clientes_table_exists($conexion, $table)
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$table] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$table];
}

function clientes_table_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function clientes_bind_stmt_params($stmt, $types, &$values)
{
    if ($types === '' || empty($values)) {
        return true;
    }
    $bind = [$types];
    foreach ($values as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function clientes_normalized_email_expr($expr)
{
    return "LOWER(TRIM(CONVERT({$expr} USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
}

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$id_usuario = $_SESSION['id_usuario'];

// GET: Obtener lista de clientes
if ($tipo == 'get') {
    $providerId = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
    $serviceProviderId = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;
    $isAdminSession = is_role_admin_session();
    $isComplementarySession = is_complementary_user_session();
    $sessionRoleText = strtolower(trim((string)($_SESSION['rol'] ?? '')));
    $sessionRoleId = current_role_id();
    $hasComplementaryRoleHint = strpos($sessionRoleText, 'complement') !== false || strpos($sessionRoleText, 'partner') !== false;
    $isLikelyMedicalProviderRole = in_array((int)$sessionRoleId, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true)
        || strpos($sessionRoleText, 'prestador') !== false
        || (!$hasComplementaryRoleHint && strpos($sessionRoleText, 'provider') !== false);
    $isMedicalProviderSession = !$isAdminSession && ($isLikelyMedicalProviderRole || ($providerId > 0 && !$isComplementarySession));

    $baseSql = "SELECT
                    c.id,
                    CONCAT(c.nombre, ' ', c.apellido) AS nombre_completo,
                    c.nombre,
                    c.apellido,
                    c.email,
                    c.telefono,
                    c.pais,
                    c.estado,
                    c.ciudad,
                    c.status,
                    c.origen_contacto,
                    c.created_at,
                    c.updated_at
                FROM clientes c";

    $whereParts = [];
    $types = '';
    $params = [];

    if (!$isAdminSession) {
        if (!clientes_table_exists($conexion, 'booking_requests') || !clientes_table_exists($conexion, 'booking_request_items')) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $hasBrSoftDelete = clientes_table_has_column($conexion, 'booking_requests', 'is_deleted');
        $hasBriSoftDelete = clientes_table_has_column($conexion, 'booking_request_items', 'is_deleted');
        $hasBrEmail = clientes_table_has_column($conexion, 'booking_requests', 'email');
        $hasBrClientUserId = clientes_table_has_column($conexion, 'booking_requests', 'client_user_id');
        $hasUsuariosTable = clientes_table_exists($conexion, 'usuarios');
        $hasUsuariosEmail = $hasUsuariosTable && clientes_table_has_column($conexion, 'usuarios', 'email');
        $hasBriProviderId = clientes_table_has_column($conexion, 'booking_request_items', 'provider_id');
        $hasBriServiceProviderId = clientes_table_has_column($conexion, 'booking_request_items', 'service_provider_id');

        $clientMatchParts = [];
        if ($hasBrEmail) {
            $clientMatchParts[] = clientes_normalized_email_expr('br.email') . " = " . clientes_normalized_email_expr('c.email');
        }
        if ($hasBrClientUserId && $hasUsuariosEmail) {
            $clientMatchParts[] = "EXISTS (
                SELECT 1
                FROM usuarios u_cli
                WHERE u_cli.id = br.client_user_id
                  AND " . clientes_normalized_email_expr('u_cli.email') . " = " . clientes_normalized_email_expr('c.email') . "
            )";
        }

        if (empty($clientMatchParts)) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $providerScope = '';
        if ($isMedicalProviderSession) {
            if ($providerId <= 0 || !$hasBriProviderId) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $providerScope = "bri.provider_id = ? AND bri.item_type = 'medical_offer'";
            $types .= 'i';
            $params[] = $providerId;
        } else {
            if ($serviceProviderId <= 0 || !$hasBriServiceProviderId) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $providerScope = "bri.service_provider_id = ? AND bri.item_type = 'complementary_service'";
            $types .= 'i';
            $params[] = $serviceProviderId;
        }

        $existsSql = "EXISTS (
            SELECT 1
            FROM booking_requests br
            INNER JOIN booking_request_items bri ON bri.booking_request_id = br.id
            WHERE ({$providerScope})";
        if ($hasBrSoftDelete) {
            $existsSql .= " AND br.is_deleted = 0";
        }
        if ($hasBriSoftDelete) {
            $existsSql .= " AND bri.is_deleted = 0";
        }
        $existsSql .= " AND (" . implode(' OR ', $clientMatchParts) . ")
        )";

        $whereParts[] = $existsSql;
    }

    $sql = $baseSql;
    if (!empty($whereParts)) {
        $sql .= " WHERE " . implode(' AND ', $whereParts);
    }
    $sql .= " ORDER BY c.id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar consulta de clientes: ' . mysqli_error($conexion)]);
        exit;
    }

    if (!clientes_bind_stmt_params($stmt, $types, $params) || !mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Error al obtener clientes: ' . $err]);
        exit;
    }

    $resultado = mysqli_stmt_get_result($stmt);
    $clientes = array();
    if ($resultado) {
        while ($row = mysqli_fetch_assoc($resultado)) {
            $clientes[] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'data' => $clientes]);
}

// GET_ONE: Obtener un cliente específico
elseif ($tipo == 'get_one') {
    $id = mysqli_real_escape_string($conexion, $_POST['id']);
    
    $sql = "SELECT * FROM clientes WHERE id = '$id'";
    $resultado = mysqli_query($conexion, $sql);
    
    if ($resultado && mysqli_num_rows($resultado) > 0) {
        $cliente = mysqli_fetch_assoc($resultado);
        echo json_encode(['success' => true, 'data' => $cliente]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    }
}

// CREATE: Crear nuevo cliente
elseif ($tipo == 'create') {
    // Información Personal
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $apellido = mysqli_real_escape_string($conexion, $_POST['apellido']);
    $email = mysqli_real_escape_string($conexion, $_POST['email']);
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? mysqli_real_escape_string($conexion, $_POST['fecha_nacimiento']) : NULL;
    $telefono = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $whatsapp = mysqli_real_escape_string($conexion, $_POST['whatsapp']);
    
    // Ubicación
    $pais = mysqli_real_escape_string($conexion, $_POST['pais']);
    $estado = mysqli_real_escape_string($conexion, $_POST['estado']);
    $ciudad = mysqli_real_escape_string($conexion, $_POST['ciudad']);
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion']);
    $codigo_postal = mysqli_real_escape_string($conexion, $_POST['codigo_postal']);
    
    // Información Adicional
    $tipo_documento = mysqli_real_escape_string($conexion, $_POST['tipo_documento']);
    $numero_pasaporte = mysqli_real_escape_string($conexion, $_POST['numero_pasaporte']);
    $idioma_preferido = mysqli_real_escape_string($conexion, $_POST['idioma_preferido']);
    $status = mysqli_real_escape_string($conexion, $_POST['status']);
    $origen_contacto = mysqli_real_escape_string($conexion, $_POST['origen_contacto']);
    
    // Contacto de Emergencia
    $contacto_emergencia_nombre = mysqli_real_escape_string($conexion, $_POST['contacto_emergencia_nombre']);
    $contacto_emergencia_telefono = mysqli_real_escape_string($conexion, $_POST['contacto_emergencia_telefono']);
    $contacto_emergencia_relacion = mysqli_real_escape_string($conexion, $_POST['contacto_emergencia_relacion']);
    
    // Información Médica
    $condiciones_medicas = mysqli_real_escape_string($conexion, $_POST['condiciones_medicas']);
    $alergias = mysqli_real_escape_string($conexion, $_POST['alergias']);
    $medicamentos_actuales = mysqli_real_escape_string($conexion, $_POST['medicamentos_actuales']);
    
    // Notas
    $notas = mysqli_real_escape_string($conexion, $_POST['notas']);
    
    // Marketing / UTM
    $utm_source = isset($_POST['utm_source']) ? mysqli_real_escape_string($conexion, $_POST['utm_source']) : '';
    $utm_medium = isset($_POST['utm_medium']) ? mysqli_real_escape_string($conexion, $_POST['utm_medium']) : '';
    $utm_campaign = isset($_POST['utm_campaign']) ? mysqli_real_escape_string($conexion, $_POST['utm_campaign']) : '';
    $utm_content = isset($_POST['utm_content']) ? mysqli_real_escape_string($conexion, $_POST['utm_content']) : '';
    $utm_term = isset($_POST['utm_term']) ? mysqli_real_escape_string($conexion, $_POST['utm_term']) : '';
    $referred_by = isset($_POST['referred_by']) ? mysqli_real_escape_string($conexion, $_POST['referred_by']) : '';
    
    // Validar que el email no exista
    $check_email = "SELECT id FROM clientes WHERE email = '$email'";
    $resultado_check = mysqli_query($conexion, $check_email);
    
    if (mysqli_num_rows($resultado_check) > 0) {
        echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
        exit;
    }
    
    // Construir query de inserción
    $sql = "INSERT INTO clientes (
                nombre, apellido, email, fecha_nacimiento, telefono, whatsapp,
                pais, estado, ciudad, direccion, codigo_postal,
                tipo_documento, numero_pasaporte, idioma_preferido,
                status, origen_contacto,
                contacto_emergencia_nombre, contacto_emergencia_telefono, contacto_emergencia_relacion,
                condiciones_medicas, alergias, medicamentos_actuales,
                notas,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term, referred_by
            ) VALUES (
                '$nombre', '$apellido', '$email', " . ($fecha_nacimiento ? "'$fecha_nacimiento'" : "NULL") . ", '$telefono', '$whatsapp',
                '$pais', '$estado', '$ciudad', '$direccion', '$codigo_postal',
                '$tipo_documento', '$numero_pasaporte', '$idioma_preferido',
                '$status', '$origen_contacto',
                '$contacto_emergencia_nombre', '$contacto_emergencia_telefono', '$contacto_emergencia_relacion',
                '$condiciones_medicas', '$alergias', '$medicamentos_actuales',
                '$notas',
                '$utm_source', '$utm_medium', '$utm_campaign', '$utm_content', '$utm_term', '$referred_by'
            )";
    
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Cliente creado exitosamente', 'id' => mysqli_insert_id($conexion)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear cliente: ' . mysqli_error($conexion)]);
    }
}

// UPDATE: Actualizar cliente
elseif ($tipo == 'update') {
    $id = mysqli_real_escape_string($conexion, $_POST['id']);
    
    // Información Personal
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $apellido = mysqli_real_escape_string($conexion, $_POST['apellido']);
    $email = mysqli_real_escape_string($conexion, $_POST['email']);
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? mysqli_real_escape_string($conexion, $_POST['fecha_nacimiento']) : NULL;
    $telefono = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $whatsapp = mysqli_real_escape_string($conexion, $_POST['whatsapp']);
    
    // Ubicación
    $pais = mysqli_real_escape_string($conexion, $_POST['pais']);
    $estado = mysqli_real_escape_string($conexion, $_POST['estado']);
    $ciudad = mysqli_real_escape_string($conexion, $_POST['ciudad']);
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion']);
    $codigo_postal = mysqli_real_escape_string($conexion, $_POST['codigo_postal']);
    
    // Información Adicional
    $tipo_documento = mysqli_real_escape_string($conexion, $_POST['tipo_documento']);
    $numero_pasaporte = mysqli_real_escape_string($conexion, $_POST['numero_pasaporte']);
    $idioma_preferido = mysqli_real_escape_string($conexion, $_POST['idioma_preferido']);
    $status = mysqli_real_escape_string($conexion, $_POST['status']);
    $origen_contacto = mysqli_real_escape_string($conexion, $_POST['origen_contacto']);
    
    // Contacto de Emergencia
    $contacto_emergencia_nombre = mysqli_real_escape_string($conexion, $_POST['contacto_emergencia_nombre']);
    $contacto_emergencia_telefono = mysqli_real_escape_string($conexion, $_POST['contacto_emergencia_telefono']);
    $contacto_emergencia_relacion = mysqli_real_escape_string($conexion, $_POST['contacto_emergencia_relacion']);
    
    // Información Médica
    $condiciones_medicas = mysqli_real_escape_string($conexion, $_POST['condiciones_medicas']);
    $alergias = mysqli_real_escape_string($conexion, $_POST['alergias']);
    $medicamentos_actuales = mysqli_real_escape_string($conexion, $_POST['medicamentos_actuales']);
    
    // Notas
    $notas = mysqli_real_escape_string($conexion, $_POST['notas']);
    
    // Marketing / UTM
    $utm_source = isset($_POST['utm_source']) ? mysqli_real_escape_string($conexion, $_POST['utm_source']) : '';
    $utm_medium = isset($_POST['utm_medium']) ? mysqli_real_escape_string($conexion, $_POST['utm_medium']) : '';
    $utm_campaign = isset($_POST['utm_campaign']) ? mysqli_real_escape_string($conexion, $_POST['utm_campaign']) : '';
    $utm_content = isset($_POST['utm_content']) ? mysqli_real_escape_string($conexion, $_POST['utm_content']) : '';
    $utm_term = isset($_POST['utm_term']) ? mysqli_real_escape_string($conexion, $_POST['utm_term']) : '';
    $referred_by = isset($_POST['referred_by']) ? mysqli_real_escape_string($conexion, $_POST['referred_by']) : '';
    
    // Validar que el email no exista para otro cliente
    $check_email = "SELECT id FROM clientes WHERE email = '$email' AND id != '$id'";
    $resultado_check = mysqli_query($conexion, $check_email);
    
    if (mysqli_num_rows($resultado_check) > 0) {
        echo json_encode(['success' => false, 'message' => 'El email ya está registrado para otro cliente']);
        exit;
    }
    
    $sql = "UPDATE clientes SET 
                nombre = '$nombre',
                apellido = '$apellido',
                email = '$email',
                fecha_nacimiento = " . ($fecha_nacimiento ? "'$fecha_nacimiento'" : "NULL") . ",
                telefono = '$telefono',
                whatsapp = '$whatsapp',
                pais = '$pais',
                estado = '$estado',
                ciudad = '$ciudad',
                direccion = '$direccion',
                codigo_postal = '$codigo_postal',
                tipo_documento = '$tipo_documento',
                numero_pasaporte = '$numero_pasaporte',
                idioma_preferido = '$idioma_preferido',
                status = '$status',
                origen_contacto = '$origen_contacto',
                contacto_emergencia_nombre = '$contacto_emergencia_nombre',
                contacto_emergencia_telefono = '$contacto_emergencia_telefono',
                contacto_emergencia_relacion = '$contacto_emergencia_relacion',
                condiciones_medicas = '$condiciones_medicas',
                alergias = '$alergias',
                medicamentos_actuales = '$medicamentos_actuales',
                notas = '$notas',
                utm_source = '$utm_source',
                utm_medium = '$utm_medium',
                utm_campaign = '$utm_campaign',
                utm_content = '$utm_content',
                utm_term = '$utm_term',
                referred_by = '$referred_by'
            WHERE id = '$id'";
    
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Cliente actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar cliente: ' . mysqli_error($conexion)]);
    }
}

// DELETE: Eliminar cliente (cambiar status a 'inactivo')
elseif ($tipo == 'delete') {
    $id = mysqli_real_escape_string($conexion, $_POST['id']);
    
    $sql = "UPDATE clientes SET status = 'inactivo' WHERE id = '$id'";
    
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Cliente eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar cliente: ' . mysqli_error($conexion)]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Tipo de operación no válido']);
}

mysqli_close($conexion);
?>

<?php
session_start();
include('../include/conexion.php');
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$id_usuario = $_SESSION['id_usuario'];

// GET: Obtener lista de clientes
if ($tipo == 'get') {
    $sql = "SELECT 
                id,
                CONCAT(nombre, ' ', apellido) as nombre_completo,
                nombre,
                apellido,
                email,
                telefono,
                pais,
                estado,
                ciudad,
                status,
                origen_contacto,
                created_at,
                updated_at
            FROM clientes 
            WHERE activo = 1
            ORDER BY id DESC";
    
    $resultado = mysqli_query($conexion, $sql);
    
    if ($resultado) {
        $clientes = array();
        while ($row = mysqli_fetch_assoc($resultado)) {
            $clientes[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $clientes]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al obtener clientes: ' . mysqli_error($conexion)]);
    }
}

// GET_ONE: Obtener un cliente específico
elseif ($tipo == 'get_one') {
    $id = mysqli_real_escape_string($conexion, $_POST['id']);
    
    $sql = "SELECT * FROM clientes WHERE id = '$id' AND activo = 1";
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
    $check_email = "SELECT id FROM clientes WHERE email = '$email' AND activo = 1";
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
                utm_source, utm_medium, utm_campaign, utm_content, utm_term, referred_by,
                created_by, activo
            ) VALUES (
                '$nombre', '$apellido', '$email', " . ($fecha_nacimiento ? "'$fecha_nacimiento'" : "NULL") . ", '$telefono', '$whatsapp',
                '$pais', '$estado', '$ciudad', '$direccion', '$codigo_postal',
                '$tipo_documento', '$numero_pasaporte', '$idioma_preferido',
                '$status', '$origen_contacto',
                '$contacto_emergencia_nombre', '$contacto_emergencia_telefono', '$contacto_emergencia_relacion',
                '$condiciones_medicas', '$alergias', '$medicamentos_actuales',
                '$notas',
                '$utm_source', '$utm_medium', '$utm_campaign', '$utm_content', '$utm_term', '$referred_by',
                '$id_usuario', 1
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
    $check_email = "SELECT id FROM clientes WHERE email = '$email' AND id != '$id' AND activo = 1";
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
                referred_by = '$referred_by',
                updated_by = '$id_usuario'
            WHERE id = '$id' AND activo = 1";
    
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Cliente actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar cliente: ' . mysqli_error($conexion)]);
    }
}

// DELETE: Eliminar cliente (soft delete)
elseif ($tipo == 'delete') {
    $id = mysqli_real_escape_string($conexion, $_POST['id']);
    
    $sql = "UPDATE clientes SET activo = 0, updated_by = '$id_usuario' WHERE id = '$id'";
    
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

<?php
session_start();
include('../include/conexion.php');
require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$id_usuario = $_SESSION['id_usuario'];

// GET: Obtener lista de proveedores con su estado de verificación
if ($tipo == 'get') {
    $sql = "SELECT 
                p.id,
                p.nombre AS provider_name,
                p.email,
                p.telefono,
                COALESCE(pv.status, 'pending') AS verification_status,
                COALESCE(pv.verification_level, 'basic') AS verification_level,
                COALESCE(pv.trust_score, 0) AS trust_score,
                pv.verified_at,
                pv.expires_at,
                COUNT(pvi.id) AS total_items,
                SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) AS checked_items,
                CASE 
                    WHEN COUNT(pvi.id) > 0 THEN 
                        ROUND((SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) / COUNT(pvi.id)) * 100, 0)
                    ELSE 0 
                END AS completion_percent
            FROM providers p
            LEFT JOIN provider_verification pv ON p.id = pv.provider_id
            LEFT JOIN provider_verification_items pvi ON p.id = pvi.provider_id
            GROUP BY p.id, p.nombre, p.email, p.telefono, pv.status, pv.verification_level, pv.trust_score, pv.verified_at, pv.expires_at
            ORDER BY p.id DESC";
    
    $resultado = mysqli_query($conexion, $sql);
    
    if ($resultado) {
        $providers = array();
        while ($row = mysqli_fetch_assoc($resultado)) {
            $providers[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $providers]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conexion)]);
    }
}

// GET_VERIFICATION: Obtener detalles de verificación de un proveedor
elseif ($tipo == 'get_verification') {
    $provider_id = mysqli_real_escape_string($conexion, $_POST['provider_id']);
    
    // Obtener o crear registro de verificación
    $sql = "SELECT * FROM provider_verification WHERE provider_id = '$provider_id'";
    $resultado = mysqli_query($conexion, $sql);
    
    if ($resultado && mysqli_num_rows($resultado) > 0) {
        $verification = mysqli_fetch_assoc($resultado);
    } else {
        // Crear registro si no existe
        $insert_sql = "INSERT INTO provider_verification (provider_id, status, verification_level, trust_score) 
                      VALUES ('$provider_id', 'pending', 'basic', 0)";
        mysqli_query($conexion, $insert_sql);
        
        $verification = [
            'provider_id' => $provider_id,
            'status' => 'pending',
            'verification_level' => 'basic',
            'trust_score' => 0,
            'admin_notes' => '',
            'verified_at' => null
        ];
    }
    
    // Obtener items del checklist
    $items_sql = "SELECT * FROM provider_verification_items 
                  WHERE provider_id = '$provider_id' 
                  ORDER BY item_category, item_key";
    $items_resultado = mysqli_query($conexion, $items_sql);
    
    $items = array();
    if ($items_resultado) {
        while ($row = mysqli_fetch_assoc($items_resultado)) {
            $items[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'verification' => $verification,
        'items' => $items
    ]);
}

// UPDATE_STATUS: Actualizar estado de verificación
elseif ($tipo == 'update_status') {
    $provider_id = mysqli_real_escape_string($conexion, $_POST['provider_id']);
    $status = mysqli_real_escape_string($conexion, $_POST['status']);
    $verification_level = mysqli_real_escape_string($conexion, $_POST['verification_level']);
    $admin_notes = mysqli_real_escape_string($conexion, $_POST['admin_notes']);
    
    // Verificar si existe el registro
    $check_sql = "SELECT id FROM provider_verification WHERE provider_id = '$provider_id'";
    $check_resultado = mysqli_query($conexion, $check_sql);
    
    if (mysqli_num_rows($check_resultado) > 0) {
        // Actualizar
        $verified_at_sql = ($status == 'verified') ? ", verified_at = NOW(), verified_by = '$id_usuario'" : "";
        
        $sql = "UPDATE provider_verification SET 
                    status = '$status',
                    verification_level = '$verification_level',
                    admin_notes = '$admin_notes'
                    $verified_at_sql
                WHERE provider_id = '$provider_id'";
    } else {
        // Crear
        $verified_at_sql = ($status == 'verified') ? ", verified_at = NOW(), verified_by = '$id_usuario'" : "";
        
        $sql = "INSERT INTO provider_verification 
                (provider_id, status, verification_level, admin_notes, trust_score $verified_at_sql) 
                VALUES ('$provider_id', '$status', '$verification_level', '$admin_notes', 0)";
    }
    
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Estado actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conexion)]);
    }
}

// INITIALIZE_CHECKLIST: Crear checklist estándar para un proveedor
elseif ($tipo == 'initialize_checklist') {
    $provider_id = mysqli_real_escape_string($conexion, $_POST['provider_id']);
    
    // Verificar si ya existen items
    $check_sql = "SELECT COUNT(*) as count FROM provider_verification_items WHERE provider_id = '$provider_id'";
    $check_resultado = mysqli_query($conexion, $check_sql);
    $row = mysqli_fetch_assoc($check_resultado);
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'El checklist ya existe para este proveedor']);
        exit;
    }
    
    // Items estándar del checklist
    $items = [
        ['key' => 'business_registration', 'label' => 'Registro Empresarial', 'desc' => 'Certificado de cámara de comercio o registro de empresa', 'category' => 'legal', 'required' => 1],
        ['key' => 'tax_id', 'label' => 'RUT o Tax ID', 'desc' => 'Identificación tributaria vigente', 'category' => 'legal', 'required' => 1],
        ['key' => 'medical_license', 'label' => 'Licencia Médica', 'desc' => 'Licencia profesional médica vigente', 'category' => 'medical', 'required' => 1],
        ['key' => 'professional_certifications', 'label' => 'Certificaciones Profesionales', 'desc' => 'Certificados de especialización', 'category' => 'medical', 'required' => 0],
        ['key' => 'clinic_accreditation', 'label' => 'Acreditación de Clínica', 'desc' => 'Certificado de habilitación de secretaría de salud', 'category' => 'medical', 'required' => 1],
        ['key' => 'facility_photos', 'label' => 'Fotos de Instalaciones', 'desc' => 'Mínimo 5 fotos de consultorios, quirófanos, áreas de recuperación', 'category' => 'facilities', 'required' => 1],
        ['key' => 'equipment_certification', 'label' => 'Certificación de Equipos', 'desc' => 'Documentos de calibración/certificación de equipos médicos', 'category' => 'facilities', 'required' => 0],
        ['key' => 'owner_identity', 'label' => 'Identidad del Responsable', 'desc' => 'Cédula o pasaporte del director/dueño', 'category' => 'identity', 'required' => 1],
        ['key' => 'staff_credentials', 'label' => 'Credenciales del Personal', 'desc' => 'Lista de personal médico con sus licencias', 'category' => 'identity', 'required' => 0],
        ['key' => 'liability_insurance', 'label' => 'Seguro de Responsabilidad', 'desc' => 'Póliza de seguro de responsabilidad civil vigente', 'category' => 'insurance', 'required' => 1],
        ['key' => 'malpractice_insurance', 'label' => 'Seguro contra Mala Praxis', 'desc' => 'Póliza de seguro médico profesional', 'category' => 'insurance', 'required' => 0],
    ];
    
    // Insertar todos los items
    $success_count = 0;
    foreach ($items as $item) {
        $key = mysqli_real_escape_string($conexion, $item['key']);
        $label = mysqli_real_escape_string($conexion, $item['label']);
        $desc = mysqli_real_escape_string($conexion, $item['desc']);
        $category = mysqli_real_escape_string($conexion, $item['category']);
        $required = $item['required'];
        
        $insert_sql = "INSERT INTO provider_verification_items 
                      (provider_id, item_key, item_label, item_description, item_category, is_required, evidence_type) 
                      VALUES ('$provider_id', '$key', '$label', '$desc', '$category', $required, 'document')";
        
        if (mysqli_query($conexion, $insert_sql)) {
            $success_count++;
        }
    }
    
    echo json_encode(['success' => true, 'message' => "$success_count items creados", 'count' => $success_count]);
}

// TOGGLE_ITEM: Marcar/desmarcar un item del checklist
elseif ($tipo == 'toggle_item') {
    $item_id = mysqli_real_escape_string($conexion, $_POST['item_id']);
    $is_checked = mysqli_real_escape_string($conexion, $_POST['is_checked']);
    
    $checked_at_sql = ($is_checked == 1) ? ", checked_at = NOW(), checked_by = '$id_usuario'" : ", checked_at = NULL, checked_by = NULL";
    
    $sql = "UPDATE provider_verification_items 
            SET is_checked = '$is_checked' $checked_at_sql 
            WHERE id = '$item_id'";
    
    if (mysqli_query($conexion, $sql)) {
        // Recalcular trust score del proveedor
        $get_provider = "SELECT provider_id FROM provider_verification_items WHERE id = '$item_id'";
        $result = mysqli_query($conexion, $get_provider);
        $row = mysqli_fetch_assoc($result);
        $provider_id = $row['provider_id'];
        
        // Calcular nuevo trust score
        $calc_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) as checked,
                        ROUND((SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 0) as score
                     FROM provider_verification_items 
                     WHERE provider_id = '$provider_id'";
        $calc_result = mysqli_query($conexion, $calc_sql);
        $calc_row = mysqli_fetch_assoc($calc_result);
        
        // Actualizar trust score
        $update_score = "UPDATE provider_verification 
                        SET trust_score = " . $calc_row['score'] . " 
                        WHERE provider_id = '$provider_id'";
        mysqli_query($conexion, $update_score);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Item actualizado', 
            'trust_score' => $calc_row['score'],
            'checked' => $calc_row['checked'],
            'total' => $calc_row['total']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conexion)]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Tipo de operación no válido']);
}

mysqli_close($conexion);
?>

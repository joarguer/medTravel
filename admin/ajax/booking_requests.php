<?php
include '../include/conexion.php';

// Verificar sesión de administrador
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$action = $_POST['action'] ?? '';

// GET ALL BOOKING REQUESTS
if ($action === 'get_all') {
    $query = "SELECT id, name, email, destination, booking_datetime, persons, 
                     selected_offers, status, origin, created_at 
              FROM booking_requests 
              ORDER BY created_at DESC";
    $result = mysqli_query($conexion, $query);
    
    if ($result) {
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al cargar solicitudes: ' . mysqli_error($conexion)]);
    }
}

// GET BOOKING DETAIL
elseif ($action === 'get_detail') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    $query = "SELECT * FROM booking_requests WHERE id = $id LIMIT 1";
    $result = mysqli_query($conexion, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró la solicitud']);
    }
}

// GET OFFERS DETAILS
elseif ($action === 'get_offers_details') {
    $offer_ids = json_decode($_POST['offer_ids'] ?? '[]', true);
    
    if (empty($offer_ids) || !is_array($offer_ids)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    $ids_string = implode(',', array_map('intval', $offer_ids));
    
    $query = "SELECT 
                o.id, o.title, o.description, o.price_from, o.currency,
                p.name AS provider_name, p.city AS provider_city
              FROM provider_service_offers o
              INNER JOIN providers p ON o.provider_id = p.id
              WHERE o.id IN ($ids_string)";
    
    $result = mysqli_query($conexion, $query);
    
    if ($result) {
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al cargar ofertas']);
    }
}

// UPDATE STATUS
elseif ($action === 'update_status') {
    $id = intval($_POST['id'] ?? 0);
    $status = mysqli_real_escape_string($conexion, $_POST['status'] ?? '');
    
    $allowed_statuses = ['pending', 'contacted', 'confirmed', 'cancelled'];
    
    if ($id <= 0 || !in_array($status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }
    
    $query = "UPDATE booking_requests SET status = '$status' WHERE id = $id";
    
    if (mysqli_query($conexion, $query)) {
        echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conexion)]);
    }
}

// DELETE BOOKING
elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    $query = "DELETE FROM booking_requests WHERE id = $id";
    
    if (mysqli_query($conexion, $query)) {
        echo json_encode(['success' => true, 'message' => 'Solicitud eliminada']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . mysqli_error($conexion)]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>

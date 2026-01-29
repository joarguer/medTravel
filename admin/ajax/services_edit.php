<?php
include("../include/include.php");
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $subtitle_1 = isset($_POST['subtitle_1']) ? trim($_POST['subtitle_1']) : '';
    $subtitle_2 = isset($_POST['subtitle_2']) ? trim($_POST['subtitle_2']) : '';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'El título es requerido']);
        exit;
    }
    
    if ($id > 0) {
        // Actualizar
        $stmt = mysqli_prepare($conexion, "UPDATE services_header SET title = ?, subtitle_1 = ?, subtitle_2 = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $subtitle_1, $subtitle_2, $id);
    } else {
        // Insertar
        $stmt = mysqli_prepare($conexion, "INSERT INTO services_header (title, subtitle_1, subtitle_2, activo) VALUES (?, ?, ?, 0)");
        mysqli_stmt_bind_param($stmt, 'sss', $title, $subtitle_1, $subtitle_2);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>

<?php
// Test simplificado del endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
session_start();

if(!isset($_SESSION['id_usuario'])){
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

require_once('../include/conexion.php');

$action = isset($_POST['action']) ? $_POST['action'] : '';

if($action == 'list') {
    $query = "SELECT id, account_type, email_address, display_name 
              FROM email_settings LIMIT 4";
    
    $result = mysqli_query($conexion, $query);
    
    if(!$result) {
        echo json_encode(['ok' => false, 'message' => mysqli_error($conexion)]);
        exit;
    }
    
    $accounts = array();
    while($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $accounts]);
} else {
    echo json_encode(['ok' => false, 'message' => 'Acción no válida: ' . $action]);
}
?>

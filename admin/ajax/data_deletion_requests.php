<?php
require_once __DIR__ . '/../include/conexion.php';
require_once __DIR__ . '/../include/roles.php';
require_once __DIR__ . '/../include/data_deletion_service.php';

require_login_ajax();
header('Content-Type: application/json; charset=utf-8');

function dd_admin_json_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function dd_admin_json_err($message, $status = 400)
{
    http_response_code((int)$status);
    echo json_encode(['ok' => false, 'error' => $message, 'message' => $message]);
    exit;
}

if (!is_role_admin_session()) {
    dd_admin_json_err('forbidden', 403);
}
if (!user_can(PERM_SETTINGS_MANAGE)) {
    dd_admin_json_err('forbidden', 403);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    try {
        $rows = dd_fetch_requests($conexion, 500);
        dd_admin_json_ok(['data' => $rows]);
    } catch (Throwable $e) {
        dd_admin_json_err('list_failed', 500);
    }
}

if ($action === 'process') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        dd_admin_json_err('method_not_allowed', 405);
    }

    $requestId = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
    if ($requestId <= 0) {
        dd_admin_json_err('invalid_request_id', 422);
    }

    $adminUserId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
    if ($adminUserId <= 0) {
        dd_admin_json_err('invalid_session_user', 401);
    }

    try {
        $result = dd_process_request($conexion, $requestId, $adminUserId);
        dd_admin_json_ok([
            'message' => 'processed',
            'request_ref' => (string)($result['request_id'] ?? ''),
            'result_summary' => (string)($result['summary'] ?? ''),
            'counts' => $result['counts'] ?? [],
        ]);
    } catch (Throwable $e) {
        $msg = trim((string)$e->getMessage());
        if ($msg === 'not_found') {
            dd_admin_json_err('not_found', 404);
        }
        if ($msg === 'already_completed') {
            dd_admin_json_ok([
                'message' => 'already_completed',
                'request_ref' => '',
                'result_summary' => '',
                'counts' => [],
            ]);
        }
        if ($msg === 'already_processing') {
            dd_admin_json_ok([
                'message' => 'already_processing',
                'request_ref' => '',
                'result_summary' => '',
                'counts' => [],
            ]);
        }
        if ($msg === 'invalid_request_id') {
            dd_admin_json_err('invalid_request_id', 422);
        }
        dd_admin_json_err('process_failed', 500);
    }
}

dd_admin_json_err('invalid_action', 400);

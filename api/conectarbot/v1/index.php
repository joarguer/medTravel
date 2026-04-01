<?php
declare(strict_types=1);
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../admin/include/conexion.php';

$configPath = __DIR__ . '/../../../config/conectarbot_api.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'data' => null,
        'meta' => ['source' => 'medtravel', 'ts' => gmdate('c')],
        'error' => ['code' => 'CONFIG_MISSING', 'message' => 'Config file config/conectarbot_api.php not found'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = require $configPath;
$API_KEY = isset($cfg['API_KEY']) ? trim((string)$cfg['API_KEY']) : '';
$RATE_LIMIT = isset($cfg['RATE_LIMIT_PER_MIN']) ? (int)$cfg['RATE_LIMIT_PER_MIN'] : 60;
$META_SOURCE = isset($cfg['SOURCE']) ? (string)$cfg['SOURCE'] : 'medtravel';

if ($API_KEY === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'data' => null,
        'meta' => ['source' => $META_SOURCE, 'ts' => gmdate('c')],
        'error' => ['code' => 'API_KEY_NOT_SET', 'message' => 'Set API_KEY in config/conectarbot_api.php'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('METHOD_NOT_ALLOWED', 'Only GET is allowed', 405, $META_SOURCE);
}

// --- Helpers -------------------------------------------------------------
function respond(bool $ok, $data, $error, int $status, string $source): void {
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'data' => $data,
        'meta' => ['source' => $source, 'ts' => gmdate('c')],
        'error' => $error,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond_error(string $code, string $message, int $status, string $source): void {
    respond(false, null, ['code' => $code, 'message' => $message], $status, $source);
}

function require_api_key(string $expectedKey, string $source): void {
    $provided = $_SERVER['HTTP_X_CONECTARBOT_KEY'] ?? '';
    if ($provided === '' || !hash_equals($expectedKey, $provided)) {
        respond_error('UNAUTHORIZED', 'Invalid or missing API key', 401, $source);
    }
}

function check_rate_limit(?int $limit, string $source): void {
    if ($limit === null || $limit <= 0) {
        return; // disabled
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha1', $ip);
    $file = sys_get_temp_dir() . '/cbot_rl_' . $key . '.json';
    $now = time();
    $window = 60;

    $data = ['count' => 0, 'start' => $now];
    if (is_file($file)) {
        $json = file_get_contents($file);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
                $data = $decoded;
            }
        }
    }

    if (($now - (int)$data['start']) >= $window) {
        $data = ['count' => 0, 'start' => $now];
    }

    if ((int)$data['count'] >= $limit) {
        respond_error('RATE_LIMITED', 'Too many requests, wait and try again', 429, $source);
    }

    $data['count'] = (int)$data['count'] + 1;
    file_put_contents($file, json_encode($data));
}

function detect_path(): string {
    $path = $_SERVER['PATH_INFO'] ?? '';
    if ($path === '') {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $scriptDir = rtrim(str_replace('/index.php', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if (str_starts_with($uriPath, $scriptDir)) {
            $path = substr($uriPath, strlen($scriptDir));
        } else {
            $path = $uriPath;
        }
    }
    return trim($path, '/');
}

function sanitize_slug(string $slug): string {
    $slug = strtolower($slug);
    if (!preg_match('/^[a-z0-9-]{1,200}$/', $slug)) {
        return '';
    }
    return $slug;
}

// --- Routing -------------------------------------------------------------
$path = detect_path();
require_api_key($API_KEY, $META_SOURCE);
check_rate_limit($RATE_LIMIT, $META_SOURCE);

switch (true) {
    case ($path === '' || $path === 'health'):
        respond(true, ['status' => 'ok'], null, 200, $META_SOURCE);
        break;

    case ($path === 'catalog/services'):
        list_services($conexion, $META_SOURCE);
        break;

    case preg_match('#^catalog/service/([a-z0-9-]+)$#', $path, $m):
        $slug = sanitize_slug($m[1]);
        if ($slug === '') {
            respond_error('INVALID_SLUG', 'Slug must match [a-z0-9-]', 400, $META_SOURCE);
        }
        service_detail($conexion, $slug, $META_SOURCE);
        break;

    default:
        respond_error('NOT_FOUND', 'Endpoint not found', 404, $META_SOURCE);
}

// --- Handlers ------------------------------------------------------------
function list_services(mysqli $db, string $source): void {
    $sql = "SELECT sc.id, sc.name, sc.slug, COALESCE(sc.short_description, '') AS description, sc.is_active,
                   MIN(CASE WHEN o.currency = 'USD' THEN o.price_from END) AS price_from_usd
            FROM service_catalog sc
            INNER JOIN provider_service_offers o ON o.service_id = sc.id AND o.is_active = 1
            WHERE sc.is_active = 1
            GROUP BY sc.id, sc.name, sc.slug, sc.short_description, sc.is_active
            ORDER BY sc.name ASC";

    $res = mysqli_query($db, $sql);
    if (!$res) {
        error_log('conectarbot list_services error: ' . mysqli_error($db));
        respond_error('SERVER_ERROR', 'Unexpected error', 500, $source);
    }

    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
            'description' => $r['description'],
            'active' => $r['is_active'] == 1,
            'price_from_usd' => isset($r['price_from_usd']) && $r['price_from_usd'] !== null ? (float)$r['price_from_usd'] : null,
        ];
    }
    mysqli_free_result($res);

    respond(true, $rows, null, 200, $source);
}

function service_detail(mysqli $db, string $slug, string $source): void {
    $sql = "SELECT sc.id, sc.name, sc.slug, COALESCE(sc.short_description, '') AS description, sc.is_active,
                   MIN(CASE WHEN o.currency = 'USD' THEN o.price_from END) AS price_from_usd
            FROM service_catalog sc
            INNER JOIN provider_service_offers o ON o.service_id = sc.id AND o.is_active = 1
            WHERE sc.slug = ? AND sc.is_active = 1
            GROUP BY sc.id, sc.name, sc.slug, sc.short_description, sc.is_active
            LIMIT 1";

    if (!$stmt = mysqli_prepare($db, $sql)) {
        error_log('conectarbot service_detail prepare error: ' . mysqli_error($db));
        respond_error('SERVER_ERROR', 'Unexpected error', 500, $source);
    }

    mysqli_stmt_bind_param($stmt, 's', $slug);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('conectarbot service_detail execute error: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        respond_error('SERVER_ERROR', 'Unexpected error', 500, $source);
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        respond_error('NOT_FOUND', 'Service not found', 404, $source);
    }

    $data = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'description' => $row['description'],
        'active' => $row['is_active'] == 1,
        'price_from_usd' => isset($row['price_from_usd']) && $row['price_from_usd'] !== null ? (float)$row['price_from_usd'] : null,
    ];

    respond(true, $data, null, 200, $source);
}

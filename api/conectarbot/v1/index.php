<?php
declare(strict_types=1);
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../admin/include/conexion.php';
require_once __DIR__ . '/../../../inc/commission_gate.php';

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

function cbot_table_has_column(mysqli $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cache[$key] = $res && mysqli_fetch_row($res) ? true : false;
    mysqli_stmt_close($stmt);
    return $cache[$key];
}

function cbot_table_exists(mysqli $db, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1";
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        $cache[$table] = false;
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cache[$table] = $res && mysqli_fetch_row($res) ? true : false;
    mysqli_stmt_close($stmt);
    return $cache[$table];
}

function cbot_nullable_string($value): ?string {
    $value = trim((string)$value);
    return $value !== '' ? $value : null;
}

function cbot_nullable_float($value): ?float {
    return $value !== null && $value !== '' ? (float)$value : null;
}

function cbot_redact_contact_info(?string $text): ?string {
    if ($text === null || $text === '') {
        return $text;
    }
    if (function_exists('commission_gate_redact_sensitive')) {
        return commission_gate_redact_sensitive($text);
    }
    return $text;
}

function cbot_public_text($value): ?string {
    $value = preg_replace('/<\s*\/?(p|div|br|li|ul|ol|h[1-6])[^>]*>/i', ' ', (string)$value);
    $value = trim(strip_tags((string)$value));
    if ($value === '') {
        return null;
    }
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    return cbot_nullable_string($value);
}

function cbot_locations_from_catalog(array $providers, array $staff): array {
    $locations = [];
    $seen = [];

    foreach ($providers as $provider) {
        $city = cbot_nullable_string($provider['location']['city'] ?? null);
        if ($city === null) {
            continue;
        }
        $key = 'provider:' . (int)$provider['id'] . ':' . strtolower($city);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $locations[] = [
            'provider_id' => (int)$provider['id'],
            'staff_id' => null,
            'name' => $provider['name'],
            'clinic_name' => null,
            'city' => $city,
        ];
    }

    foreach ($staff as $person) {
        $clinicName = cbot_nullable_string($person['clinic_name'] ?? null);
        $city = cbot_nullable_string($person['location']['city'] ?? null);
        if ($clinicName === null) {
            continue;
        }
        $key = 'staff:' . (int)$person['id'] . ':' . strtolower((string)$clinicName) . ':' . strtolower((string)$city);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $locations[] = [
            'provider_id' => (int)$person['provider_id'],
            'staff_id' => (int)$person['id'],
            'name' => $person['provider_name'],
            'clinic_name' => $clinicName,
            'city' => $city,
        ];
    }

    return $locations;
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

    case preg_match('#^catalog/offer/(\d+)$#', $path, $m):
        $offerId = (int)$m[1];
        if ($offerId <= 0) {
            respond_error('NOT_FOUND', 'Offer not found', 404, $META_SOURCE);
        }
        offer_detail($conexion, $offerId, $META_SOURCE);
        break;

    default:
        respond_error('NOT_FOUND', 'Endpoint not found', 404, $META_SOURCE);
}

// --- Handlers ------------------------------------------------------------
function list_services(mysqli $db, string $source): void {
    $offerDeletedCondition = cbot_table_has_column($db, 'provider_service_offers', 'is_deleted')
        ? ' AND o.is_deleted = 0'
        : '';
    $sql = "SELECT sc.id, sc.name, sc.slug, COALESCE(sc.short_description, '') AS description, sc.is_active,
                   MIN(CASE WHEN o.currency = 'USD' THEN o.price_from END) AS price_from_usd
            FROM service_catalog sc
            INNER JOIN provider_service_offers o ON o.service_id = sc.id AND o.is_active = 1{$offerDeletedCondition}
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

function cbot_fetch_service_providers(mysqli $db, int $serviceId): array {
    if (
        !cbot_table_exists($db, 'providers') ||
        !cbot_table_exists($db, 'provider_catalog_services') ||
        !cbot_table_exists($db, 'provider_service_offers')
    ) {
        return [];
    }

    $providerDeletedWhere = cbot_table_has_column($db, 'providers', 'is_deleted')
        ? ' AND p.is_deleted = 0'
        : '';
    $offerDeletedCondition = cbot_table_has_column($db, 'provider_service_offers', 'is_deleted')
        ? ' AND o.is_deleted = 0'
        : '';
    $providerStatusWhere = cbot_table_has_column($db, 'providers', 'is_active')
        ? ' AND p.is_active = 1'
        : '';
    $pcsStatusJoin = cbot_table_has_column($db, 'provider_catalog_services', 'is_active')
        ? ' AND pcs.is_active = 1'
        : '';

    $providerDescription = cbot_table_has_column($db, 'providers', 'description') ? 'p.description' : "'' AS description";
    $providerCity = cbot_table_has_column($db, 'providers', 'city') ? 'p.city' : "'' AS city";
    $providerType = cbot_table_has_column($db, 'providers', 'type') ? 'p.type' : "'' AS type";
    $providerSlug = cbot_table_has_column($db, 'providers', 'slug') ? 'p.slug' : "'' AS slug";
    $providerVerified = cbot_table_has_column($db, 'providers', 'is_verified') ? 'p.is_verified' : '0 AS is_verified';
    $pcsNivel = cbot_table_has_column($db, 'provider_catalog_services', 'nivel_atencion') ? 'pcs.nivel_atencion' : 'NULL AS nivel_atencion';
    $pcsTipo = cbot_table_has_column($db, 'provider_catalog_services', 'tipo_asistencial') ? 'pcs.tipo_asistencial' : 'NULL AS tipo_asistencial';

    $sql = "SELECT p.id, p.name, {$providerSlug}, {$providerType}, {$providerDescription}, {$providerCity}, {$providerVerified},
                   pcs.id AS provider_catalog_service_id, {$pcsNivel}, {$pcsTipo},
                   MIN(CASE WHEN o.currency = 'USD' THEN o.price_from END) AS price_from_usd
            FROM providers p
            LEFT JOIN provider_catalog_services pcs
              ON pcs.provider_id = p.id
             AND pcs.service_id = ?{$pcsStatusJoin}
            LEFT JOIN provider_service_offers o
              ON o.provider_id = p.id
             AND o.service_id = ?
             AND o.is_active = 1{$offerDeletedCondition}
            WHERE (pcs.id IS NOT NULL OR o.id IS NOT NULL){$providerStatusWhere}{$providerDeletedWhere}
            GROUP BY p.id, p.name, p.slug, p.type, p.description, p.city, p.is_verified,
                     pcs.id, pcs.nivel_atencion, pcs.tipo_asistencial
            ORDER BY p.name ASC";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('conectarbot providers prepare error: ' . mysqli_error($db));
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $serviceId, $serviceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $providers = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $providerId = (int)$row['id'];
        $providers[$providerId] = [
            'id' => $providerId,
            'name' => $row['name'],
            'slug' => cbot_nullable_string($row['slug'] ?? null),
            'type' => cbot_nullable_string($row['type'] ?? null),
            'description' => cbot_public_text($row['description'] ?? null),
            'verified' => (int)($row['is_verified'] ?? 0) === 1,
            'location' => [
                'city' => cbot_nullable_string($row['city'] ?? null),
            ],
            'provider_catalog_service_id' => isset($row['provider_catalog_service_id']) ? (int)$row['provider_catalog_service_id'] : null,
            'service_metadata' => [
                'nivel_atencion' => cbot_nullable_string($row['nivel_atencion'] ?? null),
                'tipo_asistencial' => cbot_nullable_string($row['tipo_asistencial'] ?? null),
            ],
            'price_from_usd' => cbot_nullable_float($row['price_from_usd'] ?? null),
        ];
    }
    mysqli_stmt_close($stmt);

    return array_values($providers);
}

function cbot_fetch_service_offers(mysqli $db, int $serviceId): array {
    if (!cbot_table_exists($db, 'provider_service_offers') || !cbot_table_exists($db, 'providers')) {
        return [];
    }

    $offerDeletedCondition = cbot_table_has_column($db, 'provider_service_offers', 'is_deleted')
        ? ' AND o.is_deleted = 0'
        : '';
    $providerDeletedWhere = cbot_table_has_column($db, 'providers', 'is_deleted')
        ? ' AND p.is_deleted = 0'
        : '';
    $providerStatusWhere = cbot_table_has_column($db, 'providers', 'is_active')
        ? ' AND p.is_active = 1'
        : '';
    $offerPcs = cbot_table_has_column($db, 'provider_service_offers', 'provider_catalog_service_id')
        ? 'o.provider_catalog_service_id'
        : 'NULL AS provider_catalog_service_id';

    $sql = "SELECT o.id, o.provider_id, {$offerPcs}, o.title, o.description, o.price_from, o.currency
            FROM provider_service_offers o
            INNER JOIN providers p ON p.id = o.provider_id
            WHERE o.service_id = ?
              AND o.is_active = 1{$offerDeletedCondition}{$providerStatusWhere}{$providerDeletedWhere}
            ORDER BY o.price_from IS NULL ASC, o.price_from ASC, o.id ASC";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('conectarbot offers prepare error: ' . mysqli_error($db));
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $offers = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $offers[] = [
            'id' => (int)$row['id'],
            'provider_id' => (int)$row['provider_id'],
            'provider_catalog_service_id' => isset($row['provider_catalog_service_id']) ? (int)$row['provider_catalog_service_id'] : null,
            'title' => cbot_nullable_string($row['title'] ?? null),
            'description' => cbot_public_text($row['description'] ?? null),
            'price_from' => cbot_nullable_float($row['price_from'] ?? null),
            'currency' => cbot_nullable_string($row['currency'] ?? null),
        ];
    }
    mysqli_stmt_close($stmt);

    return $offers;
}

function cbot_fetch_service_staff(mysqli $db, int $serviceId, ?int $providerId = null, ?int $pcsId = null): array {
    if (
        !cbot_table_exists($db, 'provider_medical_staff') ||
        !cbot_table_exists($db, 'provider_medical_staff_services') ||
        !cbot_table_exists($db, 'providers')
    ) {
        return [];
    }

    $relActiveWhere = cbot_table_has_column($db, 'provider_medical_staff_services', 'active')
        ? ' AND rel.active = 1'
        : '';
    $staffStatusWhere = cbot_table_has_column($db, 'provider_medical_staff', 'is_active')
        ? ' AND pms.is_active = 1'
        : (cbot_table_has_column($db, 'provider_medical_staff', 'active') ? ' AND pms.active = 1' : '');
    $publicStaffWhere = cbot_table_has_column($db, 'provider_medical_staff', 'allow_home_publication')
        ? ' AND pms.allow_home_publication = 1'
        : '';
    $providerDeletedWhere = cbot_table_has_column($db, 'providers', 'is_deleted')
        ? ' AND p.is_deleted = 0'
        : '';
    $providerStatusWhere = cbot_table_has_column($db, 'providers', 'is_active')
        ? ' AND p.is_active = 1'
        : '';

    $hasPcsColumn = cbot_table_has_column($db, 'provider_medical_staff_services', 'provider_catalog_service_id');
    $relPcs = $hasPcsColumn ? 'rel.provider_catalog_service_id' : 'NULL AS provider_catalog_service_id';
    $roleTitle = cbot_table_has_column($db, 'provider_medical_staff', 'role_title') ? 'pms.role_title' : "'' AS role_title";
    $specialty = cbot_table_has_column($db, 'provider_medical_staff', 'specialty') ? 'pms.specialty' : "'' AS specialty";
    $bioShort = cbot_table_has_column($db, 'provider_medical_staff', 'bio_short') ? 'pms.bio_short' : "'' AS bio_short";
    $license = cbot_table_has_column($db, 'provider_medical_staff', 'professional_license') ? 'pms.professional_license' : "'' AS professional_license";
    $clinicName = cbot_table_has_column($db, 'provider_medical_staff', 'clinic_name') ? 'pms.clinic_name' : "'' AS clinic_name";
    $providerCity = cbot_table_has_column($db, 'providers', 'city') ? 'p.city AS provider_city' : "'' AS provider_city";
    $staffSort = cbot_table_has_column($db, 'provider_medical_staff', 'sort_order') ? 'pms.sort_order' : 'pms.id';

    // pcsId is only enforced at the SQL level when the schema actually models
    // per-PCS staff relations; otherwise the relation can't express that
    // granularity and we fall back to provider+service (no invented match).
    $providerFilterWhere = $providerId !== null ? ' AND pms.provider_id = ?' : '';
    $pcsFilterWhere = ($pcsId !== null && $hasPcsColumn) ? ' AND rel.provider_catalog_service_id = ?' : '';

    $sql = "SELECT pms.id, pms.provider_id, pms.full_name, {$roleTitle}, {$specialty}, {$bioShort},
                   {$license}, {$clinicName}, {$relPcs},
                   p.name AS provider_name, {$providerCity}
            FROM provider_medical_staff_services rel
            INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id
            INNER JOIN providers p ON p.id = pms.provider_id
            WHERE rel.service_id = ?{$providerFilterWhere}{$pcsFilterWhere}{$relActiveWhere}{$staffStatusWhere}{$publicStaffWhere}{$providerStatusWhere}{$providerDeletedWhere}
            ORDER BY p.name ASC, {$staffSort} ASC, pms.full_name ASC";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('conectarbot staff prepare error: ' . mysqli_error($db));
        return [];
    }

    $types = 'i';
    $params = [$serviceId];
    if ($providerFilterWhere !== '') {
        $types .= 'i';
        $params[] = $providerId;
    }
    if ($pcsFilterWhere !== '') {
        $types .= 'i';
        $params[] = $pcsId;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $staff = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $staffId = (int)$row['id'];
        if (isset($staff[$staffId])) {
            continue;
        }
        $staff[$staffId] = [
            'id' => $staffId,
            'provider_id' => (int)$row['provider_id'],
            'provider_name' => $row['provider_name'],
            'full_name' => $row['full_name'],
            'role_title' => cbot_nullable_string($row['role_title'] ?? null),
            'specialty' => cbot_nullable_string($row['specialty'] ?? null),
            'professional_license' => cbot_nullable_string($row['professional_license'] ?? null),
            'description' => cbot_public_text($row['bio_short'] ?? null),
            'clinic_name' => cbot_nullable_string($row['clinic_name'] ?? null),
            'location' => [
                'city' => cbot_nullable_string($row['provider_city'] ?? null),
            ],
            'provider_catalog_service_id' => isset($row['provider_catalog_service_id']) ? (int)$row['provider_catalog_service_id'] : null,
        ];
    }
    mysqli_stmt_close($stmt);

    return array_values($staff);
}

function service_detail(mysqli $db, string $slug, string $source): void {
    $offerDeletedCondition = cbot_table_has_column($db, 'provider_service_offers', 'is_deleted')
        ? ' AND o.is_deleted = 0'
        : '';
    $offerProviderStatusJoin = cbot_table_has_column($db, 'providers', 'is_active')
        ? ' AND op.is_active = 1'
        : '';
    $offerProviderDeletedJoin = cbot_table_has_column($db, 'providers', 'is_deleted')
        ? ' AND op.is_deleted = 0'
        : '';
    $fullDescriptionSelect = cbot_table_has_column($db, 'service_catalog', 'description')
        ? "COALESCE(NULLIF(sc.short_description, ''), NULLIF(sc.description, ''), '') AS description"
        : "COALESCE(sc.short_description, '') AS description";
    $fullDescriptionGroup = cbot_table_has_column($db, 'service_catalog', 'description') ? ', sc.description' : '';
    $sql = "SELECT sc.id, sc.name, sc.slug, {$fullDescriptionSelect}, sc.is_active,
		                   MIN(CASE WHEN o.currency = 'USD' AND op.id IS NOT NULL THEN o.price_from END) AS price_from_usd
            FROM service_catalog sc
            LEFT JOIN provider_service_offers o ON o.service_id = sc.id AND o.is_active = 1{$offerDeletedCondition}
            LEFT JOIN providers op ON op.id = o.provider_id{$offerProviderStatusJoin}{$offerProviderDeletedJoin}
            WHERE sc.slug = ? AND sc.is_active = 1
            GROUP BY sc.id, sc.name, sc.slug, sc.short_description{$fullDescriptionGroup}, sc.is_active
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

    $serviceId = (int)$row['id'];
    $providers = cbot_fetch_service_providers($db, $serviceId);
    $offers = cbot_fetch_service_offers($db, $serviceId);
    $staff = cbot_fetch_service_staff($db, $serviceId);

    $data = [
        'id' => $serviceId,
        'name' => $row['name'],
        'slug' => $row['slug'],
        'description' => cbot_public_text($row['description'] ?? null) ?? '',
        'active' => $row['is_active'] == 1,
        'price_from_usd' => isset($row['price_from_usd']) && $row['price_from_usd'] !== null ? (float)$row['price_from_usd'] : null,
        'providers' => $providers,
        'staff' => $staff,
        'locations' => cbot_locations_from_catalog($providers, $staff),
        'offers' => $offers,
    ];

    respond(true, $data, null, 200, $source);
}

function cbot_fetch_offer_core(mysqli $db, int $offerId): ?array {
    if (
        !cbot_table_exists($db, 'provider_service_offers') ||
        !cbot_table_exists($db, 'service_catalog') ||
        !cbot_table_exists($db, 'providers')
    ) {
        return null;
    }

    $offerDeletedWhere = cbot_table_has_column($db, 'provider_service_offers', 'is_deleted')
        ? ' AND o.is_deleted = 0'
        : '';
    $offerPcsSelect = cbot_table_has_column($db, 'provider_service_offers', 'provider_catalog_service_id')
        ? 'o.provider_catalog_service_id'
        : 'NULL AS provider_catalog_service_id';

    $serviceDeletedWhere = cbot_table_has_column($db, 'service_catalog', 'is_deleted')
        ? ' AND sc.is_deleted = 0'
        : '';
    $serviceDescriptionSelect = cbot_table_has_column($db, 'service_catalog', 'description')
        ? "COALESCE(NULLIF(sc.short_description, ''), NULLIF(sc.description, ''), '') AS service_description"
        : "COALESCE(sc.short_description, '') AS service_description";

    $providerDeletedWhere = cbot_table_has_column($db, 'providers', 'is_deleted')
        ? ' AND p.is_deleted = 0'
        : '';
    $providerStatusWhere = cbot_table_has_column($db, 'providers', 'is_active')
        ? ' AND p.is_active = 1'
        : '';
    $providerSlug = cbot_table_has_column($db, 'providers', 'slug') ? 'p.slug' : "'' AS slug";
    $providerType = cbot_table_has_column($db, 'providers', 'type') ? 'p.type' : "'' AS type";
    $providerCity = cbot_table_has_column($db, 'providers', 'city') ? 'p.city' : "'' AS city";
    $providerDescription = cbot_table_has_column($db, 'providers', 'description')
        ? 'p.description AS provider_description'
        : "'' AS provider_description";

    // provider.verified is derived solely from provider_verification.status
    // (see cbot_fetch_provider_verification / offer_detail) so it can never
    // contradict provider.verification.*; providers.is_verified is not read here.
    $sql = "SELECT o.id AS offer_id, o.provider_id, o.service_id, {$offerPcsSelect},
                   o.title, o.description AS offer_description, o.price_from, o.currency,
                   sc.name AS service_name, sc.slug AS service_slug, {$serviceDescriptionSelect}, sc.is_active AS service_active,
                   p.name AS provider_name, {$providerSlug}, {$providerType}, {$providerCity}, {$providerDescription}
            FROM provider_service_offers o
            INNER JOIN service_catalog sc ON sc.id = o.service_id AND sc.is_active = 1{$serviceDeletedWhere}
            INNER JOIN providers p ON p.id = o.provider_id{$providerStatusWhere}{$providerDeletedWhere}
            WHERE o.id = ? AND o.is_active = 1{$offerDeletedWhere}
            LIMIT 1";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('conectarbot offer_detail prepare error: ' . mysqli_error($db));
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $offerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function cbot_offer_pcs_valid(mysqli $db, ?int $pcsId, int $providerId, int $serviceId): bool {
    if ($pcsId === null) {
        return true;
    }
    if (!cbot_table_exists($db, 'provider_catalog_services')) {
        return true;
    }

    $activeWhere = cbot_table_has_column($db, 'provider_catalog_services', 'is_active')
        ? ' AND is_active = 1'
        : '';
    $sql = "SELECT 1 FROM provider_catalog_services WHERE id = ? AND provider_id = ? AND service_id = ?{$activeWhere} LIMIT 1";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('conectarbot offer_pcs_valid prepare error: ' . mysqli_error($db));
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $pcsId, $providerId, $serviceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ok = $res && mysqli_fetch_row($res) ? true : false;
    mysqli_stmt_close($stmt);

    return $ok;
}

function cbot_verification_label(?string $status, ?string $level): ?string {
    if ($status === null) {
        return null;
    }
    switch ($status) {
        case 'verified':
            if ($level === 'premium') {
                return 'Verified Premium';
            }
            if ($level === 'standard') {
                return 'Verified Standard';
            }
            return 'Verified';
        case 'pending':
            return 'Pending Verification';
        case 'in_review':
            return 'Under Review';
        case 'rejected':
            return 'Not Verified';
        case 'suspended':
            return 'Verification Suspended';
        default:
            return 'Unverified';
    }
}

function cbot_fetch_provider_verification(mysqli $db, int $providerId): array {
    $result = ['status' => null, 'level' => null, 'label' => null, 'license_item_checked' => false];

    if (cbot_table_exists($db, 'provider_verification')) {
        $sql = "SELECT status, verification_level FROM provider_verification WHERE provider_id = ? LIMIT 1";
        $stmt = mysqli_prepare($db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $providerId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row) {
                $result['status'] = cbot_nullable_string($row['status'] ?? null);
                $result['level'] = cbot_nullable_string($row['verification_level'] ?? null);
            }
        }
    }
    $result['label'] = cbot_verification_label($result['status'], $result['level']);

    // Real-validation signal for professional_license_verified: an admin actually
    // checked the provider's "medical_license" verification item. Never inferred
    // from the mere presence of a license number on the staff row.
    if (cbot_table_exists($db, 'provider_verification_items') && cbot_table_has_column($db, 'provider_verification_items', 'item_key')) {
        $sql = "SELECT 1 FROM provider_verification_items
                WHERE provider_id = ? AND item_key = 'medical_license' AND is_checked = 1 LIMIT 1";
        $stmt = mysqli_prepare($db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $providerId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $result['license_item_checked'] = $res && mysqli_fetch_row($res) ? true : false;
            mysqli_stmt_close($stmt);
        }
    }

    return $result;
}

function cbot_fetch_staff_services(mysqli $db, int $providerId, int $staffId): array {
    if (
        !cbot_table_exists($db, 'provider_medical_staff_services') ||
        !cbot_table_exists($db, 'service_catalog') ||
        !cbot_table_exists($db, 'provider_service_offers')
    ) {
        return [];
    }

    $relActiveWhere = cbot_table_has_column($db, 'provider_medical_staff_services', 'active')
        ? ' AND rel.active = 1'
        : '';
    $serviceDeletedWhere = cbot_table_has_column($db, 'service_catalog', 'is_deleted')
        ? ' AND sc.is_deleted = 0'
        : '';
    $offerDeletedWhere = cbot_table_has_column($db, 'provider_service_offers', 'is_deleted')
        ? ' AND o.is_deleted = 0'
        : '';
    $hasRelPcs = cbot_table_has_column($db, 'provider_medical_staff_services', 'provider_catalog_service_id');
    $hasOfferPcs = cbot_table_has_column($db, 'provider_service_offers', 'provider_catalog_service_id');
    $pcsExactApplicable = $hasRelPcs && $hasOfferPcs;
    $relPcsSelect = $hasRelPcs ? 'rel.provider_catalog_service_id' : 'NULL';
    $offerPcsSelect = $hasOfferPcs ? 'o.provider_catalog_service_id' : 'NULL';

    // When the schema models PCS on both sides, require an exact, non-NULL
    // match — NULL is never treated as a wildcard here (SQL "=" already
    // excludes NULL vs NULL/non-NULL, so this alone enforces that). When PCS
    // isn't modeled on both sides, PCS matching doesn't apply: fall back to
    // provider+service (no invented association).
    $pcsCoherenceWhere = $pcsExactApplicable
        ? ' AND rel.provider_catalog_service_id = o.provider_catalog_service_id'
        : '';

    // Additionally require the matched provider_catalog_services row itself
    // to be active, when that column exists.
    $pcsActiveJoin = '';
    $pcsActiveWhere = '';
    if ($pcsExactApplicable && cbot_table_exists($db, 'provider_catalog_services')) {
        $pcsActiveJoin = ' INNER JOIN provider_catalog_services pcs ON pcs.id = o.provider_catalog_service_id';
        $pcsActiveWhere = cbot_table_has_column($db, 'provider_catalog_services', 'is_active')
            ? ' AND pcs.is_active = 1'
            : '';
    }

    $sql = "SELECT sc.id, sc.slug, sc.name, o.id AS offer_id, o.title AS offer_title,
                   COALESCE({$relPcsSelect}, {$offerPcsSelect}) AS provider_catalog_service_id
            FROM provider_medical_staff_services rel
            INNER JOIN service_catalog sc ON sc.id = rel.service_id AND sc.is_active = 1{$serviceDeletedWhere}
            INNER JOIN provider_service_offers o
              ON o.provider_id = ?
             AND o.service_id = rel.service_id
             AND o.is_active = 1{$offerDeletedWhere}{$pcsCoherenceWhere}
            {$pcsActiveJoin}
            WHERE rel.provider_medical_staff_id = ?{$relActiveWhere}{$pcsActiveWhere}
            ORDER BY sc.name ASC, o.price_from IS NULL ASC, o.price_from ASC, o.id ASC";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('conectarbot staff services prepare error: ' . mysqli_error($db));
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $providerId, $staffId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $services = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $serviceId = (int)$row['id'];
        if (isset($services[$serviceId])) {
            continue;
        }
        $services[$serviceId] = [
            'id' => $serviceId,
            'slug' => $row['slug'],
            'name' => $row['name'],
            'offer_id' => (int)$row['offer_id'],
            'title' => cbot_nullable_string($row['offer_title'] ?? null),
            'provider_catalog_service_id' => isset($row['provider_catalog_service_id']) && $row['provider_catalog_service_id'] !== null
                ? (int)$row['provider_catalog_service_id']
                : null,
        ];
    }
    mysqli_stmt_close($stmt);

    return array_values($services);
}

function offer_detail(mysqli $db, int $offerId, string $source): void {
    $offer = cbot_fetch_offer_core($db, $offerId);
    if (!$offer) {
        respond_error('NOT_FOUND', 'Offer not found', 404, $source);
    }

    $providerId = (int)$offer['provider_id'];
    $serviceId = (int)$offer['service_id'];
    $pcsId = isset($offer['provider_catalog_service_id']) && $offer['provider_catalog_service_id'] !== null
        ? (int)$offer['provider_catalog_service_id']
        : null;

    if (!cbot_offer_pcs_valid($db, $pcsId, $providerId, $serviceId)) {
        respond_error('NOT_FOUND', 'Offer not found', 404, $source);
    }

    $providerCity = cbot_nullable_string($offer['city'] ?? null);
    $verification = cbot_fetch_provider_verification($db, $providerId);
    // Single canonical source for both fields: provider_verification.status.
    // Keeps provider.verified and provider.verification.* impossible to contradict.
    $providerVerified = $verification['status'] === 'verified';

    $staff = cbot_fetch_service_staff($db, $serviceId, $providerId, $pcsId);
    foreach ($staff as &$person) {
        $person['description'] = cbot_redact_contact_info($person['description']);
        // declared bio, not a verified credential — see professional_license_verified below
        $person['public_experience'] = $person['description'];
        $person['professional_license_verified'] = $verification['license_item_checked']
            && $person['professional_license'] !== null;
        // No canonical signal authorizes publishing the raw license number;
        // keep only the verified indicator (see docs: PUBLIC_AI policy).
        unset($person['professional_license']);
        $person['services'] = cbot_fetch_staff_services($db, $providerId, $person['id']);
    }
    unset($person);

    $providersForLocations = [[
        'id' => $providerId,
        'name' => $offer['provider_name'],
        'location' => ['city' => $providerCity],
    ]];

    $data = [
        'offer_id' => (int)$offer['offer_id'],
        'active' => true,
        'title' => cbot_nullable_string($offer['title'] ?? null) ?? '',
        'description' => cbot_redact_contact_info(cbot_public_text($offer['offer_description'] ?? null)) ?? '',
        'price' => [
            'from' => cbot_nullable_float($offer['price_from'] ?? null),
            'currency' => cbot_nullable_string($offer['currency'] ?? null),
        ],
        'service' => [
            'id' => $serviceId,
            'slug' => $offer['service_slug'],
            'name' => $offer['service_name'],
            'description' => cbot_redact_contact_info(cbot_public_text($offer['service_description'] ?? null)) ?? '',
            'active' => $offer['service_active'] == 1,
        ],
        'provider' => [
            'id' => $providerId,
            'name' => $offer['provider_name'],
            'slug' => cbot_nullable_string($offer['slug'] ?? null),
            'type' => cbot_nullable_string($offer['type'] ?? null),
            'description' => cbot_redact_contact_info(cbot_public_text($offer['provider_description'] ?? null)) ?? '',
            'verified' => $providerVerified,
            'verification' => [
                'status' => $verification['status'],
                'level' => $verification['level'],
                'label' => $verification['label'],
            ],
            'location' => ['city' => $providerCity],
        ],
        'staff' => $staff,
        'locations' => cbot_locations_from_catalog($providersForLocations, $staff),
    ];

    respond(true, $data, null, 200, $source);
}

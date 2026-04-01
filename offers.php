<?php
include_once(__DIR__ . '/admin/include/conexion.php');

if (!function_exists('provider_verification_level_label')) {
    function provider_verification_level_label($level)
    {
        $key = strtolower(trim((string)$level));
        $map = [
            'basic'    => 'Basic',
            'standard' => 'Standard',
            'premium'  => 'Premium',
        ];
        return $map[$key] ?? ucfirst($key);
    }
}

if (!function_exists('provider_verification_public_badge')) {
    function provider_verification_public_badge($status, $level)
    {
        $levelLabel = provider_verification_level_label($level);
        if ($levelLabel === '') {
            return ['', ''];
        }
        $statusKey = strtolower(trim((string)$status));
        if ($statusKey === 'verified') {
            return ['verified', 'Verified ' . $levelLabel];
        }
        if ($statusKey === 'in_review') {
            return ['review', 'In review ' . $levelLabel];
        }
        if ($statusKey === 'pending') {
            return ['level', 'Validation level ' . $levelLabel];
        }
        return ['', ''];
    }
}

// Header desde la base de datos
$busca_header = mysqli_query($conexion, "SELECT * FROM services_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
if (mysqli_num_rows($busca_header) > 0) {
    $rst_header = mysqli_fetch_array($busca_header);
    $offers_header_title = !empty($rst_header['title'])     ? $rst_header['title']     : 'All Medical Services';
    $page_subtitle_1     = !empty($rst_header['subtitle_1']) ? $rst_header['subtitle_1'] : 'MEDICAL SERVICES';
    $page_subtitle_2     = !empty($rst_header['subtitle_2']) ? $rst_header['subtitle_2'] : 'Browse all available medical services from certified providers in Colombia';
    $bg_image            = $rst_header['bg_image'];
} else {
    $offers_header_title = 'All Medical Services';
    $page_subtitle_1     = 'MEDICAL SERVICES';
    $page_subtitle_2     = 'Browse all available medical services from certified providers in Colombia';
    $bg_image            = '';
}

// Parámetros de filtro server-side (mantienen compatibilidad con campañas y SEO existentes)
$category_id           = isset($_GET['category'])    ? (int)$_GET['category']    : 0;
$provider_filter_id    = isset($_GET['provider_id']) ? (int)$_GET['provider_id'] : 0;

// service_id en URL → se pasa a JS para inicializar el filtro client-side de servicio
// No genera filtrado server-side: opera sobre las cards ya renderizadas
$preselected_service_filter = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

$category_name        = $offers_header_title;
$category_description = '';
$provider_filter_name = '';
$provider_filter_city = '';

if ($category_id > 0) {
    $cat_query = mysqli_prepare($conexion, "SELECT name, description FROM service_categories WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($cat_query, 'i', $category_id);
    mysqli_stmt_execute($cat_query);
    $cat_result = mysqli_stmt_get_result($cat_query);
    if ($cat_row = mysqli_fetch_assoc($cat_result)) {
        $category_name        = htmlspecialchars($cat_row['name']);
        $category_description = htmlspecialchars($cat_row['description']);
    }
    mysqli_stmt_close($cat_query);
}

if ($provider_filter_id > 0) {
    $provider_query = mysqli_prepare($conexion, "SELECT name, city FROM providers WHERE id = ? LIMIT 1");
    if ($provider_query) {
        mysqli_stmt_bind_param($provider_query, 'i', $provider_filter_id);
        mysqli_stmt_execute($provider_query);
        $provider_result = mysqli_stmt_get_result($provider_query);
        if ($provider_row = mysqli_fetch_assoc($provider_result)) {
            $provider_filter_name = trim((string)($provider_row['name'] ?? ''));
            $provider_filter_city = trim((string)($provider_row['city'] ?? ''));
        } else {
            $provider_filter_id = 0;
        }
        mysqli_stmt_close($provider_query);
    } else {
        $provider_filter_id = 0;
    }
}

// Verificar disponibilidad de tabla provider_verification
$hasProviderVerification = false;
$pvTableCheck = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_verification'");
if ($pvTableCheck && mysqli_num_rows($pvTableCheck) > 0) {
    $hasProviderVerification = true;
}

if ($hasProviderVerification) {
    $verificationSelect = "COALESCE(pv.status, '') AS verification_status, COALESCE(pv.verification_level, '') AS verification_level,";
    $verificationJoin   = "LEFT JOIN provider_verification pv ON pv.provider_id = p.id";
} else {
    $verificationSelect = "'' AS verification_status, '' AS verification_level,";
    $verificationJoin   = "";
}

// Consulta de ofertas activas — 4 ramas según combinación de filtros server-side
if ($category_id > 0 && $provider_filter_id > 0) {
    $offers_query = "
        SELECT
            o.id, o.title, o.description, o.price_from, o.currency,
            p.id AS provider_id, p.name AS provider_name, p.city, p.logo,
            {$verificationSelect}
            sc.id AS service_id, sc.name AS service_name
        FROM provider_service_offers o
        INNER JOIN providers p ON o.provider_id = p.id
        {$verificationJoin}
        INNER JOIN service_catalog sc ON o.service_id = sc.id
        WHERE sc.category_id = ? AND o.provider_id = ? AND o.is_active = 1
        ORDER BY o.id DESC
    ";
    $stmt = mysqli_prepare($conexion, $offers_query);
    mysqli_stmt_bind_param($stmt, 'ii', $category_id, $provider_filter_id);
} elseif ($category_id > 0) {
    $offers_query = "
        SELECT
            o.id, o.title, o.description, o.price_from, o.currency,
            p.id AS provider_id, p.name AS provider_name, p.city, p.logo,
            {$verificationSelect}
            sc.id AS service_id, sc.name AS service_name
        FROM provider_service_offers o
        INNER JOIN providers p ON o.provider_id = p.id
        {$verificationJoin}
        INNER JOIN service_catalog sc ON o.service_id = sc.id
        WHERE sc.category_id = ? AND o.is_active = 1
        ORDER BY o.id DESC
    ";
    $stmt = mysqli_prepare($conexion, $offers_query);
    mysqli_stmt_bind_param($stmt, 'i', $category_id);
} elseif ($provider_filter_id > 0) {
    $offers_query = "
        SELECT
            o.id, o.title, o.description, o.price_from, o.currency,
            p.id AS provider_id, p.name AS provider_name, p.city, p.logo,
            {$verificationSelect}
            sc.id AS service_id, sc.name AS service_name
        FROM provider_service_offers o
        INNER JOIN providers p ON o.provider_id = p.id
        {$verificationJoin}
        INNER JOIN service_catalog sc ON o.service_id = sc.id
        WHERE o.provider_id = ? AND o.is_active = 1
        ORDER BY o.id DESC
    ";
    $stmt = mysqli_prepare($conexion, $offers_query);
    mysqli_stmt_bind_param($stmt, 'i', $provider_filter_id);
} else {
    $offers_query = "
        SELECT
            o.id, o.title, o.description, o.price_from, o.currency,
            p.id AS provider_id, p.name AS provider_name, p.city, p.logo,
            {$verificationSelect}
            sc.id AS service_id, sc.name AS service_name
        FROM provider_service_offers o
        INNER JOIN providers p ON o.provider_id = p.id
        {$verificationJoin}
        INNER JOIN service_catalog sc ON o.service_id = sc.id
        WHERE o.is_active = 1
        ORDER BY o.id DESC
    ";
    $stmt = mysqli_prepare($conexion, $offers_query);
}

mysqli_stmt_execute($stmt);
$offers_result = mysqli_stmt_get_result($stmt);

$offers = [];
if ($offers_result) {
    while ($offer_row = mysqli_fetch_assoc($offers_result)) {
        $offers[] = $offer_row;
    }
}
mysqli_stmt_close($stmt);

// Construir datos para filtros client-side a partir del dataset cargado
$services_for_filter  = [];
$providers_for_filter = [];
$cities_for_filter    = [];
$price_values         = [];

foreach ($offers as $o) {
    $sid = (int)($o['service_id'] ?? 0);
    $sname = trim((string)($o['service_name'] ?? ''));
    if ($sid > 0 && $sname !== '' && !isset($services_for_filter[$sid])) {
        $services_for_filter[$sid] = $sname;
    }

    $pid = (int)($o['provider_id'] ?? 0);
    $pname = trim((string)($o['provider_name'] ?? ''));
    if ($pid > 0 && $pname !== '' && !isset($providers_for_filter[$pid])) {
        $providers_for_filter[$pid] = $pname;
    }

    $city = trim((string)($o['city'] ?? ''));
    if ($city !== '' && !in_array($city, $cities_for_filter, true)) {
        $cities_for_filter[] = $city;
    }

    if (!empty($o['price_from'])) {
        $price_values[] = (float)$o['price_from'];
    }
}

// Ordenar servicios y ciudades alfabéticamente
asort($services_for_filter);
sort($cities_for_filter);

$price_min_all = !empty($price_values) ? (int)floor(min($price_values)) : 0;
$price_max_all = !empty($price_values) ? (int)ceil(max($price_values))  : 9999;

// Mostrar filtro de proveedor solo si el dataset tiene más de un proveedor (server no filtró por uno)
$show_provider_filter = $provider_filter_id === 0 && count($providers_for_filter) > 1;
// Mostrar filtro de ciudad solo si hay más de una ciudad
$show_city_filter     = count($cities_for_filter) > 1;

// SEO
$seo_title_source = $category_id > 0 ? $category_name : $offers_header_title;
if ($provider_filter_id > 0 && $provider_filter_name !== '') {
    $seo_title_source = $category_id > 0
        ? $category_name . ' - ' . $provider_filter_name
        : $provider_filter_name . ' Medical Services';
}
$seo_base_title = trim(html_entity_decode((string)$seo_title_source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
if ($seo_base_title === '') {
    $seo_base_title = 'Medical Services';
}
$page_title            = $seo_base_title . ' | MedTravel';
$seo_offers_description = trim(html_entity_decode((string)$category_description, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
if ($seo_offers_description === '') {
    $seo_offers_description = trim((string)$page_subtitle_2);
}
if ($provider_filter_id > 0 && $provider_filter_name !== '') {
    $seo_offers_description = $category_id > 0
        ? 'Browse ' . $category_name . ' services offered by ' . $provider_filter_name . ' through MedTravel.'
        : 'Browse medical services offered by ' . $provider_filter_name . ' through MedTravel.';
}
$page_description = $seo_offers_description !== ''
    ? $seo_offers_description
    : 'Browse medical service offers in Colombia from certified providers coordinated by MedTravel.';
$page_canonical = 'https://medtravel.com.co/offers.php';
if ($provider_filter_id > 0 || $category_id > 0) {
    $canonical_params = [];
    if ($provider_filter_id > 0) {
        $canonical_params['provider_id'] = $provider_filter_id;
    }
    if ($category_id > 0) {
        $canonical_params['category'] = $category_id;
    }
    $page_canonical .= '?' . http_build_query($canonical_params);
}

$offers_schema_items = [];
$offers_position     = 1;
foreach ($offers as $offer_item) {
    $offer_name = trim((string)($offer_item['title'] ?? ''));
    $offer_id   = (int)($offer_item['id'] ?? 0);
    if ($offer_name === '' || $offer_id <= 0) {
        continue;
    }
    $offer_object = [
        '@type'    => 'Service',
        'name'     => $offer_name,
        'url'      => 'https://medtravel.com.co/offer_detail.php?id=' . $offer_id,
        'provider' => ['@type' => 'Organization', 'name' => 'MedTravel'],
    ];
    $offer_description = trim(strip_tags((string)($offer_item['description'] ?? '')));
    if ($offer_description !== '') {
        $offer_object['description'] = $offer_description;
    }
    $offers_schema_items[] = [
        '@type'    => 'ListItem',
        'position' => $offers_position++,
        'item'     => $offer_object,
    ];
}
if (!empty($offers_schema_items)) {
    $page_schema_jsonld = [[
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => $seo_base_title,
        'itemListElement' => $offers_schema_items,
    ]];
}

include('inc/include.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        /* ── Cards ─────────────────────────────────────────── */
        .offer-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: white;
        }
        .offer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: #d1d5db;
        }
        .offer-card .card-img-top {
            height: 220px;
            object-fit: cover;
        }
        .provider-logo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: absolute;
            top: 180px;
            left: 20px;
            background: white;
        }
        .price-info {
            background: #f8fafc;
            padding: 12px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .price-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .price-amount {
            color: #0f766e;
            font-size: 20px;
            font-weight: 700;
        }
        .service-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }
        .service-badge i { font-size: 14px; }
        .offer-card .card-body { padding: 24px; }
        .offer-card .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .offer-card .card-text {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .offer-card .card-text p { margin: 0; padding: 0; }
        .offer-card .card-text p:not(:last-child) { margin-bottom: 8px; }
        .provider-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 0;
            border-top: 1px solid #f1f5f9;
            margin-top: 16px;
        }
        .provider-info i { color: #0f766e; font-size: 16px; }
        .provider-name {
            color: #334155;
            font-weight: 600;
            font-size: 14px;
            margin: 0;
        }
        .city-tag {
            color: #94a3b8;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }
        .city-tag i { font-size: 12px; }
        .provider-verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            color: #065f46;
            background: #d1fae5;
            border: 1px solid #10b981;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .provider-verification-badge.review {
            color: #92400e;
            background: #fef3c7;
            border-color: #f59e0b;
        }
        .provider-verification-badge.level {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #60a5fa;
        }
        .btn-book-offer {
            background: #0f766e;
            border: none;
            color: white;
            padding: 11px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            text-decoration: none;
        }
        .btn-book-offer:hover {
            background: #0d9488;
            color: white;
            box-shadow: 0 4px 12px rgba(15,118,110,0.3);
        }
        .btn-details-offer {
            padding: 10px 16px;
            border: 1px solid #0f766e;
            color: #0f766e;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-details-offer:hover {
            background: #f0fdfa;
            color: #0f766e;
        }
        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 4px;
        }

        /* ── Category / page header ─────────────────────────── */
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 80px 0;
            margin-bottom: 0;
        }

        /* ── Filter panel ───────────────────────────────────── */
        .offers-filter-bar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .offers-filter-bar .form-control,
        .offers-filter-bar .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 13px;
            height: 40px;
            padding: 0 12px;
        }
        .offers-filter-bar .form-control:focus,
        .offers-filter-bar .form-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15,118,110,0.12);
        }
        .offers-filter-bar label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }
        .filter-price-range {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-price-range .form-control {
            width: 90px;
        }
        .filter-price-sep {
            color: #94a3b8;
            font-size: 12px;
            white-space: nowrap;
        }
        .offers-count-badge {
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .btn-clear-filters {
            font-size: 12px;
            color: #64748b;
            background: none;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 5px 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-clear-filters:hover {
            border-color: #0f766e;
            color: #0f766e;
        }
        .no-offers {
            text-align: center;
            padding: 80px 20px;
        }
        .no-offers i {
            font-size: 70px;
            color: #ddd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Navbar Start -->
    <div class="container-fluid position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
            <?php echo $logo; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <?php echo $menu; ?>
        </nav>
    </div>
    <!-- Navbar End -->

    <!-- Page Header Start -->
    <div class="category-header" style="<?php
        if (!empty($bg_image)) {
            echo 'background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url(' . htmlspecialchars($bg_image) . '); background-size: cover; background-position: center;';
        }
    ?>">
        <div class="container text-center">
            <h5 class="text-white-50 mb-3">
                <?php
                if ($provider_filter_id > 0) {
                    echo $category_id > 0 ? 'PROVIDER CATEGORY' : 'MEDICAL PROVIDER SERVICES';
                } else {
                    echo $category_id > 0 ? 'MEDICAL CATEGORY' : htmlspecialchars($page_subtitle_1);
                }
                ?>
            </h5>
            <h1 class="display-3 text-white mb-4">
                <?php
                if ($provider_filter_id > 0 && $provider_filter_name !== '') {
                    echo htmlspecialchars($category_id > 0 ? $category_name : ($provider_filter_name . ' Services'));
                } else {
                    echo htmlspecialchars($category_name);
                }
                ?>
            </h1>
            <p class="text-white-50 mb-0" style="max-width: 800px; margin: 0 auto; font-size: 1.1rem;">
                <?php
                if ($provider_filter_id > 0 && $provider_filter_name !== '') {
                    echo htmlspecialchars(
                        $category_id > 0
                            ? 'Explore ' . strtolower($category_name) . ' services offered by ' . $provider_filter_name . ($provider_filter_city !== '' ? ' in ' . $provider_filter_city : '') . '.'
                            : 'Explore medical services offered by ' . $provider_filter_name . ($provider_filter_city !== '' ? ' in ' . $provider_filter_city : '') . '.'
                    );
                } elseif ($category_id > 0 && !empty($category_description)) {
                    echo $category_description;
                } else {
                    echo htmlspecialchars($page_subtitle_2);
                }
                ?>
            </p>
        </div>
    </div>
    <!-- Page Header End -->

    <?php if (count($offers) > 0): ?>
    <!-- Filter Bar Start -->
    <div class="offers-filter-bar">
        <div class="container">
            <div class="row g-2 align-items-end">

                <!-- Text search -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="filter-q"><i class="fas fa-search me-1"></i>Search</label>
                    <input type="text" id="filter-q" class="form-control" placeholder="Service, provider, city…" autocomplete="off">
                </div>

                <!-- Service filter -->
                <?php if (count($services_for_filter) > 1): ?>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="filter-service"><i class="fas fa-stethoscope me-1"></i>Service</label>
                    <select id="filter-service" class="form-select">
                        <option value="">All services</option>
                        <?php foreach ($services_for_filter as $sid => $sname): ?>
                            <option value="<?php echo (int)$sid; ?>"><?php echo htmlspecialchars($sname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- City filter -->
                <?php if ($show_city_filter): ?>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="filter-city"><i class="fas fa-map-marker-alt me-1"></i>City</label>
                    <select id="filter-city" class="form-select">
                        <option value="">All cities</option>
                        <?php foreach ($cities_for_filter as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Provider filter (solo si no hay filtro server-side por proveedor) -->
                <?php if ($show_provider_filter): ?>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="filter-provider"><i class="fas fa-hospital me-1"></i>Provider</label>
                    <select id="filter-provider" class="form-select">
                        <option value="">All providers</option>
                        <?php foreach ($providers_for_filter as $pid => $pname): ?>
                            <option value="<?php echo (int)$pid; ?>"><?php echo htmlspecialchars($pname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Price range -->
                <?php if (!empty($price_values)): ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label><i class="fas fa-dollar-sign me-1"></i>Price (USD)</label>
                    <div class="filter-price-range">
                        <input type="number" id="filter-price-min" class="form-control" placeholder="Min"
                               min="0" max="<?php echo $price_max_all; ?>" step="1">
                        <span class="filter-price-sep">–</span>
                        <input type="number" id="filter-price-max" class="form-control" placeholder="Max"
                               min="0" max="<?php echo $price_max_all + 1000; ?>" step="1">
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sort -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="filter-sort"><i class="fas fa-sort me-1"></i>Sort</label>
                    <select id="filter-sort" class="form-select">
                        <option value="default">Relevance</option>
                        <option value="price_asc">Price: low to high</option>
                        <option value="price_desc">Price: high to low</option>
                    </select>
                </div>

                <!-- Count + Clear -->
                <div class="col-12 col-sm-6 col-lg-1 d-flex align-items-end gap-2 flex-wrap">
                    <span class="offers-count-badge" id="offers-count"><?php echo count($offers); ?> offers</span>
                    <button class="btn-clear-filters" id="btn-clear-filters" title="Clear filters">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>

            </div>
        </div>
    </div>
    <!-- Filter Bar End -->
    <?php endif; ?>

    <!-- Offers Grid Start -->
    <div class="container-fluid py-5">
        <div class="container py-4">

            <!-- Section heading -->
            <?php if ($category_id > 0): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h2 class="mb-1">Available Services</h2>
                                <p class="text-muted mb-0">
                                    <?php if ($provider_filter_id > 0 && $provider_filter_name !== ''): ?>
                                        <?php echo htmlspecialchars($provider_filter_name); ?> offering <?php echo htmlspecialchars(strtolower($category_name)); ?> services through MedTravel
                                    <?php else: ?>
                                        Certified providers offering <?php echo htmlspecialchars(strtolower($category_name)); ?> services in Colombia
                                    <?php endif; ?>
                                </p>
                            </div>
                            <a href="offers.php<?php echo $provider_filter_id > 0 ? '?provider_id=' . (int)$provider_filter_id : ''; ?>" class="btn btn-outline-primary">View All Categories</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row mb-4">
                    <div class="col-12 text-center">
                        <h2 class="mb-2">
                            <?php if ($provider_filter_id > 0 && $provider_filter_name !== ''): ?>
                                Explore Medical Services from <?php echo htmlspecialchars($provider_filter_name); ?>
                            <?php else: ?>
                                Explore All Medical Services
                            <?php endif; ?>
                        </h2>
                        <p class="text-muted">
                            <?php if ($provider_filter_id > 0 && $provider_filter_name !== ''): ?>
                                Browse the medical services currently offered by <?php echo htmlspecialchars($provider_filter_name); ?> through MedTravel
                            <?php else: ?>
                                Browse through our comprehensive catalog of medical services from certified providers across Colombia
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($offers) > 0): ?>

                <!-- Cards grid -->
                <div class="row g-4" id="offers-grid">
                    <?php foreach ($offers as $offer): ?>
                        <?php
                        $verificationStatus = trim((string)($offer['verification_status'] ?? ''));
                        $verificationLevel  = trim((string)($offer['verification_level'] ?? ''));
                        [$badgeKind, $badgeText] = provider_verification_public_badge($verificationStatus, $verificationLevel);

                        // Texto para búsqueda (sin HTML)
                        $desc_decoded = html_entity_decode($offer['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $desc_plain   = preg_replace('/\s+/', ' ', trim(strip_tags($desc_decoded)));

                        // Texto truncado para card
                        $desc_display = $desc_plain;
                        if (mb_strlen($desc_display, 'UTF-8') > 100) {
                            $truncated  = mb_substr($desc_display, 0, 100, 'UTF-8');
                            $last_space = mb_strrpos($truncated, ' ', 0, 'UTF-8');
                            if ($last_space !== false && $last_space > 80) {
                                $truncated = mb_substr($truncated, 0, $last_space, 'UTF-8');
                            }
                            $desc_display = $truncated . '…';
                        }

                        $price_data   = $offer['price_from'] ? (float)$offer['price_from'] : '';
                        $offer_id_int = (int)$offer['id'];
                        ?>
                        <div class="col-lg-4 col-md-6 offer-col"
                             data-title="<?php echo htmlspecialchars(strtolower($offer['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-desc="<?php echo htmlspecialchars(strtolower(mb_substr($desc_plain, 0, 300, 'UTF-8')), ENT_QUOTES, 'UTF-8'); ?>"
                             data-service-id="<?php echo (int)($offer['service_id'] ?? 0); ?>"
                             data-service-name="<?php echo htmlspecialchars(strtolower($offer['service_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-provider-id="<?php echo (int)($offer['provider_id'] ?? 0); ?>"
                             data-provider-name="<?php echo htmlspecialchars(strtolower($offer['provider_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-city="<?php echo htmlspecialchars(strtolower($offer['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-price="<?php echo $price_data !== '' ? htmlspecialchars((string)$price_data, ENT_QUOTES, 'UTF-8') : ''; ?>"
                             data-verification="<?php echo htmlspecialchars(strtolower($verificationLevel), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="offer-card card h-100">
                                <div class="position-relative">
                                    <?php
                                    $image_path = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-family="Arial" font-size="18"%3EMedical Service%3C/text%3E%3C/svg%3E';
                                    $img_query  = mysqli_prepare($conexion, "SELECT path FROM offer_media WHERE offer_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
                                    if ($img_query) {
                                        mysqli_stmt_bind_param($img_query, 'i', $offer_id_int);
                                        mysqli_stmt_execute($img_query);
                                        $img_result = mysqli_stmt_get_result($img_query);
                                        if ($img_row = mysqli_fetch_assoc($img_result)) {
                                            $image_path = htmlspecialchars($img_row['path'], ENT_QUOTES, 'UTF-8');
                                        }
                                        mysqli_stmt_close($img_query);
                                    }
                                    ?>
                                    <img src="<?php echo $image_path; ?>"
                                         class="card-img-top"
                                         alt="<?php echo htmlspecialchars($offer['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22 font-size=%2218%22%3EMedical Service%3C/text%3E%3C/svg%3E';">

                                    <?php if ($offer['logo']): ?>
                                        <img src="admin/img/providers/<?php echo (int)$offer['provider_id']; ?>/<?php echo htmlspecialchars($offer['logo'], ENT_QUOTES, 'UTF-8'); ?>"
                                             class="provider-logo"
                                             alt="<?php echo htmlspecialchars($offer['provider_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                </div>

                                <div class="card-body">
                                    <span class="service-badge">
                                        <i class="fas fa-stethoscope"></i>
                                        <?php echo htmlspecialchars($offer['service_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>

                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($offer['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </h5>

                                    <p class="card-text" style="height: 60px; overflow: hidden;">
                                        <?php echo htmlspecialchars($desc_display, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>

                                    <div class="provider-info">
                                        <div style="flex: 1;">
                                            <div class="provider-name">
                                                <i class="fas fa-hospital"></i>
                                                <?php echo htmlspecialchars($offer['provider_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <?php if ($offer['city']): ?>
                                                <div class="city-tag">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($offer['city'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($badgeText !== ''): ?>
                                                <div class="provider-verification-badge <?php echo htmlspecialchars($badgeKind, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="fas fa-shield-alt"></i>
                                                    <?php echo htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <a href="booking.php?offer_id=<?php echo $offer_id_int; ?>" class="btn-book-offer">
                                            <i class="fas fa-calendar-check me-1"></i>Book Now
                                        </a>
                                        <a href="offer_detail.php?id=<?php echo $offer_id_int; ?>" class="btn-details-offer">
                                            <i class="fas fa-info-circle me-1"></i>Details
                                        </a>
                                    </div>
                                </div>

                                <?php if ($offer['price_from']): ?>
                                    <div class="price-info">
                                        <span class="price-label">Starting from</span>
                                        <span class="price-amount">
                                            <?php echo htmlspecialchars($offer['currency'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php echo number_format((float)$offer['price_from'], 2); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- /Cards grid -->

                <!-- Empty-state (shown by JS when all cards are filtered out) -->
                <div id="offers-empty" class="no-offers" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3 class="text-muted mb-3">No results match your filters</h3>
                    <p class="text-muted mb-4">Try broadening your search or clearing some filters.</p>
                    <button class="btn btn-outline-primary" id="btn-clear-filters-empty">
                        <i class="fas fa-times me-2"></i>Clear all filters
                    </button>
                </div>

            <?php else: ?>
                <div class="no-offers">
                    <i class="fas fa-inbox"></i>
                    <h3 class="text-muted mb-3">No offers available yet</h3>
                    <p class="text-muted">
                        <?php if ($provider_filter_id > 0 && $provider_filter_name !== ''): ?>
                            Check back soon for services from <?php echo htmlspecialchars($provider_filter_name, ENT_QUOTES, 'UTF-8'); ?>
                        <?php elseif ($category_id > 0): ?>
                            Check back soon for new medical services in this category
                        <?php else: ?>
                            Check back soon for new medical services
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <!-- Offers Grid End -->

    <!-- Booking Widget Start -->
    <?php echo $booking_widget; ?>
    <!-- Booking Widget End -->

    <?php echo $footer; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/lightbox/js/lightbox.min.js"></script>
    <?php echo $script; ?>
    <script src="<?php echo htmlspecialchars(mt_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

    <script>
        // Spinner
        (function () {
            function hideSpinner() {
                var s = document.getElementById('spinner');
                if (s) { s.classList.remove('show'); s.style.display = 'none'; }
            }
            setTimeout(hideSpinner, 500);
            window.addEventListener('load', hideSpinner);
            setTimeout(hideSpinner, 2000);
        })();

        // ── Offers client-side filter engine ───────────────────────────────
        (function () {
            var allCols   = Array.from(document.querySelectorAll('.offer-col'));
            var grid      = document.getElementById('offers-grid');
            var countEl   = document.getElementById('offers-count');
            var emptyEl   = document.getElementById('offers-empty');

            var elQ            = document.getElementById('filter-q');
            var elService      = document.getElementById('filter-service');
            var elCity         = document.getElementById('filter-city');
            var elProvider     = document.getElementById('filter-provider');
            var elPriceMin     = document.getElementById('filter-price-min');
            var elPriceMax     = document.getElementById('filter-price-max');
            var elSort         = document.getElementById('filter-sort');
            var elClear        = document.getElementById('btn-clear-filters');
            var elClearEmpty   = document.getElementById('btn-clear-filters-empty');

            if (!allCols.length) return;

            // Original DOM order preserved for "default" sort
            var originalOrder = allCols.slice();

            function getFilters() {
                return {
                    q:        elQ         ? elQ.value.trim().toLowerCase()         : '',
                    service:  elService   ? elService.value                         : '',
                    city:     elCity      ? elCity.value.toLowerCase()              : '',
                    provider: elProvider  ? elProvider.value                        : '',
                    priceMin: elPriceMin && elPriceMin.value !== '' ? parseFloat(elPriceMin.value) : null,
                    priceMax: elPriceMax && elPriceMax.value !== '' ? parseFloat(elPriceMax.value) : null,
                    sort:     elSort      ? elSort.value                            : 'default'
                };
            }

            function applyFilters() {
                var f = getFilters();
                var visible = [];

                allCols.forEach(function (col) {
                    var d   = col.dataset;
                    var hay = (d.title || '') + ' ' + (d.desc || '') + ' ' + (d.serviceName || '') + ' ' + (d.providerName || '') + ' ' + (d.city || '');
                    var ok  = true;

                    if (f.q       && hay.indexOf(f.q) === -1)                              ok = false;
                    if (f.service && d.serviceId !== f.service)                             ok = false;
                    if (f.city    && (d.city || '') !== f.city)                             ok = false;
                    if (f.provider && d.providerId !== f.provider)                          ok = false;
                    if (f.priceMin !== null) {
                        if (d.price === '' || parseFloat(d.price) < f.priceMin)            ok = false;
                    }
                    if (f.priceMax !== null) {
                        if (d.price === '' || parseFloat(d.price) > f.priceMax)            ok = false;
                    }

                    col.style.display = ok ? '' : 'none';
                    if (ok) visible.push(col);
                });

                // Sorting
                if (f.sort === 'price_asc' || f.sort === 'price_desc') {
                    visible.sort(function (a, b) {
                        var pa = a.dataset.price !== '' ? parseFloat(a.dataset.price) : (f.sort === 'price_asc' ? Infinity : -Infinity);
                        var pb = b.dataset.price !== '' ? parseFloat(b.dataset.price) : (f.sort === 'price_asc' ? Infinity : -Infinity);
                        return f.sort === 'price_asc' ? pa - pb : pb - pa;
                    });
                } else {
                    // Restore original document order
                    visible.sort(function (a, b) {
                        return originalOrder.indexOf(a) - originalOrder.indexOf(b);
                    });
                }
                visible.forEach(function (col) { if (grid) grid.appendChild(col); });

                // Count badge
                if (countEl) {
                    countEl.textContent = visible.length + ' offer' + (visible.length !== 1 ? 's' : '');
                }

                // Empty state
                if (emptyEl) emptyEl.style.display = visible.length === 0 ? '' : 'none';

                // URL sync (preserves server-side params, adds/removes client-side params)
                updateUrl(f);
            }

            function updateUrl(f) {
                if (!window.history || !window.history.replaceState) return;
                var current = new URLSearchParams(window.location.search);
                var next    = new URLSearchParams();

                // Preserve server-side params
                ['category', 'provider_id'].forEach(function (k) {
                    if (current.has(k)) next.set(k, current.get(k));
                });

                // Client-side params (only set when non-default)
                if (f.q)                next.set('q',          f.q);
                if (f.service)          next.set('service_id', f.service);
                if (f.city)             next.set('city',       f.city);
                if (f.provider)         next.set('pf',         f.provider);
                if (f.priceMin !== null) next.set('price_min', f.priceMin);
                if (f.priceMax !== null) next.set('price_max', f.priceMax);
                if (f.sort && f.sort !== 'default') next.set('sort', f.sort);

                var qs = next.toString();
                history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
            }

            function clearFilters() {
                if (elQ)        elQ.value        = '';
                if (elService)  elService.value  = '';
                if (elCity)     elCity.value     = '';
                if (elProvider) elProvider.value = '';
                if (elPriceMin) elPriceMin.value = '';
                if (elPriceMax) elPriceMax.value = '';
                if (elSort)     elSort.value     = 'default';
                applyFilters();
            }

            // Attach events
            [elQ, elPriceMin, elPriceMax].forEach(function (el) {
                if (el) el.addEventListener('input', applyFilters);
            });
            [elService, elCity, elProvider, elSort].forEach(function (el) {
                if (el) el.addEventListener('change', applyFilters);
            });
            if (elClear)      elClear.addEventListener('click', clearFilters);
            if (elClearEmpty) elClearEmpty.addEventListener('click', clearFilters);

            // Init from URL params (supports campaigns and deep-links)
            var urlP = new URLSearchParams(window.location.search);
            if (elQ        && urlP.has('q'))          elQ.value        = urlP.get('q');
            if (elCity     && urlP.has('city'))        elCity.value     = urlP.get('city');
            if (elProvider && urlP.has('pf'))          elProvider.value = urlP.get('pf');
            if (elPriceMin && urlP.has('price_min'))   elPriceMin.value = urlP.get('price_min');
            if (elPriceMax && urlP.has('price_max'))   elPriceMax.value = urlP.get('price_max');
            if (elSort     && urlP.has('sort'))        elSort.value     = urlP.get('sort');

            // service_id: from URL param or from PHP preselect (booking deep-link)
            var phpServiceFilter = <?php echo (int)$preselected_service_filter; ?>;
            var urlServiceId     = urlP.has('service_id') ? urlP.get('service_id') : '';
            if (elService) {
                elService.value = urlServiceId || (phpServiceFilter ? String(phpServiceFilter) : '');
            }

            // Run on load
            applyFilters();
        })();
    </script>
</body>
</html>

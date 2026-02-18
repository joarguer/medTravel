<?php
session_start();
include(__DIR__ . '/../inc/include.php');
$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
$submission_status = isset($_SESSION['booking_request_status']) ? $_SESSION['booking_request_status'] : '';
$submission_message = isset($_SESSION['booking_request_message']) ? $_SESSION['booking_request_message'] : '';
$submission_summary = (isset($_SESSION['booking_submission_summary']) && is_array($_SESSION['booking_submission_summary']))
    ? $_SESSION['booking_submission_summary']
    : [];
unset($_SESSION['booking_request_status'], $_SESSION['booking_request_message'], $_SESSION['booking_submission_summary']);
$allow_submission = ($submission_status !== 'submitted');

// Capturar oferta pre-seleccionada si existe
$preselected_offer_id = !empty($booking['preselected_offer']) ? intval($booking['preselected_offer']) : 0;

// Cargar header del wizard desde la base de datos
$wizard_header = [
    'title' => 'Booking Wizard',
    'subtitle_1' => 'Home',
    'subtitle_2' => 'Booking Request',
    'bg_image' => 'img/carousel-1.jpg'
];
$header_query = mysqli_query($conexion, "SELECT title, subtitle_1, subtitle_2, bg_image FROM booking_wizard_header WHERE activo = '0' LIMIT 1");
if ($header_query && mysqli_num_rows($header_query) > 0) {
    $wizard_header = mysqli_fetch_assoc($header_query);
}

function mt_slugify($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 'general';
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');
    return $value !== '' ? $value : 'general';
}

function mt_column_exists($conexion, $table, $column) {
    $table_escaped = mysqli_real_escape_string($conexion, $table);
    $column_escaped = mysqli_real_escape_string($conexion, $column);
    $sql = "SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'";
    $res = mysqli_query($conexion, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}

function mt_wizard_url($flow, $cat = '') {
    $url = 'wizard.php?flow=' . urlencode($flow);
    if ($cat !== '') {
        $url .= '&cat=' . urlencode($cat);
    }
    return $url;
}

function mt_find_route_index($route, $slug) {
    foreach ($route as $i => $item) {
        if (!empty($item['slug']) && $item['slug'] === $slug) {
            return $i;
        }
    }
    return 0;
}

// Cargar ofertas activas de proveedores con información completa
$offers = [];
$offers_sql = "SELECT 
                o.id, o.title, o.description, o.price_from, o.currency, o.provider_id,
                p.name AS provider_name, p.city AS provider_city, p.logo AS provider_logo,
                sc.name AS service_name, sc.category_id,
                cat.name AS category_name,
                cat.sort_order AS category_sort_order
               FROM provider_service_offers o
               INNER JOIN providers p ON o.provider_id = p.id
               INNER JOIN service_catalog sc ON o.service_id = sc.id
               LEFT JOIN service_categories cat ON sc.category_id = cat.id
               WHERE o.is_active = 1
               ORDER BY COALESCE(cat.sort_order, 9999) ASC, cat.name ASC, sc.sort_order ASC, o.id DESC";
$offers_res = mysqli_query($conexion, $offers_sql);
if ($offers_res) {
    while ($row = mysqli_fetch_assoc($offers_res)) {
        $offers[] = $row;
    }
}

// Agrupar ofertas por categoría para mejor visualización
$offers_by_category = [];
foreach ($offers as $offer) {
    $cat_id = $offer['category_id'];
    if (!isset($offers_by_category[$cat_id])) {
        $offers_by_category[$cat_id] = [
            'category_name' => $offer['category_name'],
            'offers' => []
        ];
    }
    $offers_by_category[$cat_id]['offers'][] = $offer;
}

$medical_route = [];
$medical_offers_by_slug = [];
foreach ($offers_by_category as $cat_id => $category_data) {
    $category_name = trim((string)($category_data['category_name'] ?: 'General Medical'));
    $base_slug = mt_slugify($category_name);
    $slug = $base_slug;
    $suffix = 2;
    while (isset($medical_offers_by_slug[$slug])) {
        $slug = $base_slug . '-' . $suffix;
        $suffix++;
    }
    $medical_route[] = [
        'slug' => $slug,
        'name' => $category_name,
        'count' => count($category_data['offers']),
    ];
    $medical_offers_by_slug[$slug] = $category_data['offers'];
}

$addon_route = [];
$addon_services_by_slug = [];
$addon_type_order = ['accommodation', 'transport', 'meals', 'support', 'flight', 'other'];
$addon_type_labels = [
    'accommodation' => 'Accommodation',
    'transport' => 'Transport',
    'meals' => 'Meals',
    'support' => 'Support',
    'flight' => 'Flight',
    'other' => 'Other',
];
$addon_has_soft_delete = mt_column_exists($conexion, 'medtravel_services_catalog', 'is_deleted');
$addon_counts = [];
$addon_counts_sql = "SELECT s.service_type, COUNT(*) AS total
                     FROM medtravel_services_catalog s
                     WHERE s.is_active = 1 AND s.availability_status = 'available'";
if ($addon_has_soft_delete) {
    $addon_counts_sql .= " AND s.is_deleted = 0";
}
$addon_counts_sql .= " GROUP BY s.service_type";
$addon_counts_res = mysqli_query($conexion, $addon_counts_sql);
if ($addon_counts_res) {
    while ($row = mysqli_fetch_assoc($addon_counts_res)) {
        $type = strtolower(trim((string)$row['service_type']));
        if ($type !== '') {
            $addon_counts[$type] = (int)$row['total'];
        }
    }
}
foreach ($addon_type_order as $type) {
    if (!isset($addon_counts[$type]) || $addon_counts[$type] <= 0) {
        continue;
    }
    $addon_route[] = [
        'slug' => $type,
        'name' => isset($addon_type_labels[$type]) ? $addon_type_labels[$type] : ucfirst($type),
        'count' => (int)$addon_counts[$type],
    ];
    $addon_services_by_slug[$type] = [];
}

$addon_field_order = "'accommodation','transport','meals','support','flight','other'";
$addon_services_sql = "SELECT
                        s.id,
                        s.service_type,
                        s.service_name,
                        s.short_description,
                        s.sale_price,
                        s.currency,
                        s.availability_status,
                        s.image_url,
                        COALESCE(p.provider_name, 'MedTravel') AS provider_name
                      FROM medtravel_services_catalog s
                      LEFT JOIN service_providers p ON s.provider_id = p.id
                      WHERE s.is_active = 1 AND s.availability_status = 'available'
                      " . ($addon_has_soft_delete ? "AND s.is_deleted = 0" : "") . "
                      ORDER BY FIELD(s.service_type, {$addon_field_order}), s.display_order ASC, s.service_name ASC";
$addon_services_res = mysqli_query($conexion, $addon_services_sql);
if ($addon_services_res) {
    while ($row = mysqli_fetch_assoc($addon_services_res)) {
        $type = strtolower(trim((string)$row['service_type']));
        if (!isset($addon_services_by_slug[$type])) {
            continue;
        }
        $row['category_name'] = isset($addon_type_labels[$type]) ? $addon_type_labels[$type] : ucfirst($type);
        $addon_services_by_slug[$type][] = $row;
    }
}
if (!empty($addon_route)) {
    $filtered_addon_route = [];
    foreach ($addon_route as $route_item) {
        $slug = $route_item['slug'];
        $count = isset($addon_services_by_slug[$slug]) ? count($addon_services_by_slug[$slug]) : 0;
        if ($count <= 0) {
            continue;
        }
        $route_item['count'] = $count;
        $filtered_addon_route[] = $route_item;
    }
    $addon_route = $filtered_addon_route;
}

$requested_flow = isset($_GET['flow']) ? strtolower(trim((string)$_GET['flow'])) : '';
$flow = in_array($requested_flow, ['addon', 'medical', 'review'], true) ? $requested_flow : '';
if ($flow === '') {
    if (!empty($addon_route)) {
        $flow = 'addon';
    } elseif (!empty($medical_route)) {
        $flow = 'medical';
    } else {
        $flow = 'review';
    }
}

if ($flow === 'addon' && empty($addon_route)) {
    $flow = !empty($medical_route) ? 'medical' : 'review';
}
if ($flow === 'medical' && empty($medical_route)) {
    $flow = 'review';
}

$requested_cat = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
$current_category_name = '';
$current_addon_services = [];
$current_medical_offers = [];
$prev_step_url = '';
$next_step_url = '';

if ($flow === 'addon' && !empty($addon_route)) {
    $current_index = mt_find_route_index($addon_route, $requested_cat);
    $current_route = $addon_route[$current_index];
    $current_category_name = $current_route['name'];
    $current_addon_services = isset($addon_services_by_slug[$current_route['slug']]) ? $addon_services_by_slug[$current_route['slug']] : [];

    if ($current_index > 0) {
        $prev_step_url = mt_wizard_url('addon', $addon_route[$current_index - 1]['slug']);
    }
    if ($current_index < count($addon_route) - 1) {
        $next_step_url = mt_wizard_url('addon', $addon_route[$current_index + 1]['slug']);
    } elseif (!empty($medical_route)) {
        $next_step_url = mt_wizard_url('medical', $medical_route[0]['slug']);
    } else {
        $next_step_url = mt_wizard_url('review');
    }
} elseif ($flow === 'medical' && !empty($medical_route)) {
    $current_index = mt_find_route_index($medical_route, $requested_cat);
    $current_route = $medical_route[$current_index];
    $current_category_name = $current_route['name'];
    $current_medical_offers = isset($medical_offers_by_slug[$current_route['slug']]) ? $medical_offers_by_slug[$current_route['slug']] : [];

    if ($current_index > 0) {
        $prev_step_url = mt_wizard_url('medical', $medical_route[$current_index - 1]['slug']);
    } elseif (!empty($addon_route)) {
        $prev_step_url = mt_wizard_url('addon', $addon_route[count($addon_route) - 1]['slug']);
    }

    if ($current_index < count($medical_route) - 1) {
        $next_step_url = mt_wizard_url('medical', $medical_route[$current_index + 1]['slug']);
    } else {
        $next_step_url = mt_wizard_url('review');
    }
} else {
    $flow = 'review';
    if (!empty($medical_route)) {
        $prev_step_url = mt_wizard_url('medical', $medical_route[count($medical_route) - 1]['slug']);
    } elseif (!empty($addon_route)) {
        $prev_step_url = mt_wizard_url('addon', $addon_route[count($addon_route) - 1]['slug']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php 
    // Ajustar rutas relativas para subdirectorio
    $head_adjusted = str_replace(
        ['href="assets/', 'href="lib/', 'href="css/', 'href="index.php"'],
        ['href="../assets/', 'href="../lib/', 'href="../css/', 'href="../index.php"'],
        $head
    );
    echo $head_adjusted; 
    ?>
    <style>
        .wizard-summary { 
            background: #f8fafc; 
            border-radius: 12px; 
            padding: 24px; 
            margin-bottom: 32px;
            border: 1px solid #e5e7eb;
        }
        .wizard-summary h2 { margin-top: 0; color: #1e293b; }
        .wizard-summary p { margin-bottom: 6px; }
        .wizard-stage { 
            border: 1px solid #e5e7eb; 
            border-radius: 10px; 
            padding: 24px; 
            margin-bottom: 20px;
            background: white;
        }
        .wizard-stage h3 { 
            font-size: 1.2rem; 
            margin-bottom: 12px;
            color: #1e293b;
        }
        
        /* Estilos para ofertas de proveedores */
        .offer-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: white;
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
        }
        .offer-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }
        .offer-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .offer-card .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .provider-logo-small {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .provider-info h6 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .provider-info small {
            color: #64748b;
            font-size: 12px;
        }
        .offer-card .card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
        }
        .offer-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            min-height: 48px;
            max-height: 48px;
            overflow: hidden;
        }
        .offer-description {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            line-height: 1.5;
            min-height: 60px;
            max-height: 60px;
            overflow: hidden;
        }
        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            text-decoration: none;
        }
        .offer-price {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: 18px;
            margin-top: auto;
        }
        .offer-price small {
            font-size: 12px;
            font-weight: 500;
            opacity: 0.9;
        }
        .offer-checkbox {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .category-section {
            margin-bottom: 32px;
        }
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        /* MedTravel services cards (same layout base as Stage 3 cards) */
        .service-card .card-img-top { height: 200px; overflow: hidden; position: relative; background: #f1f5f9; }
        .service-card .card-img-top img { width: 100%; height: 100%; object-fit: cover; }
        .category-header-complementary {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
        }
        .card-complementary.selected {
            border-color: #0f766e;
            background: #ecfdf5;
        }
        .card-complementary:hover {
            border-color: #0f766e;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);
        }
        .card-complementary .offer-price {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
        }
        .service-badge { background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .availability-badge { padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; border: 1px solid #e2e8f0; color: #475569; background: #f8fafc; }
        .availability-badge.available { color: #15803d; background: #ecfdf3; border-color: #bbf7d0; }
        .availability-badge.limited { color: #b45309; background: #fef3c7; border-color: #fde68a; }
        .availability-badge.unavailable { color: #0f172a; background: #e2e8f0; border-color: #cbd5e1; }
        .availability-badge.seasonal { color: #0369a1; background: #e0f2fe; border-color: #bae6fd; }
        .btn-add-service { background: #0f766e; border: none; color: white; padding: 10px 14px; border-radius: 10px; font-weight: 700; width: 100%; transition: all 0.3s ease; }
        .btn-add-service:hover { background: #0d9488; color: #fff; box-shadow: 0 4px 12px rgba(15,118,110,0.25); }
        .btn-add-service.active { background: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.25); }
        .package-summary {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            bottom: 18px;
            z-index: 1050;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            padding: 18px 20px;
            width: min(1200px, calc(100% - 32px));
        }
        .package-summary h5 { margin: 0 0 6px 0; color: #0f172a; }
        .package-summary small { color: #475569; }
        .summary-total { color: #0f1c4d; font-weight: 700; }
        .summary-actions .btn { border-radius: 999px; padding: 10px 18px; font-weight: 700; }
        .summary-active #stage4-header { display: none; }
        body.summary-active { padding-bottom: 120px; }
        body.summary-active .container { padding-bottom: 120px; }
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
            <?php 
            // Ajustar rutas para subdirectorio
            $logo_adjusted = str_replace('href="index.php"', 'href="../index.php"', $logo);
            echo $logo_adjusted; 
            ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <?php 
            // Ajustar rutas del menú para subdirectorio (incluyendo dropdowns)
            $menu_adjusted = str_replace(
                ['href="index.php"', 'href="about.php"', 'href="services.php"', 'href="offers.php"', 'href="packages.php"', 'href="destination.html"', 'href="tour.php"', 'href="gallery.html"', 'href="guides.html"', 'href="testimonial.php"', 'href="blog.php"', 'href="contact.php"', 'href="booking.php"', 'href="offers.php?category='],
                ['href="../index.php"', 'href="../about.php"', 'href="../services.php"', 'href="../offers.php"', 'href="../packages.php"', 'href="../destination.html"', 'href="../tour.php"', 'href="../gallery.html"', 'href="../guides.html"', 'href="../testimonial.php"', 'href="../blog.php"', 'href="../contact.php"', 'href="../booking.php"', 'href="../offers.php?category='],
                $menu
            );
            echo $menu_adjusted;
            ?>
        </nav>
    </div>
    <!-- Navbar End -->

    <!-- Header Start -->
    <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(19, 53, 123, 0.5), rgba(19, 53, 123, 0.5)), url(../<?php echo htmlspecialchars($wizard_header['bg_image']); ?>); background-position: center center; background-repeat: no-repeat; background-size: cover;">
        <div class="container text-center py-5" style="max-width: 900px;">
            <h3 class="text-white display-3 mb-4"><?php echo htmlspecialchars($wizard_header['title']); ?></h3>
            <ol class="breadcrumb justify-content-center mb-0">
                <li class="breadcrumb-item"><a href="../index.php"><?php echo htmlspecialchars($wizard_header['subtitle_1']); ?></a></li>
                <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($wizard_header['subtitle_2']); ?></li>
            </ol>
        </div>
    </div>
    <!-- Header End -->
    <div class="container py-5">
        <?php if ($submission_status === 'submitted'): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($submission_message ?: 'Thank you. Your request was recorded. One of our coordinators will contact you soon.'); ?>
            </div>
        <?php elseif ($submission_status === 'error'): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($submission_message ?: 'Please review the form.'); ?>
            </div>
        <?php endif; ?>
        <div class="wizard-summary">
            <h2>Step 1 completed</h2>
            <p>We captured your contact context so we can continue with the wizard.</p>
            <?php if (!empty($booking)): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                <?php if (!empty($booking['destination'])): ?>
                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($booking['destination']); ?></p>
                <?php endif; ?>
                <?php if (!empty($booking['timeline_from']) || !empty($booking['timeline_to'])): ?>
                    <p><strong>Preferred dates:</strong>
                        <?php echo htmlspecialchars($booking['timeline_from'] ?: ''); ?>
                        <?php echo (!empty($booking['timeline_from']) && !empty($booking['timeline_to'])) ? ' - ' : ''; ?>
                        <?php echo htmlspecialchars($booking['timeline_to'] ?: ''); ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($booking['special_request'])): ?>
                    <p><strong>Special request:</strong> <?php echo htmlspecialchars($booking['special_request']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p>No data captured yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($preselected_offer_id > 0): ?>
            <div class="alert alert-success" style="background: #dcfce7; border: 1px solid #86efac; color: #166534; margin-bottom: 20px;">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Offer Pre-Selected:</strong> We've already selected the offer you were viewing. You can add more offers below or proceed to submit.
            </div>
        <?php endif; ?>

        <?php if ($allow_submission): ?>
            <?php if ($flow === 'addon'): ?>
                <div class="wizard-stage mb-4">
                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-2">
                        <div>
                            <h3 class="mb-1">Stage 2 – Complementary Services</h3>
                            <p class="text-muted mb-0">Category: <strong><?php echo htmlspecialchars($current_category_name); ?></strong></p>
                        </div>
                        <div class="flex-grow-1" style="max-width: 360px; min-width: 240px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                <input type="search" class="form-control" id="medtravel-filter" placeholder="Search complementary services...">
                            </div>
                        </div>
                    </div>
                    <div class="category-header category-header-complementary">
                        <i class="fas fa-briefcase-medical me-2"></i><?php echo htmlspecialchars($current_category_name); ?>
                    </div>
                    <?php if (count($current_addon_services) > 0): ?>
                        <div class="row g-3">
                            <?php foreach ($current_addon_services as $service):
                                $status_class = '';
                                switch ($service['availability_status']) {
                                    case 'available': $status_class = 'available'; break;
                                    case 'limited': $status_class = 'limited'; break;
                                    case 'unavailable': $status_class = 'unavailable'; break;
                                    case 'seasonal': $status_class = 'seasonal'; break;
                                }
                                $image_path = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f1f5f9" width="400" height="300"/%3E%3Ctext fill="%2399a1ab" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-family="Arial" font-size="18"%3EMedTravel%3C/text%3E%3C/svg%3E';
                                if (!empty($service['image_url'])) {
                                    $raw_url = $service['image_url'];
                                    if (preg_match('#^(https?:)?//#', $raw_url) || strpos($raw_url, '/') === 0) {
                                        $image_path = htmlspecialchars($raw_url);
                                    } else {
                                        $image_path = '/' . htmlspecialchars(ltrim($raw_url, '/'));
                                    }
                                }
                            ?>
                                <div class="col-md-6 col-lg-4 d-flex">
                                    <div class="card offer-card service-card card-complementary"
                                         onclick="toggleMedService(<?php echo (int)$service['id']; ?>)"
                                         data-service-id="<?php echo (int)$service['id']; ?>"
                                         data-name="<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES); ?>"
                                         data-type="<?php echo htmlspecialchars($service['service_type'], ENT_QUOTES); ?>"
                                         data-provider="<?php echo htmlspecialchars($service['provider_name'], ENT_QUOTES); ?>">
                                        <input type="checkbox" class="d-none medtravel-checkbox" name="medtravel_services[]" value="<?php echo (int)$service['id']; ?>"
                                               data-name="<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES); ?>"
                                               data-type="<?php echo htmlspecialchars($service['service_type'], ENT_QUOTES); ?>"
                                               data-price="<?php echo htmlspecialchars($service['sale_price'], ENT_QUOTES); ?>"
                                               data-currency="<?php echo htmlspecialchars($service['currency'], ENT_QUOTES); ?>">
                                        <div class="card-img-top">
                                            <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23f1f5f9%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%2399a1ab%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22 font-size=%2218%22%3EMedTravel%3C/text%3E%3C/svg%3E';">
                                        </div>
                                        <div class="card-header">
                                            <div class="provider-logo-small" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                                <?php echo strtoupper(substr(($service['provider_name'] ?: 'M'), 0, 1)); ?>
                                            </div>
                                            <div class="provider-info flex-grow-1">
                                                <h6><?php echo htmlspecialchars($service['provider_name']); ?></h6>
                                                <small>
                                                    <i class="fas fa-briefcase-medical me-1"></i>
                                                    <?php echo htmlspecialchars(ucfirst($service['service_type'])); ?>
                                                </small>
                                            </div>
                                            <?php if (!empty($service['availability_status'])): ?>
                                                <span class="availability-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($service['availability_status']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <div class="offer-title">
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                            </div>
                                            <?php if (!empty($service['short_description'])): ?>
                                                <div class="offer-description">
                                                    <?php echo htmlspecialchars(substr($service['short_description'], 0, 120)); ?>
                                                    <?php if (strlen($service['short_description']) > 120): ?>...<?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="service-badge mb-3">
                                                <i class="fas fa-stethoscope"></i><?php echo htmlspecialchars(ucfirst($service['service_type'])); ?>
                                            </div>
                                            <div class="mt-auto">
                                                <?php if ((float)$service['sale_price'] > 0): ?>
                                                    <div class="offer-price">
                                                        <small>From</small>
                                                        <?php echo htmlspecialchars($service['currency']); ?>
                                                        $<?php echo number_format((float)$service['sale_price'], 0); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="offer-price" style="background: #64748b;">
                                                        <small>Price on request</small>
                                                    </div>
                                                <?php endif; ?>
                                                <button type="button" class="btn-add-service mt-2" data-service-trigger="<?php echo (int)$service['id']; ?>" onclick="event.stopPropagation();">Agregar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>No available complementary services found in this category.
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <?php if ($prev_step_url !== ''): ?>
                                <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($prev_step_url); ?>"><i class="fas fa-arrow-left me-2"></i>Anterior</a>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-primary" href="<?php echo htmlspecialchars($next_step_url); ?>">Siguiente<i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                </div>
            <?php elseif ($flow === 'medical'): ?>
                <div class="wizard-stage mb-4">
                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-2">
                        <div>
                            <h3 class="mb-1">Stage 3 – Medical Services</h3>
                            <p class="mb-0">Category: <strong><?php echo htmlspecialchars($current_category_name); ?></strong></p>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="input-group" style="min-width: 260px; max-width: 360px;">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                <input type="search" class="form-control" id="offers-filter" placeholder="Search by service, provider, or city...">
                            </div>
                            <div id="selection-counter" class="badge bg-primary" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                <i class="fas fa-check-circle me-2"></i>
                                <span id="counter-value">0</span> selected
                            </div>
                        </div>
                    </div>
                    <div class="category-header">
                        <i class="fas fa-heartbeat me-2"></i>
                        <?php echo htmlspecialchars($current_category_name); ?>
                    </div>
                    <?php if (count($current_medical_offers) > 0): ?>
                        <div class="row g-3">
                            <?php foreach ($current_medical_offers as $offer): ?>
                                <div class="col-md-6 col-lg-4 d-flex">
                                    <div class="card offer-card" onclick="toggleOfferSelection(this, <?php echo $offer['id']; ?>)" data-name="<?php echo htmlspecialchars($offer['title'] ?: $offer['service_name'], ENT_QUOTES); ?>" data-type="<?php echo htmlspecialchars($offer['service_name'], ENT_QUOTES); ?>" data-provider="<?php echo htmlspecialchars($offer['provider_name'], ENT_QUOTES); ?>" data-city="<?php echo htmlspecialchars($offer['provider_city'] ?: 'Colombia', ENT_QUOTES); ?>" data-category="<?php echo htmlspecialchars($current_category_name, ENT_QUOTES); ?>">
                                        <input type="checkbox"
                                               name="selected_offers[]"
                                               value="<?php echo $offer['id']; ?>"
                                               class="offer-checkbox"
                                               data-name="<?php echo htmlspecialchars($offer['title'] ?: $offer['service_name'], ENT_QUOTES); ?>"
                                               data-type="<?php echo htmlspecialchars($offer['service_name'], ENT_QUOTES); ?>"
                                               data-price="<?php echo htmlspecialchars($offer['price_from'], ENT_QUOTES); ?>"
                                               data-currency="<?php echo htmlspecialchars($offer['currency'], ENT_QUOTES); ?>"
                                               <?php echo ($preselected_offer_id === (int)$offer['id']) ? 'checked' : ''; ?>
                                               id="offer-<?php echo $offer['id']; ?>">

                                        <?php
                                        $img_query = mysqli_query($conexion, "SELECT path FROM offer_media WHERE offer_id = {$offer['id']} ORDER BY sort_order ASC, id ASC LIMIT 1");
                                        if ($img_query && $img_row = mysqli_fetch_assoc($img_query)) {
                                            $image_path = htmlspecialchars($img_row['path']);
                                        ?>
                                            <div class="card-img-top" style="height: 200px; overflow: hidden; position: relative;">
                                                <img src="../<?php echo $image_path; ?>"
                                                     alt="<?php echo htmlspecialchars($offer['title']); ?>"
                                                     style="width: 100%; height: 100%; object-fit: cover;"
                                                     onerror="this.parentElement.style.display='none';">
                                            </div>
                                        <?php } ?>

                                        <div class="card-header">
                                            <?php
                                            $logo_path = !empty($offer['provider_logo']) ? "../img/providers/{$offer['provider_id']}/{$offer['provider_logo']}" : '';
                                            $has_logo = !empty($offer['provider_logo']);
                                            ?>
                                            <?php if ($has_logo): ?>
                                                <img src="<?php echo htmlspecialchars($logo_path); ?>"
                                                     alt="<?php echo htmlspecialchars($offer['provider_name']); ?>"
                                                     class="provider-logo-small provider-logo-img"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="provider-logo-small provider-logo-fallback" style="display:none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                                    <?php echo strtoupper(substr($offer['provider_name'], 0, 1)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="provider-logo-small" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                                    <?php echo strtoupper(substr($offer['provider_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="provider-info flex-grow-1">
                                                <h6><?php echo htmlspecialchars($offer['provider_name']); ?></h6>
                                                <small>
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($offer['provider_city'] ?: 'Colombia'); ?>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="card-body">
                                            <div class="offer-title">
                                                <?php echo htmlspecialchars($offer['title'] ?: $offer['service_name']); ?>
                                            </div>
                                            <?php if (!empty($offer['description'])): ?>
                                                <div class="offer-description">
                                                    <?php echo htmlspecialchars(substr($offer['description'], 0, 120)); ?>
                                                    <?php if (strlen($offer['description']) > 120): ?>...<?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <a href="../offer_detail.php?id=<?php echo $offer['id']; ?>"
                                               class="btn btn-sm btn-outline-primary mt-2"
                                               onclick="event.stopPropagation(); return true;"
                                               target="_blank">
                                                <i class="fas fa-info-circle"></i> More details
                                            </a>
                                            <?php if ($offer['price_from'] > 0): ?>
                                                <div class="offer-price">
                                                    <small>From</small>
                                                    <?php echo htmlspecialchars($offer['currency']); ?>
                                                    $<?php echo number_format($offer['price_from'], 0); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="offer-price" style="background: #64748b;">
                                                    <small>Price on request</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>No active offers found in this category.
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <?php if ($prev_step_url !== ''): ?>
                                <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($prev_step_url); ?>"><i class="fas fa-arrow-left me-2"></i>Anterior</a>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-primary" href="<?php echo htmlspecialchars($next_step_url); ?>">Siguiente<i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                </div>
            <?php else: ?>
                <form action="submit.php" method="POST" id="booking-wizard-form">
                    <div class="wizard-stage mb-4">
                        <h3 id="stage4-header">Stage 4 – Final Review & Submit</h3>
                        <p>Review selected services and complete context before sending your request.</p>
                        <div id="wizard-selected-hidden"></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Preferred dates</label>
                                <div class="row g-2 align-items-center">
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="date" class="form-control bg-white border-0" name="timeline_from" id="wizard-date-from" placeholder="Start" value="<?php echo isset($booking['timeline_from']) ? htmlspecialchars($booking['timeline_from']) : ''; ?>">
                                            <label for="wizard-date-from"><i class="fas fa-calendar me-2"></i>Start</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="date" class="form-control bg-white border-0" name="timeline_to" id="wizard-date-to" placeholder="End" value="<?php echo isset($booking['timeline_to']) ? htmlspecialchars($booking['timeline_to']) : ''; ?>">
                                            <label for="wizard-date-to"><i class="fas fa-calendar me-2"></i>End</label>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">Select the period you plan to use the service.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="budget" class="form-label">Budget (USD)</label>
                                <input type="number" step="50" min="0" class="form-control" name="budget" id="budget" placeholder="Example: 5000">
                                <small class="text-muted">Optional - helps us provide better recommendations</small>
                            </div>
                            <div class="col-12">
                                <label for="additional_notes" class="form-label">Additional context</label>
                                <textarea name="additional_notes" id="additional_notes" class="form-control" rows="4" placeholder="Anything else we should know? (medical conditions, special requirements, etc.)"><?php echo !empty($booking['special_request']) ? htmlspecialchars($booking['special_request']) : ''; ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <?php if ($prev_step_url !== ''): ?>
                                    <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($prev_step_url); ?>"><i class="fas fa-arrow-left me-2"></i>Anterior</a>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary px-4 py-3">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
            <?php include __DIR__ . '/../inc/wizard_package_summary.php'; ?>
        <?php else: ?>
        <div class="wizard-stage mb-4">
            <h3 class="mb-2 text-success"><i class="fas fa-check-circle me-2"></i>Request sent</h3>
            <p class="mb-3">We already received your request. If you need to start a new one, return to the booking form.</p>
            <?php if (!empty($submission_summary)): ?>
                <div class="alert alert-info" style="margin-bottom:15px;">
                    <p class="mb-1"><strong>Booking ID:</strong> #<?php echo intval($submission_summary['booking_id'] ?? 0); ?></p>
                    <?php if (!empty($submission_summary['total_display'])): ?>
                        <p class="mb-1"><strong>Total estimated:</strong> <?php echo htmlspecialchars((string)$submission_summary['total_display']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($submission_summary['items']) && is_array($submission_summary['items'])): ?>
                        <hr style="margin:10px 0;">
                        <p class="mb-1"><strong>Requested items:</strong></p>
                        <ul style="padding-left:20px; margin-bottom:8px;">
                            <?php foreach ($submission_summary['items'] as $summary_item): ?>
                                <li>
                                    <?php echo htmlspecialchars((string)($summary_item['name'] ?? 'Service')); ?>
                                    <?php if (!empty($summary_item['provider'])): ?>
                                        - <?php echo htmlspecialchars((string)$summary_item['provider']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($summary_item['category'])): ?>
                                        (<?php echo htmlspecialchars((string)$summary_item['category']); ?>)
                                    <?php endif; ?>
                                    <?php if (!empty($summary_item['price_display'])): ?>
                                        - <?php echo htmlspecialchars((string)$summary_item['price_display']); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="mb-1"><strong>Next steps:</strong></p>
                    <ol style="padding-left:20px; margin-bottom:0;">
                        <li>Availability check</li>
                        <li>Virtual consultation scheduling</li>
                        <li>Budget confirmation</li>
                        <li>Schedule coordination</li>
                        <li>Payment</li>
                    </ol>
                </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="../booking.php">Start new request</a>
                <a class="btn btn-outline-primary" href="../offers.php">Back to catalog</a>
            </div>
        </div>
        <?php endif; ?>
        <div class="mt-3">
            <a href="../offers.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to catalog
            </a>
        </div>
    </div>
    <!-- Wizard End -->

    <!-- Footer Start -->
    <?php 
    // Ajustar rutas del footer para subdirectorio
    $footer_adjusted = str_replace(
        ['href="index.php"', 'href="about.php"', 'href="services.php"', 'href="offers.php"', 'href="packages.php"', 'href="destination.html"', 'href="tour.php"', 'href="gallery.html"', 'href="guides.html"', 'href="testimonial.php"', 'href="blog.php"', 'href="contact.php"', 'href="booking.php"'],
        ['href="../index.php"', 'href="../about.php"', 'href="../services.php"', 'href="../offers.php"', 'href="../packages.php"', 'href="../destination.html"', 'href="../tour.php"', 'href="../gallery.html"', 'href="../guides.html"', 'href="../testimonial.php"', 'href="../blog.php"', 'href="../contact.php"', 'href="../booking.php"'],
        $footer
    );
    echo $footer_adjusted;
    ?>
    <!-- Footer End -->

    <!-- Copyright Start -->
    <?php 
    // Ajustar rutas del copyright para subdirectorio
    $copyright_adjusted = str_replace(
        ['href="index.php"', 'href="about.php"', 'href="services.php"', 'href="contact.php"'],
        ['href="../index.php"', 'href="../about.php"', 'href="../services.php"', 'href="../contact.php"'],
        $copyright
    );
    echo $copyright_adjusted;
    ?>
    <!-- Copyright End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../lib/easing/easing.min.js"></script>
    <script src="../lib/waypoints/waypoints.min.js"></script>
    <script src="../lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="../lib/lightbox/js/lightbox.min.js"></script>
    <?php echo $script; ?>
    <script src="../js/main.js"></script>

    <script>
        const KEY_SELECTED_SERVICES = 'mt_selected_services';
        const KEY_SELECTED_OFFERS = 'mt_selected_offers';
        const KEY_PRESELECTED_OFFER = 'mt_preselected_offer_id';
        const WAS_SUBMITTED = <?php echo ($submission_status === 'submitted') ? 'true' : 'false'; ?>;

        function clearBookingStorage() {
            const keys = [
                'mt_selected_services',
                'mt_selected_offers',
                'mt_preselected_offer_id',
                'mt_booking_draft',
                'mt_booking_started',
                'mt_booking_step1_submitted'
            ];
            keys.forEach(function(k) { localStorage.removeItem(k); });
            sessionStorage.removeItem('preselected_offer_id');
        }

        const timelineFrom = document.getElementById('wizard-date-from');
        const timelineTo = document.getElementById('wizard-date-to');
        if (timelineFrom && timelineTo) {
            const today = new Date().toISOString().split('T')[0];
            timelineFrom.setAttribute('min', today);
            timelineTo.setAttribute('min', today);
            timelineFrom.addEventListener('change', function() {
                if (this.value) timelineTo.setAttribute('min', this.value);
            });
        }

        function parseStoredJson(raw) {
            if (!raw) return [];
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function getStoredServices() {
            return parseStoredJson(localStorage.getItem(KEY_SELECTED_SERVICES));
        }

        function getStoredOffers() {
            return parseStoredJson(localStorage.getItem(KEY_SELECTED_OFFERS));
        }

        function ensureBookingStarted() {
            try {
                localStorage.setItem('mt_booking_started', '1');
                window.dispatchEvent(new Event('mt-booking-state-changed'));
            } catch (e) {}
        }

        function setStoredServices(items) {
            localStorage.setItem(KEY_SELECTED_SERVICES, JSON.stringify(items));
            ensureBookingStarted();
        }

        function setStoredOffers(items) {
            localStorage.setItem(KEY_SELECTED_OFFERS, JSON.stringify(items));
            ensureBookingStarted();
        }

        function buildOfferItemFromCheckbox(cb) {
            return {
                id: String(cb.value),
                name: String(cb.dataset.name || cb.value || ''),
                type: String(cb.dataset.type || 'medical_offer'),
                price: String(cb.dataset.price || '0'),
                currency: String(cb.dataset.currency || '')
            };
        }

        function buildServiceItemFromCheckbox(cb) {
            return {
                id: String(cb.value),
                name: String(cb.dataset.name || cb.value || ''),
                type: String(cb.dataset.type || 'complementary_service'),
                price: String(cb.dataset.price || '0'),
                currency: String(cb.dataset.currency || '')
            };
        }

        function setWizardFieldIfEmpty(name, value) {
            if (value === null || value === undefined) return;
            const field = document.querySelector('[name="' + name + '"]');
            if (!field || String(field.value || '').trim() !== '') return;
            const normalized = String(value);
            if (normalized.trim() === '') return;
            if (field.type === 'date' && !/^\d{4}-\d{2}-\d{2}$/.test(normalized)) return;
            field.value = normalized;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function updateMedButtonState(card, checked) {
            if (!card) return;
            const button = card.querySelector('[data-service-trigger]');
            if (!button) return;
            button.classList.toggle('active', checked);
            button.textContent = checked ? 'Quitar' : 'Agregar';
        }

        function syncOfferCheckbox(checkbox) {
            if (!checkbox) return;
            const card = checkbox.closest('.offer-card');
            if (card) card.classList.toggle('selected', checkbox.checked);
            let items = getStoredOffers();
            const id = String(checkbox.value);
            items = items.filter(function(item) { return String(item.id) !== id; });
            if (checkbox.checked) {
                items.push(buildOfferItemFromCheckbox(checkbox));
            }
            setStoredOffers(items);
            updateSelectionSummary();
        }

        function syncMedCheckbox(checkbox) {
            if (!checkbox) return;
            const card = checkbox.closest('.service-card');
            if (card) card.classList.toggle('selected', checkbox.checked);
            updateMedButtonState(card, checkbox.checked);
            let items = getStoredServices();
            const id = String(checkbox.value);
            items = items.filter(function(item) { return String(item.id) !== id; });
            if (checkbox.checked) {
                items.push(buildServiceItemFromCheckbox(checkbox));
            }
            setStoredServices(items);
            updateSelectionSummary();
        }

        function toggleOfferSelection(card) {
            if (!card) return;
            const checkbox = card.querySelector('.offer-checkbox');
            if (!checkbox) return;
            checkbox.checked = !checkbox.checked;
            syncOfferCheckbox(checkbox);
        }

        function toggleMedService(serviceId) {
            const checkbox = document.querySelector('.medtravel-checkbox[value="' + serviceId + '"]');
            if (!checkbox) return;
            checkbox.checked = !checkbox.checked;
            syncMedCheckbox(checkbox);
        }

        function applyStoredSelectionsToCurrentStep() {
            const preOffer = String(localStorage.getItem(KEY_PRESELECTED_OFFER) || '').trim();
            const offerMap = {};
            getStoredOffers().forEach(function(item) { offerMap[String(item.id)] = true; });
            if (/^\d+$/.test(preOffer)) {
                offerMap[preOffer] = true;
            }

            document.querySelectorAll('.offer-checkbox').forEach(function(cb) {
                const checked = !!offerMap[String(cb.value)];
                cb.checked = checked;
                const card = cb.closest('.offer-card');
                if (card) card.classList.toggle('selected', checked);
            });

            const servicesMap = {};
            getStoredServices().forEach(function(item) { servicesMap[String(item.id)] = true; });
            document.querySelectorAll('.medtravel-checkbox').forEach(function(cb) {
                const checked = !!servicesMap[String(cb.value)];
                cb.checked = checked;
                const card = cb.closest('.service-card');
                if (card) card.classList.toggle('selected', checked);
                updateMedButtonState(card, checked);
            });
        }

        function buildSummaryItemsFromStorage() {
            const summaryItems = [];
            getStoredServices().forEach(function(item) {
                summaryItems.push({
                    value: String(item.id || ''),
                    dataset: {
                        name: String(item.name || ('Service #' + item.id)),
                        type: String(item.type || 'complementary_service'),
                        price: String(item.price || '0'),
                        currency: String(item.currency || '')
                    }
                });
            });

            const storedOffers = getStoredOffers();
            storedOffers.forEach(function(item) {
                summaryItems.push({
                    value: String(item.id || ''),
                    dataset: {
                        name: String(item.name || ('Offer #' + item.id)),
                        type: String(item.type || 'medical_offer'),
                        price: String(item.price || '0'),
                        currency: String(item.currency || '')
                    }
                });
            });

            const preOffer = String(localStorage.getItem(KEY_PRESELECTED_OFFER) || '').trim();
            if (/^\d+$/.test(preOffer) && !storedOffers.some(function(item){ return String(item.id) === preOffer; })) {
                summaryItems.push({
                    value: preOffer,
                    dataset: {
                        name: 'Oferta médica preseleccionada #' + preOffer,
                        type: 'medical_offer',
                        price: '0',
                        currency: ''
                    }
                });
            }
            return summaryItems;
        }

        function updateSelectionSummary() {
            const allSelected = buildSummaryItemsFromStorage();
            const count = allSelected.length;
            const counterValue = document.getElementById('counter-value');
            const counterBadge = document.getElementById('selection-counter');

            if (counterValue) counterValue.textContent = count;
            if (counterBadge) {
                if (count === 0) counterBadge.className = 'badge bg-secondary';
                else if (count <= 2) counterBadge.className = 'badge bg-primary';
                else counterBadge.className = 'badge bg-success';
            }

            if (window.BookingSummary && typeof window.BookingSummary.renderFromSelections === 'function') {
                if (allSelected.length > 0) {
                    window.BookingSummary.renderFromSelections(allSelected, { addBodyClass: true });
                } else if (typeof window.BookingSummary.hide === 'function') {
                    window.BookingSummary.hide();
                }
            }
        }

        function filterMedtravelServices(term) {
            const value = term.trim().toLowerCase();
            document.querySelectorAll('.service-card').forEach(function(card) {
                const wrapper = card.closest('[class*="col-"]');
                if (!value) {
                    card.classList.remove('d-none');
                    if (wrapper) wrapper.classList.remove('d-none');
                    return;
                }
                const name = (card.dataset.name || '').toLowerCase();
                const type = (card.dataset.type || '').toLowerCase();
                const provider = (card.dataset.provider || '').toLowerCase();
                const match = name.includes(value) || type.includes(value) || provider.includes(value);
                card.classList.toggle('d-none', !match);
                if (wrapper) wrapper.classList.toggle('d-none', !match);
            });
        }

        function filterOffers(term) {
            const value = term.trim().toLowerCase();
            document.querySelectorAll('.offer-card').forEach(function(card) {
                const wrapper = card.closest('[class*="col-"]');
                if (!value) {
                    card.classList.remove('d-none');
                    if (wrapper) wrapper.classList.remove('d-none');
                    return;
                }
                const fields = [card.dataset.name, card.dataset.type, card.dataset.provider, card.dataset.city, card.dataset.category]
                    .map(function(v){ return (v || '').toLowerCase(); });
                const match = fields.some(function(f){ return f.includes(value); });
                card.classList.toggle('d-none', !match);
                if (wrapper) wrapper.classList.toggle('d-none', !match);
            });
        }

        function renderHiddenSelectionsForSubmit() {
            const container = document.getElementById('wizard-selected-hidden');
            if (!container) return;
            container.innerHTML = '';

            let selectedOffers = getStoredOffers();
            const preOffer = String(localStorage.getItem(KEY_PRESELECTED_OFFER) || '').trim();
            if (selectedOffers.length === 0 && /^\d+$/.test(preOffer)) {
                selectedOffers = [{ id: preOffer }];
            }
            const selectedServices = getStoredServices();

            selectedOffers.forEach(function(item) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_offers[]';
                input.value = String(item.id);
                container.appendChild(input);
            });
            selectedServices.forEach(function(item) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'medtravel_services[]';
                input.value = String(item.id);
                container.appendChild(input);
            });
        }

        function hydrateWizardFromStorage() {
            <?php if ($preselected_offer_id > 0): ?>
            if (!localStorage.getItem(KEY_PRESELECTED_OFFER)) {
                localStorage.setItem(KEY_PRESELECTED_OFFER, '<?php echo (int)$preselected_offer_id; ?>');
            }
            <?php endif; ?>
            const draft = (function() {
                try { return JSON.parse(localStorage.getItem('mt_booking_draft') || '{}'); } catch (e) { return {}; }
            })();
            setWizardFieldIfEmpty('timeline_from', draft.timeline_from);
            setWizardFieldIfEmpty('timeline_to', draft.timeline_to);
            setWizardFieldIfEmpty('budget', draft.budget);
            setWizardFieldIfEmpty('additional_notes', draft.additional_notes || draft.special_request);
            applyStoredSelectionsToCurrentStep();
            updateSelectionSummary();
            renderHiddenSelectionsForSubmit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (WAS_SUBMITTED) {
                clearBookingStorage();
                if (window.BookingSummary && typeof window.BookingSummary.hide === 'function') {
                    window.BookingSummary.hide();
                }
                window.dispatchEvent(new Event('mt-booking-state-changed'));
            }
            hydrateWizardFromStorage();

            document.querySelectorAll('.offer-checkbox').forEach(function(cb) {
                cb.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
                cb.addEventListener('change', function(e) {
                    syncOfferCheckbox(cb);
                    e.stopPropagation();
                });
            });

            document.querySelectorAll('[data-service-trigger]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    const id = btn.getAttribute('data-service-trigger');
                    toggleMedService(id);
                    e.stopPropagation();
                });
            });

            document.querySelectorAll('.medtravel-checkbox').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    syncMedCheckbox(cb);
                });
            });

            const medFilter = document.getElementById('medtravel-filter');
            if (medFilter) {
                medFilter.addEventListener('input', function() {
                    filterMedtravelServices(medFilter.value);
                });
            }

            const offersFilter = document.getElementById('offers-filter');
            if (offersFilter) {
                offersFilter.addEventListener('input', function() {
                    filterOffers(offersFilter.value);
                });
            }

            const bookingForm = document.getElementById('booking-wizard-form');
            if (bookingForm) {
                bookingForm.addEventListener('submit', function() {
                    renderHiddenSelectionsForSubmit();
                });
            }
        });

        window.addEventListener('mt-booking-state-changed', function() {
            applyStoredSelectionsToCurrentStep();
            updateSelectionSummary();
            renderHiddenSelectionsForSubmit();
        });
    </script>
</body>
</html>

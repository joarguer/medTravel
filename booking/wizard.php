<?php
session_start();
include(__DIR__ . '/../inc/include.php');
$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
$submission_status = isset($_SESSION['booking_request_status']) ? $_SESSION['booking_request_status'] : '';
$submission_message = isset($_SESSION['booking_request_message']) ? $_SESSION['booking_request_message'] : '';
unset($_SESSION['booking_request_status'], $_SESSION['booking_request_message']);

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

$categories = [];
$cat_query = "SELECT id, name, description FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, id DESC";
$cat_res = mysqli_query($conexion, $cat_query);
if ($cat_res) {
    while ($row = mysqli_fetch_assoc($cat_res)) {
        $categories[] = $row;
    }
}

// Cargar ofertas activas de proveedores con información completa
$offers = [];
$offers_sql = "SELECT 
                o.id, o.title, o.description, o.price_from, o.currency, o.provider_id,
                p.name AS provider_name, p.city AS provider_city, p.logo AS provider_logo,
                sc.name AS service_name, sc.category_id,
                cat.name AS category_name
               FROM provider_service_offers o
               INNER JOIN providers p ON o.provider_id = p.id
               INNER JOIN service_catalog sc ON o.service_id = sc.id
               LEFT JOIN service_categories cat ON sc.category_id = cat.id
               WHERE o.is_active = 1
               ORDER BY cat.name ASC, sc.sort_order ASC, o.id DESC";
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
        }
        .offer-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .offer-description {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            line-height: 1.5;
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
        /* MedTravel services cards */
        .service-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: #fff;
        }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); border-color: #d1d5db; }
        .service-card .card-img-top { height: 200px; object-fit: cover; background: #f1f5f9; }
        .service-badge { background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .availability-badge { padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; border: 1px solid #e2e8f0; color: #475569; background: #f8fafc; }
        .availability-badge.available { color: #15803d; background: #ecfdf3; border-color: #bbf7d0; }
        .availability-badge.limited { color: #b45309; background: #fef3c7; border-color: #fde68a; }
        .availability-badge.unavailable { color: #0f172a; background: #e2e8f0; border-color: #cbd5e1; }
        .availability-badge.seasonal { color: #0369a1; background: #e0f2fe; border-color: #bae6fd; }
        .provider-info i { color: #0f766e; }
        .provider-name { color: #334155; font-weight: 600; font-size: 14px; margin: 0; }
        .price-info { background: #f8fafc; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .price-label { color: #64748b; font-size: 12px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; }
        .price-amount { color: #0f766e; font-size: 18px; font-weight: 700; }
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

        <?php
        // Cargar servicios complementarios de MedTravel (catálogo)
        $medtravel_services = [];
        $medtravel_query = mysqli_query($conexion, "SELECT s.id, s.service_type, s.service_name, s.short_description, s.sale_price, s.currency, s.availability_status, s.image_url, COALESCE(p.provider_name, 'MedTravel') AS provider_name FROM medtravel_services_catalog s LEFT JOIN service_providers p ON s.provider_id = p.id WHERE s.is_active = 1 ORDER BY s.service_type, s.display_order, s.service_name");
        if ($medtravel_query) {
            while ($row = mysqli_fetch_assoc($medtravel_query)) {
                $medtravel_services[$row['service_type']][] = $row;
            }
        }
        ?>
        
        <form action="submit.php" method="POST" id="booking-wizard-form">
            <?php if (count($medtravel_services) > 0): ?>
            <div class="wizard-stage mb-4">
                <h3 class="mb-3">Stage 2 – MedTravel Complementary Services</h3>
                <p class="text-muted mb-3">Select concierge and travel add-ons to complete your package</p>
                <?php foreach ($medtravel_services as $type => $services_group): ?>
                <div class="mb-3">
                    <h5 class="text-primary mb-2" style="text-transform: capitalize;">
                        <i class="fas fa-briefcase-medical me-2"></i><?php echo htmlspecialchars($type); ?>
                    </h5>
                    <div class="row g-3">
                        <?php foreach ($services_group as $service): 
                            $status_class = '';
                            switch($service['availability_status']){
                                case 'available': $status_class = 'available'; break;
                                case 'limited': $status_class = 'limited'; break;
                                case 'unavailable': $status_class = 'unavailable'; break;
                                case 'seasonal': $status_class = 'seasonal'; break;
                            }
                            $image_path = !empty($service['image_url']) ? htmlspecialchars($service['image_url']) : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f1f5f9" width="400" height="300"/%3E%3Ctext fill="%2399a1ab" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-family="Arial" font-size="18"%3EMedTravel%3C/text%3E%3C/svg%3E';
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="service-card card h-100" data-service-id="<?php echo (int)$service['id']; ?>">
                                <input type="checkbox" class="d-none medtravel-checkbox" name="medtravel_services[]" value="<?php echo (int)$service['id']; ?>"
                                       data-name="<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES); ?>"
                                       data-type="<?php echo htmlspecialchars($service['service_type'], ENT_QUOTES); ?>"
                                       data-price="<?php echo htmlspecialchars($service['sale_price'], ENT_QUOTES); ?>"
                                       data-currency="<?php echo htmlspecialchars($service['currency'], ENT_QUOTES); ?>">
                                <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($service['service_name']); ?>" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23f1f5f9%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%2399a1ab%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22 font-size=%2218%22%3EMedTravel%3C/text%3E%3C/svg%3E';">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="service-badge"><i class="fas fa-stethoscope"></i><?php echo htmlspecialchars(ucfirst($service['service_type'])); ?></span>
                                        <?php if(!empty($service['availability_status'])): ?><span class="availability-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($service['availability_status']); ?></span><?php endif; ?>
                                    </div>
                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($service['service_name']); ?></h5>
                                    <p class="card-text mb-3" style="min-height:60px; color:#64748b; line-height:1.5;">
                                        <?php echo htmlspecialchars($service['short_description']); ?>
                                    </p>
                                    <div class="provider-info d-flex align-items-center gap-2">
                                        <i class="fas fa-building"></i>
                                        <p class="provider-name mb-0"><?php echo htmlspecialchars($service['provider_name']); ?></p>
                                    </div>
                                    <div class="mt-auto">
                                        <div class="price-info">
                                            <span class="price-label">Starting from</span>
                                            <span class="price-amount"><?php echo ($service['currency']==='USD'?'$':'') . number_format((float)$service['sale_price'], 0, '.', ',') . ' ' . htmlspecialchars($service['currency']); ?></span>
                                        </div>
                                        <button type="button" class="btn-add-service" data-service-trigger="<?php echo (int)$service['id']; ?>">Agregar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="wizard-stage mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Stage 3 – Select Medical Services</h3>
                    <div id="selection-counter" class="badge bg-primary" style="font-size: 1rem; padding: 0.5rem 1rem;">
                        <i class="fas fa-check-circle me-2"></i>
                        <span id="counter-value">0</span> selected
                    </div>
                </div>
                <p class="mb-4">Choose from our certified providers' available services. You can select multiple services to build your medical travel package.</p>
                
                <?php if (count($offers_by_category) > 0): ?>
                    <?php foreach ($offers_by_category as $cat_id => $category_data): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <i class="fas fa-heartbeat me-2"></i>
                                <?php echo htmlspecialchars($category_data['category_name']); ?>
                            </div>
                            <div class="row g-3">
                                <?php foreach ($category_data['offers'] as $offer): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card offer-card" onclick="toggleOfferSelection(this, <?php echo $offer['id']; ?>)">
                                            <input type="checkbox" 
                                                   name="selected_offers[]" 
                                                   value="<?php echo $offer['id']; ?>" 
                                                   class="offer-checkbox"
                                                   data-name="<?php echo htmlspecialchars($offer['title'] ?: $offer['service_name'], ENT_QUOTES); ?>"
                                                   data-type="<?php echo htmlspecialchars($offer['service_name'], ENT_QUOTES); ?>"
                                                   data-price="<?php echo htmlspecialchars($offer['price_from'], ENT_QUOTES); ?>"
                                                   data-currency="<?php echo htmlspecialchars($offer['currency'], ENT_QUOTES); ?>"
                                                   <?php echo ($preselected_offer_id === $offer['id']) ? 'checked' : ''; ?>
                                                   id="offer-<?php echo $offer['id']; ?>">
                                            
                                            <?php 
                                            // Obtener imagen de offer_media
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
                                                // Los logos se guardan en img/providers/{id}/ no en admin/img/providers/
                                                $logo_path = !empty($offer['provider_logo']) ? "../img/providers/{$offer['provider_id']}/{$offer['provider_logo']}" : '';
                                                $has_logo = !empty($offer['provider_logo']);
                                                ?>
                                                
                                                <?php if ($has_logo): ?>
                                                    <!-- DEBUG: Logo path = <?php echo $logo_path; ?> -->
                                                    <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                                                         alt="<?php echo htmlspecialchars($offer['provider_name']); ?>\" 
                                                         class="provider-logo-small provider-logo-img"
                                                         onerror="console.error('Failed to load: <?php echo $logo_path; ?>'); this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                                         onload="console.log('Loaded successfully: <?php echo $logo_path; ?>')">
                                                    <div class="provider-logo-small provider-logo-fallback" style="display:none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                                        <?php echo strtoupper(substr($offer['provider_name'], 0, 1)); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- DEBUG: No logo in DB for provider <?php echo $offer['provider_id']; ?> -->
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
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No active service offers are currently available. Please check back later or contact us directly.
                    </div>
                <?php endif; ?>
            </div>
                <div id="wizard-package-summary" class="package-summary d-none">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Tu paquete</h5>
                            <div id="wizard-summary-list" class="small text-muted">No has añadido servicios.</div>
                        </div>
                        <div id="wizard-summary-total" class="summary-total"></div>
                        <div class="summary-actions d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary" id="wizard-summary-continue">Continuar al booking</button>
                            <button type="button" class="btn btn-outline-primary" id="wizard-summary-clear">Limpiar</button>
                        </div>
                    </div>
                </div>
            <div class="wizard-stage mb-4">
                <h3 id="stage4-header">Stage 4 – Finalize Context</h3>
                <p>Add any budget, urgency or additional notes before sending the request.</p>
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
                    <div class="col-md-6">
                        <label class="form-label">Preferred timeline</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" class="form-control" name="timeline_from" id="timeline_from" placeholder="From">
                                <small class="text-muted">From</small>
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" name="timeline_to" id="timeline_to" placeholder="To">
                                <small class="text-muted">To</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="additional_notes" class="form-label">Additional context</label>
                        <textarea name="additional_notes" id="additional_notes" class="form-control" rows="4" placeholder="Anything else we should know? (medical conditions, special requirements, etc.)"></textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-3">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                    <small class="d-block mt-2 text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Your information is secure. Our team will review your request within 24 hours.
                    </small>
                </div>
            </div>
        </form>
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
        // Timeline date range validation
        const timelineFrom = document.getElementById('timeline_from');
        const timelineTo = document.getElementById('timeline_to');
        
        if (timelineFrom && timelineTo) {
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            timelineFrom.setAttribute('min', today);
            timelineTo.setAttribute('min', today);
            
            // Validate that 'to' date is after 'from' date
            timelineFrom.addEventListener('change', function() {
                if (this.value) {
                    timelineTo.setAttribute('min', this.value);
                }
            });
        }
        
        // Toggle offer card selection
        function toggleOfferSelection(card, offerId) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            const wasChecked = checkbox.checked;
            
            // Toggle checkbox
            checkbox.checked = !wasChecked;
            
            // Toggle card visual state
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            
            // Update selection summary
            updateSelectionSummary();
        }

        // Toggle MedTravel complementary service card
        function toggleMedService(serviceId) {
            const checkbox = document.querySelector('.medtravel-checkbox[value="' + serviceId + '"]');
            if (!checkbox) return;
            checkbox.checked = !checkbox.checked;
            const card = checkbox.closest('.service-card');
            const button = card ? card.querySelector('[data-service-trigger]') : null;
            if (card) card.classList.toggle('selected', checkbox.checked);
            if (button) button.classList.toggle('active', checkbox.checked);
            updateSelectionSummary();
        }
        
        // Update selection summary
        function updateSelectionSummary() {
            const selectedOffers = Array.from(document.querySelectorAll('input[name="selected_offers[]"]:checked'));
            const selectedMed = Array.from(document.querySelectorAll('.medtravel-checkbox:checked'));
            const allSelected = selectedOffers.concat(selectedMed);
            const count = allSelected.length;

            // Actualizar contador visual
            const counterValue = document.getElementById('counter-value');
            const counterBadge = document.getElementById('selection-counter');

            if (counterValue) {
                counterValue.textContent = count;
            }

            if (counterBadge) {
                if (count === 0) {
                    counterBadge.className = 'badge bg-secondary';
                } else if (count <= 2) {
                    counterBadge.className = 'badge bg-primary';
                } else {
                    counterBadge.className = 'badge bg-success';
                }
            }

            // Resumen flotante
            const summary = document.getElementById('wizard-package-summary');
            const summaryList = document.getElementById('wizard-summary-list');
            const summaryTotal = document.getElementById('wizard-summary-total');
            if (!summary || !summaryList || !summaryTotal) return;

            if (count === 0) {
                summary.classList.add('d-none');
                document.body.classList.remove('summary-active');
                summaryList.textContent = 'No has añadido servicios.';
                summaryTotal.textContent = '';
                return;
            }

            summary.classList.remove('d-none');
            document.body.classList.add('summary-active');

            // Texto de items
            const preview = allSelected.slice(0, 3).map(cb => {
                const name = cb.dataset.name || cb.value;
                const type = cb.dataset.type || '';
                return type ? `${name} (${type})` : name;
            }).join(' · ');
            let previewText = preview;
            if (allSelected.length > 3) {
                previewText += ` + ${allSelected.length - 3} más`;
            }
            summaryList.textContent = previewText;

            // Totales por moneda
            const totals = {};
            allSelected.forEach(cb => {
                const price = parseFloat(cb.dataset.price) || 0;
                const curr = cb.dataset.currency || '';
                if (!totals[curr]) totals[curr] = 0;
                totals[curr] += price;
            });
            const parts = Object.keys(totals).map(curr => {
                const val = totals[curr];
                const symbol = curr === 'USD' ? '$' : (curr === 'COP' ? '$' : '');
                return `${symbol}${val.toLocaleString('en-US')} ${curr}`.trim();
            }).filter(Boolean);
            summaryTotal.textContent = parts.length ? `Total estimado: ${parts.join(' / ')}` : '';

            console.log(`Selected ${count} offer(s)`);
        }
        
        // Initialize - mark pre-selected cards
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar contador y resumen
            updateSelectionSummary();
            
            document.querySelectorAll('.offer-card input[type="checkbox"]:checked').forEach(function(checkbox) {
                const card = checkbox.closest('.offer-card');
                card.classList.add('selected');
                
                // Auto-scroll a la oferta pre-seleccionada
                <?php if ($preselected_offer_id > 0): ?>
                    if (checkbox.value === '<?php echo $preselected_offer_id; ?>') {
                        setTimeout(function() {
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            // Highlight temporal
                            card.style.boxShadow = '0 0 0 3px rgba(34, 197, 94, 0.5)';
                            setTimeout(function() {
                                card.style.boxShadow = '';
                            }, 2000);
                        }, 500);
                    }
                <?php endif; ?>
            });

            // Escuchar cambios directos en checkboxes
            document.querySelectorAll('.offer-checkbox').forEach(function(cb) {
                cb.addEventListener('change', function(e) {
                    const card = cb.closest('.offer-card');
                    if (card) {
                        card.classList.toggle('selected', cb.checked);
                    }
                    updateSelectionSummary();
                    e.stopPropagation();
                });
            });

            // MedTravel services buttons
            document.querySelectorAll('[data-service-trigger]').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    const id = btn.getAttribute('data-service-trigger');
                    toggleMedService(id);
                    e.stopPropagation();
                });
            });

            // MedTravel checkboxes (fallback)
            document.querySelectorAll('.medtravel-checkbox').forEach(function(cb){
                cb.addEventListener('change', function(){
                    const card = cb.closest('.service-card');
                    const button = card ? card.querySelector('[data-service-trigger]') : null;
                    if (card) card.classList.toggle('selected', cb.checked);
                    if (button) button.classList.toggle('active', cb.checked);
                    updateSelectionSummary();
                });
            });

            // Botones del resumen
            const clearBtn = document.getElementById('wizard-summary-clear');
            const continueBtn = document.getElementById('wizard-summary-continue');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    document.querySelectorAll('.offer-checkbox, .medtravel-checkbox').forEach(function(cb) {
                        cb.checked = false;
                        const card = cb.closest('.offer-card, .service-card');
                        if (card) card.classList.remove('selected');
                        const button = card ? card.querySelector('[data-service-trigger]') : null;
                        if (button) button.classList.remove('active');
                    });
                    updateSelectionSummary();
                });
            }
            if (continueBtn) {
                continueBtn.addEventListener('click', function() {
                    const submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        submitBtn.focus();
                    }
                });
            }
        });
    </script>
</body>
</html>

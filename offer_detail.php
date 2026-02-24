<?php
include_once(__DIR__ . '/admin/include/conexion.php');
require_once __DIR__ . '/inc/auth_client.php';
require_once __DIR__ . '/client/include/client_notifications.php';
require_once __DIR__ . '/inc/fee_gate.php';

if (!function_exists('provider_verification_level_label')) {
    function provider_verification_level_label($level)
    {
        $key = strtolower(trim((string)$level));
        $map = [
            'basic' => 'Basic',
            'standard' => 'Standard',
            'premium' => 'Premium',
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

// Obtener configuración del header desde la base de datos
$busca_header = mysqli_query($conexion,"SELECT * FROM offer_detail_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
if(mysqli_num_rows($busca_header) > 0) {
    $rst_header = mysqli_fetch_array($busca_header);
    $detail_header_title = $rst_header['title'];
    $detail_header_subtitle_1 = $rst_header['subtitle_1'];
    $detail_header_subtitle_2 = $rst_header['subtitle_2'];
    $bg_image = $rst_header['bg_image'];
} else {
    $detail_header_title = 'Medical Service Details';
    $detail_header_subtitle_1 = 'OFFER DETAILS';
    $detail_header_subtitle_2 = 'Complete information about your medical service';
    $bg_image = '';
}

// Obtener ID de la oferta
$offer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($offer_id == 0) {
    die('ID de oferta inválido');
}

// Consulta simplificada compatible con producción
$hasProviderVerification = false;
$pvTableCheck = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_verification'");
if ($pvTableCheck && mysqli_num_rows($pvTableCheck) > 0) {
    $hasProviderVerification = true;
}

if ($hasProviderVerification) {
    $verificationSelect = "COALESCE(pv.status, '') AS verification_status, COALESCE(pv.verification_level, '') AS verification_level,";
    $verificationJoin = "LEFT JOIN provider_verification pv ON pv.provider_id = p.id";
} else {
    $verificationSelect = "'' AS verification_status, '' AS verification_level,";
    $verificationJoin = "";
}

$query = "
    SELECT 
        o.id, o.title, o.description, o.price_from, o.currency,
        {$verificationSelect}
        p.id as provider_id, p.name as provider_name, 
        p.city, p.phone, p.email, p.logo
    FROM provider_service_offers o
    INNER JOIN providers p ON o.provider_id = p.id
    {$verificationJoin}
    WHERE o.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conexion, $query);
if (!$stmt) {
    die('Error en prepare: ' . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $offer_id);
if (!mysqli_stmt_execute($stmt)) {
    die('Error en execute: ' . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo '<!DOCTYPE html><html><head><title>Offer Not Found</title></head><body>';
    echo '<div style="max-width:800px;margin:100px auto;padding:40px;background:#f7fafc;border-radius:20px;text-align:center;">';
    echo '<h1 style="color:#667eea;margin-bottom:20px;">🔍 Oferta No Encontrada</h1>';
    echo '<p style="font-size:18px;color:#4a5568;margin-bottom:30px;">La oferta con ID <strong>' . $offer_id . '</strong> no existe o no está activa.</p>';
    echo '<a href="offers.php" style="display:inline-block;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:15px 40px;border-radius:30px;text-decoration:none;font-weight:bold;">← Ver Todas las Ofertas</a>';
    echo '</div></body></html>';
    mysqli_stmt_close($stmt);
    exit();
}

$offer = mysqli_fetch_assoc($result);
$verificationStatus = trim((string)($offer['verification_status'] ?? ''));
$verificationLevel = trim((string)($offer['verification_level'] ?? ''));
[$verificationBadgeKind, $verificationBadgeText] = provider_verification_public_badge($verificationStatus, $verificationLevel);

$offer_title = trim((string)($offer['title'] ?? ''));
if ($offer_title === '') {
    $offer_title = 'Medical Service Detail';
}
$page_title = $offer_title . ' | MedTravel';
$offer_description = trim(strip_tags((string)($offer['description'] ?? '')));
if ($offer_description === '') {
    $offer_description = 'Complete information about this medical service coordinated by MedTravel in Colombia.';
}
$page_description = mb_substr($offer_description, 0, 160, 'UTF-8');
$page_canonical = 'https://medtravel.com.co/offer_detail.php?id=' . (int)$offer_id;

$service_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Service',
    'name' => $offer_title,
    'description' => $offer_description,
    'url' => $page_canonical,
    'areaServed' => 'Colombia',
    'provider' => [
        '@type' => 'Organization',
        'name' => 'MedTravel',
        'url' => 'https://medtravel.com.co/',
    ],
];
$offer_price = isset($offer['price_from']) ? (float)$offer['price_from'] : 0;
$offer_currency = trim((string)($offer['currency'] ?? ''));
if ($offer_price > 0 && $offer_currency !== '') {
    $service_schema['offers'] = [
        '@type' => 'Offer',
        'price' => (string)$offer_price,
        'priceCurrency' => $offer_currency,
    ];
}
$page_schema_jsonld = [$service_schema];

include('inc/include.php');

$hideProviderDirectContact = false;
$feeGuardMessage = 'Unlock after Coordination Fee';
$contextBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0);
if ($contextBookingId > 0 && function_exists('is_client_session') && is_client_session() && isset($conexion) && $conexion) {
    $ownerScope = client_build_booking_owner_scope($conexion, 'br', get_client_user_id(), client_get_session_email());
    if (($ownerScope['sql'] ?? '1=0') !== '1=0') {
        $hideProviderDirectContact = mt_fee_booking_required_for_owner_scope($conexion, $contextBookingId, $ownerScope);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        .offer-hero {
            position: relative;
            padding: 80px 0;
            overflow: hidden;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            background-size: cover;
            background-position: center;
        }
        .hero-content {
            position: relative;
            color: white;
            z-index: 10;
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            color: white;
        }
        .hero-subtitle {
            font-size: 1rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
        .content-section {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 25px;
        }
        .section-heading {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-heading i {
            color: #0f766e;
            font-size: 1.3rem;
        }
        .price-card {
            background: white;
            border: 2px solid #0f766e;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.15);
            position: sticky;
            top: 100px;
        }
        .price-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .price-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f766e;
            margin: 15px 0;
        }
        .btn-book {
            background: #0f766e;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            border: none;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-book:hover {
            background: #0d9488;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.3);
        }
        .provider-card {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            margin-top: 20px;
        }
        .provider-card h3 {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .provider-card h3 i {
            color: #0f766e;
        }
        .provider-validation-chip {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            border: 1px solid #10b981;
            background: #d1fae5;
            color: #065f46;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .provider-validation-chip.review {
            color: #92400e;
            background: #fef3c7;
            border-color: #f59e0b;
        }
        .provider-validation-chip.level {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #60a5fa;
        }
        .contact-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 0;
            color: #475569;
            border-bottom: 1px solid #e5e7eb;
        }
        .contact-item:last-child {
            border-bottom: none;
        }
        .contact-item i {
            width: 35px;
            height: 35px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .contact-item strong {
            color: #334155;
            font-size: 13px;
            display: block;
            margin-bottom: 4px;
        }
        .contact-item a {
            color: #0f766e;
            text-decoration: none;
            font-weight: 500;
        }
        .contact-item a:hover {
            text-decoration: underline;
        }
        
        /* Galería de Fotos */
        .gallery-section {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 25px;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            aspect-ratio: 4/3;
        }
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .gallery-item::after {
            content: '🔍';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            font-size: 3rem;
            color: white;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            transition: transform 0.3s ease;
            pointer-events: none;
        }
        .gallery-item:hover::after {
            transform: translate(-50%, -50%) scale(1);
        }
        .gallery-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.7), rgba(20, 184, 166, 0.7));
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .gallery-item:hover::before {
            opacity: 1;
        }
        .no-photos {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        .no-photos i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        /* Estilos para contenido HTML formateado */
        .description-content {
            font-size: 1rem;
            line-height: 1.8;
            color: #475569;
        }
        .description-content p {
            margin-bottom: 1rem;
        }
        .description-content h1, 
        .description-content h2, 
        .description-content h3, 
        .description-content h4 {
            color: #1e293b;
            font-weight: 700;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .description-content h1 { font-size: 2rem; }
        .description-content h2 { font-size: 1.75rem; }
        .description-content h3 { font-size: 1.5rem; }
        .description-content h4 { font-size: 1.25rem; }
        .description-content ul,
        .description-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .description-content li {
            margin-bottom: 0.5rem;
        }
        .description-content strong,
        .description-content b {
            font-weight: 700;
            color: #334155;
        }
        .description-content em,
        .description-content i {
            font-style: italic;
        }
        .description-content a {
            color: #0f766e;
            text-decoration: underline;
        }
        .description-content a:hover {
            color: #0d9488;
        }
        .description-content blockquote {
            border-left: 4px solid #0f766e;
            padding-left: 1rem;
            margin: 1rem 0;
            color: #64748b;
            font-style: italic;
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

    <!-- Hero Section -->
    <div class="offer-hero" style="<?php 
        if (!empty($bg_image)) {
            echo 'background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url(' . htmlspecialchars($bg_image) . ');';
        }
    ?>">
        <div class="hero-content">
            <div class="container text-center">
                <p class="hero-subtitle mb-2"><?php echo htmlspecialchars($detail_header_subtitle_1); ?></p>
                <h1 class="hero-title"><?php echo htmlspecialchars($detail_header_title); ?></h1>
                <p class="hero-subtitle mt-2"><?php echo htmlspecialchars($detail_header_subtitle_2); ?></p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container" style="margin-top: 50px;">
        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Galería de Fotos -->
                <?php
                // Obtener fotos de la oferta
                $photos_query = mysqli_query($conexion, "SELECT id, path FROM offer_media WHERE offer_id = {$offer['id']} AND is_active = 1 ORDER BY sort_order ASC, id ASC");
                $photos = [];
                if ($photos_query) {
                    while ($photo = mysqli_fetch_assoc($photos_query)) {
                        $photos[] = $photo;
                    }
                }
                ?>
                
                <?php if (count($photos) > 0): ?>
                <div class="gallery-section">
                    <h2 class="section-heading">
                        <i class="fas fa-images"></i> 
                        Gallery
                    </h2>
                    <div class="gallery-grid">
                        <?php foreach ($photos as $photo): ?>
                            <a href="<?php echo htmlspecialchars($photo['path']); ?>" 
                               data-lightbox="offer-gallery" 
                               data-title="<?php echo htmlspecialchars($offer['title']); ?>" 
                               class="gallery-item">
                                <img src="<?php echo htmlspecialchars($photo['path']); ?>" 
                                     alt="<?php echo htmlspecialchars($offer['title']); ?>"
                                     loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Descripción -->
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-file-medical-alt"></i> 
                        Description
                    </h2>
                    <div class="description-content">
                        <?php 
                        // Decodificar entidades HTML y mostrar el contenido formateado
                        $description = html_entity_decode($offer['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        echo $description;
                        ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Price Card -->
                <div class="price-card">
                    <div class="price-label">Starting from</div>
                    <div class="price-amount">
                        <?php echo htmlspecialchars($offer['currency']); ?> 
                        <?php echo number_format($offer['price_from'], 2); ?>
                    </div>
                    <a href="#booking-section" class="btn btn-book" onclick="scrollToBooking(<?php echo $offer['id']; ?>); return false;">
                        <i class="fas fa-calendar-check me-2"></i>Book This Service
                    </a>
                    <?php if ($hideProviderDirectContact): ?>
                        <div class="alert alert-warning mt-2 mb-0" style="padding:10px 12px; font-size: 13px;">
                            <i class="fas fa-lock me-1"></i><?php echo htmlspecialchars($feeGuardMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php elseif (!empty($offer['email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($offer['email']); ?>" class="btn btn-outline-secondary mt-2" style="width: 100%; padding: 10px; text-align: center; display: block; text-decoration: none; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px;">
                            <i class="fas fa-envelope me-2"></i>Email Provider
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Provider Card -->
                <div class="provider-card">
                    <h3>
                        <i class="fas fa-hospital"></i>
                        <?php echo htmlspecialchars($offer['provider_name']); ?>
                    </h3>
                    <?php if ($verificationBadgeText !== ''): ?>
                        <div class="provider-validation-chip <?php echo htmlspecialchars($verificationBadgeKind, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-shield-alt"></i>
                            <?php echo htmlspecialchars($verificationBadgeText, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($offer['city'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong>Location</strong><br>
                                <?php echo htmlspecialchars($offer['city']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($hideProviderDirectContact): ?>
                        <div class="contact-item">
                            <i class="fas fa-lock"></i>
                            <div>
                                <strong>Contact</strong><br>
                                <?php echo htmlspecialchars($feeGuardMessage, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($offer['phone'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <strong>Phone</strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($offer['phone']); ?>">
                                        <?php echo htmlspecialchars($offer['phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($offer['email'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <strong>Email</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($offer['email']); ?>">
                                        <?php echo htmlspecialchars($offer['email']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="pb-5"></div>

    <!-- Booking Widget Start -->
    <?php echo $booking_widget; ?>
    <!-- Booking Widget End -->

    <!-- Footer Start -->
    <?php echo $footer; ?>
    <!-- Footer End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/lightbox/js/lightbox.min.js"></script>
    <?php echo $script; ?>
    <script src="js/main.js"></script>
    
    <script>
        // Remove spinner immediately and on load
        (function() {
            var spinner = document.getElementById('spinner');
            if (spinner) {
                setTimeout(function() {
                    spinner.classList.remove('show');
                    spinner.style.display = 'none';
                }, 500);
            }
        })();
        
        window.addEventListener('load', function() {
            var spinner = document.getElementById('spinner');
            if (spinner) {
                spinner.classList.remove('show');
                spinner.style.display = 'none';
            }
        });
        
        // Force hide after 2 seconds regardless
        setTimeout(function() {
            var spinner = document.getElementById('spinner');
            if (spinner) {
                spinner.classList.remove('show');
                spinner.style.display = 'none';
            }
        }, 2000);
    </script>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>

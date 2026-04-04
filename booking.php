<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page_title = 'Online Booking | MedTravel';
$page_description = 'Submit your medical travel request and receive a coordinated plan with trusted providers in Colombia.';
$page_canonical = 'https://medtravel.com.co/booking.php';
include('inc/include.php');
require_once __DIR__ . '/inc/booking_page_header.php';
// booking_form.php already included by include.php

// --- offer_id preseleccionado desde URL -----------------------------------------
// Valida la oferta contra provider_service_offers; ignora si es inválida o inactiva.
// $offer_id_in_url = true cuando el param está presente en la URL (sea válido o no).
// Necesario para limpiar storage si la oferta resultó inválida o inactiva.
$preselected_offer_id = 0;
$offer_id_in_url = !empty($_GET['offer_id']);
if ($offer_id_in_url) {
    $offer_id_raw = trim((string)$_GET['offer_id']);
    if (ctype_digit($offer_id_raw)) {
        $offer_id_int = (int)$offer_id_raw;
        if ($offer_id_int > 0) {
            $stmt_offer_check = mysqli_prepare(
                $conexion,
                'SELECT id FROM provider_service_offers WHERE id = ? AND is_active = 1 LIMIT 1'
            );
            if ($stmt_offer_check) {
                mysqli_stmt_bind_param($stmt_offer_check, 'i', $offer_id_int);
                if (mysqli_stmt_execute($stmt_offer_check)) {
                    $res_offer_check = mysqli_stmt_get_result($stmt_offer_check);
                    if ($res_offer_check && mysqli_num_rows($res_offer_check) > 0) {
                        $preselected_offer_id = $offer_id_int;
                    }
                }
                mysqli_stmt_close($stmt_offer_check);
            }
        }
    }
}

// --- Precarga de campos del formulario desde URL (solo para UX de campañas) ----
// No se persisten automáticamente sin interacción del usuario.
$preload = [];
$preload_name = '';
if (!empty($_GET['first_name'])) {
    $preload_name = substr(trim((string)$_GET['first_name']), 0, 100);
}
if (!empty($_GET['last_name'])) {
    $preload_name .= ($preload_name !== '' ? ' ' : '') . substr(trim((string)$_GET['last_name']), 0, 100);
}
if ($preload_name !== '') {
    $preload['name'] = $preload_name;
}
if (!empty($_GET['email'])) {
    $preload['email'] = substr(trim((string)$_GET['email']), 0, 255);
}
if (!empty($_GET['phone'])) {
    $preload['phone'] = substr(trim((string)$_GET['phone']), 0, 50);
}

// --- service_id pre-seleccionado desde URL ------------------------------------------
// Solo activo si offer_id NO está en la URL (offer_id tiene precedencia).
// Valida que el servicio exista en service_catalog y tenga al menos una oferta activa.
$preselected_service_id = 0;
$service_id_in_url = !empty($_GET['service_id']);
if ($service_id_in_url && !$offer_id_in_url) {
    $service_id_raw = trim((string)$_GET['service_id']);
    if (ctype_digit($service_id_raw)) {
        $service_id_int = (int)$service_id_raw;
        if ($service_id_int > 0) {
            $stmt_service_check = mysqli_prepare(
                $conexion,
                'SELECT sc.id FROM service_catalog sc
                 INNER JOIN provider_service_offers pso ON pso.service_id = sc.id AND pso.is_active = 1
                 WHERE sc.id = ? LIMIT 1'
            );
            if ($stmt_service_check) {
                mysqli_stmt_bind_param($stmt_service_check, 'i', $service_id_int);
                if (mysqli_stmt_execute($stmt_service_check)) {
                    $res_service = mysqli_stmt_get_result($stmt_service_check);
                    if ($res_service && mysqli_num_rows($res_service) > 0) {
                        $preselected_service_id = $service_id_int;
                    }
                }
                mysqli_stmt_close($stmt_service_check);
            }
        }
    }
}

// --- UTM params para tracking client-side (se sincronizan vía JS más abajo) ---
$booking_utm = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utmKey) {
    if (!empty($_GET[$utmKey])) {
        $booking_utm[$utmKey] = substr(trim((string)$_GET[$utmKey]), 0, 255);
    }
}
// -------------------------------------------------------------------------------

$booking_texts = get_booking_texts();
$booking_style = booking_background_style($booking_texts);
$bookingPageHeader = mt_booking_page_header_fetch($conexion);
$bookingHeaderTitle = trim((string)($bookingPageHeader['title'] ?? '')) ?: 'Online Booking';
$bookingHeaderSubtitle = trim((string)($bookingPageHeader['subtitle'] ?? '')) ?: 'Submit your medical travel request and receive a coordinated plan with trusted providers in Colombia.';
$bookingHeaderImage = trim((string)($bookingPageHeader['bg_image'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php echo $head; ?>
</head>

<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Navbar & Hero Start -->
    <div class="container-fluid position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
            <?php echo $logo; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <?php echo $menu; ?>
        </nav>
    </div>
    <!-- Navbar & Hero End -->

    <div class="container-fluid bg-breadcrumb" <?php if ($bookingHeaderImage !== ''): ?>style="background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('<?php echo htmlspecialchars($bookingHeaderImage, ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
        <div class="container text-center py-5" style="max-width: 900px;">
            <h3 class="text-white display-3 mb-3"><?php echo htmlspecialchars($bookingHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="text-white mb-4"><?php echo htmlspecialchars($bookingHeaderSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
            <ol class="breadcrumb justify-content-center mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="how-medtravel-works.php">How It Works</a></li>
                <li class="breadcrumb-item active text-white">Online Booking</li>
            </ol>
        </div>
    </div>

    <!-- Tour Booking Start -->
    <div class="container-fluid booking py-5" <?php echo $booking_style; ?>>
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                <h5 class="section-booking-title pe-3">Booking</h5>
                <h1 class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['intro_title']); ?></h1>
                <p class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['intro_paragraph']); ?></p>
                <p class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['secondary_paragraph']); ?></p>
                    <div class="p-3 rounded mb-3" style="background: rgba(255,255,255,0.12); border-left: 3px solid rgba(255,193,7,0.9);">
                        <p class="text-white mb-2"><strong>What MedTravel does:</strong> case coordination, provider matching support, scheduling guidance, and travel logistics support.</p>
                        <p class="text-white mb-0"><strong>What MedTravel does not do:</strong> direct medical treatment, clinical diagnosis, or medical decision-making.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="booking.php#booking-section" class="btn btn-light text-primary rounded-pill py-3 px-4">Request care coordination</a>
                        <a href="specialists.php" class="btn btn-outline-light rounded-pill py-3 px-4">View specialists</a>
                        <a href="services.php" class="btn btn-outline-light rounded-pill py-3 px-4">View services</a>
                        <a href="faq.php" class="btn btn-outline-light rounded-pill py-3 px-4">Review FAQ</a>
                        <a href="how-medtravel-works.php" class="btn btn-outline-light rounded-pill py-3 px-4">How it works</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h1 class="text-white mb-3"><?php echo htmlspecialchars($booking_texts['form_title']); ?></h1>
                    <p class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['form_paragraph']); ?></p>
                    <?php render_booking_form('booking_page', $preselected_offer_id, $preload, $preselected_service_id); ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Tour Booking End -->

    <!-- Subscribe Start -->
    <div class="container-fluid subscribe py-5">
        <div class="container text-center py-5">
            <div class="mx-auto text-center" style="max-width: 900px;">
                <h5 class="subscribe-title px-3">Need Help Now?</h5>
                <h1 class="text-white mb-4">Request Assistance From a MedTravel Coordinator</h1>
                <p class="text-white mb-5">Questions about providers, travel logistics, or treatment planning? Our team can guide you before you submit your request.</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="contact.php" class="btn btn-primary rounded-pill py-3 px-5">Request assistance</a>
                    <a href="https://wa.me/573502431667" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light rounded-pill py-3 px-5">Start your case review</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Subscribe End -->

    <!-- Footer Start -->
    <?php echo $footer; ?>
    <!-- Footer End -->

    <!-- Copyright Start -->
    <?php echo $copyright; ?>
    <!-- Copyright End -->
    <!-- Back to Top -->
    <a href="#" class="btn btn-primary btn-primary-outline-0 btn-md-square back-to-top"><i class="fa fa-arrow-up"></i></a>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/lightbox/js/lightbox.min.js"></script>
    <?php echo $script; ?>

    <!-- Template Javascript -->
    <script src="<?php echo htmlspecialchars(mt_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

    <?php if ($offer_id_in_url || $service_id_in_url || !empty($booking_utm)): ?>
    <!-- offer_id / service_id + UTM sync a localStorage/sessionStorage (generado server-side) -->
    <script>
    (function () {
        "use strict";
        try {
            <?php if ($preselected_offer_id > 0): ?>
            // Caso A: offer_id válido y activo — imponer; limpiar cualquier service_id previo.
            localStorage.setItem("mt_preselected_offer_id", "<?php echo $preselected_offer_id; ?>");
            sessionStorage.setItem("preselected_offer_id", "<?php echo $preselected_offer_id; ?>");
            localStorage.removeItem("mt_preselected_service_id");
            sessionStorage.removeItem("preselected_service_id");
            localStorage.setItem("mt_booking_started", "1");
            window.dispatchEvent(new Event("mt-booking-state-changed"));
            <?php elseif ($offer_id_in_url): ?>
            // Caso B: offer_id presente en URL pero inválido o inactivo —
            // limpiar storage para evitar heredar oferta o servicio viejo contaminado.
            localStorage.removeItem("mt_preselected_offer_id");
            sessionStorage.removeItem("preselected_offer_id");
            localStorage.removeItem("mt_preselected_service_id");
            sessionStorage.removeItem("preselected_service_id");
            <?php endif; ?>
            <?php if (!$offer_id_in_url && $preselected_service_id > 0): ?>
            // Caso C: service_id válido (offer_id no en URL) — pre-seleccionar servicio.
            localStorage.setItem("mt_preselected_service_id", "<?php echo $preselected_service_id; ?>");
            sessionStorage.setItem("preselected_service_id", "<?php echo $preselected_service_id; ?>");
            localStorage.setItem("mt_booking_started", "1");
            window.dispatchEvent(new Event("mt-booking-state-changed"));
            <?php elseif (!$offer_id_in_url && $service_id_in_url): ?>
            // Caso D: service_id presente pero inválido o sin ofertas activas — limpiar.
            localStorage.removeItem("mt_preselected_service_id");
            sessionStorage.removeItem("preselected_service_id");
            <?php endif; ?>
            <?php foreach ($booking_utm as $utmKey => $utmVal): ?>
            sessionStorage.setItem("mt_<?php echo htmlspecialchars($utmKey, ENT_QUOTES, 'UTF-8'); ?>", "<?php echo htmlspecialchars($utmVal, ENT_QUOTES, 'UTF-8'); ?>");
            <?php endforeach; ?>
        } catch (e) {}
    }());
    </script>
    <?php endif; ?>
</body>

</html>

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
$preselected_offer_id = 0;
if (!empty($_GET['offer_id'])) {
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
                <li class="breadcrumb-item"><a href="#">Pages</a></li>
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
                    <a href="#" class="btn btn-light text-primary rounded-pill py-3 px-5 mt-2">Read More</a>
                </div>
                <div class="col-lg-6">
                    <h1 class="text-white mb-3"><?php echo htmlspecialchars($booking_texts['form_title']); ?></h1>
                    <p class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['form_paragraph']); ?></p>
                    <?php render_booking_form('booking_page', $preselected_offer_id, $preload); ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Tour Booking End -->

    <!-- Subscribe Start -->
    <div class="container-fluid subscribe py-5">
        <div class="container text-center py-5">
            <div class="mx-auto text-center" style="max-width: 900px;">
                <h5 class="subscribe-title px-3">Subscribe</h5>
                <h1 class="text-white mb-4">Our Newsletter</h1>
                <p class="text-white mb-5">Lorem ipsum dolor sit amet consectetur adipisicing elit. Laborum tempore nam, architecto doloremque velit explicabo? Voluptate sunt eveniet fuga eligendi! Expedita laudantium fugiat corrupti eum cum repellat a laborum quasi.</p>
                <div class="position-relative mx-auto">
                    <input class="form-control border-primary rounded-pill w-100 py-3 ps-4 pe-5" type="text" placeholder="Your email">
                    <button type="button" class="btn btn-primary rounded-pill position-absolute top-0 end-0 py-2 px-4 mt-2 me-2">Subscribe</button>
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

    <?php if ($preselected_offer_id > 0 || !empty($booking_utm)): ?>
    <!-- offer_id + UTM sync a localStorage/sessionStorage (generado server-side) -->
    <script>
    (function () {
        "use strict";
        try {
            <?php if ($preselected_offer_id > 0): ?>
            // Sobreescribir cualquier oferta previa en storage con la del URL actual.
            localStorage.setItem("mt_preselected_offer_id", "<?php echo $preselected_offer_id; ?>");
            sessionStorage.setItem("preselected_offer_id", "<?php echo $preselected_offer_id; ?>");
            localStorage.setItem("mt_booking_started", "1");
            window.dispatchEvent(new Event("mt-booking-state-changed"));
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

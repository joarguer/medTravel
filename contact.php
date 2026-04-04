<?php
$page_title = 'Contact MedTravel | Medical Travel Coordination';
$page_description = 'Contact MedTravel for questions about medical tourism coordination, providers, and booking support in Colombia.';
$page_canonical = 'https://medtravel.com.co/contact.php';
include('inc/include.php');
require_once __DIR__ . '/inc/contact_header.php';
$contactHeader = mt_contact_header_fetch($conexion);
$contactHeaderTitle = trim((string)($contactHeader['title'] ?? '')) ?: 'Contact Us';
$contactHeaderSubtitle = trim((string)($contactHeader['subtitle'] ?? '')) ?: 'Talk to MedTravel about providers, coordination, and booking support for your medical journey.';
$contactHeaderImage = trim((string)($contactHeader['bg_image'] ?? ''));
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

        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" <?php if ($contactHeaderImage !== ''): ?>style="background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('<?php echo htmlspecialchars($contactHeaderImage, ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
            <div class="container text-center py-5" style="max-width: 900px;">
                <h3 class="text-white display-3 mb-3"><?php echo htmlspecialchars($contactHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="text-white mb-4"><?php echo htmlspecialchars($contactHeaderSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Pages</a></li>
                    <li class="breadcrumb-item active text-white">Contact</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->

        <!-- Contact Start -->
        <?php echo $contact; ?>
        <!-- Contact End -->

        <!-- Booking Widget Start -->
        <?php echo $booking_widget; ?>
        <!-- Booking Widget End -->

        <!-- Subscribe Start -->
        <div class="container-fluid subscribe py-5">
            <div class="container text-center py-5">
                <div class="mx-auto text-center" style="max-width: 900px;">
                        <h5 class="subscribe-title px-3">Need Immediate Support?</h5>
                        <h1 class="text-white mb-4">We Are Ready to Help</h1>
                        <p class="text-white mb-5">Reach out by WhatsApp for quick guidance, or go to booking to submit your medical travel request.</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="https://wa.me/573502431667" target="_blank" rel="noopener noreferrer" class="btn btn-primary rounded-pill py-3 px-5">Chat on WhatsApp</a>
                            <a href="booking.php#booking-section" class="btn btn-outline-light rounded-pill py-3 px-5">Start Booking</a>
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
    </body>

</html>

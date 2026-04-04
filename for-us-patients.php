<?php
$page_title = 'For U.S. Patients | MedTravel Coordination Support';
$page_description = 'Learn how MedTravel supports U.S. patients with bilingual medical travel coordination, planning, and logistics in Colombia.';
$page_canonical = 'https://medtravel.com.co/for-us-patients.php';
$page_schema_jsonld = [[
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'For U.S. Patients',
    'description' => 'Landing page for U.S. patients exploring coordinated medical travel planning in Colombia.',
    'isPartOf' => ['@id' => 'https://medtravel.com.co/#website'],
    'about' => ['@id' => 'https://medtravel.com.co/#organization'],
]];
include(__DIR__ . '/inc/include.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
</head>
<body>
    <div class="container-fluid position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
            <?php echo $logo; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <?php echo $menu; ?>
        </nav>
    </div>

    <div class="container-fluid bg-breadcrumb">
        <div class="container text-center py-5" style="max-width: 900px;">
            <h1 class="text-white display-4 mb-3">For U.S. Patients</h1>
            <p class="text-white mb-0">Bilingual coordination support for patients planning treatment-related travel to Colombia.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Designed for international patient coordination</h2>
                    <p class="mb-2">MedTravel supports U.S.-based patients with bilingual communication, planning guidance, and logistics coordination.</p>
                    <p class="mb-3"><strong>Role clarity:</strong> MedTravel is a coordinator and facilitator, not a medical provider.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Request care coordination</a>
                        <a href="/contact.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Request assistance</a>
                        <a href="/specialists.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View specialists</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">Bilingual support</h3>
                    <p class="mb-0">Clear communication in English and Spanish for better decision support and family alignment.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">Structured next steps</h3>
                    <p class="mb-0">Submit your case, receive a coordination review, and follow a transparent process.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">Travel-aware coordination</h3>
                    <p class="mb-0">Planning support includes practical logistics around timing and destination preparation.</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-5">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Related pages</h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/services.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View services</a>
                        <a href="/how-medtravel-works.php" class="btn btn-outline-primary rounded-pill py-2 px-4">How MedTravel Works</a>
                        <a href="/faq.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Read FAQ</a>
                        <a href="/medical-travel-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Medical Travel Colombia</a>
                        <a href="/medical-travel-armenia-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Armenia, Colombia</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php echo $footer; ?>
    <?php echo $copyright; ?>

    <a href="#" class="btn btn-primary btn-primary-outline-0 btn-md-square back-to-top"><i class="fa fa-arrow-up"></i></a>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/lightbox/js/lightbox.min.js"></script>
    <?php echo $script; ?>
    <script src="js/main.js"></script>
</body>
</html>

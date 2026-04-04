<?php
$page_title = 'Medical Travel Colombia Planning | MedTravel Coordination';
$page_description = 'National-level coordination for medical travel in Colombia, including case intake guidance, provider communication flow, and logistics planning.';
$page_canonical = 'https://medtravel.com.co/medical-travel-colombia.php';
$page_schema_jsonld = [[
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'Medical Travel in Colombia',
    'description' => 'Commercial landing for medical travel coordination in Colombia through MedTravel.',
    'isPartOf' => ['@id' => 'https://medtravel.com.co/#website'],
    'about' => ['@id' => 'https://medtravel.com.co/#organization'],
]];
include(__DIR__ . '/inc/include.php');
require_once __DIR__ . '/inc/testimonials.php';
$landing_testimonials = mt_testimonials_fetch_approved($conexion, 2);
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
            <h1 class="text-white display-4 mb-3">Medical Travel Colombia Planning</h1>
            <p class="text-white mb-0">National coordination support for international patients evaluating independent providers in Colombia.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">National coordination path for Colombia</h2>
                    <p class="mb-2">MedTravel coordinates your case review journey, including communication, timeline planning, and logistics guidance.</p>
                    <p class="mb-3"><strong>Important:</strong> MedTravel is not a hospital or clinic. Clinical treatment is provided by independent providers and specialists.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Request care coordination</a>
                        <a href="/for-us-patients.php" class="btn btn-outline-primary rounded-pill py-2 px-4">For U.S. patients</a>
                        <a href="/services.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View services</a>
                        <a href="/specialists.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View specialists</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">Case review first</h3>
                    <p class="mb-0">Share your goals and timing so coordination starts with clear expectations.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">Provider-aligned planning</h3>
                    <p class="mb-0">We support communication flow with independent providers for next-step organization.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">Travel logistics coordination</h3>
                    <p class="mb-0">Get practical guidance for trip timing and logistics around your treatment process.</p>
                </div>
            </div>
        </div>

        <?php if (!empty($landing_testimonials)) { ?>
        <div class="row justify-content-center mt-4">
            <div class="col-lg-10">
                <div class="p-4 bg-white border rounded">
                    <h2 class="h5 mb-3">Patient trust signals</h2>
                    <div class="row g-3">
                        <?php foreach ($landing_testimonials as $testimonial) { ?>
                        <div class="col-md-6">
                            <div class="p-3 h-100 bg-light rounded border">
                                <div class="mb-2"><?php echo mt_testimonials_render_stars((int)($testimonial['rating'] ?? 5)); ?></div>
                                <p class="mb-1 text-muted">"<?php echo mt_testimonials_escape((string)($testimonial['comment'] ?? '')); ?>"</p>
                                <p class="mb-0 small text-secondary"><?php echo mt_testimonials_escape((string)($testimonial['client_name'] ?? 'Patient')); ?></p>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <div class="row justify-content-center mt-5">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Explore related resources</h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/how-medtravel-works.php" class="btn btn-outline-primary rounded-pill py-2 px-4">How MedTravel Works</a>
                        <a href="/faq.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Read FAQ</a>
                        <a href="/for-us-patients.php" class="btn btn-outline-primary rounded-pill py-2 px-4">For U.S. Patients</a>
                        <a href="/medical-travel-armenia-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Armenia, Colombia</a>
                        <a href="/contact.php" class="btn btn-primary rounded-pill py-2 px-4">Request assistance</a>
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

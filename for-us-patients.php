<?php
$page_title = 'For U.S. Patients Seeking Care Coordination in Colombia | MedTravel';
$page_description = 'Audience-focused guidance for U.S. patients, with bilingual support, clear intake steps, and travel-aware coordination in Colombia.';
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
require_once __DIR__ . '/inc/testimonials.php';
$us_testimonials = mt_testimonials_fetch_approved($conexion, 2);
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
            <p class="text-white mb-0">U.S.-focused bilingual coordination for patients planning treatment-related travel to Colombia.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Built for U.S.-based patients and families</h2>
                    <p class="mb-2">MedTravel supports U.S.-based patients with bilingual communication, timeline guidance, and logistics coordination before travel.</p>
                    <p class="mb-3"><strong>Role clarity:</strong> MedTravel is a coordinator and facilitator, not a medical provider.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Request care coordination</a>
                        <a href="/medical-travel-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Medical Travel Colombia</a>
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

        <?php if (!empty($us_testimonials)) { ?>
        <div class="row justify-content-center mt-4">
            <div class="col-lg-10">
                <div class="p-4 bg-white border rounded">
                    <h2 class="h5 mb-3">What patients value in coordination</h2>
                    <div class="row g-3">
                        <?php foreach ($us_testimonials as $testimonial) { ?>
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

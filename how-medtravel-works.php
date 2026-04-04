<?php
$page_title = 'How MedTravel Works | Medical Travel Coordination Process';
$page_description = 'Understand the MedTravel process from request and review to provider coordination, travel planning, and follow-up support.';
$page_canonical = 'https://medtravel.com.co/how-medtravel-works.php';
$page_schema_jsonld = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => 'https://medtravel.com.co/how-medtravel-works.php#webpage',
        'name' => 'How MedTravel Works',
        'description' => 'Step-by-step process explaining how MedTravel coordinates international patients with independent providers in Colombia.',
        'isPartOf' => ['@id' => 'https://medtravel.com.co/#website'],
        'about' => ['@id' => 'https://medtravel.com.co/#organization'],
    ],
];
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
            <h1 class="text-white display-4 mb-3">How MedTravel Works</h1>
            <p class="text-white mb-0">A clear process for medical travel coordination from Florida to Colombia.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-10">
                <div class="alert alert-primary" role="alert">
                    <strong>Important:</strong> MedTravel coordinates your medical travel process. MedTravel is not a hospital or clinic, and does not provide direct medical treatment.
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <div class="col-lg-10">
                <div class="p-4 border rounded bg-white mb-4">
                    <h3 class="mb-3">1. Request</h3>
                    <p class="mb-0">You submit your goals, preferred dates, and any relevant case details through our booking flow.</p>
                </div>

                <div class="p-4 border rounded bg-white mb-4">
                    <h3 class="mb-3">2. Review</h3>
                    <p class="mb-0">Our coordination team reviews your request for operational planning and identifies the right path to continue your process.</p>
                </div>

                <div class="p-4 border rounded bg-white mb-4">
                    <h3 class="mb-3">3. Coordination with Providers</h3>
                    <p class="mb-0">MedTravel coordinates communication with independent specialists and clinics. Providers are responsible for clinical evaluation, decisions, and treatment.</p>
                </div>

                <div class="p-4 border rounded bg-white mb-4">
                    <h3 class="mb-3">4. Travel and Treatment Planning</h3>
                    <p class="mb-0">We support scheduling, logistics, and travel planning so your treatment process is organized before your trip.</p>
                </div>

                <div class="p-4 border rounded bg-white mb-4">
                    <h3 class="mb-3">5. Follow-up Support</h3>
                    <p class="mb-0">After key milestones, MedTravel continues supporting communication and coordination steps as needed.</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-4">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h4 class="mb-3">Ready to begin?</h4>
                    <p class="mb-3">Start your request and our team will guide your next coordination steps.</p>
                    <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4 me-2">Request care coordination</a>
                    <a href="/contact.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">Request assistance</a>
                    <a href="/specialists.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">View specialists</a>
                    <a href="/faq.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">Read FAQ</a>
                    <a href="/services.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View services</a>
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

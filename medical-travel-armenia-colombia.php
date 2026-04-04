<?php
$page_title = 'Medical Travel in Armenia, Colombia | MedTravel';
$page_description = 'Explore medical travel coordination in Armenia, Colombia with MedTravel support for case review, scheduling, and patient logistics.';
$page_canonical = 'https://medtravel.com.co/medical-travel-armenia-colombia.php';
$page_schema_jsonld = [[
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'Medical Travel in Armenia, Colombia',
    'description' => 'Landing page for Armenia, Colombia focused coordination services for international patients.',
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
            <h1 class="text-white display-4 mb-3">Medical Travel in Armenia, Colombia</h1>
            <p class="text-white mb-0">A practical coordination path for patients exploring care options in the Coffee Region.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Local destination, coordinated process</h2>
                    <p class="mb-2">MedTravel helps structure your planning for providers and services in Armenia, Colombia, with coordination focused on timeline clarity and patient logistics.</p>
                    <p class="mb-3"><strong>Medical boundary:</strong> MedTravel coordinates. Independent providers and specialists deliver treatment and clinical decisions.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Start your case review</a>
                        <a href="/services.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View services</a>
                        <a href="/specialists.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View specialists</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">What you can prepare in advance</h3>
                    <p class="mb-0">Your preferred travel window, service priorities, and practical constraints help speed up coordination.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-4 h-100 border rounded bg-white">
                    <h3 class="h5">What happens next</h3>
                    <p class="mb-0">After intake, our team follows up to align next steps with independent providers and your travel plan.</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-5">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Continue exploring</h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/how-medtravel-works.php" class="btn btn-outline-primary rounded-pill py-2 px-4">How MedTravel Works</a>
                        <a href="/faq.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Read FAQ</a>
                        <a href="/medical-travel-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Medical Travel Colombia</a>
                        <a href="/for-us-patients.php" class="btn btn-outline-primary rounded-pill py-2 px-4">For U.S. Patients</a>
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

<?php
include(__DIR__ . '/inc/include.php');
$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.0';
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
            <h3 class="text-white display-3 mb-3">Terms of Service</h3>
            <p class="text-white mb-0">Medical Disclaimer and Platform Terms (Version <?php echo htmlspecialchars($termsVersion); ?>)</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h4>Summary</h4>
                <p>MedTravel is a coordination platform that connects patients with independent medical providers and travel services. We do not deliver medical care, and we do not guarantee medical outcomes.</p>

                <h4>1. Platform Role</h4>
                <p>MedTravel facilitates introductions, scheduling, and coordination. We are not a hospital, clinic, physician, or medical provider.</p>

                <h4>2. No Medical Services</h4>
                <p>MedTravel does not provide medical advice, diagnosis, or treatment. All medical services are provided by independent third-party providers who are solely responsible for their services.</p>

                <h4>3. No Outcome Guarantee</h4>
                <p>Medical results vary by individual and procedure. MedTravel makes no guarantees or warranties regarding outcomes, recovery, or suitability of any treatment.</p>

                <h4>4. Third-Party Providers</h4>
                <p>Providers listed on MedTravel are independent entities. You are responsible for evaluating providers and making informed decisions about care.</p>

                <h4>5. Limitation of Liability</h4>
                <p>To the maximum extent permitted by law, MedTravel is not liable for any medical outcomes, complications, or damages arising from services provided by third parties.</p>

                <h4>6. Governing Law</h4>
                <p>These terms are governed by the laws of the United States (general jurisdiction to be specified in a future update).</p>

                <p class="text-muted mt-4">If you have questions about these terms, contact us before submitting a booking request.</p>
            </div>
        </div>
    </div>

    <?php echo $footer; ?>
    <?php echo $copyright; ?>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php echo $script; ?>
    <script src="js/main.js"></script>
</body>
</html>

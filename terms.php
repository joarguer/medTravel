<?php
include(__DIR__ . '/inc/include.php');
$termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : 'v1.1';
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
            <h3 class="text-white display-3 mb-3">Terms of Service &amp; Medical Disclaimer</h3>
            <p class="text-white mb-0">Version: <?php echo htmlspecialchars($termsVersion); ?></p>
            <p class="text-white mb-0">Effective Date: 2026-02-21</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h4>Summary</h4>
                <p>MedTravel is a coordination and facilitation platform that connects patients with independent healthcare providers and related travel services. MedTravel does not provide medical care and does not guarantee medical outcomes.</p>

                <hr>

                <h4>1. Platform Role (No Provider Status)</h4>
                <p>MedTravel acts solely as a coordination and introduction platform. MedTravel is not a hospital, clinic, medical practice, physician group, travel agency, or healthcare provider. No medical services are provided by MedTravel.</p>

                <hr>

                <h4>2. No Medical Advice or Medical Relationship</h4>
                <p>MedTravel does not provide medical advice, diagnosis, or treatment. Use of this platform does not create a doctor-patient relationship between you and MedTravel. Any medical relationship is exclusively between you and the independent provider you select.</p>

                <hr>

                <h4>3. Independent Third-Party Providers</h4>
                <p>All healthcare providers and travel service providers listed on MedTravel are independent third parties. MedTravel does not control, supervise, or direct medical decisions or treatment protocols. Providers are solely responsible for the services they render.</p>

                <hr>

                <h4>4. Assumption of Risk</h4>
                <p>You understand that medical procedures carry inherent risks, including complications, dissatisfaction with outcomes, or unforeseen medical conditions. By submitting a booking request, you acknowledge and accept these risks.</p>

                <hr>

                <h4>5. No Guarantee of Outcomes</h4>
                <p>Medical results vary based on individual conditions, provider judgment, and other factors. MedTravel makes no representations or warranties regarding:</p>
                <ul>
                    <li>Treatment suitability</li>
                    <li>Medical outcomes</li>
                    <li>Recovery time</li>
                    <li>Travel safety</li>
                    <li>Financial expectations</li>
                </ul>

                <hr>

                <h4>6. Limitation of Liability</h4>
                <p>To the fullest extent permitted by law, MedTravel shall not be liable for:</p>
                <ul>
                    <li>Medical malpractice</li>
                    <li>Complications</li>
                    <li>Provider negligence</li>
                    <li>Travel disruptions</li>
                    <li>Personal injury</li>
                    <li>Loss of income</li>
                    <li>Emotional distress</li>
                    <li>Indirect, incidental, or consequential damages</li>
                </ul>
                <p>MedTravel's liability, if any, shall not exceed the coordination fee paid to MedTravel.</p>

                <hr>

                <h4>7. No Emergency Services</h4>
                <p>MedTravel does not provide emergency medical services. In case of emergency, you must contact local emergency services immediately.</p>

                <hr>

                <h4>8. User Representations</h4>
                <p>By submitting a booking request, you represent that:</p>
                <ul>
                    <li>You are at least 18 years old.</li>
                    <li>You have the legal capacity to enter into agreements.</li>
                    <li>The information you provide is accurate and truthful.</li>
                </ul>

                <hr>

                <h4>9. Indemnification</h4>
                <p>You agree to indemnify and hold harmless MedTravel from any claims, damages, or disputes arising from services provided by third-party providers.</p>

                <hr>

                <h4>10. Governing Law and Dispute Resolution</h4>
                <p>These Terms shall be governed by the laws of the United States. Any disputes shall be resolved through binding arbitration unless otherwise required by law. Class action claims are waived to the extent permitted.</p>

                <hr>

                <h4>11. Electronic Acceptance</h4>
                <p>By checking the acceptance box and submitting a booking request, you acknowledge that this constitutes a legally binding electronic signature.</p>
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

<?php
$page_title = 'MedTravel | Privacy Policy';
$page_description = 'Learn how MedTravel collects, uses, and shares information for medical coordination services, including cross-border data transfers and payment processing.';
$page_canonical = 'https://medtravel.com.co/privacy/';
$page_meta_extra = '<base href="/">';
include(__DIR__ . '/../inc/include.php');

// Version string (do NOT break if constants missing)
$privacy_version = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : 'v1.2-FL-USA';
$effective_date = 'April 1, 2026';
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

        <!-- Navbar Start -->
        <div class="container-fluid position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
                <?php echo $logo; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <?php echo $menu; ?>
            </nav>
        </div>
        <!-- Navbar End -->

        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h1 class="text-white display-4 mb-3">Privacy Policy</h1>
                <p class="text-white-50 mb-0">
                    Version: <?php echo htmlspecialchars($privacy_version); ?> · Effective Date: <?php echo htmlspecialchars($effective_date); ?>
                </p>
            </div>
        </div>
        <!-- Header End -->

        <!-- Privacy Content Start -->
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <div class="bg-white p-4 p-md-5 shadow-sm rounded">

                        <h2 class="mb-2">Privacy Policy</h2>
                        <p class="text-muted mb-4">
                            <strong>Version:</strong> <?php echo htmlspecialchars($privacy_version); ?><br>
                            <strong>Effective Date:</strong> <?php echo htmlspecialchars($effective_date); ?>
                        </p>

                        <h3 class="mt-4">1. Scope and Role</h3>
                        <p>
                            MedTravel ("Company," "we," "us," or "our") is a medical coordination platform operating from Florida, United States.
                            We connect patients with independent healthcare providers and related travel services. This Privacy Policy describes how we collect,
                            use, and share information when you use our platform.
                        </p>

                        <h3 class="mt-4">2. Information We Collect</h3>
                        <p>Depending on your request, we may collect:</p>
                        <ul>
                            <li>Contact details (name, email, phone).</li>
                            <li>Identity details you choose to provide (date of birth, passport or government ID).</li>
                            <li>Medical and health-related information you voluntarily submit.</li>
                            <li>Travel and coordination details (destination, dates, preferences, budget).</li>
                            <li>Technical data (IP address, user agent, device/browser information, access timestamps).</li>
                            <li>Payment and transaction data for coordination fees (processed by third parties).</li>
                        </ul>

                        <h3 class="mt-4">3. How We Use Information</h3>
                        <ul>
                            <li>Coordinate services with independent providers you select.</li>
                            <li>Communicate with you about requests, updates, or missing information.</li>
                            <li>Process coordination fees and manage payment support.</li>
                            <li>Prevent fraud, misuse, and security incidents.</li>
                            <li>Improve platform functionality and reliability.</li>
                            <li>Comply with applicable laws and lawful requests.</li>
                        </ul>

                        <h3 class="mt-4">4. Medical Information Handling</h3>
                        <p>
                            MedTravel does not provide medical advice, diagnosis, or treatment. We are not a healthcare provider and are not a HIPAA covered entity
                            or business associate. Medical information you submit is used solely for coordination purposes, including intake processing, matching your
                            request with appropriate providers, appointment scheduling, and operational support.
                        </p>
                        <p>
                            MedTravel does not clinically interpret any medical information you provide. Information is shared with independent providers solely
                            at your direction and to the extent necessary to facilitate your request. Independent providers may, under their own legal or clinical
                            obligations, request additional documentation from you directly.
                        </p>

                        <h3 class="mt-4">5. Sharing with Providers and Third Parties</h3>
                        <p>We may share information with:</p>
                        <ul>
                            <li>Independent healthcare providers and travel service providers you choose.</li>
                            <li>Payment processors and financial institutions involved in coordination fees.</li>
                            <li>Technology vendors that support hosting, security, or communications.</li>
                            <li>Legal authorities when required by law.</li>
                        </ul>
                        <p>We do not sell personal information.</p>

                        <h3 class="mt-4">6. Payments, Commissions, and Chargebacks</h3>
                        <p>
                            MedTravel operates on a coordination fee or commission model. Payments are processed by U.S.-based payment processors.
                            We do not store full payment card numbers. Chargebacks or payment disputes may require sharing transaction data with processors.
                        </p>

                        <h3 class="mt-4">7. International Data Transfers</h3>
                        <p>
                            MedTravel's coordination activities involve providers and services across multiple jurisdictions, primarily the United States and Colombia.
                            Your information may be transferred to, stored in, or processed in these or other countries as necessary to coordinate your care,
                            process bookings, communicate with providers, or deliver operational support. Data protection standards vary across jurisdictions.
                            By using the platform, you expressly acknowledge and consent to these transfers.
                        </p>

                        <h3 class="mt-4">8. Cookies and Tracking</h3>
                        <p>
                            We use cookies and similar technologies to improve performance, security, and user experience. You can control cookies
                            through your browser settings.
                        </p>

                        <h3 class="mt-4">9. Data Retention</h3>
                        <p>
                            We retain information for as long as necessary to provide services, comply with legal obligations, resolve disputes,
                            and enforce agreements.
                        </p>

                        <h3 class="mt-4">10. Security</h3>
                        <p>
                            We implement reasonable technical and organizational safeguards to protect information. No system is completely secure,
                            and we cannot guarantee absolute security.
                        </p>

                        <h3 class="mt-4">11. Security Incidents and Breach Notification</h3>
                        <p>
                            In the event of a security incident that affects your personal information, MedTravel will take reasonable steps to assess and contain
                            the incident. Where required by applicable law, we will notify affected users and/or relevant authorities within a reasonable timeframe
                            and in the manner required by law. The scope and timing of any notification will depend on the nature of the incident and the legal
                            requirements of the applicable jurisdiction.
                        </p>

                        <h3 class="mt-4">12. Your Choices and Rights</h3>
                        <p>
                            Subject to applicable U.S. and Florida law, you may request access, correction, or deletion of certain personal data,
                            subject to identity verification and legal limitations.
                        </p>

                        <h3 class="mt-4">13. California Residents</h3>
                        <p>
                            If you are a California resident, you may have additional rights under the California Consumer Privacy Act (CCPA) and related
                            regulations, including the right to know what personal information we collect, the right to request correction or deletion of certain
                            data, and the right to opt out of the sale of personal information. MedTravel does not sell personal information in the traditional
                            sense. To exercise your California privacy rights or to submit a request, please contact us at <strong>privacy@medtravel.com</strong>.
                            We will respond within the timeframe required by applicable law, subject to identity verification.
                        </p>

                        <h3 class="mt-4">14. Colombian Data Protection</h3>
                        <p>
                            When your information is shared with providers or partners located in Colombia for coordination purposes, that information may be
                            subject to treatment under applicable Colombian data protection law, including Ley 1581 de 2012 (Ley de Protección de Datos Personales)
                            and related regulations, to the extent applicable. Each Colombian provider is an independent data controller for information they
                            receive directly from you or through MedTravel at your direction.
                        </p>

                        <h3 class="mt-4">15. Children's Privacy</h3>
                        <p>
                            The platform is intended for users 18 and older. We do not knowingly collect personal information from children.
                        </p>

                        <h3 class="mt-4">16. Governing Law</h3>
                        <p>
                            This Privacy Policy is governed by the laws of the State of Florida, United States.
                        </p>

                        <h3 class="mt-4">17. Changes to This Policy</h3>
                        <p>
                            We may update this Privacy Policy from time to time. When updated, we will revise the version number and effective date.
                            Continued use of the platform constitutes acceptance of the revised policy.
                        </p>

                        <h3 class="mt-4">18. Contact</h3>
                        <p>If you have questions about this Privacy Policy, please contact MedTravel before submitting a booking request.</p>
                        <ul class="mb-0">
                            <li>Email: privacy@medtravel.com</li>
                        </ul>

                    </div>
                </div>
            </div>
        </div>
        <!-- Privacy Content End -->

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
        <script src="js/main.js"></script>
    </body>
</html>
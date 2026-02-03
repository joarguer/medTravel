<?php
include(__DIR__ . '/../inc/include.php');

// Override base <title> and meta description for SEO
$head_privacy = str_replace(
    ['<title>MedTravel - Tourism and Health </title>', '<meta content="" name="description">'],
    [
        '<title>MedTravel | Privacy Policy</title>',
        '<meta content="Learn how MedTravel collects and uses personal information when you contact us via web chat or WhatsApp." name="description">'
    ],
    $head
);

// Ensure assets resolve from the site root when served from /privacy/
$head_privacy = str_replace(
    '<meta content="width=device-width, initial-scale=1.0" name="viewport">',
    '<meta content="width=device-width, initial-scale=1.0" name="viewport">' . "\n    <base href=\"/\">",
    $head_privacy
);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php echo $head_privacy; ?>
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
                <p class="text-white-50 mb-0">Last updated: February 2, 2026</p>
            </div>
        </div>
        <!-- Header End -->

        <!-- Privacy Content Start -->
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <div class="bg-white p-4 p-md-5 shadow-sm rounded">
                        <h2 class="mb-3">Privacy Policy</h2>
                        <p class="text-muted">Last updated: February 2, 2026</p>

                        <h3 class="mt-4">1. Who we are</h3>
                        <p>MedTravel helps individuals explore and coordinate medical travel options, including information requests, service coordination, and support during the planning process.</p>
                        <p>This Privacy Policy explains how we collect, use, and protect personal information when you interact with MedTravel through our website, web chat, or messaging channels such as WhatsApp.</p>

                        <h3 class="mt-4">2. Information we collect</h3>
                        <h4 class="mt-3">2.1 Information you provide</h4>
                        <ul>
                            <li>Contact information such as name, phone number, and email address.</li>
                            <li>Conversation information from messages you send through web chat or WhatsApp.</li>
                            <li>Information necessary to respond to your request, coordinate services, provide quotes, or follow up on inquiries.</li>
                        </ul>
                        <div class="alert alert-warning mt-3" role="alert" style="border-left: 4px solid #ffc107;">
                            <strong>Important health-related notice:</strong><br>
                            Please do not send sensitive medical information (such as medical records, diagnoses, test results, identification documents, or insurance details) through WhatsApp or web chat. If sensitive information is required, MedTravel will provide a more appropriate and secure method.
                        </div>

                        <h4 class="mt-4">2.2 Information collected automatically</h4>
                        <ul>
                            <li>Basic technical data (device type, browser, IP address, timestamps).</li>
                            <li>Security and operational logs used to protect the service and prevent abuse.</li>
                        </ul>

                        <h3 class="mt-4">3. How we use your information</h3>
                        <p>We use personal information to:</p>
                        <ul>
                            <li>Respond to inquiries and provide customer support.</li>
                            <li>Coordinate and manage requests related to medical travel services.</li>
                            <li>Send operational communications (confirmations, reminders, follow-ups).</li>
                            <li>Improve service quality and internal performance.</li>
                            <li>Maintain security, prevent fraud, and ensure platform reliability.</li>
                        </ul>

                        <h3 class="mt-4">4. Legal basis and consent</h3>
                        <p>By contacting MedTravel and voluntarily providing your information, you consent to the collection and use of that information for the purposes described in this Privacy Policy. Where required, additional consent may be requested.</p>

                        <h3 class="mt-4">5. WhatsApp and messaging communications</h3>
                        <p>When you communicate with MedTravel via WhatsApp, messaging is subject to WhatsApp platform rules.</p>
                        <ul>
                            <li>WhatsApp may enforce a 24-hour messaging window following your last message.</li>
                            <li>In certain cases, MedTravel may use pre-approved message templates for notifications or follow-ups, as required by WhatsApp.</li>
                        </ul>
                        <p><strong>Opt-out:</strong> You may request to stop non-essential messages at any time by replying “STOP” or by contacting us using the details below.</p>

                        <h3 class="mt-4">6. Sharing information with third parties</h3>
                        <p>MedTravel does not sell personal information.</p>
                        <p>We may share limited data with trusted service providers strictly as necessary to operate the service, including messaging infrastructure and customer communication systems. These providers are authorized to use information only for providing services to MedTravel.</p>

                        <h3 class="mt-4">7. Data retention</h3>
                        <p>Personal information is retained only for as long as reasonably necessary to provide support, maintain service continuity, comply with legal obligations, resolve disputes, and improve operations.</p>

                        <h3 class="mt-4">8. Security</h3>
                        <p>We implement reasonable administrative, technical, and organizational safeguards to protect personal information. However, no system can be guaranteed to be completely secure.</p>

                        <h3 class="mt-4">9. Your rights</h3>
                        <p>Depending on applicable laws, you may have the right to request access, correction, or deletion of your personal information, or to restrict certain processing activities.</p>
                        <p>Requests can be made using the contact details below.</p>

                        <h3 class="mt-4">10. Cookies and similar technologies</h3>
                        <p>Our website may use cookies or similar technologies to ensure essential functionality, understand basic usage patterns, and improve performance. You can control cookies through your browser settings.</p>

                        <h3 class="mt-4">11. Contact information</h3>
                        <p>If you have questions about this Privacy Policy or about how your information is handled, you may contact us at:</p>
                        <ul class="mb-5">
                            <li>WhatsApp: +57 3502431667</li>
                            <li>Email: privacy@medtravel.com</li>
                        </ul>

                        <h3 class="mt-4">Resumen en Español (informativo)</h3>
                        <p>MedTravel recopila datos de contacto y mensajes enviados por web chat o WhatsApp para responder solicitudes, coordinar servicios y brindar soporte. No vendemos información personal. No se debe enviar información médica sensible por WhatsApp o chat web. WhatsApp puede aplicar reglas como la ventana de 24 horas y el uso de plantillas aprobadas.<br>
                        Contacto: WhatsApp +57 3502431667 | privacy@medtravel.com</p>
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

<?php
$page_title = 'MedTravel FAQ | Medical Travel Coordination Questions';
$page_description = 'Frequently asked questions about MedTravel legitimacy, how coordination works, and the role of providers and specialists.';
$page_canonical = 'https://medtravel.com.co/faq.php';
$page_schema_jsonld = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        '@id' => 'https://medtravel.com.co/faq.php#faqpage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'Is MedTravel legit?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Yes. MedTravel is a real coordination platform based in Florida that connects international patients with independent providers in Colombia. MedTravel coordinates planning, communication, and logistics. Clinical care is delivered by licensed independent providers and specialists.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Is MedTravel a hospital or clinic?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. MedTravel is not a hospital, clinic, or medical practice. MedTravel is a coordination and facilitation platform.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'How does MedTravel work?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'You submit your request, MedTravel reviews your case details for coordination, and then connects you with appropriate independent providers. MedTravel supports scheduling and travel planning while providers handle medical decisions and treatment.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Who provides treatment?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Treatment is provided by independent clinics and medical specialists. MedTravel does not provide medical treatment and does not replace the patient-provider clinical relationship.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'How do I start?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Start by submitting your booking request with your goals and preferred timeline. Our coordination team will contact you with next steps and provider options.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Do you work with real specialists and clinics?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Yes. MedTravel coordinates with independent specialists and clinics that participate as providers in the MedTravel network. They are responsible for their own clinical services and decisions.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'What does MedTravel coordinate?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'MedTravel coordinates request intake, provider matching, scheduling support, travel and logistics planning, and follow-up communication support.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'What happens after I submit my request?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'A coordinator reviews your information and contacts you to confirm details, explain timelines, and organize next coordination steps with appropriate providers.',
                ],
            ],
        ],
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
            <h1 class="text-white display-4 mb-3">Frequently Asked Questions</h1>
            <p class="text-white mb-0">Clear answers about MedTravel, our role, and how coordination works.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq1h">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                                Is MedTravel legit?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" aria-labelledby="faq1h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Yes. MedTravel is a real coordination platform based in Florida that connects international patients with independent providers in Colombia. We coordinate planning, communication, and logistics while clinical care is delivered by independent providers and specialists.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq2h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                Is MedTravel a hospital or clinic?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" aria-labelledby="faq2h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">No. MedTravel is not a hospital, clinic, or medical practice. MedTravel is a coordination and facilitation platform.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq3h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                How does MedTravel work?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" aria-labelledby="faq3h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">You submit your request, MedTravel reviews your case details for coordination, and then connects you with appropriate independent providers. We support scheduling and travel planning while providers handle medical decisions and treatment.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq4h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                Who provides treatment?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" aria-labelledby="faq4h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Treatment is provided by independent clinics and medical specialists. MedTravel does not provide medical treatment and does not replace your clinical relationship with your provider.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq5h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                                How do I start?
                            </button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" aria-labelledby="faq5h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Start by submitting your booking request with your goals and preferred timeline. Our coordination team will contact you with next steps and provider options.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq6h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                                Do you work with real specialists and clinics?
                            </button>
                        </h2>
                        <div id="faq6" class="accordion-collapse collapse" aria-labelledby="faq6h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">Yes. MedTravel coordinates with independent specialists and clinics that participate as providers in our network. They are responsible for their own clinical services and decisions.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq7h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7" aria-expanded="false" aria-controls="faq7">
                                What does MedTravel coordinate?
                            </button>
                        </h2>
                        <div id="faq7" class="accordion-collapse collapse" aria-labelledby="faq7h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">MedTravel coordinates request intake, provider matching, scheduling support, travel and logistics planning, and follow-up communication support.</div>
                        </div>
                    </div>

                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header" id="faq8h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8" aria-expanded="false" aria-controls="faq8">
                                What happens after I submit my request?
                            </button>
                        </h2>
                        <div id="faq8" class="accordion-collapse collapse" aria-labelledby="faq8h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">A MedTravel coordinator reviews your information and contacts you to confirm details, explain timelines, and organize next coordination steps with appropriate providers.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 p-4 bg-light rounded">
                    <h4 class="mb-3">Need a personalized answer?</h4>
                    <p class="mb-3">Our team can review your case and explain the next coordination steps.</p>
                    <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4 me-2">Start your case review</a>
                    <a href="/services.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">View services</a>
                    <a href="/specialists.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">View specialists</a>
                    <a href="/how-medtravel-works.php" class="btn btn-outline-primary rounded-pill py-2 px-4">See How MedTravel Works</a>
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

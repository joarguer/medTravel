<?php
include_once(__DIR__ . '/admin/include/conexion.php');
require_once __DIR__ . '/inc/public_specialists.php';

$page_title = 'Specialists in Colombia | MedTravel Care Coordination Network';
$page_description = 'Explore specialists from active independent providers in Colombia coordinated through MedTravel for international patient case planning.';
$page_canonical = 'https://medtravel.com.co/specialists.php';

$specialists = mt_home_specialists_fetch($conexion, 12);

$page_schema_jsonld = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'MedTravel Specialists',
    'description' => 'Profiles of specialists from independent providers coordinated through MedTravel for medical travel planning.',
    'isPartOf' => ['@id' => 'https://medtravel.com.co/#website'],
    'about' => ['@id' => 'https://medtravel.com.co/#organization'],
]];

include(__DIR__ . '/inc/include.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        .sp-card {
            height: 100%;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }
        .sp-photo {
            width: 100%;
            height: 300px;
            object-fit: cover;
            background: #f8fafc;
        }
        .sp-body { padding: 18px; }
        .sp-role { color: #13357B; font-weight: 600; margin-bottom: 8px; }
        .sp-provider { color: #64748b; font-size: 14px; margin-bottom: 8px; }
        .sp-bio { color: #475569; font-size: 14px; margin-bottom: 0; }
        .sp-trust-list {
            margin: 0;
            padding-left: 18px;
            color: #334155;
        }
        .sp-card-cta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        .sp-offers {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 12px;
            margin-bottom: 4px;
        }
        .sp-offers span {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #f0fdf4;
            color: #166534;
            padding: 3px 9px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #bbf7d0;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sp-offers .sp-chip-more {
            background: #f8fafc;
            color: #64748b;
            border-color: #e2e8f0;
        }
    </style>
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
            <h1 class="text-white display-4 mb-3">Our Specialists</h1>
            <p class="text-white mb-0">Independent specialists from active providers in Colombia, presented for transparent care coordination planning.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-10">
                <div class="p-4 bg-light rounded">
                    <h2 class="h4 mb-3">Trusted specialist visibility for your case review</h2>
                    <p class="mb-2"><strong>Important:</strong> MedTravel coordinates communication and planning. We are not a hospital or clinic.</p>
                    <p class="mb-3">Clinical evaluation and treatment are delivered by independent providers and specialists.</p>
                    <ul class="sp-trust-list mb-3">
                        <li>Profiles shown here come from the same public specialist source used on Home and About.</li>
                        <li>Specialist publication is controlled by provider-side authorization settings.</li>
                        <li>MedTravel supports coordination, logistics, and patient communication across the journey.</li>
                    </ul>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Request care coordination</a>
                        <a href="/services.php" class="btn btn-outline-primary rounded-pill py-2 px-4">View services</a>
                        <a href="/medical-travel-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Medical travel in Colombia</a>
                        <a href="/how-medtravel-works.php" class="btn btn-outline-primary rounded-pill py-2 px-4">How MedTravel works</a>
                        <a href="/faq.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Read FAQ</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <?php if (!empty($specialists)) { ?>
                <?php foreach ($specialists as $specialist) { ?>
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <article class="sp-card">
                            <img src="<?php echo htmlspecialchars((string)$specialist['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="sp-photo"
                                 alt="<?php echo htmlspecialchars((string)$specialist['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 onerror="this.onerror=null;this.src='<?php echo htmlspecialchars((string)($specialist['photo_fallback'] ?: 'img/site/placeholder-medical.jpg'), ENT_QUOTES, 'UTF-8'); ?>';">
                            <div class="sp-body">
                                <h4 class="mb-2"><?php echo htmlspecialchars((string)$specialist['full_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="sp-role"><?php echo htmlspecialchars((string)$specialist['display_role'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="sp-provider"><?php echo htmlspecialchars((string)$specialist['provider_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="sp-bio"><?php echo htmlspecialchars((string)($specialist['bio_short'] !== '' ? $specialist['bio_short'] : 'Independent specialist available through MedTravel coordination.'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($specialist['offer_chips'])) {
                                    $sp_chips   = $specialist['offer_chips'];
                                    $sp_visible = array_slice($sp_chips, 0, 3);
                                    $sp_extra   = count($sp_chips) - count($sp_visible);
                                ?>
                                <div class="sp-offers">
                                    <?php foreach ($sp_visible as $sp_chip) { ?>
                                        <span><?php echo htmlspecialchars($sp_chip, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php } ?>
                                    <?php if ($sp_extra > 0) { ?>
                                        <span class="sp-chip-more">+<?php echo $sp_extra; ?> more</span>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                                <div class="sp-card-cta">
                                    <a href="/offers.php?staff_id=<?php echo (int)$specialist['id']; ?>" class="btn btn-sm btn-success rounded-pill">View services</a>
                                    <a href="/booking.php#booking-section" class="btn btn-sm btn-primary rounded-pill">Start your case review</a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="col-12 text-center">
                    <p class="mb-3">Specialist profiles are being updated.</p>
                    <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Start your case review</a>
                </div>
            <?php } ?>
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

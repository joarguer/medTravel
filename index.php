<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$page_title = 'Medical Care Coordination in Colombia | MedTravel';
$page_description = 'MedTravel coordinates international patient case reviews with independent providers in Colombia, including scheduling and travel logistics support.';
$page_canonical = 'https://medtravel.com.co/';
include('inc/include.php');
require_once __DIR__ . '/inc/testimonials.php';
require_once __DIR__ . '/inc/public_specialists.php';

function home_hero_table_exists_public($conexion) {
    $query = mysqli_query($conexion, "SHOW TABLES LIKE 'home_hero_settings'");
    $exists = ($query && mysqli_num_rows($query) > 0);
    if ($query instanceof mysqli_result) {
        mysqli_free_result($query);
    }
    return $exists;
}

function home_hero_column_exists_public($conexion, $column_name) {
    $safe_column = mysqli_real_escape_string($conexion, (string)$column_name);
    $query = mysqli_query(
        $conexion,
        "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'home_hero_settings' AND COLUMN_NAME = '" . $safe_column . "'"
    );
    $exists = false;
    if ($query) {
        $row = mysqli_fetch_assoc($query);
        $exists = !empty($row) && intval($row['total']) > 0;
    }
    if ($query instanceof mysqli_result) {
        mysqli_free_result($query);
    }
    return $exists;
}

function home_hero_public_defaults() {
    return [
        'is_enabled' => 1,
        'media_type' => 'carousel',
        'video_url' => '',
        'video_poster' => '',
        'title' => '',
        'subtitle' => '',
        'cta_text' => '',
        'cta_url' => '',
    ];
}

$hero_settings = home_hero_public_defaults();
if (home_hero_table_exists_public($conexion)) {
    $hero_query = mysqli_query($conexion, "SELECT is_enabled, media_type, video_url, video_poster, title, subtitle, cta_text, cta_url FROM home_hero_settings ORDER BY id DESC LIMIT 1");
    if ($hero_query && mysqli_num_rows($hero_query) > 0) {
        $hero_row = mysqli_fetch_assoc($hero_query);
        if (is_array($hero_row)) {
            $hero_settings = array_merge($hero_settings, $hero_row);
        }
    }
    if ($hero_query instanceof mysqli_result) {
        mysqli_free_result($hero_query);
    }
}

$hero_is_enabled = intval($hero_settings['is_enabled']) === 1;
$hero_media_type = ($hero_settings['media_type'] === 'video') ? 'video' : 'carousel';
$hero_video_url = trim((string)$hero_settings['video_url']);
$hero_video_poster = trim((string)$hero_settings['video_poster']);
$hero_title = trim((string)$hero_settings['title']);
$hero_subtitle = trim((string)$hero_settings['subtitle']);
$hero_cta_text = trim((string)$hero_settings['cta_text']);
$hero_cta_url = trim((string)$hero_settings['cta_url']);
$hero_video_ready = $hero_video_url !== '' && preg_match('~\.(mp4|m4v)(\?.*)?$~i', preg_replace('~^https?://[^/]+~i', '', $hero_video_url));
$show_video_hero = $hero_is_enabled && $hero_media_type === 'video' && $hero_video_ready;
$show_carousel_hero = $hero_is_enabled && !$show_video_hero;
$hero_shell_class = $hero_is_enabled ? 'hero-shell' : 'hero-shell hero-shell--disabled';
$detailed_services_enabled = 1;
if (home_hero_table_exists_public($conexion) && home_hero_column_exists_public($conexion, 'detailed_services_enabled')) {
    $services_settings_query = mysqli_query($conexion, "SELECT detailed_services_enabled FROM home_hero_settings ORDER BY id DESC LIMIT 1");
    if ($services_settings_query && mysqli_num_rows($services_settings_query) > 0) {
        $services_settings_row = mysqli_fetch_assoc($services_settings_query);
        $detailed_services_enabled = intval($services_settings_row['detailed_services_enabled'] ?? 1) === 1 ? 1 : 0;
    }
    if ($services_settings_query instanceof mysqli_result) {
        mysqli_free_result($services_settings_query);
    }
}
$busca_carrucel = mysqli_query($conexion,"SELECT * FROM carrucel WHERE activo = '0' ORDER BY id ASC");
$busca_carrucel_2 = mysqli_query($conexion,"SELECT * FROM carrucel WHERE activo = '0' ORDER BY id ASC");
$home_specialists = mt_home_specialists_fetch($conexion, 8);
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <?php echo $head; ?>
        <style>
            .home-specialist-card {
                height: 100%;
                background: #fff;
                border-radius: 14px;
                overflow: hidden;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
                border: 1px solid rgba(226, 232, 240, 0.9);
            }
            .home-specialist-photo {
                width: 100%;
                height: 320px;
                object-fit: cover;
                object-position: center top;
                display: block;
            }
            .home-specialist-avatar {
                width: 100%;
                height: 320px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #13357b, #1d4ed8);
                color: #fff;
                font-size: 72px;
                font-weight: 700;
                letter-spacing: 0.06em;
            }
            .home-specialist-body {
                padding: 24px;
            }
            .home-specialist-provider {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #64748b;
                margin-bottom: 12px;
            }
            .home-specialist-provider-logo {
                width: 28px;
                height: 28px;
                object-fit: cover;
                border-radius: 50%;
                border: 1px solid #e2e8f0;
                background: #fff;
            }
            .home-specialist-role {
                color: #13357b;
                font-weight: 600;
                margin-bottom: 10px;
            }
            .home-specialist-bio {
                color: #64748b;
                margin-bottom: 0;
                min-height: 72px;
            }
            .home-specialist-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 16px;
            }
            .home-specialist-meta span {
                display: inline-flex;
                align-items: center;
                border-radius: 999px;
                background: #eff6ff;
                color: #1d4ed8;
                padding: 6px 12px;
                font-size: 12px;
                font-weight: 600;
            }
            .home-specialist-cta {
                display: block;
                text-align: center;
                margin-top: 16px;
                padding: 9px 16px;
                border-radius: 8px;
                background: #0f766e;
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                text-decoration: none;
                transition: background 0.2s;
                letter-spacing: 0.3px;
            }
            .home-specialist-cta:hover {
                background: #0d9488;
                color: #fff;
            }
            .home-specialist-offers {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 10px;
            }
            .home-specialist-offers span {
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
            .home-specialist-offers .hs-chip-more {
                background: #f8fafc;
                color: #64748b;
                border-color: #e2e8f0;
            }
        </style>
    </head>

    <body>

        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=2081221109277505&ev=PageView&noscript=1"
        /></noscript>

        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Topbar Start -->
        <?php //echo $topbar; ?>
        <!-- Topbar End -->

        <!-- Navbar & Hero Start -->
        <div class="container-fluid position-relative p-0 <?php echo $hero_shell_class; ?>">
            <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
                <?php echo $logo; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <?php echo $menu; ?>
            </nav>

            <?php if ($show_carousel_hero) { ?>
            <!-- Carousel Start -->
            <div class="carousel-header">
                <div id="carouselId" class="carousel slide" data-bs-ride="carousel">
                    <ol class="carousel-indicators">
                        <?php
                            $n = 0;
                            while($fil = mysqli_fetch_array($busca_carrucel)){
                                if($n == 0){
                                    $active = 'active';
                                } else {
                                    $active = '';
                                }
                        ?>
                            <li data-bs-target="#carouselId" data-bs-slide-to="<?php echo $n;?>" class="<?php echo $active;?>"></li>
                        <?php
                            $n++;
                            }
                        ?>
                    </ol>
                    <div class="carousel-inner" role="listbox">
                        <?php
                            $n = 0;
                            while($fil = mysqli_fetch_array($busca_carrucel_2)){
                                if($n == 0){
                                    $active = 'active';
                                } else {
                                    $active = '';
                                }
                        ?>
                        <div class="carousel-item <?php echo $active;?>">
                            <img src="<?php echo $fil['img'];?>" class="img-fluid" alt="Image">
                            <div class="carousel-caption">
                                <div class="p-3" style="max-width: 900px;">
                                    <h4 class="text-white text-uppercase fw-bold mb-4" style="letter-spacing: 3px;"><?php echo $fil['over_title'];?></h4>
                                    <h1 class="display-2 text-capitalize text-white mb-4"><?php echo $fil['title'];?></h1>
                                    <p class="mb-5 fs-5"><?php echo $fil['parrafo'];?>
                                    </p>
                                    <div class="d-flex align-items-center justify-content-center">
                                        <a class="btn-hover-bg btn btn-primary rounded-pill text-white py-3 px-5" href="#booking-section" onclick="scrollToBooking(); return false;"><?php echo $fil['btn'];?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                            $n++;
                            }
                        ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselId" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon btn bg-primary" aria-hidden="false"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselId" data-bs-slide="next">
                        <span class="carousel-control-next-icon btn bg-primary" aria-hidden="false"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
            <!-- Carousel End -->
            <?php } elseif ($show_video_hero) { ?>
            <div class="hero-video-block">
                <video id="homeHeroVideo" class="hero-video-block__media" autoplay muted loop playsinline preload="metadata" <?php echo $hero_video_poster !== '' ? 'poster="' . htmlspecialchars($hero_video_poster, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                    <source src="<?php echo htmlspecialchars($hero_video_url, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
                <div class="hero-video-block__overlay"></div>
                <div class="hero-video-block__content">
                    <div class="p-3" style="max-width: 900px;">
                        <?php if ($hero_title !== '') { ?>
                        <h1 class="display-2 text-capitalize text-white mb-4"><?php echo htmlspecialchars($hero_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <?php } ?>
                        <?php if ($hero_subtitle !== '') { ?>
                        <p class="mb-5 fs-5"><?php echo htmlspecialchars($hero_subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php } ?>
                        <?php if ($hero_cta_text !== '' && $hero_cta_url !== '') { ?>
                        <div class="d-flex align-items-center justify-content-center">
                            <a class="btn-hover-bg btn btn-primary rounded-pill text-white py-3 px-5" href="<?php echo htmlspecialchars($hero_cta_url, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($hero_cta_text, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                        <?php } ?>
                        <div class="d-flex align-items-center justify-content-center mt-4">
                            <button type="button" id="heroVideoAudioToggle" class="hero-video-audio-toggle" aria-pressed="false" aria-label="Enable video audio">
                                Listen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <!-- <div class="container-fluid search-bar position-relative" style="top: -50%; transform: translateY(-50%);">
            <div class="container">
                <div class="position-relative rounded-pill w-100 mx-auto p-5" style="background: rgba(19, 53, 123, 0.8);">
                    <input class="form-control border-0 rounded-pill w-100 py-3 ps-4 pe-5" type="text" placeholder="Eg: Thailand">
                    <button type="button" class="btn btn-primary rounded-pill py-2 px-4 position-absolute me-2" style="top: 50%; right: 46px; transform: translateY(-50%);">Search</button>
                </div>
            </div>
        </div>
        <!-- Navbar & Hero End -->

        <!-- Cómo Funciona Start -->
        <div class="container-fluid destination py-5">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Simple Process</h5>
                    <h1 class="mb-4">How Does MedTravel Work?</h1>
                    <p class="mb-0">In just 4 steps we coordinate your complete medical trip from the United States to Colombia</p>
                </div>
                <div class="row g-4">
                    <?php
                    $busca_como_funciona = mysqli_query($conexion,"SELECT * FROM home_como_funciona WHERE activo = '0' ORDER BY step_number ASC");
                    while($rst_como = mysqli_fetch_array($busca_como_funciona)){
                    ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="text-center">
                            <div class="d-inline-flex align-items-center justify-content-center bg-primary rounded-circle mb-4" style="width: 100px; height: 100px;">
                                <i class="<?php echo $rst_como['icon_class'];?> fa-3x text-white"></i>
                            </div>
                            <h4><?php echo $rst_como['step_number'];?>. <?php echo $rst_como['title'];?></h4>
                            <p class="text-muted"><?php echo $rst_como['description'];?></p>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-10">
                        <div class="p-4 bg-light rounded">
                            <p class="mb-2"><strong>MedTravel is a coordination platform.</strong> We are not a hospital or clinic. Medical care is provided by independent specialists and providers.</p>
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4">Start your case review</a>
                                <a href="/faq.php" class="btn btn-outline-primary rounded-pill py-2 px-4">Read FAQ</a>
                                <a href="/about.php" class="btn btn-outline-primary rounded-pill py-2 px-4">About MedTravel</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4 mt-2">
                    <div class="col-md-4">
                        <div class="p-4 h-100 bg-white rounded border">
                            <h3 class="h5 mb-2">Why patients choose MedTravel</h3>
                            <p class="mb-0 text-muted">Clear coordination, bilingual support, and transparent planning with independent providers in Colombia.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-4 h-100 bg-white rounded border">
                            <h3 class="h5 mb-2">What to expect after you submit your case</h3>
                            <p class="mb-0 text-muted">A coordination review, next-step guidance, and provider-aligned planning before your trip.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-4 h-100 bg-white rounded border">
                            <h3 class="h5 mb-2">Bilingual coordination for international patients</h3>
                            <p class="mb-0 text-muted">Our team supports English and Spanish communication for patients and families in the U.S.</p>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="/medical-travel-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">Medical travel in Colombia</a>
                    <a href="/medical-travel-armenia-colombia.php" class="btn btn-outline-primary rounded-pill py-2 px-4 me-2">Armenia, Colombia</a>
                    <a href="/for-us-patients.php" class="btn btn-outline-primary rounded-pill py-2 px-4">For U.S. patients</a>
                </div>
            </div>
        </div>
        <!-- Cómo Funciona End -->

        <!-- Old Destination Section Removed -->
        <div style="display:none;">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Destination</h5>
                    <h1 class="mb-0">Popular Destination</h1>
                </div>
                <div class="tab-class text-center">
                    <ul class="nav nav-pills d-inline-flex justify-content-center mb-5">
                        <li class="nav-item">
                            <a class="d-flex mx-3 py-2 border border-primary bg-light rounded-pill active" data-bs-toggle="pill" href="#tab-1">
                                <span class="text-dark" style="width: 150px;">All</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex py-2 mx-3 border border-primary bg-light rounded-pill" data-bs-toggle="pill" href="#tab-2">
                                <span class="text-dark" style="width: 150px;">USA</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex mx-3 py-2 border border-primary bg-light rounded-pill" data-bs-toggle="pill" href="#tab-3">
                                <span class="text-dark" style="width: 150px;">Canada</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex mx-3 py-2 border border-primary bg-light rounded-pill" data-bs-toggle="pill" href="#tab-4">
                                <span class="text-dark" style="width: 150px;">Europe</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex mx-3 py-2 border border-primary bg-light rounded-pill" data-bs-toggle="pill" href="#tab-5">
                                <span class="text-dark" style="width: 150px;">China</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex mx-3 py-2 border border-primary bg-light rounded-pill" data-bs-toggle="pill" href="#tab-6">
                                <span class="text-dark" style="width: 150px;">Singapore</span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div id="tab-1" class="tab-pane fade show p-0 active">
                            <div class="row g-4">
                                <div class="col-xl-8">
                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <div class="destination-img">
                                                <img class="img-fluid rounded w-100" src="img/destination-1.jpg" alt="">
                                                <div class="destination-overlay p-4">
                                                    <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                                    <h4 class="text-white mb-2 mt-3">New York City</h4>
                                                    <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                                </div>
                                                <div class="search-icon">
                                                    <a href="img/destination-1.jpg" data-lightbox="destination-1"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="destination-img">
                                                <img class="img-fluid rounded w-100" src="img/destination-2.jpg" alt="">
                                                <div class="destination-overlay p-4">
                                                    <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                                    <h4 class="text-white mb-2 mt-3">Las vegas</h4>
                                                    <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                                </div>
                                                <div class="search-icon">
                                                    <a href="img/destination-2.jpg" data-lightbox="destination-2"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="destination-img">
                                                <img class="img-fluid rounded w-100" src="img/destination-7.jpg" alt="">
                                                <div class="destination-overlay p-4">
                                                    <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                                    <h4 class="text-white mb-2 mt-3">Los angelas</h4>
                                                    <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                                </div>
                                                <div class="search-icon">
                                                    <a href="img/destination-7.jpg" data-lightbox="destination-7"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="destination-img">
                                                <img class="img-fluid rounded w-100" src="img/destination-8.jpg" alt="">
                                                <div class="destination-overlay p-4">
                                                    <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                                    <h4 class="text-white mb-2 mt-3">Los angelas</h4>
                                                    <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                                </div>
                                                <div class="search-icon">
                                                    <a href="img/destination-8.jpg" data-lightbox="destination-8"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4">
                                    <div class="destination-img h-100">
                                        <img class="img-fluid rounded w-100 h-100" src="img/destination-9.jpg" style="object-fit: cover; min-height: 300px;" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-9.jpg" data-lightbox="destination-4"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-4.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">Los angelas</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-4.jpg" data-lightbox="destination-4"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-5.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">Los angelas</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-5.jpg" data-lightbox="destination-5"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-6.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">Los angelas</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-6.jpg" data-lightbox="destination-6"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="tab-2" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-5.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-5.jpg" data-lightbox="destination-5"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-6.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-6.jpg" data-lightbox="destination-6"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="tab-3" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-5.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-5.jpg" data-lightbox="destination-5"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-6.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-6.jpg" data-lightbox="destination-6"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="tab-4" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-5.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-5.jpg" data-lightbox="destination-5"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-6.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-6.jpg" data-lightbox="destination-6"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="tab-5" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-5.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-5.jpg" data-lightbox="destination-5"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-6.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-6.jpg" data-lightbox="destination-6"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="tab-6" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-5.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-5.jpg" data-lightbox="destination-5"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="destination-img">
                                        <img class="img-fluid rounded w-100" src="img/destination-6.jpg" alt="">
                                        <div class="destination-overlay p-4">
                                            <a href="#" class="btn btn-primary text-white rounded-pill border py-2 px-3">20 Photos</a>
                                            <h4 class="text-white mb-2 mt-3">San francisco</h4>
                                            <a href="#" class="btn-hover text-white">View All Place <i class="fa fa-arrow-right ms-2"></i></a>
                                        </div>
                                        <div class="search-icon">
                                            <a href="img/destination-6.jpg" data-lightbox="destination-6"><i class="fa fa-plus-square fa-1x btn btn-light btn-lg-square text-primary"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <!-- Old Destination Section End -->

        <?php if ($detailed_services_enabled === 1) { ?>
        <!-- Servicios Detallados Start -->
        <div class="container-fluid ExploreTour py-5 bg-light">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3 text-dark">Our Services</h5>
                    <h1 class="mb-4 text-dark">Medical Travel Coordination Services</h1>
                    <p class="mb-0 text-muted">We help coordinate the key services that support your medical journey in Colombia, from provider matching and appointments to travel, recovery, and ongoing support.</p>
                </div>
                <div class="row g-4">
                    <?php
                    $busca_services = mysqli_query($conexion,"SELECT * FROM home_services WHERE activo = '0' ORDER BY orden ASC");
                    while($rst_service = mysqli_fetch_array($busca_services)){
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="national-item h-100">
                            <img src="<?php echo $rst_service['img'];?>" class="img-fluid w-100 rounded" alt="<?php echo $rst_service['title'];?>">
                            <div class="national-content">
                                <div class="national-info">
                                    <h5 class="text-white text-uppercase mb-2"><?php echo $rst_service['title'];?></h5>
                                    <?php
                                    $service_title = $rst_service['title'] ?? '';
                                    $service_description = $rst_service['description'] ?? '';
                                    if (stripos($service_title, '24/7') !== false) {
                                        $service_description = str_ireplace('Billingual', 'Bilingual', $service_description);
                                    }
                                    ?>
                                    <p class="text-white mb-2" style="font-size: 14px;"><?php echo $service_description;?></p>
                                    <a href="services.php" class="btn-hover text-white">Learn More <i class="fa fa-arrow-right ms-2"></i></a>
                                </div>
                            </div>
                            <?php
                            $badge_raw = isset($rst_service['badge']) ? trim((string)$rst_service['badge']) : '';
                            $badge_lc = strtolower($badge_raw);
                            $badge_valid = ($badge_raw !== '' && $badge_lc !== 'null');
                            if ($badge_valid) {
                            ?>
                            <div class="tour-offer <?php echo $rst_service['badge_class'];?>"><?php echo $badge_raw;?></div>
                            <?php } ?>
                            <div class="national-plus-icon">
                                <a href="services.php" class="my-auto"><i class="<?php echo $rst_service['icon_class'];?> fa-2x text-white"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <!-- Servicios Detallados End -->
        <?php } ?>

        <?php if (!empty($home_specialists)) { ?>
        <div class="container-fluid guide py-5 bg-light">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Our Specialists</h5>
                    <h1 class="mb-4">Meet the Physicians Behind Our Trusted Provider Network</h1>
                    <p class="mb-0 text-muted">Discover real doctors and clinical specialists from active providers in the MedTravel network. Each profile below is published with explicit authorization from the provider side to help patients in the United States evaluate who may be involved in their care journey.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($home_specialists as $specialist) { ?>
                    <div class="col-sm-6 col-lg-3">
                        <article class="home-specialist-card">
                            <?php if ($specialist['photo'] !== '') { ?>
                                <img src="<?php echo htmlspecialchars($specialist['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                     class="home-specialist-photo"
                                     alt="<?php echo htmlspecialchars($specialist['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($specialist['photo_fallback'] ?: 'img/site/placeholder-medical.jpg', ENT_QUOTES, 'UTF-8'); ?>';">
                            <?php } else { ?>
                                <div class="home-specialist-avatar"><?php echo htmlspecialchars(mt_home_specialist_initials($specialist['full_name']), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php } ?>
                            <div class="home-specialist-body">
                                <div class="home-specialist-provider">
                                    <?php if ($specialist['provider_logo'] !== '') { ?>
                                        <img src="<?php echo htmlspecialchars($specialist['provider_logo'], ENT_QUOTES, 'UTF-8'); ?>" class="home-specialist-provider-logo" alt="<?php echo htmlspecialchars($specialist['provider_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php } ?>
                                    <span><?php echo htmlspecialchars($specialist['provider_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <h4 class="mt-2 mb-2"><?php echo htmlspecialchars($specialist['full_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="home-specialist-role"><?php echo htmlspecialchars($specialist['display_role'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="home-specialist-bio"><?php echo htmlspecialchars($specialist['display_bio'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="home-specialist-meta">
                                    <?php if ($specialist['is_primary_doctor'] === 1) { ?>
                                        <span>Lead specialist</span>
                                    <?php } ?>
                                    <?php if ($specialist['provider_name'] !== '') { ?>
                                        <span><?php echo htmlspecialchars($specialist['provider_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php } ?>
                                    <?php if ($specialist['provider_city'] !== '') { ?>
                                        <span><?php echo htmlspecialchars($specialist['provider_city'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php } ?>
                                </div>
                                <?php if (!empty($specialist['offer_chips'])) {
                                    $hs_chips   = $specialist['offer_chips'];
                                    $hs_visible = array_slice($hs_chips, 0, 3);
                                    $hs_extra   = count($hs_chips) - count($hs_visible);
                                ?>
                                <div class="home-specialist-offers">
                                    <?php foreach ($hs_visible as $hs_chip) { ?>
                                        <span><?php echo htmlspecialchars($hs_chip, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php } ?>
                                    <?php if ($hs_extra > 0) { ?>
                                        <span class="hs-chip-more">+<?php echo $hs_extra; ?> more</span>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                                <a href="/offers.php?staff_id=<?php echo (int)$specialist['id']; ?>"
                                   class="home-specialist-cta">
                                    <i class="fas fa-stethoscope me-1"></i>View services
                                </a>
                            </div>
                        </article>
                    </div>
                    <?php } ?>
                </div>
                <div class="text-center mt-4">
                    <a href="/specialists.php" class="btn btn-primary rounded-pill py-2 px-4 me-2">View specialists</a>
                    <a href="/booking.php#booking-section" class="btn btn-outline-primary rounded-pill py-2 px-4">Request care coordination</a>
                </div>
            </div>
        </div>
        <?php } ?>

        <!-- Call to Action Start -->
        <div class="container-fluid py-5" style="background: linear-gradient(rgba(19, 53, 123, 0.9), rgba(19, 53, 123, 0.9)), url(img/about-img-1.png);">
            <div class="container text-center py-5">
                <div class="mx-auto" style="max-width: 900px;">
                    <h5 class="section-title px-3 text-white">READY TO PLAN YOUR MEDICAL JOURNEY?</h5>
                    <h1 class="mb-4 text-white">Start Your MedTravel Experience</h1>
                    <p class="mb-4 text-white">We coordinate care planning and travel logistics for patients from the United States. Independent providers in Colombia are responsible for medical evaluation and treatment.</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="https://medtravel.com.co/booking.php#booking-section" class="btn btn-light rounded-pill py-3 px-5">Request care coordination</a>
                        <a href="https://medtravel.com.co/contact.php" class="btn btn-outline-light rounded-pill py-3 px-5">Request assistance</a>
                        <a href="https://medtravel.com.co/services.php" class="btn btn-outline-light rounded-pill py-3 px-5">View services</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Call to Action End -->

        <!-- Tour Booking Start -->
        <?php echo $booking_widget; ?>
        <!-- Tour Booking End -->

        <!-- Testimonial Start -->
        <div class="container-fluid testimonial py-5">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Testimonials</h5>
                    <h1 class="mb-2">What Our Clients Say About Coordination</h1>
                    <p class="text-muted mb-0">Feedback focused on communication clarity, next-step guidance, and travel-aware support.</p>
                </div>
                <div class="testimonial-carousel owl-carousel">
                    <?php
                    $testimonials = mt_testimonials_fetch_approved($conexion, 6);
                    foreach ($testimonials as $testimonial) {
                        $comment = nl2br(mt_testimonials_escape($testimonial['comment'] ?? ''));
                        $avatar = mt_testimonials_escape(mt_testimonials_avatar_path($testimonial));
                        $name = mt_testimonials_escape($testimonial['client_name'] ?? '');
                        $location = mt_testimonials_escape($testimonial['client_location'] ?? '');
                        $stars = mt_testimonials_render_stars($testimonial['rating'] ?? 5);
                        $initial = mt_testimonials_escape(mt_testimonials_initial($testimonial['client_name'] ?? ''));
                    ?>
                    <div class="testimonial-item text-center rounded pb-4">
                        <div class="testimonial-comment bg-light rounded p-4">
                            <p class="text-center mb-5"><?php echo $comment; ?></p>
                        </div>
                        <div class="testimonial-img p-1">
                            <?php if ($avatar !== '') { ?>
                                <img src="<?php echo $avatar; ?>" class="img-fluid rounded-circle" alt="Image">
                            <?php } else { ?>
                                <div class="testimonial-avatar-default"><?php echo $initial; ?></div>
                            <?php } ?>
                        </div>
                        <div style="margin-top: -35px;">
                            <h5 class="mb-0"><?php echo $name; ?></h5>
                            <p class="mb-0"><?php echo $location; ?></p>
                            <div class="d-flex justify-content-center">
                                <?php echo $stars; ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="text-center mt-4">
                    <a href="/booking.php#booking-section" class="btn btn-primary rounded-pill py-2 px-4 me-2">Start your case review</a>
                    <a href="/how-medtravel-works.php" class="btn btn-outline-primary rounded-pill py-2 px-4">See next steps</a>
                </div>
            </div>
        </div>
        <!-- Testimonial End -->

        <!-- Contact Start -->
        <?php echo $contact; ?>
        <!-- Contact End -->

        <!-- Subscribe Start -->
        <div class="container-fluid subscribe py-5">
            <div class="container text-center py-5">
                <div class="mx-auto text-center" style="max-width: 900px;">
                </div>
            </div>
        </div>
        <!-- Subscribe End -->

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
        <script src="<?php echo htmlspecialchars(mt_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php if ($show_video_hero) { ?>
        <script>
            (function () {
                var video = document.getElementById('homeHeroVideo');
                var toggle = document.getElementById('heroVideoAudioToggle');
                if (!video || !toggle) {
                    return;
                }

                function syncState() {
                    var isMuted = !!video.muted;
                    toggle.textContent = isMuted ? 'Listen' : 'Mute';
                    toggle.setAttribute('aria-pressed', isMuted ? 'false' : 'true');
                    toggle.setAttribute('aria-label', isMuted ? 'Enable video audio' : 'Mute video audio');
                }

                toggle.addEventListener('click', function () {
                    video.muted = !video.muted;
                    if (!video.muted) {
                        video.volume = 1;
                    }
                    syncState();
                });

                syncState();
            })();
        </script>
        <?php } ?>
    </body>

</html>

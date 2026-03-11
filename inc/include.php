<?php
include_once(__DIR__ . "/../admin/include/conexion.php");
include_once(__DIR__ . '/constants.php');
include_once(__DIR__ . '/booking_form.php');

// Simple guard to avoid rendering dead/legal placeholder links in the footer.
function mt_valid_public_page(string $relativePath): bool {
    $root = realpath(__DIR__ . '/..');
    $fullPath = realpath($root . '/' . ltrim($relativePath, '/'));

    // Resolve directories to their index.php if present.
    if ($fullPath && is_dir($fullPath)) {
        $indexCandidate = $fullPath . '/index.php';
        $fullPath = file_exists($indexCandidate) ? realpath($indexCandidate) : $fullPath;
    }

    if (!$fullPath || !is_file($fullPath)) {
        return false;
    }
    if (strpos($fullPath, $root) !== 0) {
        return false;
    }

    $size = filesize($fullPath);
    if ($size === false || $size < 300) {
        return false; // very small files are likely placeholders
    }

    $snippet = file_get_contents($fullPath, false, null, 0, 1000);
    $placeholders = ['lorem', 'coming soon', 'template', 'under construction'];
    foreach ($placeholders as $needle) {
        if (stripos($snippet, $needle) !== false) {
            return false;
        }
    }
    return true;
}

function mt_asset_url(string $path): string {
    $trimmed = ltrim($path, '/');
    if ($trimmed === '') {
        return $path;
    }

    $fullPath = __DIR__ . '/../' . $trimmed;
    if (!is_file($fullPath)) {
        return $path;
    }

    $version = filemtime($fullPath);
    if ($version === false) {
        return $path;
    }

    $prefix = str_starts_with($path, '/') ? '/' : '';
    return $prefix . $trimmed . '?v=' . $version;
}

function mt_is_assoc_array(array $array): bool {
    if ($array === []) {
        return false;
    }
    return array_keys($array) !== range(0, count($array) - 1);
}

$page_title = $page_title ?? 'MedTravel - Tourism and Health';
$page_description = $page_description ?? 'Medical tourism services in Colombia. We connect international patients with trusted providers and coordinate their care.';
$page_robots = $page_robots ?? 'index,follow,max-image-preview:large';
$page_og_type = $page_og_type ?? 'website';
$page_og_site_name = $page_og_site_name ?? 'MedTravel';
$page_twitter_card = $page_twitter_card ?? 'summary_large_image';
$page_meta_extra = $page_meta_extra ?? '';
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$request_path = parse_url((string)$request_uri, PHP_URL_PATH);
if (!is_string($request_path) || $request_path === '') {
    $request_path = '/';
}
if ($request_path[0] !== '/') {
    $request_path = '/' . $request_path;
}
$page_canonical = $page_canonical ?? ('https://medtravel.com.co' . $request_path);
$page_og_image = $page_og_image ?? 'https://medtravel.com.co/img/site/logo_800_182.png';
$page_lang = $page_lang ?? 'en';
$page_schema_jsonld = $page_schema_jsonld ?? [];

if (!preg_match('~^https?://~i', (string)$page_canonical)) {
    $canonical_path = (string)$page_canonical;
    if ($canonical_path === '' || $canonical_path[0] !== '/') {
        $canonical_path = '/' . ltrim($canonical_path, '/');
    }
    $page_canonical = 'https://medtravel.com.co' . $canonical_path;
}
if (!preg_match('~^https?://~i', (string)$page_og_image)) {
    $og_image_path = (string)$page_og_image;
    if ($og_image_path === '' || $og_image_path[0] !== '/') {
        $og_image_path = '/' . ltrim($og_image_path, '/');
    }
    $page_og_image = 'https://medtravel.com.co' . $og_image_path;
}

if (!is_array($page_schema_jsonld)) {
    $page_schema_jsonld = [];
} elseif (mt_is_assoc_array($page_schema_jsonld) && isset($page_schema_jsonld['@type'])) {
    $page_schema_jsonld = [$page_schema_jsonld];
}

$organization_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    '@id' => 'https://medtravel.com.co/#organization',
    'name' => 'MedTravel',
    'url' => 'https://medtravel.com.co/',
    'logo' => 'https://medtravel.com.co/img/site/logo_800_182.png',
];

$website_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    '@id' => 'https://medtravel.com.co/#website',
    'name' => 'MedTravel',
    'url' => 'https://medtravel.com.co/',
    'publisher' => [
        '@id' => 'https://medtravel.com.co/#organization',
    ],
];

$jsonld_objects = [$organization_schema, $website_schema];
foreach ($page_schema_jsonld as $schema_item) {
    if (is_array($schema_item) && !empty($schema_item)) {
        $jsonld_objects[] = $schema_item;
    }
}

$jsonld_scripts = '';
foreach ($jsonld_objects as $jsonld_object) {
    $encoded = json_encode($jsonld_object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded !== false) {
        $jsonld_scripts .= "\n    <script type=\"application/ld+json\">{$encoded}</script>";
    }
}

$meta_title = htmlspecialchars((string)$page_title, ENT_QUOTES, 'UTF-8');
$meta_description = htmlspecialchars((string)$page_description, ENT_QUOTES, 'UTF-8');
$meta_robots = htmlspecialchars((string)$page_robots, ENT_QUOTES, 'UTF-8');
$meta_canonical = htmlspecialchars((string)$page_canonical, ENT_QUOTES, 'UTF-8');
$meta_og_image = htmlspecialchars((string)$page_og_image, ENT_QUOTES, 'UTF-8');
$meta_lang = htmlspecialchars((string)$page_lang, ENT_QUOTES, 'UTF-8');
$meta_og_type = htmlspecialchars((string)$page_og_type, ENT_QUOTES, 'UTF-8');
$meta_og_site_name = htmlspecialchars((string)$page_og_site_name, ENT_QUOTES, 'UTF-8');
$meta_twitter_card = htmlspecialchars((string)$page_twitter_card, ENT_QUOTES, 'UTF-8');
$meta_extra = is_string($page_meta_extra) ? trim($page_meta_extra) : '';
$meta_extra = $meta_extra !== '' ? "\n    " . $meta_extra : '';
$toastr_css_url = htmlspecialchars(mt_asset_url('assets/global/plugins/bootstrap-toastr/toastr.min.css'), ENT_QUOTES, 'UTF-8');
$bootstrap_css_url = htmlspecialchars(mt_asset_url('css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8');
$style_css_url = htmlspecialchars(mt_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8');
$toastr_js_url = htmlspecialchars(mt_asset_url('assets/global/plugins/bootstrap-toastr/toastr.min.js'), ENT_QUOTES, 'UTF-8');
$booking_summary_js_url = htmlspecialchars(mt_asset_url('/js/booking_summary.js'), ENT_QUOTES, 'UTF-8');

$head = '<meta charset="utf-8">
    <title>' . $meta_title . '</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="' . $meta_description . '" name="description">
    <meta name="robots" content="' . $meta_robots . '">
    <meta name="language" content="' . $meta_lang . '">
    <link rel="canonical" href="' . $meta_canonical . '">
    <meta property="og:type" content="' . $meta_og_type . '">
    <meta property="og:site_name" content="' . $meta_og_site_name . '">
    <meta property="og:title" content="' . $meta_title . '">
    <meta property="og:description" content="' . $meta_description . '">
    <meta property="og:url" content="' . $meta_canonical . '">
    <meta property="og:image" content="' . $meta_og_image . '">
    <meta name="twitter:card" content="' . $meta_twitter_card . '">
    <meta name="twitter:title" content="' . $meta_title . '">
    <meta name="twitter:description" content="' . $meta_description . '">
    <meta name="twitter:image" content="' . $meta_og_image . '">' . $meta_extra . '

    <!-- Google Web Fonts -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@500;600&family=Roboto&display=swap" rel="stylesheet"> 

    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="' . $toastr_css_url . '" rel="stylesheet" type="text/css" />

    <!-- Libraries Stylesheet -->
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="lib/lightbox/css/lightbox.min.css" rel="stylesheet">


    <!-- Customized Bootstrap Stylesheet -->
    <link href="' . $bootstrap_css_url . '" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="' . $style_css_url . '" rel="stylesheet">' . $jsonld_scripts;

$logo = '<a href="index.php" class="navbar-brand p-0">
<h1 class="m-0"><i class="fas fa-stethoscope me-3"></i><span class="text-warning">Med</span>Travel</h1>
<!-- <img src="img/logo.png" alt="Logo"> -->
</a>';

$company_links = [
    ['label' => 'About',     'path' => 'about.php'],
    ['label' => 'Services',  'path' => 'services.php'],
    ['label' => 'Blog',      'path' => 'blog.php'],
    ['label' => 'Press',     'path' => 'press.php'],
    ['label' => 'Gift Cards','path' => 'gift-cards.php'],
    ['label' => 'Magazine',  'path' => 'magazine.php'],
];

$company_links_html = '';
foreach ($company_links as $link) {
    if (mt_valid_public_page($link['path'])) {
        $company_links_html .= '<a href="' . $link['path'] . '"><i class="fas fa-angle-right me-2"></i> ' . $link['label'] . '</a>';
    }
}

$topbar = '<div class="container-fluid bg-primary px-5 d-none d-lg-block">
    <div class="row gx-0">
        <div class="col-lg-8 text-center text-lg-start mb-2 mb-lg-0">
            <div class="d-inline-flex align-items-center" style="height: 45px;">
                <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle me-2" href=""><i class="fab fa-facebook-f fw-normal"></i></a>
                <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle me-2" href=""><i class="fab fa-instagram fw-normal"></i></a>
                <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle" href=""><i class="fab fa-youtube fw-normal"></i></a>
            </div>
        </div>
        <div class="col-lg-4 text-center text-lg-end">
            <div class="d-inline-flex align-items-center" style="height: 45px;">
                <a href="#"><small class="me-3 text-light"><i class="fa fa-user me-2"></i>Register</small></a>
                <a href="#"><small class="me-3 text-light"><i class="fa fa-sign-in-alt me-2"></i>Login</small></a>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle text-light" data-bs-toggle="dropdown"><small><i class="fa fa-home me-2"></i> My Dashboard</small></a>
                    <div class="dropdown-menu rounded">
                        <a href="#" class="dropdown-item"><i class="fas fa-user-alt me-2"></i> My Profile</a>
                        <a href="#" class="dropdown-item"><i class="fas fa-comment-alt me-2"></i> Inbox</a>
                        <a href="#" class="dropdown-item"><i class="fas fa-bell me-2"></i> Notifications</a>
                        <a href="#" class="dropdown-item"><i class="fas fa-cog me-2"></i> Account Settings</a>
                        <a href="#" class="dropdown-item"><i class="fas fa-power-off me-2"></i> Log Out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';

// Cargar categorías de servicio dinámicamente
$categories_query = "SELECT id, name, icon FROM service_categories WHERE is_active = 1 ORDER BY name";
$categories_result = $conexion->query($categories_query);
$categories_menu = '';
if ($categories_result && $categories_result->num_rows > 0) {
    while ($cat = $categories_result->fetch_assoc()) {
        $icon = !empty($cat['icon']) ? $cat['icon'] : 'fa-medkit';
        $categories_menu .= '<a href="offers.php?category=' . $cat['id'] . '" class="dropdown-item">';
        $categories_menu .= '<i class="fas ' . htmlspecialchars($icon) . ' me-2"></i>' . htmlspecialchars($cat['name']);
        $categories_menu .= '</a>';
    }
} else {
    $categories_menu = '<a href="offers.php" class="dropdown-item">Ver todos los servicios</a>';
}

// Detectar la página actual para marcar el menú activo
$current_page = basename($_SERVER['PHP_SELF']);
$home_active = ($current_page == 'index.php') ? 'active' : '';
$about_active = ($current_page == 'about.php') ? 'active' : '';
$services_active = (in_array($current_page, ['offers.php', 'offer_detail.php', 'services.php'])) ? 'active' : '';
$blog_active = (in_array($current_page, ['blog.php', 'blog_post.php'])) ? 'active' : '';
$booking_active = ($current_page == 'booking.php') ? 'active' : '';
$contact_active = ($current_page == 'contact.php') ? 'active' : '';

$menu = '<div class="collapse navbar-collapse" id="navbarCollapse">
    <div class="navbar-nav ms-auto py-0">
        <a href="index.php" class="nav-item nav-link ' . $home_active . '">Home</a>
        <a href="about.php" class="nav-item nav-link ' . $about_active . '">About</a>
        <a href="services.php" class="nav-item nav-link ' . $services_active . '">Services</a>
        <a href="blog.php" class="nav-item nav-link ' . $blog_active . '">Blog</a>
        <a href="booking.php" class="nav-item nav-link ' . $booking_active . '">Booking</a>
        <div class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Medical Services</a>
            <div class="dropdown-menu m-0">
                <a href="offers.php" class="dropdown-item"><i class="fas fa-list me-2"></i>All Services</a>
                <div class="dropdown-divider"></div>
                ' . $categories_menu . '
            </div>
        </div>
        <a href="contact.php" class="nav-item nav-link ' . $contact_active . '">Contact</a>
    </div>
    <a href="login.php" class="btn btn-primary rounded-pill py-2 px-4 ms-lg-4">Sign In</a>
</div>';

$contact_link = mt_valid_public_page('contact.php') ? 'contact.php' : null;
$terms_candidates = ['terms.php', 'terms.html', 'terms-and-conditions.php', 'terms-and-conditions.html'];
$cookie_candidates = ['cookie.php', 'cookies.php', 'cookie-policy.php', 'cookie-policy.html'];
$legal_candidates = ['legal.php', 'legal.html', 'legal-notice.php', 'legal-notice.html'];
$sitemap_candidates = ['sitemap.php', 'sitemap.html', 'sitemap.xml'];

$terms_link = null;
foreach ($terms_candidates as $candidate) {
    if (mt_valid_public_page($candidate)) {
        $terms_link = $candidate;
        break;
    }
}
$cookie_link = null;
foreach ($cookie_candidates as $candidate) {
    if (mt_valid_public_page($candidate)) {
        $cookie_link = $candidate;
        break;
    }
}
$legal_link = null;
foreach ($legal_candidates as $candidate) {
    if (mt_valid_public_page($candidate)) {
        $legal_link = $candidate;
        break;
    }
}
$sitemap_link = null;
foreach ($sitemap_candidates as $candidate) {
    if (mt_valid_public_page($candidate)) {
        $sitemap_link = $candidate;
        break;
    }
}

$legal_links_html = '';
if ($contact_link) {
    $legal_links_html .= '<a href="' . $contact_link . '"><i class="fas fa-angle-right me-2"></i> Contact</a>';
}
$legal_links_html .= '<a href="/privacy/"><i class="fas fa-angle-right me-2"></i> Privacy Policy</a>';
// Data deletion instructions (public)
$legal_links_html .= '<a href="/data-deletion.php"><i class="fas fa-angle-right me-2"></i> Data Deletion</a>';
if ($terms_link) {
    $legal_links_html .= '<a href="' . $terms_link . '"><i class="fas fa-angle-right me-2"></i> Terms and Conditions</a>';
}
if ($cookie_link) {
    $legal_links_html .= '<a href="' . $cookie_link . '"><i class="fas fa-angle-right me-2"></i> Cookie policy</a>';
}
if ($legal_link) {
    $legal_links_html .= '<a href="' . $legal_link . '"><i class="fas fa-angle-right me-2"></i> Legal Notice</a>';
}
if ($sitemap_link) {
    $legal_links_html .= '<a href="' . $sitemap_link . '"><i class="fas fa-angle-right me-2"></i> Sitemap</a>';
}

$footer = '<div class="container-fluid footer py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <h4 class="mb-4 text-white">Get In Touch</h4>
                    <a href=""><i class="fas fa-envelope me-2"></i> info@medtravel.com</a>
                    <a href=""><i class="fas fa-phone me-2"></i> +561 698 8069</a>
                    <a href="" class="mb-3"><i class="fas fa-print me-2"></i> +561 698 8069</a>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-share fa-2x text-white me-2"></i>
                        <a class="btn-square btn btn-primary rounded-circle mx-1" href=""><i class="fab fa-facebook-f"></i></a>
                        <a class="btn-square btn btn-primary rounded-circle mx-1" href=""><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <h4 class="mb-4 text-white">Company</h4>
                    ' . $company_links_html . '
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <h4 class="mb-4 text-white">Support</h4>
                    ' . $legal_links_html . '
                </div>
            </div>
            <!--<div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item">
                    <div class="row gy-3 gx-2 mb-4">
                        <div class="col-xl-6">
                            <form>
                                <div class="form-floating">
                                    <select class="form-select bg-dark border" id="select1">
                                        <option value="1">Arabic</option>
                                        <option value="2">German</option>
                                        <option value="3">Greek</option>
                                        <option value="3">New York</option>
                                    </select>
                                    <label for="select1">English</label>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-6">
                            <form>
                                <div class="form-floating">
                                    <select class="form-select bg-dark border" id="select1">
                                        <option value="1">USD</option>
                                        <option value="2">EUR</option>
                                        <option value="3">INR</option>
                                        <option value="3">GBP</option>
                                    </select>
                                    <label for="select1">$</label>
                                </div>
                            </form>
                        </div>
                    </div>
                    <h4 class="text-white mb-3">Payments</h4>
                    <div class="footer-bank-card">
                        <a href="#" class="text-white me-2"><i class="fab fa-cc-amex fa-2x"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-cc-visa fa-2x"></i></a>
                        <a href="#" class="text-white me-2"><i class="fas fa-credit-card fa-2x"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-cc-mastercard fa-2x"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-cc-paypal fa-2x"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-cc-discover fa-2x"></i></a>
                    </div>
                </div>
            </div>-->
        </div>
    </div>
</div>';

$contact = '<div class="container-fluid contact bg-light py-5">
                <div class="container py-5">
                    <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                        <h5 class="section-title px-3">Contact Us</h5>
                        <h1 class="mb-0">Contact For Any Query</h1>
                    </div>
                    <div class="row g-5 align-items-center">
                        <div class="col-lg-4">
                            <div class="bg-white rounded p-4">
                                <div class="text-center mb-4">
                                    <i class="fa fa-map-marker-alt fa-3x text-primary"></i>
                                    <h4 class="text-primary"><Address></Address></h4>
                                    <p class="mb-0">417 Golden River Drive West, <br> Palm Beach Fl. 33411, USA</p>
                                </div>
                                <div class="text-center mb-4">
                                    <i class="fa fa-phone-alt fa-3x text-primary mb-3"></i>
                                    <h4 class="text-primary">Mobile</h4>
                                    <p class="mb-0"><a href="phone:+1561698-8069">+1 (561) 698-8069</a></p>
                                </div>
                            
                                <div class="text-center">
                                    <i class="fa fa-envelope-open fa-3x text-primary mb-3"></i>
                                    <h4 class="text-primary">Email</h4>
                                    <p class="mb-0">info@medtravel.com.co</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <h3 class="mb-2">Send us a message</h3>
                            <p class="mb-4">The contact form is currently inactive. Get a functional and working contact form with Ajax & PHP in a few minutes. Just copy and paste the files, add a little code and youre done. <a href="https://htmlcodex.com/contact-form">Download Now</a>.</p>
                            <form>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control border-0" id="name" placeholder="Your Name">
                                            <label for="name">Your Name</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control border-0" id="email" placeholder="Your Email">
                                            <label for="email">Your Email</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control border-0" id="subject" placeholder="Subject">
                                            <label for="subject">Subject</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <textarea class="form-control border-0" placeholder="Leave a message here" id="message" style="height: 160px"></textarea>
                                            <label for="message">Message</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <a class="btn btn-primary w-100 py-3" onclick="submit()">Send Message</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>';

$booking_widget = (function() {
    $booking_texts = get_booking_texts();
    $booking_style_attr = booking_background_style($booking_texts);
    ob_start();
    ?>
    <div class="container-fluid booking py-5" id="booking-section" <?php echo $booking_style_attr; ?>>
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <h5 class="section-booking-title pe-3">Booking</h5>
                    <h1 class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['intro_title']); ?></h1>
                    <p class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['intro_paragraph']); ?></p>
                    <p class="text-white mb-4"><?php echo htmlspecialchars($booking_texts['secondary_paragraph']); ?></p>
                    <a href="#" class="btn btn-light text-primary rounded-pill py-3 px-5 mt-2">Read More</a>
                </div>
                <div class="col-lg-6">
                    <h1 class="text-white mb-3">Book A Tour Deals</h1>
                    <p class="text-white mb-4">Get <span class="text-warning">50% Off</span> On Your First Adventure Trip With MedTravel. Get More Deal Offers Here.</p>
                    <?php render_booking_form('booking_widget'); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
})();

$current_path = parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH);
$is_booking_wizard_page = ($current_page === 'wizard.php' && strpos((string)$current_path, '/booking/') !== false);
$wizard_package_summary_markup = '';
if (!$is_booking_wizard_page) {
    ob_start();
    include __DIR__ . '/wizard_package_summary.php';
    $wizard_package_summary_markup = ob_get_clean();
}

// Inyectar el resumen canónico en el layout global (footer) para que exista cross-page.
$footer .= $wizard_package_summary_markup;

$script =  '<script src="' . $toastr_js_url . '" type="text/javascript"></script>
            <script>
            // Función para hacer scroll suave al widget de booking
            function scrollToBooking(offerId) {
                try {
                    localStorage.setItem("mt_booking_started", "1");
                    if (offerId) {
                        localStorage.setItem("mt_preselected_offer_id", String(offerId));
                    }
                    window.dispatchEvent(new Event("mt-booking-state-changed"));
                } catch (e) {}

                const bookingSection = document.getElementById("booking-section");
                if (bookingSection) {
                    // Si hay un ID de oferta, guardarlo en sessionStorage para pre-seleccionarlo
                    if (offerId) {
                        sessionStorage.setItem("preselected_offer_id", offerId);
                    }

                    // Smooth scroll al widget
                    bookingSection.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });

                    // Opcional: highlight temporal del widget
                    bookingSection.style.transition = "box-shadow 0.3s ease";
                    bookingSection.style.boxShadow = "0 0 20px rgba(102, 126, 234, 0.6)";
                    setTimeout(() => {
                        bookingSection.style.boxShadow = "none";
                    }, 1500);
                    return;
                }

                // Fallback cross-page: llevar al booking central si esta página no tiene widget.
                window.location.href = "/booking.php#booking-section";
            }
                
            (function(d,t) {
                var BASE_URL="https://app.conectarbot.com";
                var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
                g.src=BASE_URL+"/packs/js/sdk.js";
                g.async = true;
                s.parentNode.insertBefore(g,s);
                g.onload=function(){
                window.chatwootSDK.run({
                    websiteToken: \'3htHsJ7ig63EHraeYDRqgRCk\',
                    baseUrl: BASE_URL
                })
                }
            })(document,"script");
            </script>
            <script src="' . $booking_summary_js_url . '"></script>';

$copyright = <<<HTML
<div class="container-fluid copyright text-body py-4">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-6 text-center text-md-end mb-md-0">
                <i class="fas fa-copyright me-2"></i><a class="text-white" href="#">MedTravel</a>, All right reserved.
            </div>
        </div>
    </div>
</div>
HTML;
?>

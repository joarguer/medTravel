<?php
include('inc/include.php');

// Obtener ID de la oferta
$offer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($offer_id == 0) {
    header('Location: offers.php');
    exit();
}

// Consulta de la oferta con información del prestador
$query = "
    SELECT 
        o.id, o.title, o.description, o.price_from, o.currency, o.duration,
        o.includes, o.not_includes, o.requirements, o.recovery_time,
        p.id as provider_id, p.name as provider_name, p.description as provider_description,
        p.city, p.address, p.phone, p.email, p.website, p.logo,
        sc.id as service_id, sc.name as service_name, sc.icon as service_icon,
        scat.name as category_name
    FROM provider_service_offers o
    INNER JOIN providers p ON o.provider_id = p.id
    INNER JOIN service_catalog sc ON o.service_id = sc.id
    INNER JOIN service_categories scat ON sc.category_id = scat.id
    WHERE o.id = ? AND o.is_active = 1 AND p.is_active = 1
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $offer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header('Location: offers.php');
    exit();
}

$offer = mysqli_fetch_assoc($result);

// Obtener imágenes de la oferta
$images_query = "SELECT image_path, is_primary FROM offer_media WHERE offer_id = ? ORDER BY is_primary DESC, sort_order ASC";
$img_stmt = mysqli_prepare($conn, $images_query);
mysqli_stmt_bind_param($img_stmt, "i", $offer_id);
mysqli_stmt_execute($img_stmt);
$images_result = mysqli_stmt_get_result($img_stmt);
$images = [];
while ($img = mysqli_fetch_assoc($images_result)) {
    $images[] = $img;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        /* Hero Section */
        .offer-hero {
            position: relative;
            height: 500px;
            overflow: hidden;
        }
        .hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 100%);
        }
        .hero-content {
            position: absolute;
            bottom: 60px;
            left: 0;
            right: 0;
            color: white;
            z-index: 10;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 20px;
            text-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        .hero-service-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 15px;
            box-shadow: 0 6px 20px rgba(245, 87, 108, 0.5);
        }
        
        /* Price Card */
        .price-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(118, 75, 162, 0.4);
            position: sticky;
            top: 100px;
        }
        .price-amount {
            font-size: 3.5rem;
            font-weight: 900;
            margin: 20px 0;
        }
        .price-label {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        .btn-book {
            background: white;
            color: #667eea;
            padding: 18px 40px;
            border-radius: 35px;
            font-weight: 800;
            width: 100%;
            border: none;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .btn-book:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.3);
            color: #764ba2;
        }
        
        /* Provider Card */
        .provider-card {
            background: white;
            padding: 35px;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.08);
            margin-top: 30px;
        }
        .provider-logo-large {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 20px;
            border: 4px solid #e2e8f0;
            padding: 10px;
            background: white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .provider-name-large {
            font-size: 1.8rem;
            font-weight: 800;
            color: #2d3748;
            margin: 20px 0 10px 0;
        }
        .contact-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            color: #4a5568;
            font-size: 1rem;
        }
        .contact-item i {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        /* Content Sections */
        .content-section {
            background: white;
            padding: 50px;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        .section-heading {
            font-size: 2rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e2e8f0;
        }
        .section-heading i {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-right: 15px;
        }
        
        /* Features List */
        .features-list {
            list-style: none;
            padding: 0;
        }
        .features-list li {
            padding: 15px 20px;
            margin-bottom: 12px;
            background: #f7fafc;
            border-radius: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .features-list li:hover {
            transform: translateX(10px);
            background: #edf2f7;
        }
        .features-list li i {
            color: #667eea;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        /* Excluded List */
        .excluded-list {
            list-style: none;
            padding: 0;
        }
        .excluded-list li {
            padding: 15px 20px;
            margin-bottom: 12px;
            background: #fff5f5;
            border-radius: 15px;
            border-left: 4px solid #f56565;
            color: #742a2a;
        }
        .excluded-list li i {
            color: #f56565;
            margin-right: 12px;
        }
        
        /* Info Badges */
        .info-badge {
            display: inline-block;
            background: #edf2f7;
            color: #4a5568;
            padding: 12px 25px;
            border-radius: 25px;
            margin: 10px 10px 10px 0;
            font-weight: 600;
        }
        .info-badge i {
            color: #667eea;
            margin-right: 8px;
        }
        
        /* Image Gallery */
        .gallery-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .gallery-image:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 35px rgba(0,0,0,0.2);
        }
        
        /* Breadcrumb Custom */
        .breadcrumb-custom {
            background: rgba(255,255,255,0.2);
            padding: 12px 25px;
            border-radius: 30px;
            backdrop-filter: blur(10px);
            display: inline-block;
        }
        .breadcrumb-custom a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        .breadcrumb-custom a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            .price-amount {
                font-size: 2.5rem;
            }
            .content-section {
                padding: 30px 25px;
            }
        }
    </style>
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

    <!-- Hero Section -->
    <div class="offer-hero">
        <?php 
        $hero_image = !empty($images[0]['image_path']) ? $images[0]['image_path'] : 'img/site/placeholder-medical.jpg';
        ?>
        <img src="<?php echo htmlspecialchars($hero_image); ?>" 
             alt="<?php echo htmlspecialchars($offer['title']); ?>" 
             class="hero-image"
             onerror="this.src='img/site/placeholder-medical.jpg'">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="container">
                <div class="breadcrumb-custom mb-4">
                    <a href="index.php">Home</a> / 
                    <a href="offers.php">Services</a> / 
                    <span><?php echo htmlspecialchars($offer['category_name']); ?></span>
                </div>
                <span class="hero-service-badge">
                    <i class="fas <?php echo !empty($offer['service_icon']) ? $offer['service_icon'] : 'fa-medkit'; ?>"></i>
                    <?php echo htmlspecialchars($offer['service_name']); ?>
                </span>
                <h1 class="hero-title"><?php echo htmlspecialchars($offer['title']); ?></h1>
                <div class="info-badge" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-hospital-alt"></i><?php echo htmlspecialchars($offer['provider_name']); ?>
                </div>
                <?php if (!empty($offer['city'])): ?>
                    <div class="info-badge" style="background: rgba(255,255,255,0.2); color: white;">
                        <i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($offer['city']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Hero Section End -->

    <!-- Main Content -->
    <div class="container" style="margin-top: -80px; position: relative; z-index: 100;">
        <div class="row g-4">
            <!-- Left Column - Content -->
            <div class="col-lg-8">
                <!-- Description -->
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-info-circle"></i>Description
                    </h2>
                    <p style="font-size: 1.1rem; line-height: 1.8; color: #4a5568;">
                        <?php echo nl2br(htmlspecialchars($offer['description'])); ?>
                    </p>
                    
                    <?php if (!empty($offer['duration'])): ?>
                        <div class="mt-4">
                            <span class="info-badge">
                                <i class="fas fa-clock"></i>Duration: <?php echo htmlspecialchars($offer['duration']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($offer['recovery_time'])): ?>
                        <div class="mt-2">
                            <span class="info-badge">
                                <i class="fas fa-heartbeat"></i>Recovery: <?php echo htmlspecialchars($offer['recovery_time']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- What's Included -->
                <?php if (!empty($offer['includes'])): ?>
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-check-circle"></i>What's Included
                    </h2>
                    <ul class="features-list">
                        <?php 
                        $includes = explode("\n", $offer['includes']);
                        foreach ($includes as $item):
                            $item = trim($item);
                            if (!empty($item)):
                        ?>
                            <li>
                                <i class="fas fa-check"></i>
                                <?php echo htmlspecialchars($item); ?>
                            </li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- What's NOT Included -->
                <?php if (!empty($offer['not_includes'])): ?>
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-times-circle"></i>Not Included
                    </h2>
                    <ul class="excluded-list">
                        <?php 
                        $not_includes = explode("\n", $offer['not_includes']);
                        foreach ($not_includes as $item):
                            $item = trim($item);
                            if (!empty($item)):
                        ?>
                            <li>
                                <i class="fas fa-times"></i>
                                <?php echo htmlspecialchars($item); ?>
                            </li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Requirements -->
                <?php if (!empty($offer['requirements'])): ?>
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-clipboard-list"></i>Requirements
                    </h2>
                    <ul class="features-list">
                        <?php 
                        $requirements = explode("\n", $offer['requirements']);
                        foreach ($requirements as $item):
                            $item = trim($item);
                            if (!empty($item)):
                        ?>
                            <li>
                                <i class="fas fa-file-medical"></i>
                                <?php echo htmlspecialchars($item); ?>
                            </li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Image Gallery -->
                <?php if (count($images) > 1): ?>
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-images"></i>Gallery
                    </h2>
                    <div class="row g-3">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-4">
                                <a href="<?php echo htmlspecialchars($image['image_path']); ?>" data-lightbox="offer-gallery">
                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="Gallery image" 
                                         class="gallery-image"
                                         onerror="this.src='img/site/placeholder-medical.jpg'">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="col-lg-4">
                <!-- Price Card -->
                <div class="price-card">
                    <div class="text-center">
                        <div class="price-label">Starting from</div>
                        <div class="price-amount">
                            <?php echo htmlspecialchars($offer['currency']); ?> 
                            <?php echo number_format($offer['price_from'], 2); ?>
                        </div>
                        <button class="btn-book" onclick="scrollToProvider()">
                            <i class="fas fa-envelope me-2"></i>Request Information
                        </button>
                    </div>
                </div>

                <!-- Provider Card -->
                <div class="provider-card">
                    <div class="text-center">
                        <?php if (!empty($offer['logo'])): ?>
                            <img src="admin/img/providers/<?php echo $offer['provider_id']; ?>/<?php echo htmlspecialchars($offer['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($offer['provider_name']); ?>" 
                                 class="provider-logo-large mx-auto"
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                        <h3 class="provider-name-large"><?php echo htmlspecialchars($offer['provider_name']); ?></h3>
                        <?php if (!empty($offer['provider_description'])): ?>
                            <p class="text-muted mb-4"><?php echo htmlspecialchars(substr($offer['provider_description'], 0, 150)) . '...'; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4" id="provider-contact">
                        <?php if (!empty($offer['city'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <strong>Location</strong><br>
                                    <?php echo htmlspecialchars($offer['city']); ?>
                                    <?php if (!empty($offer['address'])): ?>
                                        <br><small><?php echo htmlspecialchars($offer['address']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($offer['phone'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <strong>Phone</strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($offer['phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($offer['phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($offer['email'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <strong>Email</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($offer['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($offer['email']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($offer['website'])): ?>
                            <div class="contact-item">
                                <i class="fas fa-globe"></i>
                                <div>
                                    <strong>Website</strong><br>
                                    <a href="<?php echo htmlspecialchars($offer['website']); ?>" target="_blank" class="text-decoration-none">
                                        Visit Website
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Main Content End -->

    <div class="pb-5"></div>

    <!-- Footer Start -->
    <?php echo $footer; ?>
    <!-- Footer End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/lightbox/js/lightbox.min.js"></script>
    <script src="js/main.js"></script>
    
    <script>
        // Remove spinner after page load
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('spinner').classList.remove('show');
            }, 500);
        });
        
        // Scroll to provider section
        function scrollToProvider() {
            document.getElementById('provider-contact').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    </script>
</body>
</html>
<?php 
mysqli_stmt_close($stmt);
mysqli_stmt_close($img_stmt);
?>

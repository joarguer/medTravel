<?php
include('inc/include.php');

// Obtener ID de la oferta
$offer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($offer_id == 0) {
    die('ID de oferta inválido');
}

// Consulta simplificada
$query = "
    SELECT 
        o.id, o.title, o.description, o.price_from, o.currency,
        p.id as provider_id, p.name as provider_name, 
        p.city, p.phone, p.email, p.logo
    FROM provider_service_offers o
    INNER JOIN providers p ON o.provider_id = p.id
    WHERE o.id = ? AND o.is_active = 1
    LIMIT 1
";

$stmt = mysqli_prepare($conexion, $query);
if (!$stmt) {
    die('Error en prepare: ' . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $offer_id);
if (!mysqli_stmt_execute($stmt)) {
    die('Error en execute: ' . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo '<!DOCTYPE html><html><head><title>Offer Not Found</title></head><body>';
    echo '<div style="max-width:800px;margin:100px auto;padding:40px;background:#f7fafc;border-radius:20px;text-align:center;">';
    echo '<h1 style="color:#667eea;margin-bottom:20px;">🔍 Oferta No Encontrada</h1>';
    echo '<p style="font-size:18px;color:#4a5568;margin-bottom:30px;">La oferta con ID <strong>' . $offer_id . '</strong> no existe o no está activa.</p>';
    echo '<a href="offers.php" style="display:inline-block;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:15px 40px;border-radius:30px;text-decoration:none;font-weight:bold;">← Ver Todas las Ofertas</a>';
    echo '</div></body></html>';
    mysqli_stmt_close($stmt);
    exit();
}

$offer = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        .offer-hero {
            position: relative;
            height: 400px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .hero-content {
            position: relative;
            padding-top: 100px;
            color: white;
            z-index: 10;
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 20px;
        }
        .content-section {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .section-heading {
            font-size: 1.8rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 20px;
        }
        .price-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(118, 75, 162, 0.4);
            position: sticky;
            top: 100px;
        }
        .price-amount {
            font-size: 3rem;
            font-weight: 900;
            margin: 15px 0;
        }
        .btn-book {
            background: white;
            color: #667eea;
            padding: 15px 30px;
            border-radius: 30px;
            font-weight: 800;
            width: 100%;
            border: none;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .provider-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            margin-top: 20px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            color: #4a5568;
        }
        .contact-item i {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
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
        <div class="hero-content">
            <div class="container text-center">
                <h1 class="hero-title"><?php echo htmlspecialchars($offer['title']); ?></h1>
                <p class="text-white-50">By <?php echo htmlspecialchars($offer['provider_name']); ?></p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container" style="margin-top: 50px;">
        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-8">
                <div class="content-section">
                    <h2 class="section-heading">
                        <i class="fas fa-info-circle"></i> Description
                    </h2>
                    <p style="font-size: 1.1rem; line-height: 1.8; color: #4a5568;">
                        <?php echo nl2br(htmlspecialchars($offer['description'])); ?>
                    </p>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Price Card -->
                <div class="price-card">
                    <div class="text-center">
                        <div style="font-size: 1rem; opacity: 0.9;">Starting from</div>
                        <div class="price-amount">
                            <?php echo htmlspecialchars($offer['currency']); ?> 
                            <?php echo number_format($offer['price_from'], 2); ?>
                        </div>
                        <a href="mailto:<?php echo htmlspecialchars($offer['email']); ?>" class="btn-book">
                            Request Information
                        </a>
                    </div>
                </div>

                <!-- Provider Card -->
                <div class="provider-card">
                    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($offer['provider_name']); ?>
                    </h3>
                    
                    <?php if (!empty($offer['city'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong>Location</strong><br>
                                <?php echo htmlspecialchars($offer['city']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($offer['phone'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong>Phone</strong><br>
                                <a href="tel:<?php echo htmlspecialchars($offer['phone']); ?>">
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
                                <a href="mailto:<?php echo htmlspecialchars($offer['email']); ?>">
                                    <?php echo htmlspecialchars($offer['email']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('spinner').classList.remove('show');
            }, 300);
        });
    </script>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>

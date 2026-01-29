<?php
include('inc/include.php');

// Obtener configuración del header desde la base de datos
$busca_header = mysqli_query($conexion,"SELECT * FROM services_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
if(mysqli_num_rows($busca_header) > 0) {
    $rst_header = mysqli_fetch_array($busca_header);
    $page_title = $rst_header['title'];
    $page_subtitle_1 = $rst_header['subtitle_1'];
    $page_subtitle_2 = $rst_header['subtitle_2'];
    $bg_image = $rst_header['bg_image'];
} else {
    // Valores por defecto si no existe configuración
    $page_title = 'Our Medical Services';
    $page_subtitle_1 = 'MEDICAL SERVICES';
    $page_subtitle_2 = 'Discover quality medical services from verified providers';
    $bg_image = '';
}

// Obtener categoría del parámetro GET
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$category_name = $page_title;

// Obtener información de la categoría si existe
if ($category_id > 0) {
    $cat_query = mysqli_prepare($conexion, "SELECT name, description FROM service_categories WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($cat_query, 'i', $category_id);
    mysqli_stmt_execute($cat_query);
    $cat_result = mysqli_stmt_get_result($cat_query);
    if ($cat_row = mysqli_fetch_assoc($cat_result)) {
        $category_name = htmlspecialchars($cat_row['name']);
    }
    mysqli_stmt_close($cat_query);
}

// Obtener ofertas activas con sus prestadores
if ($category_id > 0) {
    // Filtrar por categoría
    $offers_query = "
        SELECT 
            o.id, o.title, o.description, o.price_from, o.currency,
            p.id as provider_id, p.name as provider_name, p.city, p.logo,
            sc.name as service_name
        FROM provider_service_offers o
        INNER JOIN providers p ON o.provider_id = p.id
        INNER JOIN service_catalog sc ON o.service_id = sc.id
        WHERE sc.category_id = ?
        ORDER BY o.id DESC
    ";
    $stmt = mysqli_prepare($conexion, $offers_query);
    mysqli_stmt_bind_param($stmt, 'i', $category_id);
} else {
    // Todas las ofertas
    $offers_query = "
        SELECT 
            o.id, o.title, o.description, o.price_from, o.currency,
            p.id as provider_id, p.name as provider_name, p.city, p.logo,
            sc.name as service_name
        FROM provider_service_offers o
        INNER JOIN providers p ON o.provider_id = p.id
        INNER JOIN service_catalog sc ON o.service_id = sc.id
        ORDER BY o.id DESC
    ";
    $stmt = mysqli_prepare($conexion, $offers_query);
}

mysqli_stmt_execute($stmt);
$offers_result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        .offer-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: white;
        }
        .offer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: #d1d5db;
        }
        .offer-card .card-img-top {
            height: 220px;
            object-fit: cover;
        }
        .provider-logo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: absolute;
            top: 180px;
            left: 20px;
            background: white;
        }
        .price-info {
            background: #f8fafc;
            padding: 12px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .price-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .price-amount {
            color: #0f766e;
            font-size: 20px;
            font-weight: 700;
        }
        .service-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }
        .service-badge i {
            font-size: 14px;
        }
        .offer-card .card-body {
            padding: 24px;
        }
        .offer-card .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .offer-card .card-text {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .provider-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 0;
            border-top: 1px solid #f1f5f9;
            margin-top: 16px;
        }
        .provider-info i {
            color: #0f766e;
            font-size: 16px;
        }
        .provider-name {
            color: #334155;
            font-weight: 600;
            font-size: 14px;
            margin: 0;
        }
        .city-tag {
            color: #94a3b8;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }
        .city-tag i {
            font-size: 12px;
        }
        .btn-view-offer {
            background: #0f766e;
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-view-offer:hover {
            background: #0d9488;
            color: white;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.3);
        }
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        .no-offers {
            text-align: center;
            padding: 100px 20px;
        }
        .no-offers i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
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

    <!-- Header Start -->
    <div class="category-header" style="<?php 
        if (!empty($bg_image)) {
            echo 'background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url(' . htmlspecialchars($bg_image) . '); background-size: cover; background-position: center;';
        }
    ?>">
        <div class="container text-center">
            <h5 class="text-white-50 mb-3"><?php echo htmlspecialchars($page_subtitle_1); ?></h5>
            <h1 class="display-3 text-white mb-4"><?php echo htmlspecialchars($category_name); ?></h1>
            <p class="text-white-50 mb-0"><?php echo htmlspecialchars($page_subtitle_2); ?></p>
        </div>
    </div>
    <!-- Header End -->

    <!-- Offers Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <?php if (mysqli_num_rows($offers_result) > 0): ?>
                <div class="row g-4">
                    <?php while ($offer = mysqli_fetch_assoc($offers_result)): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="offer-card card h-100">
                                <div class="position-relative">
                                    <?php 
                                    // Intentar obtener imagen de offer_media
                                    $image_path = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-family="Arial" font-size="18"%3EMedical Service%3C/text%3E%3C/svg%3E';
                                    
                                    $img_query = mysqli_query($conexion, "SELECT file_path FROM offer_media WHERE offer_id = {$offer['id']} AND media_type = 'image' ORDER BY id ASC LIMIT 1");
                                    if ($img_query && $img_row = mysqli_fetch_assoc($img_query)) {
                                        $image_path = htmlspecialchars($img_row['file_path']);
                                    }
                                    ?>
                                    <img src="<?php echo $image_path; ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($offer['title']); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22 font-size=%2218%22%3EMedical Service%3C/text%3E%3C/svg%3E';">
                                    
                                    <?php if ($offer['logo']): ?>
                                        <img src="admin/img/providers/<?php echo $offer['provider_id']; ?>/<?php echo htmlspecialchars($offer['logo']); ?>" 
                                             class="provider-logo" 
                                             alt="<?php echo htmlspecialchars($offer['provider_name']); ?>"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <span class="service-badge">
                                        <i class="fas fa-stethoscope"></i>
                                        <?php echo htmlspecialchars($offer['service_name']); ?>
                                    </span>
                                    
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($offer['title']); ?>
                                    </h5>
                                    
                                    <p class="card-text" style="height: 60px; overflow: hidden;">
                                        <?php echo htmlspecialchars(substr($offer['description'], 0, 120)) . '...'; ?>
                                    </p>
                                    
                                    <div class="provider-info">
                                        <div style="flex: 1;">
                                            <div class="provider-name">
                                                <i class="fas fa-hospital"></i>
                                                <?php echo htmlspecialchars($offer['provider_name']); ?>
                                            </div>
                                            <?php if ($offer['city']): ?>
                                                <div class="city-tag">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($offer['city']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <a href="offer_detail.php?id=<?php echo $offer['id']; ?>" class="btn btn-view-offer">
                                        <i class="fas fa-info-circle me-2"></i>View Details
                                    </a>
                                </div>
                                
                                <?php if ($offer['price_from']): ?>
                                    <div class="price-info">
                                        <span class="price-label">Starting from</span>
                                        <span class="price-amount">
                                            <?php echo $offer['currency']; ?> <?php echo number_format($offer['price_from'], 2); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-offers">
                    <i class="fas fa-inbox"></i>
                    <h3 class="text-muted mb-3">No offers available yet</h3>
                    <p class="text-muted">Check back soon for new medical services in this category</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Offers End -->

    <?php echo $footer; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/lightbox/js/lightbox.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Remove spinner immediately and on load
        (function() {
            var spinner = document.getElementById('spinner');
            if (spinner) {
                setTimeout(function() {
                    spinner.classList.remove('show');
                    spinner.style.display = 'none';
                }, 500);
            }
        })();
        
        window.addEventListener('load', function() {
            var spinner = document.getElementById('spinner');
            if (spinner) {
                spinner.classList.remove('show');
                spinner.style.display = 'none';
            }
        });
        
        // Force hide after 2 seconds regardless
        setTimeout(function() {
            var spinner = document.getElementById('spinner');
            if (spinner) {
                spinner.classList.remove('show');
                spinner.style.display = 'none';
            }
        }, 2000);
    </script>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>

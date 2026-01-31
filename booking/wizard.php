<?php
session_start();
include(__DIR__ . '/../inc/include.php');
$booking = isset($_SESSION['booking_request']) ? $_SESSION['booking_request'] : [];
$submission_status = isset($_SESSION['booking_request_status']) ? $_SESSION['booking_request_status'] : '';
$submission_message = isset($_SESSION['booking_request_message']) ? $_SESSION['booking_request_message'] : '';
unset($_SESSION['booking_request_status'], $_SESSION['booking_request_message']);

// Capturar oferta pre-seleccionada si existe
$preselected_offer_id = !empty($booking['preselected_offer']) ? intval($booking['preselected_offer']) : 0;

$categories = [];
$cat_query = "SELECT id, name, description FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, id DESC";
$cat_res = mysqli_query($conexion, $cat_query);
if ($cat_res) {
    while ($row = mysqli_fetch_assoc($cat_res)) {
        $categories[] = $row;
    }
}

// Cargar ofertas activas de proveedores con información completa
$offers = [];
$offers_sql = "SELECT 
                o.id, o.title, o.description, o.price_from, o.currency, o.provider_id,
                p.name AS provider_name, p.city AS provider_city, p.logo AS provider_logo,
                sc.name AS service_name, sc.category_id,
                cat.name AS category_name
               FROM provider_service_offers o
               INNER JOIN providers p ON o.provider_id = p.id
               INNER JOIN service_catalog sc ON o.service_id = sc.id
               LEFT JOIN service_categories cat ON sc.category_id = cat.id
               WHERE o.is_active = 1
               ORDER BY cat.name ASC, sc.sort_order ASC, o.id DESC";
$offers_res = mysqli_query($conexion, $offers_sql);
if ($offers_res) {
    while ($row = mysqli_fetch_assoc($offers_res)) {
        $offers[] = $row;
    }
}

// Agrupar ofertas por categoría para mejor visualización
$offers_by_category = [];
foreach ($offers as $offer) {
    $cat_id = $offer['category_id'];
    if (!isset($offers_by_category[$cat_id])) {
        $offers_by_category[$cat_id] = [
            'category_name' => $offer['category_name'],
            'offers' => []
        ];
    }
    $offers_by_category[$cat_id]['offers'][] = $offer;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $head; ?>
    <style>
        .wizard-summary { 
            background: #f8fafc; 
            border-radius: 12px; 
            padding: 24px; 
            margin-bottom: 32px;
            border: 1px solid #e5e7eb;
        }
        .wizard-summary h2 { margin-top: 0; color: #1e293b; }
        .wizard-summary p { margin-bottom: 6px; }
        .wizard-stage { 
            border: 1px solid #e5e7eb; 
            border-radius: 10px; 
            padding: 24px; 
            margin-bottom: 20px;
            background: white;
        }
        .wizard-stage h3 { 
            font-size: 1.2rem; 
            margin-bottom: 12px;
            color: #1e293b;
        }
        
        /* Estilos para ofertas de proveedores */
        .offer-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: white;
        }
        .offer-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }
        .offer-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .offer-card .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .provider-logo-small {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .provider-info h6 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .provider-info small {
            color: #64748b;
            font-size: 12px;
        }
        .offer-card .card-body {
            padding: 16px;
        }
        .offer-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .offer-description {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .offer-price {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: 18px;
        }
        .offer-price small {
            font-size: 12px;
            font-weight: 500;
            opacity: 0.9;
        }
        .offer-checkbox {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .category-section {
            margin-bottom: 32px;
        }
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
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
    <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(19, 53, 123, 0.5), rgba(19, 53, 123, 0.5)), url(../img/carousel-1.jpg); background-position: center center; background-repeat: no-repeat; background-size: cover;">
        <div class="container text-center py-5" style="max-width: 900px;">
            <h3 class="text-white display-3 mb-4">Booking Wizard</h3>
            <ol class="breadcrumb justify-content-center mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active text-white">Booking Request</li>
            </ol>
        </div>
    </div>
    <!-- Header End -->
    
    <!-- Wizard Start -->
    <div class="container py-5">
        <?php if ($submission_status === 'submitted'): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($submission_message ?: 'Thank you. Your request was recorded. One of our coordinators will contact you soon.'); ?>
            </div>
        <?php elseif ($submission_status === 'error'): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($submission_message ?: 'Please review the form.'); ?>
            </div>
        <?php endif; ?>
        <div class="wizard-summary">
            <h2>Step 1 completed</h2>
            <p>We captured your contact context so we can continue with the wizard.</p>
            <?php if (!empty($booking)): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                <?php if (!empty($booking['destination'])): ?>
                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($booking['destination']); ?></p>
                <?php endif; ?>
                <?php if (!empty($booking['datetime'])): ?>
                    <p><strong>Preferred time:</strong> <?php echo htmlspecialchars($booking['datetime']); ?></p>
                <?php endif; ?>
                <?php if (!empty($booking['special_request'])): ?>
                    <p><strong>Special request:</strong> <?php echo htmlspecialchars($booking['special_request']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p>No data captured yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($preselected_offer_id > 0): ?>
            <div class="alert alert-success" style="background: #dcfce7; border: 1px solid #86efac; color: #166534; margin-bottom: 20px;">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Offer Pre-Selected:</strong> We've already selected the offer you were viewing. You can add more offers below or proceed to submit.
            </div>
        <?php endif; ?>

        <form action="/booking/submit.php" method="POST" id="booking-wizard-form">
            <div class="wizard-stage">
                <h3>Stage 2 – Select Medical Services</h3>
                <p class="mb-4">Choose from our certified providers' available services. You can select multiple services to build your medical travel package.</p>
                
                <?php if (count($offers_by_category) > 0): ?>
                    <?php foreach ($offers_by_category as $cat_id => $category_data): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <i class="fas fa-heartbeat me-2"></i>
                                <?php echo htmlspecialchars($category_data['category_name']); ?>
                            </div>
                            <div class="row g-3">
                                <?php foreach ($category_data['offers'] as $offer): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card offer-card" onclick="toggleOfferSelection(this, <?php echo $offer['id']; ?>)">
                                            <input type="checkbox" 
                                                   name="selected_offers[]" 
                                                   value="<?php echo $offer['id']; ?>" 
                                                   class="offer-checkbox"
                                                   <?php echo ($preselected_offer_id === $offer['id']) ? 'checked' : ''; ?>
                                                   id="offer-<?php echo $offer['id']; ?>">
                                            
                                            <div class="card-header">
                                                <?php if (!empty($offer['provider_logo'])): ?>
                                                    <img src="/<?php echo htmlspecialchars($offer['provider_logo']); ?>" 
                                                         alt="<?php echo htmlspecialchars($offer['provider_name']); ?>" 
                                                         class="provider-logo-small">
                                                <?php else: ?>
                                                    <div class="provider-logo-small" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                                        <?php echo strtoupper(substr($offer['provider_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="provider-info flex-grow-1">
                                                    <h6><?php echo htmlspecialchars($offer['provider_name']); ?></h6>
                                                    <small>
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($offer['provider_city'] ?: 'Colombia'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body">
                                                <div class="offer-title">
                                                    <?php echo htmlspecialchars($offer['title'] ?: $offer['service_name']); ?>
                                                </div>
                                                
                                                <?php if (!empty($offer['description'])): ?>
                                                    <div class="offer-description">
                                                        <?php echo htmlspecialchars(substr($offer['description'], 0, 120)); ?>
                                                        <?php if (strlen($offer['description']) > 120): ?>...<?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($offer['price_from'] > 0): ?>
                                                    <div class="offer-price">
                                                        <small>From</small>
                                                        <?php echo htmlspecialchars($offer['currency']); ?> 
                                                        $<?php echo number_format($offer['price_from'], 0); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="offer-price" style="background: #64748b;">
                                                        <small>Price on request</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No active service offers are currently available. Please check back later or contact us directly.
                    </div>
                <?php endif; ?>
            </div>
            <div class="wizard-stage">
                <h3>Stage 3 – Finalize Context</h3>
                <p>Add any budget, urgency or additional notes before sending the request.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="budget" class="form-label">Budget (USD)</label>
                        <input type="number" step="50" min="0" class="form-control" name="budget" id="budget" placeholder="Example: 5000">
                        <small class="text-muted">Optional - helps us provide better recommendations</small>
                    </div>
                    <div class="col-md-6">
                        <label for="timeline" class="form-label">Preferred timeline</label>
                        <input type="text" class="form-control" name="timeline" id="timeline" placeholder="e.g. Between March 10-20">
                        <small class="text-muted">When would you like to schedule your services?</small>
                    </div>
                    <div class="col-12">
                        <label for="additional_notes" class="form-label">Additional context</label>
                        <textarea name="additional_notes" id="additional_notes" class="form-control" rows="4" placeholder="Anything else we should know? (medical conditions, special requirements, etc.)"></textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-3">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                    <small class="d-block mt-2 text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Your information is secure. Our team will review your request within 24 hours.
                    </small>
                </div>
            </div>
        </form>
        <div class="mt-3">
            <a href="/offers.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to catalog
            </a>
        </div>
    </div>
    <!-- Wizard End -->

    <!-- Footer Start -->
    <?php echo $footer; ?>
    <!-- Footer End -->

    <!-- Copyright Start -->
    <?php echo $copyright; ?>
    <!-- Copyright End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../lib/easing/easing.min.js"></script>
    <script src="../lib/waypoints/waypoints.min.js"></script>
    <script src="../lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="../lib/lightbox/js/lightbox.min.js"></script>
    <?php echo $script; ?>
    <script src="../js/main.js"></script>

    <script>
        // Toggle offer card selection
        function toggleOfferSelection(card, offerId) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            const wasChecked = checkbox.checked;
            
            // Toggle checkbox
            checkbox.checked = !wasChecked;
            
            // Toggle card visual state
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            
            // Update selection summary
            updateSelectionSummary();
        }
        
        // Update selection summary
        function updateSelectionSummary() {
            const selectedOffers = document.querySelectorAll('input[name="selected_offers[]"]:checked');
            const count = selectedOffers.length;
            
            // You can add a summary display here if needed
            console.log(`Selected ${count} offer(s)`);
        }
        
        // Initialize - mark pre-selected cards
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.offer-card input[type="checkbox"]:checked').forEach(function(checkbox) {
                const card = checkbox.closest('.offer-card');
                card.classList.add('selected');
                
                // Auto-scroll a la oferta pre-seleccionada
                <?php if ($preselected_offer_id > 0): ?>
                    if (checkbox.value === '<?php echo $preselected_offer_id; ?>') {
                        setTimeout(function() {
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            // Highlight temporal
                            card.style.boxShadow = '0 0 0 3px rgba(34, 197, 94, 0.5)';
                            setTimeout(function() {
                                card.style.boxShadow = '';
                            }, 2000);
                        }, 500);
                    }
                <?php endif; ?>
            });
        });
    </script>
</body>
</html>

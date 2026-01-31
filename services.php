<?php
include('inc/include.php'); 

// Obtener configuración del header
$busca_header = mysqli_query($conexion, "SELECT * FROM services_page_header WHERE activo = '1' ORDER BY id ASC LIMIT 1");
$header_data = mysqli_fetch_array($busca_header);

// Valores por defecto si no hay datos en BD
$page_title = isset($header_data['title']) ? $header_data['title'] : 'Our Services';
$subtitle = isset($header_data['subtitle']) ? $header_data['subtitle'] : 'Comprehensive Services';
$main_title = isset($header_data['main_title']) ? $header_data['main_title'] : 'Complete Coordination & Management';
$description = isset($header_data['description']) ? $header_data['description'] : 'At MedTravel we connect patients from the United States with certified medical providers in Colombia, offering complete coordination service from planning to post-procedure follow-up.';
$header_image = isset($header_data['header_image']) ? $header_data['header_image'] : 'img/carousel-1.jpg';

// Obtener catálogo de servicios complementarios (MedTravel Services)
$catalog = [];
$sql_catalog = "SELECT s.id, s.service_type, s.service_name, s.short_description, s.sale_price, s.currency, s.availability_status, s.featured, s.image_url,
                       COALESCE(p.provider_name, 'MedTravel') AS provider_name
                FROM medtravel_services_catalog s
                LEFT JOIN service_providers p ON s.provider_id = p.id
                WHERE s.is_active = 1
                ORDER BY s.service_type, s.display_order, s.service_name";
$res_catalog = mysqli_query($conexion, $sql_catalog);
if($res_catalog){
    while($row = mysqli_fetch_assoc($res_catalog)){
        $catalog[$row['service_type']][] = $row;
    }
}

function format_price($amount, $currency){
    $symbol = ($currency === 'USD') ? '$' : ($currency === 'COP' ? '$' : '');
    return $symbol . number_format((float)$amount, 0, '.', ',') . ' ' . $currency;
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <?php echo $head; ?>
        <style>
            .package-summary { position: sticky; bottom: 1rem; z-index: 2; }
            .service-card {
                transition: all 0.3s ease;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                background: #fff;
            }
            .service-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                border-color: #d1d5db;
            }
            .service-card .card-img-top {
                height: 220px;
                object-fit: cover;
                background: #f1f5f9;
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
                margin-bottom: 0;
            }
            .availability-badge {
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                text-transform: capitalize;
                border: 1px solid #e2e8f0;
                color: #475569;
                background: #f8fafc;
            }
            .availability-badge.available { color: #15803d; background: #ecfdf3; border-color: #bbf7d0; }
            .availability-badge.limited { color: #b45309; background: #fef3c7; border-color: #fde68a; }
            .availability-badge.unavailable { color: #0f172a; background: #e2e8f0; border-color: #cbd5e1; }
            .availability-badge.seasonal { color: #0369a1; background: #e0f2fe; border-color: #bae6fd; }
            .service-card .card-title {
                font-size: 18px;
                font-weight: 700;
                color: #1e293b;
                line-height: 1.4;
            }
            .service-card .card-text {
                color: #64748b;
                font-size: 14px;
                line-height: 1.6;
                min-height: 56px;
            }
            .provider-info {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 0;
                border-top: 1px solid #f1f5f9;
                margin-top: 8px;
            }
            .provider-info i { color: #0f766e; }
            .provider-name { color: #334155; font-weight: 600; font-size: 14px; margin: 0; }
            .price-info {
                background: #f8fafc;
                padding: 12px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }
            .price-label { color: #64748b; font-size: 12px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; }
            .price-amount { color: #0f766e; font-size: 18px; font-weight: 700; }
            .btn-add-package {
                background: #0f766e;
                border: none;
                color: white;
                padding: 12px 16px;
                border-radius: 10px;
                font-weight: 700;
                width: 100%;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .btn-add-package:hover { background: #0d9488; color: #fff; box-shadow: 0 4px 12px rgba(15,118,110,0.25); }
            .btn-add-package.active { background: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.25); }
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

        <!-- Navbar & Hero Start -->
        <div class="container-fluid position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
                <?php echo $logo; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <?php echo $menu; ?>
            </nav>
        </div>
        <!-- Navbar & Hero End -->

        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(19, 53, 123, 0.5), rgba(19, 53, 123, 0.5)), url(<?php echo $header_image; ?>); background-position: center center; background-repeat: no-repeat; background-size: cover; background-attachment: fixed;">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h4 class="mb-3" style="color: #FFA500; font-weight: 600; text-transform: uppercase;"><?php echo $subtitle; ?></h4>
                <h3 class="text-white display-3 mb-4"><?php echo $main_title; ?></h3>
            </div>
        </div>
        <!-- Header End -->

        <!-- Services Start -->
        <div class="container-fluid bg-light service py-5">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <p class="mb-0"><?php echo $description; ?></p>
                </div>
                <?php if(!empty($catalog)){ ?>
                <?php foreach($catalog as $type => $items){ ?>
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="text-primary mb-3" style="text-transform: capitalize;"><?php echo htmlspecialchars($type); ?></h4>
                    </div>
                    <?php foreach($items as $svc){ 
                        $status_class = '';
                        switch($svc['availability_status']){
                            case 'available': $status_class = 'available'; break;
                            case 'limited': $status_class = 'limited'; break;
                            case 'unavailable': $status_class = 'unavailable'; break;
                            case 'seasonal': $status_class = 'seasonal'; break;
                        }
                        $image_path = !empty($svc['image_url']) ? htmlspecialchars($svc['image_url']) : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f1f5f9" width="400" height="300"/%3E%3Ctext fill="%2399a1ab" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-family="Arial" font-size="18"%3EMedTravel Service%3C/text%3E%3C/svg%3E';
                    ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 d-flex">
                            <div class="service-card card h-100 w-100">
                                <div class="position-relative">
                                    <img src="<?php echo $image_path; ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($svc['service_name']); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23f1f5f9%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%2399a1ab%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22 font-size=%2218%22%3EMedTravel%3C/text%3E%3C/svg%3E';">
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="service-badge"><i class="fas fa-briefcase-medical"></i><?php echo htmlspecialchars(ucfirst($svc['service_type'])); ?></span>
                                        <?php if(!empty($svc['availability_status'])){ ?>
                                            <span class="availability-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($svc['availability_status']); ?></span>
                                        <?php } ?>
                                    </div>

                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($svc['service_name']); ?></h5>
                                    <p class="card-text mb-3"><?php echo htmlspecialchars($svc['short_description']); ?></p>

                                    <div class="provider-info">
                                        <i class="fas fa-hospital"></i>
                                        <p class="provider-name mb-0"><?php echo htmlspecialchars($svc['provider_name']); ?></p>
                                    </div>

                                    <div class="mt-auto">
                                        <div class="price-info">
                                            <span class="price-label">Starting from</span>
                                            <span class="price-amount"><?php echo format_price($svc['sale_price'], $svc['currency']); ?></span>
                                        </div>
                                        <button type="button" class="btn-add-package add-service-btn" 
                                            data-id="<?php echo (int)$svc['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($svc['service_name'], ENT_QUOTES); ?>"
                                            data-price="<?php echo htmlspecialchars($svc['sale_price'], ENT_QUOTES); ?>"
                                            data-currency="<?php echo htmlspecialchars($svc['currency'], ENT_QUOTES); ?>"
                                            data-type="<?php echo htmlspecialchars($svc['service_type'], ENT_QUOTES); ?>"
                                            data-provider="<?php echo htmlspecialchars($svc['provider_name'], ENT_QUOTES); ?>">
                                            Añadir al paquete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php } else { ?>
                <div class="row"><div class="col-12 text-center"><p>No hay servicios disponibles en este momento.</p></div></div>
                <?php } ?>
                <div id="package-summary" class="package-summary bg-white border rounded shadow-sm p-3 mt-4 d-none">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Tu paquete</h5>
                            <div id="package-summary-list" class="small text-muted">No has añadido servicios.</div>
                        </div>
                        <div id="package-summary-total" class="fw-bold text-primary"></div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button id="package-summary-booking" type="button" class="btn btn-primary">Continuar al booking</button>
                            <button id="package-summary-clear" type="button" class="btn btn-outline-secondary">Limpiar</button>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <button type="button" class="btn btn-primary rounded-pill py-3 px-5" onclick="scrollToBooking();">Continuar al booking</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Services End -->

        <script>
        (function() {
            var STORAGE_KEY = 'mt_selected_services';
            var buttons = document.querySelectorAll('.add-service-btn');
            var summary = document.getElementById('package-summary');
            if (!summary) return;

            var summaryList = document.getElementById('package-summary-list');
            var summaryTotal = document.getElementById('package-summary-total');
            var clearBtn = document.getElementById('package-summary-clear');
            var bookingBtn = document.getElementById('package-summary-booking');

            function loadSelection() {
                try {
                    var raw = localStorage.getItem(STORAGE_KEY);
                    if (!raw) return [];
                    var parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }

            function saveSelection(data) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            }

            function formatTotals(items) {
                var totalsByCurrency = {};
                items.forEach(function(item) {
                    var price = parseFloat(item.price) || 0;
                    var curr = item.currency || '';
                    if (!totalsByCurrency[curr]) {
                        totalsByCurrency[curr] = 0;
                    }
                    totalsByCurrency[curr] += price;
                });
                var parts = Object.keys(totalsByCurrency).map(function(curr) {
                    var val = totalsByCurrency[curr];
                    if (!curr) return '';
                    return (curr === 'USD' ? '$' : '') + val.toLocaleString('en-US') + ' ' + curr;
                }).filter(Boolean);
                return parts.length ? 'Total estimado: ' + parts.join(' / ') : '';
            }

            function updateButtons(selection) {
                var selectedIds = new Set(selection.map(function(item) { return String(item.id); }));
                buttons.forEach(function(btn) {
                    var isActive = selectedIds.has(btn.dataset.id);
                    btn.classList.toggle('active', isActive);
                    btn.textContent = isActive ? 'Añadido' : 'Añadir al paquete';
                });
            }

            function updateSummary(selection) {
                if (!selection.length) {
                    summary.classList.add('d-none');
                    summaryList.textContent = 'No has añadido servicios.';
                    summaryTotal.textContent = '';
                    return;
                }

                summary.classList.remove('d-none');
                var preview = selection.slice(0, 3).map(function(item) {
                    return item.name + ' (' + item.type + ')';
                }).join(' · ');
                if (selection.length > 3) {
                    preview += ' + ' + (selection.length - 3) + ' más';
                }
                summaryList.textContent = preview;
                summaryTotal.textContent = formatTotals(selection);
            }

            function toggleSelection(btn) {
                var current = loadSelection();
                var id = String(btn.dataset.id);
                var idx = current.findIndex(function(item) { return String(item.id) === id; });
                if (idx >= 0) {
                    current.splice(idx, 1);
                } else {
                    current.push({
                        id: id,
                        name: btn.dataset.name || '',
                        price: btn.dataset.price || '0',
                        currency: btn.dataset.currency || '',
                        type: btn.dataset.type || '',
                        provider: btn.dataset.provider || ''
                    });
                }
                saveSelection(current);
                updateButtons(current);
                updateSummary(current);
            }

            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() { toggleSelection(btn); });
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    localStorage.removeItem(STORAGE_KEY);
                    updateButtons([]);
                    updateSummary([]);
                });
            }

            if (bookingBtn) {
                bookingBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    scrollToBooking();
                });
            }

            // Inicializar estado desde storage
            var initial = loadSelection();
            updateButtons(initial);
            updateSummary(initial);
        })();
        </script>

        <!-- Testimonial Start -->
        <div class="container-fluid testimonial py-5">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Testimonial</h5>
                    <h1 class="mb-0">Our Clients Say!!!</h1>
                </div>
                <div class="testimonial-carousel owl-carousel">
                    <div class="testimonial-item text-center rounded pb-4">
                        <div class="testimonial-comment bg-light rounded p-4">
                            <p class="text-center mb-5">Lorem ipsum dolor, sit amet consectetur adipisicing elit. Quis nostrum cupiditate, eligendi repellendus saepe illum earum architecto dicta quisquam quasi porro officiis. Vero reiciendis,
                            </p>
                        </div>
                        <div class="testimonial-img p-1">
                            <img src="img/testimonial-1.jpg" class="img-fluid rounded-circle" alt="Image">
                        </div>
                        <div style="margin-top: -35px;">
                            <h5 class="mb-0">John Abraham</h5>
                            <p class="mb-0">New York, USA</p>
                            <div class="d-flex justify-content-center">
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-item text-center rounded pb-4">
                        <div class="testimonial-comment bg-light rounded p-4">
                            <p class="text-center mb-5">Lorem ipsum dolor, sit amet consectetur adipisicing elit. Quis nostrum cupiditate, eligendi repellendus saepe illum earum architecto dicta quisquam quasi porro officiis. Vero reiciendis,
                            </p>
                        </div>
                        <div class="testimonial-img p-1">
                            <img src="img/testimonial-2.jpg" class="img-fluid rounded-circle" alt="Image">
                        </div>
                        <div style="margin-top: -35px;">
                            <h5 class="mb-0">John Abraham</h5>
                            <p class="mb-0">New York, USA</p>
                            <div class="d-flex justify-content-center">
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-item text-center rounded pb-4">
                        <div class="testimonial-comment bg-light rounded p-4">
                            <p class="text-center mb-5">Lorem ipsum dolor, sit amet consectetur adipisicing elit. Quis nostrum cupiditate, eligendi repellendus saepe illum earum architecto dicta quisquam quasi porro officiis. Vero reiciendis,
                            </p>
                        </div>
                        <div class="testimonial-img p-1">
                            <img src="img/testimonial-3.jpg" class="img-fluid rounded-circle" alt="Image">
                        </div>
                        <div style="margin-top: -35px;">
                            <h5 class="mb-0">John Abraham</h5>
                            <p class="mb-0">New York, USA</p>
                            <div class="d-flex justify-content-center">
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-item text-center rounded pb-4">
                        <div class="testimonial-comment bg-light rounded p-4">
                            <p class="text-center mb-5">Lorem ipsum dolor, sit amet consectetur adipisicing elit. Quis nostrum cupiditate, eligendi repellendus saepe illum earum architecto dicta quisquam quasi porro officiis. Vero reiciendis,
                            </p>
                        </div>
                        <div class="testimonial-img p-1">
                            <img src="img/testimonial-4.jpg" class="img-fluid rounded-circle" alt="Image">
                        </div>
                        <div style="margin-top: -35px;">
                            <h5 class="mb-0">John Abraham</h5>
                            <p class="mb-0">New York, USA</p>
                            <div class="d-flex justify-content-center">
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                                <i class="fas fa-star text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Testimonial End -->

        <!-- Booking Widget Start -->
        <?php echo $booking_widget; ?>
        <!-- Booking Widget End -->

        <!-- Subscribe Start -->
        <div class="container-fluid subscribe py-5">
            <div class="container text-center py-5">
                <div class="mx-auto text-center" style="max-width: 900px;">
                    <h5 class="subscribe-title px-3">Subscribe</h5>
                    <h1 class="text-white mb-4">Our Newsletter</h1>
                    <p class="text-white mb-5">Lorem ipsum dolor sit amet consectetur adipisicing elit. Laborum tempore nam, architecto doloremque velit explicabo? Voluptate sunt eveniet fuga eligendi! Expedita laudantium fugiat corrupti eum cum repellat a laborum quasi.
                    </p>
                    <div class="position-relative mx-auto">
                        <input class="form-control border-primary rounded-pill w-100 py-3 ps-4 pe-5" type="text" placeholder="Your email">
                        <button type="button" class="btn btn-primary rounded-pill position-absolute top-0 end-0 py-2 px-4 mt-2 me-2">Subscribe</button>
                    </div>
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
        <script src="js/main.js"></script>
    </body>

</html>

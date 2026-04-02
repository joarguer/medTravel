<?php
include("include/include.php");

if (!user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

function ab_page_has_column($conexion, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $columnEsc = mysqli_real_escape_string($conexion, $column);
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

$categories = [];
$hasCategoryActive    = ab_page_has_column($conexion, 'service_categories', 'is_active');
$hasCategoryDeleted   = ab_page_has_column($conexion, 'service_categories', 'is_deleted');
$hasServiceActive     = ab_page_has_column($conexion, 'service_catalog', 'is_active');
$hasServiceDeleted    = ab_page_has_column($conexion, 'service_catalog', 'is_deleted');
$hasOfferDeleted      = ab_page_has_column($conexion, 'provider_service_offers', 'is_deleted');
$hasProviderDeleted   = ab_page_has_column($conexion, 'providers', 'is_deleted');

$categoryWhereParts = [];
if ($hasCategoryActive) {
    $categoryWhereParts[] = 'cat.is_active = 1';
}
if ($hasCategoryDeleted) {
    $categoryWhereParts[] = 'cat.is_deleted = 0';
}
$categoryWhereSql = !empty($categoryWhereParts) ? 'WHERE ' . implode(' AND ', $categoryWhereParts) : '';

$serviceJoinConds = [];
if ($hasServiceActive) {
    $serviceJoinConds[] = 'sc.is_active = 1';
}
if ($hasServiceDeleted) {
    $serviceJoinConds[] = 'sc.is_deleted = 0';
}
$serviceJoinSql = !empty($serviceJoinConds) ? ' AND ' . implode(' AND ', $serviceJoinConds) : '';

$offerJoinSql = ' AND o.is_active = 1';
if ($hasOfferDeleted) {
    $offerJoinSql .= ' AND o.is_deleted = 0';
}

$providerJoinSql = ' AND p.is_active = 1';
if ($hasProviderDeleted) {
    $providerJoinSql .= ' AND p.is_deleted = 0';
}

$categoriesSql = "SELECT
                    cat.id,
                    cat.name,
                    COALESCE(cat.sort_order, 9999) AS sort_order,
                    COUNT(DISTINCT sc.id) AS service_count,
                    COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN o.id END) AS offer_count
                  FROM service_categories cat
                  LEFT JOIN service_catalog sc
                    ON sc.category_id = cat.id{$serviceJoinSql}
                  LEFT JOIN provider_service_offers o
                    ON o.service_id = sc.id{$offerJoinSql}
                  LEFT JOIN providers p
                    ON p.id = o.provider_id{$providerJoinSql}
                  {$categoryWhereSql}
                  GROUP BY cat.id, cat.name, sort_order
                  ORDER BY sort_order ASC, cat.name ASC";
$categoriesRes = mysqli_query($conexion, $categoriesSql);
if ($categoriesRes) {
    while ($row = mysqli_fetch_assoc($categoriesRes)) {
        $categories[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'service_count' => (int)($row['service_count'] ?? 0),
            'offer_count' => (int)($row['offer_count'] ?? 0),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>medTravel – Assisted Booking</title>
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
    <style>
        .offer-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid #e8edf2; border-radius:4px; margin-bottom:4px; cursor:pointer; transition:background .15s; }
        .offer-item:hover { background:#f4f8ff; }
        .offer-item.selected { background:#ebf3ff; border-color:#4a8af4; }
        .offer-item input[type=checkbox] { flex-shrink:0; }
        .offer-label { flex-grow:1; font-size:13px; }
        .offer-provider { font-size:11px; color:#888; }
        .offer-price { font-size:12px; font-weight:600; color:#2563eb; white-space:nowrap; }
        .terms-warning { background:#fff8e1; border:1px solid #ffe082; border-radius:4px; padding:10px 14px; font-size:13px; color:#7b5800; margin-top:16px; }
        .agent-badge { display:inline-block; background:#e8f4fd; border:1px solid #b8daf8; border-radius:3px; padding:2px 8px; font-size:11px; color:#1a6ca8; font-weight:600; }
        #services-loading { display:none; color:#888; font-size:13px; padding:8px 0 4px; }
        #services-empty { display:none; margin-top:8px; }
        #offers-panel { margin-top:12px; min-height:40px; }
        #offers-loading { display:none; color:#888; font-size:13px; padding:8px 0; }
        #offers-empty { display:none; }
    </style>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
<div class="wrapper">
    <header class="page-header">
        <nav class="navbar mega-menu" role="navigation">
            <div class="container-fluid">
                <?php echo $top_header; ?>
                <?php echo $top_header_2; ?>
            </div>
        </nav>
    </header>

    <div class="container-fluid">
        <div class="page-content">
            <div class="breadcrumbs">
                <h1>Assisted Booking <span class="agent-badge">Agent mode</span></h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="booking_requests.php">Booking Requests</a></li>
                    <li class="active">Assisted Booking</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="row">
                    <div class="col-md-8 col-md-offset-2">

                        <div class="alert alert-info alert-dismissable">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>Agent-assisted flow:</strong> You create the booking on behalf of the patient.
                            The patient will receive an email to activate their account and
                            <strong>must personally accept the Terms &amp; Conditions</strong> on first login.
                        </div>

                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-calendar font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase">Create booking for patient</span>
                                </div>
                            </div>
                            <div class="portlet-body">

                                <form id="agent-booking-form" autocomplete="off">

                                    <!-- Channel -->
                                    <h4 class="form-section">Contact channel</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Channel <span class="required">*</span></label>
                                                <select name="agent_channel" id="agent_channel" class="form-control" required>
                                                    <option value="">— Select channel —</option>
                                                    <option value="whatsapp">WhatsApp</option>
                                                    <option value="widget_chat">Widget Chat</option>
                                                    <option value="phone">Phone call</option>
                                                    <option value="email_inquiry">Email inquiry</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Patient data -->
                                    <h4 class="form-section">Patient data</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Email <span class="required">*</span></label>
                                                <div class="input-group">
                                                    <input type="email" name="email" id="patient_email" class="form-control" placeholder="patient@example.com" required />
                                                    <span class="input-group-btn">
                                                        <button type="button" class="btn btn-default" id="btn-lookup-client" title="Look up existing client">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </span>
                                                </div>
                                                <div id="lookup-result" style="margin-top:6px;font-size:12px;"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Full name <span class="required">*</span></label>
                                                <input type="text" name="name" id="patient_name" class="form-control" placeholder="Jane Doe" required />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Phone</label>
                                                <input type="text" name="phone" id="patient_phone" class="form-control" placeholder="+1 305 000 0000" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Origin (city/state)</label>
                                                <input type="text" name="origin" id="patient_origin" class="form-control" placeholder="Miami, FL" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Persons</label>
                                                <input type="number" name="persons" id="patient_persons" class="form-control" value="1" min="1" max="20" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Trip details -->
                                    <h4 class="form-section">Trip details</h4>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Destination</label>
                                                <input type="text" name="destination" id="patient_destination" class="form-control" value="Armenia, Quindío" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Travel from</label>
                                                <input type="date" name="timeline_from" id="timeline_from" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Travel to</label>
                                                <input type="date" name="timeline_to" id="timeline_to" class="form-control" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Patient notes / special requests</label>
                                        <textarea name="special_request" id="patient_special_request" class="form-control" rows="3" placeholder="Medical history, preferences or special requirements…"></textarea>
                                    </div>

                                    <!-- Service selection — canonical: category → service → offers -->
                                    <h4 class="form-section">Medical service &amp; offers</h4>
                                    <?php if (empty($categories)): ?>
                                        <div class="alert alert-warning">
                                            No active medical categories were found in the real catalog.
                                            Review `service_categories` and `service_catalog` first.
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Category <span class="required">*</span></label>
                                                    <select name="category_id" id="category_id" class="form-control" required>
                                                        <option value="">— Select a category —</option>
                                                        <?php foreach ($categories as $category): ?>
                                                            <option value="<?php echo (int)$category['id']; ?>">
                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $category['name']
                                                                    . ($category['service_count'] > 0
                                                                        ? ' (' . $category['service_count'] . ' services)'
                                                                        : ' (No active services)'),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                                ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <p class="help-block" style="font-size:12px;">
                                                        Start from a real category in `service_categories`.
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Service <span class="required">*</span></label>
                                                    <select name="service_id" id="service_id" class="form-control" required disabled>
                                                        <option value="">— Select a service —</option>
                                                    </select>
                                                    <p class="help-block" style="font-size:12px;">
                                                        Services are loaded only from the selected category.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="services-loading">
                                            <i class="fa fa-spinner fa-spin"></i> Loading services…
                                        </div>
                                        <div id="services-empty" class="alert alert-warning" style="display:none;">
                                            No active services are available for this category.
                                        </div>

                                        <!-- Offers loaded dynamically once service is selected -->
                                        <div id="offers-panel">
                                            <div id="offers-loading">
                                                <i class="fa fa-spinner fa-spin"></i> Loading offers…
                                            </div>
                                            <div id="offers-empty" class="alert alert-warning" style="display:none;">
                                                Select a service to load active offers.
                                            </div>
                                            <div id="offers-list"></div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Terms notice -->
                                    <div class="terms-warning">
                                        <i class="fa fa-info-circle"></i>
                                        <strong>Terms &amp; Conditions:</strong>
                                        The patient will be required to personally accept the Terms of Service on
                                        their first login. The agent cannot accept on their behalf.
                                    </div>

                                    <div class="form-actions" style="margin-top:24px;">
                                        <button type="submit" class="btn btn-primary btn-lg" id="btn-submit-booking">
                                            <i class="fa fa-paper-plane"></i> Create booking &amp; send credentials
                                        </button>
                                        <a href="booking_requests.php" class="btn btn-default btn-lg" style="margin-left:8px;">Cancel</a>
                                    </div>

                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
        <?php echo $footer; ?>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
<script>
(function () {
    'use strict';

    var categorySelect = document.getElementById('category_id');
    var serviceSelect  = document.getElementById('service_id');
    var servicesLoading = document.getElementById('services-loading');
    var servicesEmpty = document.getElementById('services-empty');
    var offersLoading  = document.getElementById('offers-loading');
    var offersEmpty    = document.getElementById('offers-empty');
    var offersList     = document.getElementById('offers-list');

    function resetOffers(message) {
        if (!offersList || !offersEmpty) {
            return;
        }
        offersList.innerHTML = '';
        offersEmpty.textContent = message || 'No active offers available for this service. You can still save the case and add offers later.';
        offersEmpty.style.display = message ? 'block' : 'none';
        if (offersLoading) {
            offersLoading.style.display = 'none';
        }
    }

    function resetServices(message) {
        if (!serviceSelect) {
            return;
        }
        serviceSelect.innerHTML = '<option value="">— Select a service —</option>';
        serviceSelect.value = '';
        serviceSelect.disabled = true;
        if (servicesEmpty) {
            servicesEmpty.textContent = message || 'No active services are available for this category.';
            servicesEmpty.style.display = message ? 'block' : 'none';
        }
        if (servicesLoading) {
            servicesLoading.style.display = 'none';
        }
        resetOffers('');
    }

    function loadServices(categoryId) {
        resetServices('');

        if (!categoryId) {
            return;
        }

        servicesLoading.style.display = 'block';
        $.post('ajax/booking_asistido.php', { action: 'get_services', category_id: categoryId }, function (res) {
            servicesLoading.style.display = 'none';

            if (!res || !res.success) {
                resetServices((res && res.message) ? res.message : 'Could not load services for this category.');
                return;
            }

            if (!res.services || res.services.length === 0) {
                resetServices(res.message || 'No active services are available for this category.');
                return;
            }

            serviceSelect.disabled = false;
            res.services.forEach(function (service) {
                var option = document.createElement('option');
                option.value = service.id;
                option.textContent = service.name + (service.offer_count > 0
                    ? ' (' + service.offer_count + ' active offers)'
                    : ' (No active offers)');
                serviceSelect.appendChild(option);
            });

            resetOffers('');
        }, 'json').fail(function () {
            resetServices('Could not load services for this category.');
            toastr.error('Could not load services. Please try again.');
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            loadServices(this.value);
        });
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', function () {
            var serviceId = this.value;
            var categoryId = categorySelect ? categorySelect.value : '';

            resetOffers('');

            if (!categoryId) {
                resetOffers('Select a category first.');
                return;
            }
            if (!serviceId) {
                resetOffers('');
                return;
            }

            offersLoading.style.display = 'block';

            $.post('ajax/booking_asistido.php', { action: 'get_offers', category_id: categoryId, service_id: serviceId }, function (res) {
                offersLoading.style.display = 'none';
                if (!res || !res.success) {
                    resetOffers((res && res.message) ? res.message : 'Could not load offers for this service.');
                    return;
                }
                if (!res.offers || res.offers.length === 0) {
                    resetOffers(res.message || 'No active offers available for this service. You can still save the case and add offers later.');
                    offersEmpty.style.display = 'block';
                    return;
                }
                offersEmpty.style.display = 'none';
                if (!offersList) {
                    offersEmpty.style.display = 'block';
                    return;
                }
                var html = '';
                res.offers.forEach(function (o) {
                    var priceLabel = (o.price > 0)
                        ? o.currency + ' $' + Number(o.price).toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0})
                        : 'On request';
                    html += '<label class="offer-item" data-offer-id="' + o.id + '">'
                        + '<input type="checkbox" name="selected_offers[]" value="' + o.id + '" />'
                        + '<span class="offer-label">'
                        +   '<span class="offer-title">' + escHtml(o.title) + '</span><br>'
                        +   '<span class="offer-provider">' + escHtml(o.provider) + '</span>'
                        + '</span>'
                        + '<span class="offer-price">' + escHtml(priceLabel) + '</span>'
                        + '</label>';
                });
                offersList.innerHTML = html;

                // Bind toggle highlight
                offersList.querySelectorAll('.offer-item').forEach(function (lbl) {
                    var chk = lbl.querySelector('input[type=checkbox]');
                    if (chk) {
                        chk.addEventListener('change', function () {
                            lbl.classList.toggle('selected', chk.checked);
                        });
                    }
                });
            }, 'json').fail(function () {
                resetOffers('Could not load offers for this service.');
                toastr.error('Could not load offers. Please try again.');
            });
        });
    }

    // Client lookup
    document.getElementById('btn-lookup-client').addEventListener('click', function () {
        var email = document.getElementById('patient_email').value.trim();
        if (!email) { toastr.warning('Enter an email first.'); return; }
        var resultEl = document.getElementById('lookup-result');
        resultEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Looking up…';
        $.post('ajax/booking_asistido.php', { action: 'lookup', email: email }, function (res) {
            if (res.found) {
                resultEl.innerHTML = '<span class="text-success"><i class="fa fa-check"></i> Existing client: <strong>' + escHtml(res.nombre) + '</strong></span>';
                if (res.nombre)   document.getElementById('patient_name').value  = res.nombre;
                if (res.telefono) document.getElementById('patient_phone').value = res.telefono;
            } else {
                resultEl.innerHTML = '<span class="text-info"><i class="fa fa-user-plus"></i> New patient — account will be created.</span>';
            }
        }, 'json').fail(function () {
            resultEl.innerHTML = '<span class="text-warning">Lookup failed. Continue filling in data.</span>';
        });
    });

    // Form submit
    document.getElementById('agent-booking-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btn-submit-booking');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';
        $.post('ajax/booking_asistido.php', $(this).serialize() + '&action=submit', function (res) {
            if (res.success) {
                toastr.success('Booking created. Credentials email sent to patient.');
                setTimeout(function () { window.location = 'booking_requests.php'; }, 2000);
            } else {
                toastr.error(res.message || 'Could not create booking. Check fields and try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Create booking &amp; send credentials';
            }
        }, 'json').fail(function () {
            toastr.error('Server error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane"></i> Create booking &amp; send credentials';
        });
    });

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }
}());
</script>
</body>
</html>

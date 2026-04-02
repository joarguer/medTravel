<?php
include("include/include.php");

if (!user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Load service catalog: only services that have at least one valid active offer.
// Mirrors the canonical INNER JOIN used in booking/wizard.php.
// is_deleted is checked only when the column actually exists (not in base schema).
$services = [];
$_offerHasDeleted    = (bool)mysqli_query($conexion, "SHOW COLUMNS FROM `provider_service_offers` LIKE 'is_deleted'") &&
                       mysqli_num_rows(mysqli_query($conexion, "SHOW COLUMNS FROM `provider_service_offers` LIKE 'is_deleted'")) > 0;
$_providerHasDeleted = (bool)mysqli_query($conexion, "SHOW COLUMNS FROM `providers` LIKE 'is_deleted'") &&
                       mysqli_num_rows(mysqli_query($conexion, "SHOW COLUMNS FROM `providers` LIKE 'is_deleted'")) > 0;

$_offerDeletedCond    = $_offerHasDeleted    ? ' AND o.is_deleted = 0' : '';
$_providerDeletedCond = $_providerHasDeleted ? ' AND p.is_deleted = 0' : '';

$servicesSql = "SELECT sc.id, sc.name,
                       COALESCE(cat.name,'General') AS category_name,
                       COALESCE(cat.sort_order,9999) AS cat_sort
                FROM service_catalog sc
                INNER JOIN service_categories cat ON cat.id = sc.category_id
                WHERE sc.is_active = 1
                  AND EXISTS (
                      SELECT 1
                      FROM provider_service_offers o
                      INNER JOIN providers p ON p.id = o.provider_id
                          AND p.is_active = 1{$_providerDeletedCond}
                      WHERE o.service_id = sc.id
                        AND o.is_active = 1{$_offerDeletedCond}
                  )
                ORDER BY cat_sort ASC, cat.name ASC,
                         COALESCE(sc.sort_order,9999) ASC, sc.name ASC";
$servicesRes = mysqli_query($conexion, $servicesSql);
if ($servicesRes) {
    while ($row = mysqli_fetch_assoc($servicesRes)) {
        $cat = htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8');
        $services[$cat][] = [
            'id'   => (int)$row['id'],
            'name' => (string)$row['name'],
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
                                    <?php if (empty($services)): ?>
                                        <div class="alert alert-warning">
                                            No active services with published offers found.
                                            Please publish at least one offer in the catalog first.
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <label>Medical service <span class="required">*</span></label>
                                                    <select name="service_id" id="service_id" class="form-control" required>
                                                        <option value="">— Select a service —</option>
                                                        <?php foreach ($services as $categoryName => $catServices): ?>
                                                            <optgroup label="<?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php foreach ($catServices as $svc): ?>
                                                                    <option value="<?php echo $svc['id']; ?>">
                                                                        <?php echo htmlspecialchars($svc['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </optgroup>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <p class="help-block" style="font-size:12px;">
                                                        Select a service to load available offers from providers.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Offers loaded dynamically once service is selected -->
                                        <div id="offers-panel">
                                            <div id="offers-loading">
                                                <i class="fa fa-spinner fa-spin"></i> Loading offers…
                                            </div>
                                            <div id="offers-empty" class="alert alert-warning" style="display:none;">
                                                No active offers available for this service. You can still save the case and add offers later.
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

    var serviceSelect  = document.getElementById('service_id');
    var offersLoading  = document.getElementById('offers-loading');
    var offersEmpty    = document.getElementById('offers-empty');
    var offersList     = document.getElementById('offers-list');

    // Load offers when a service is selected
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function () {
            var serviceId = this.value;
            // Clear previous selections
            offersList.innerHTML = '';
            offersEmpty.style.display = 'none';

            if (!serviceId) return;

            offersLoading.style.display = 'block';

            $.post('ajax/booking_asistido.php', { action: 'get_offers', service_id: serviceId }, function (res) {
                offersLoading.style.display = 'none';
                if (!res.success || !res.offers || res.offers.length === 0) {
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
                offersLoading.style.display = 'none';
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

<?php
include("include/include.php");

if (!user_can(PERM_BOOKING_MANAGE)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Load active offers grouped by category for the offer selector
$offers = [];
$offersQuery = mysqli_query($conexion,
    "SELECT o.id,
            COALESCE(NULLIF(o.title,''), sc.name, CONCAT('Offer #',o.id)) AS offer_title,
            COALESCE(p.name,'') AS provider_name,
            COALESCE(cat.name,'General') AS category_name,
            o.price_from,
            COALESCE(NULLIF(o.currency,''),'USD') AS currency
     FROM provider_service_offers o
     INNER JOIN providers p ON p.id = o.provider_id AND p.is_active = 1 AND p.is_deleted = 0
     LEFT JOIN service_catalog sc ON sc.id = o.service_id
     LEFT JOIN service_categories cat ON cat.id = sc.category_id
     WHERE o.is_active = 1 AND o.is_deleted = 0
     ORDER BY cat.name ASC, p.name ASC, offer_title ASC"
);
if ($offersQuery) {
    while ($row = mysqli_fetch_assoc($offersQuery)) {
        $cat = htmlspecialchars((string)($row['category_name'] ?? 'General'), ENT_QUOTES, 'UTF-8');
        $offers[$cat][] = $row;
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
        .offer-group { margin-bottom: 12px; }
        .offer-group-title { font-size:12px; font-weight:700; color:#6c757d; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
        .offer-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid #e8edf2; border-radius:4px; margin-bottom:4px; cursor:pointer; transition:background .15s; }
        .offer-item:hover { background:#f4f8ff; }
        .offer-item.selected { background:#ebf3ff; border-color:#4a8af4; }
        .offer-item input[type=checkbox] { flex-shrink:0; }
        .offer-label { flex-grow:1; font-size:13px; }
        .offer-provider { font-size:11px; color:#888; }
        .offer-price { font-size:12px; font-weight:600; color:#2563eb; white-space:nowrap; }
        .terms-warning { background:#fff8e1; border:1px solid #ffe082; border-radius:4px; padding:10px 14px; font-size:13px; color:#7b5800; margin-top:16px; }
        .agent-badge { display:inline-block; background:#e8f4fd; border:1px solid #b8daf8; border-radius:3px; padding:2px 8px; font-size:11px; color:#1a6ca8; font-weight:600; }
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

                        <!-- Terms notice -->
                        <div class="alert alert-info alert-dismissable">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>Agent-assisted flow:</strong> You create the booking on behalf of the patient.
                            The patient will receive an email to activate their account and
                            <strong>must personally accept the Terms &amp; Conditions</strong> on first login — the agent cannot accept on their behalf.
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
                                    <!-- Section: Contact channel -->
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

                                    <!-- Section: Patient data -->
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

                                    <!-- Section: Trip details -->
                                    <h4 class="form-section">Trip details</h4>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Destination</label>
                                                <input type="text" name="destination" id="patient_destination" class="form-control" placeholder="Armenia, Quindío" value="Armenia, Quindío" />
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
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Medical category</label>
                                                <input type="text" name="category" id="patient_category" class="form-control" placeholder="e.g. Dental, Plastic Surgery" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Patient notes / special requests</label>
                                        <textarea name="special_request" id="patient_special_request" class="form-control" rows="3" placeholder="Any medical history, preferences or special requirements…"></textarea>
                                    </div>

                                    <!-- Section: Service offers -->
                                    <h4 class="form-section">Service offers</h4>
                                    <?php if (empty($offers)): ?>
                                        <div class="alert alert-warning">No active offers available. Please publish at least one offer first.</div>
                                    <?php else: ?>
                                        <p class="text-muted" style="font-size:13px;">Select one or more offers. You can proceed without selecting offers and add them from the case later.</p>
                                        <div id="offers-container">
                                            <?php foreach ($offers as $categoryName => $categoryOffers): ?>
                                                <div class="offer-group">
                                                    <div class="offer-group-title"><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <?php foreach ($categoryOffers as $offer): ?>
                                                        <?php
                                                        $price = is_numeric($offer['price_from']) ? (float)$offer['price_from'] : 0;
                                                        $priceLabel = $price > 0 ? strtoupper($offer['currency']) . ' $' . number_format($price, 0) : 'On request';
                                                        ?>
                                                        <label class="offer-item" data-offer-id="<?php echo (int)$offer['id']; ?>">
                                                            <input type="checkbox" name="selected_offers[]" value="<?php echo (int)$offer['id']; ?>" />
                                                            <span class="offer-label">
                                                                <span class="offer-title"><?php echo htmlspecialchars($offer['offer_title'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                                                <span class="offer-provider"><?php echo htmlspecialchars($offer['provider_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </span>
                                                            <span class="offer-price"><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Terms notice -->
                                    <div class="terms-warning">
                                        <i class="fa fa-info-circle"></i>
                                        <strong>Terms &amp; Conditions:</strong>
                                        The patient will be required to personally accept the Terms of Service on their
                                        first login to the patient portal. The booking will be placed in pending status
                                        until acceptance is confirmed.
                                    </div>

                                    <!-- Submit -->
                                    <div class="form-actions" style="margin-top:24px;">
                                        <button type="submit" class="btn btn-primary btn-lg" id="btn-submit-booking">
                                            <i class="fa fa-paper-plane"></i> Create booking &amp; send credentials
                                        </button>
                                        <a href="booking_requests.php" class="btn btn-default btn-lg" style="margin-left:8px;">Cancel</a>
                                    </div>
                                </form>

                            </div><!-- portlet-body -->
                        </div><!-- portlet -->

                    </div>
                </div>
            </div>

        </div><!-- page-content -->
        <?php echo $footer; ?>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
<script>
(function () {
    'use strict';

    // Highlight offer items on checkbox toggle
    document.querySelectorAll('.offer-item').forEach(function (label) {
        var chk = label.querySelector('input[type=checkbox]');
        if (!chk) return;
        chk.addEventListener('change', function () {
            label.classList.toggle('selected', chk.checked);
        });
    });

    // Client lookup
    document.getElementById('btn-lookup-client').addEventListener('click', function () {
        var email = document.getElementById('patient_email').value.trim();
        if (!email) { toastr.warning('Enter an email first.'); return; }

        var resultEl = document.getElementById('lookup-result');
        resultEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Looking up…';

        $.post('ajax/booking_asistido.php', { action: 'lookup', email: email }, function (res) {
            if (res.found) {
                resultEl.innerHTML = '<span class="text-success"><i class="fa fa-check"></i> Existing client: <strong>' +
                    $('<span>').text(res.nombre).html() + '</strong></span>';
                if (res.nombre) document.getElementById('patient_name').value = res.nombre;
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
                toastr.success('Booking created successfully. Credentials email sent to patient.');
                setTimeout(function () {
                    window.location = 'booking_requests.php';
                }, 2000);
            } else {
                toastr.error(res.message || 'Could not create booking. Please check fields and try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Create booking &amp; send credentials';
            }
        }, 'json').fail(function () {
            toastr.error('Server error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane"></i> Create booking &amp; send credentials';
        });
    });
}());
</script>
</body>
</html>

<?php
/**
 * admin/providers_edit.php
 * Provider detail/edit page — shows provider info + Commission Settings tab.
 *
 * URL: providers_edit.php?id=123
 */
include('include/include.php');
require_once 'include/roles.php';

require_login();
if (!user_can(PERM_BOOKING_MANAGE)) {
    redirect_to_403();
}

$providerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($providerId <= 0) {
    header('Location: providers.php');
    exit;
}

// ── Load provider row ──────────────────────────────────────────────────────
$provider = null;
if (isset($conexion) && $conexion) {
    $stmt = mysqli_prepare($conexion,
        "SELECT id, name, legal_name, type, kind, city, address, phone,
                email, website, description, is_verified, is_active
         FROM providers
         WHERE id = ?
         LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $providerId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $provider = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}
if (!$provider) {
    header('Location: providers.php');
    exit;
}

$provName    = htmlspecialchars($provider['name'] ?? '', ENT_QUOTES);
$isVerified  = !empty($provider['is_verified']);
$isActive    = !empty($provider['is_active']);

// ── Commission gate badge (resolved via commission_settings AJAX on load) ──
// The JS will inject the badge after fetching; this placeholder prevents flicker.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> — <?php echo $provName; ?></title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
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

            <!-- Breadcrumbs -->
            <div class="breadcrumbs">
                <h1>
                    <?php echo $provName; ?>
                    <span id="commission-badge" class="label label-default" style="font-size:.75em; vertical-align:middle; margin-left:8px;"></span>
                    <?php if ($isVerified): ?>
                        <span class="label label-success" style="font-size:.65em; vertical-align:middle;">Verificado</span>
                    <?php endif; ?>
                    <?php if (!$isActive): ?>
                        <span class="label label-default" style="font-size:.65em; vertical-align:middle;">Inactivo</span>
                    <?php endif; ?>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="#">Site</a></li>
                    <li><a href="providers.php">Prestadores</a></li>
                    <li class="active"><?php echo $provName; ?></li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="page-content-row">

                    <!-- Sidebar -->
                    <div class="page-sidebar">
                        <nav class="navbar" role="navigation">
                            <ul class="nav navbar-nav">
                                <li><a href="providers.php"><i class="icon-list"></i> Todos los prestadores</a></li>
                            </ul>
                        </nav>
                    </div>

                    <!-- Main content -->
                    <div class="page-content-col">

                        <!-- Tabs -->
                        <div class="tabbable-line">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab-info" data-toggle="tab"><i class="fa fa-info-circle"></i> Información</a></li>
                                <li><a href="#tab-commission" data-toggle="tab" id="tab-commission-link"><i class="fa fa-percent"></i> Commission Settings</a></li>
                            </ul>
                            <div class="tab-content">

                                <!-- ── Tab 1: Provider info (read-only summary) ── -->
                                <div class="tab-pane active" id="tab-info">
                                    <div class="portlet light">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-user theme-font"></i>
                                                <span class="caption-subject font-dark bold uppercase">Información del Prestador</span>
                                            </div>
                                        </div>
                                        <div class="portlet-body">
                                            <table class="table table-striped table-condensed" style="max-width:680px;">
                                                <tbody>
                                                    <tr><th style="width:180px;">ID</th><td><?php echo (int)$provider['id']; ?></td></tr>
                                                    <tr><th>Nombre</th><td><?php echo htmlspecialchars($provider['name'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Razón Social</th><td><?php echo htmlspecialchars($provider['legal_name'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Tipo</th><td><?php echo htmlspecialchars($provider['type'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Clasificación</th><td><?php echo htmlspecialchars($provider['kind'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Ciudad</th><td><?php echo htmlspecialchars($provider['city'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Teléfono</th><td><?php echo htmlspecialchars($provider['phone'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Email</th><td><?php echo htmlspecialchars($provider['email'] ?? '', ENT_QUOTES); ?></td></tr>
                                                    <tr><th>Website</th><td><?php echo htmlspecialchars($provider['website'] ?? '', ENT_QUOTES); ?></td></tr>
                                                </tbody>
                                            </table>
                                            <a href="providers.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Volver al listado</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Tab 2: Commission Settings ── -->
                                <div class="tab-pane" id="tab-commission">
                                    <div class="portlet light">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-percent theme-font"></i>
                                                <span class="caption-subject font-dark bold uppercase">Commission Settings</span>
                                            </div>
                                        </div>
                                        <div class="portlet-body">

                                            <div id="commission-loading" class="text-center" style="padding:30px 0;">
                                                <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                            </div>

                                            <form id="form-commission" style="display:none; max-width:620px;">
                                                <input type="hidden" id="cs-provider-id" name="provider_id" value="<?php echo $providerId; ?>" />

                                                <!-- Commission Percentage -->
                                                <div class="form-group">
                                                    <label class="control-label">Commission Percentage</label>
                                                    <div class="input-group" style="max-width:200px;">
                                                        <input type="number" class="form-control" id="cs-commission-pct" name="commission_pct"
                                                               step="0.01" min="0" max="100" value="10" placeholder="e.g. 10.00" />
                                                        <span class="input-group-addon">%</span>
                                                    </div>
                                                    <span class="help-block">MedTravel platform fee percentage (0 – 100).</span>
                                                </div>

                                                <!-- Fixed Fee COP -->
                                                <div class="form-group">
                                                    <label class="control-label">Flat Fee (COP)</label>
                                                    <div class="input-group" style="max-width:240px;">
                                                        <span class="input-group-addon">$</span>
                                                        <input type="number" class="form-control" id="cs-fixed-fee" name="fixed_fee_cop"
                                                               min="0" step="1" value="0" placeholder="0" />
                                                    </div>
                                                    <span class="help-block">Optional flat fee in COP added on top of the percentage (0 = none).</span>
                                                </div>

                                                <!-- Currency -->
                                                <div class="form-group">
                                                    <label class="control-label">Currency</label>
                                                    <select class="form-control" id="cs-currency" name="currency" style="max-width:160px;">
                                                        <option value="COP">COP – Colombian Peso</option>
                                                        <option value="USD">USD – US Dollar</option>
                                                        <option value="EUR">EUR – Euro</option>
                                                    </select>
                                                </div>

                                                <!-- Payment Terms -->
                                                <div class="form-group">
                                                    <label class="control-label">Payment Terms</label>
                                                    <input type="text" class="form-control" id="cs-payment-terms" name="payment_terms"
                                                           maxlength="255" placeholder="e.g. 30 days after procedure" />
                                                </div>

                                                <!-- Stripe Account ID -->
                                                <div class="form-group">
                                                    <label class="control-label">Stripe Account ID</label>
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><i class="fa fa-credit-card"></i></span>
                                                        <input type="text" class="form-control" id="cs-stripe-account" name="stripe_account_id"
                                                               maxlength="64" placeholder="acct_xxxxxxxxxxxx" />
                                                    </div>
                                                    <span class="help-block">Stripe Connect account ID for provider payouts (leave blank if not applicable).</span>
                                                </div>

                                                <hr />

                                                <!-- is_active toggle -->
                                                <div class="form-group">
                                                    <div class="mt-checkbox-inline">
                                                        <label class="mt-checkbox mt-checkbox-outline">
                                                            <input type="checkbox" id="cs-is-active" name="is_active" value="1" />
                                                            Enable Stage 2 commission gate for this provider
                                                            <span></span>
                                                        </label>
                                                    </div>
                                                    <span class="help-block">
                                                        When active, the client portal will redact sensitive provider contact details
                                                        (phone, email, links) until the commission payment is confirmed.
                                                    </span>
                                                </div>

                                                <hr />

                                                <!-- Save button + feedback -->
                                                <div class="form-group">
                                                    <button type="submit" id="btn-save-commission" class="btn btn-primary">
                                                        <i class="fa fa-save"></i> Save Commission Settings
                                                    </button>
                                                    <span id="cs-save-msg" style="margin-left:12px; display:none;"></span>
                                                </div>

                                                <!-- Last updated info -->
                                                <div id="cs-meta" class="text-muted small" style="display:none;">
                                                    Last saved: <span id="cs-updated-at"></span>
                                                </div>

                                            </form>

                                        </div><!-- /portlet-body -->
                                    </div><!-- /portlet -->
                                </div><!-- /tab-commission -->

                            </div><!-- /tab-content -->
                        </div><!-- /tabbable-line -->

                    </div><!-- /page-content-col -->
                </div><!-- /page-content-row -->
            </div><!-- /page-content-container -->

            <?php echo $footer; ?>
        </div>
    </div>

    <?php echo $sider_bar; ?>
    <?php echo $theme_layout_script; ?>

    <script>
        var PROVIDER_COMMISSION_ID = <?php echo $providerId; ?>;
    </script>
    <script src="js/provider_commission.js" type="text/javascript"></script>
</div>
</body>
</html>

<?php
/**
 * admin/providers_edit.php
 * Provider detail/edit page — shows provider info + Commission Settings tab.
 *
 * URL: providers_edit.php?id=123
 */
include('include/include.php');
require_once 'include/roles.php';
require_once __DIR__ . '/include/conexion.php';

$provider_id = intval($_GET['id'] ?? 0);
if ($provider_id <= 0) {
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Proveedor inválido</title></head><body>';
    echo '<p>Proveedor inválido. <a href="providers.php">Volver</a></p>';
    echo '</body></html>';
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
        mysqli_stmt_bind_param($stmt, 'i', $provider_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $provider = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}
if (!$provider) {
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Proveedor no encontrado</title></head><body>';
    echo '<p>Provider not found. <a href="providers.php">Volver</a></p>';
    echo '</body></html>';
    exit;
}

$provName    = htmlspecialchars($provider['name'] ?? '', ENT_QUOTES);
$isVerified  = !empty($provider['is_verified']);
$isActive    = !empty($provider['is_active']);
$hasCommissionAjax = is_file(__DIR__ . '/ajax/provider_commission_settings.php');
$hasCommissionJs = is_file(__DIR__ . '/js/provider_commission.js');
$hasMedicalStaffAjax = is_file(__DIR__ . '/ajax/provider_medical_staff.php');
$hasMedicalStaffJs = is_file(__DIR__ . '/js/provider_medical_staff.js');

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
                                <li><a href="#tab-medical-staff" data-toggle="tab"><i class="fa fa-user-md"></i> Staff médico <span id="staff-count-badge" class="badge badge-default">0</span></a></li>
                                <li><a href="#tab-commission" data-toggle="tab" id="tab-commission-link"><i class="fa fa-usd"></i> Commission Settings</a></li>
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

                                <!-- ── Tab 2: Staff medico ── -->
                                <div class="tab-pane" id="tab-medical-staff">
                                    <?php if (!$hasMedicalStaffAjax || !$hasMedicalStaffJs): ?>
                                        <div class="note note-danger">
                                            Faltan assets del staff médico. Verifica
                                            <code>admin/ajax/provider_medical_staff.php</code> y
                                            <code>admin/js/provider_medical_staff.js</code>.
                                        </div>
                                    <?php endif; ?>
                                    <div class="portlet light">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-user-md theme-font"></i>
                                                <span class="caption-subject font-dark bold uppercase">Staff médico</span>
                                            </div>
                                            <div class="actions">
                                                <span class="text-muted" style="margin-right:12px;">
                                                    Activos: <strong id="staff-active-counter">0</strong>
                                                </span>
                                                <button type="button" class="btn btn-primary btn-sm" id="btn-add-medical-staff">
                                                    <i class="fa fa-plus"></i> Agregar médico
                                                </button>
                                            </div>
                                        </div>
                                        <div class="portlet-body">
                                            <p class="text-muted" style="max-width:840px;">
                                                Registra médicos o staff clínico interno del prestador. Esta relación deja preparada la base para futuras asignaciones de médico al caso o al item, sin reemplazar al prestador como entidad principal.
                                            </p>
                                            <div id="medical-staff-feedback"></div>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-bordered table-hover" id="tbl-provider-medical-staff">
                                                    <thead>
                                                        <tr>
                                                            <th>Nombre / especialidad</th>
                                                            <th>Registro profesional</th>
                                                            <th>Contacto</th>
                                                            <th>Clínica / sede</th>
                                                            <th>Usuario vinculado / acceso</th>
                                                            <th>Estado</th>
                                                            <th>Actualizado</th>
                                                            <th style="width:180px;">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted" style="padding:24px 12px;">Cargando staff médico...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Tab 2: Commission Settings ── -->
                                <div class="tab-pane" id="tab-commission">
                                    <?php if (!$hasCommissionAjax || !$hasCommissionJs): ?>
                                        <div class="note note-danger">
                                            Commission assets missing. Ensure
                                            <code>admin/ajax/provider_commission_settings.php</code> and
                                            <code>admin/js/provider_commission.js</code> exist.
                                        </div>
                                    <?php endif; ?>
                                    <div class="portlet light">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-usd theme-font"></i>
                                                <span class="caption-subject font-dark bold uppercase">Commission Settings</span>
                                            </div>
                                        </div>
                                        <div class="portlet-body">

                                            <div id="commission-loading" class="text-center" style="padding:30px 0;">
                                                <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                            </div>

                                            <form id="form-commission" style="display:none; max-width:620px;">
                                                <input type="hidden" id="cs-provider-id" name="provider_id" value="<?php echo $provider_id; ?>" />

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

    <div id="providerMedicalStaffModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="providerMedicalStaffModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                    <h4 class="modal-title" id="providerMedicalStaffModalLabel"><strong>Agregar médico</strong></h4>
                </div>
                <div class="modal-body">
                    <form id="form-provider-medical-staff">
                        <input type="hidden" id="pms-id" name="id" value="" />
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-full-name">Nombre completo <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="pms-full-name" name="full_name" maxlength="180" required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-specialty">Especialidad</label>
                                    <input type="text" class="form-control" id="pms-specialty" name="specialty" maxlength="180" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-license">Registro profesional</label>
                                    <input type="text" class="form-control" id="pms-license" name="professional_license" maxlength="120" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-clinic">Clínica / sede</label>
                                    <input type="text" class="form-control" id="pms-clinic" name="clinic_name" maxlength="180" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-email">Correo</label>
                                    <input type="email" class="form-control" id="pms-email" name="email" maxlength="190" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-phone">Teléfono</label>
                                    <input type="text" class="form-control" id="pms-phone" name="phone" maxlength="80" />
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pms-notes">Notas</label>
                            <textarea class="form-control" id="pms-notes" name="notes" rows="4"></textarea>
                        </div>
                        <div id="pms-access-section" style="display:none;">
                            <hr />
                            <h4 class="bold" style="margin-top:0;">Acceso al sistema</h4>
                            <p class="text-muted">Configura aquí si este médico tendrá acceso propio al admin y con qué usuario quedará vinculado.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pms-linked-user-id">Usuario vinculado</label>
                                        <select class="form-control" id="pms-linked-user-id" name="linked_user_id">
                                            <option value="">Sin usuario vinculado</option>
                                        </select>
                                        <span class="help-block">Debe pertenecer al mismo prestador y tener usuario activo en el sistema.</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" style="padding-top:26px;">
                                        <label class="mt-checkbox mt-checkbox-outline">
                                            <input type="checkbox" id="pms-can-access-admin" name="can_access_admin" value="1" />
                                            Permitir acceso al admin
                                            <span></span>
                                        </label>
                                        <span class="help-block">
                                            Estado de acceso: <strong id="pms-access-status">Sin usuario vinculado</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="mt-checkbox mt-checkbox-outline">
                                <input type="checkbox" id="pms-active" name="active" value="1" checked />
                                Activo
                                <span></span>
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <span id="pms-save-msg" class="pull-left" style="display:none;"></span>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-save-medical-staff">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.PROVIDER_ID = <?php echo $provider_id; ?>;
    </script>
    <?php if ($hasCommissionJs): ?>
        <script src="js/provider_commission.js" type="text/javascript"></script>
    <?php endif; ?>
    <?php if ($hasMedicalStaffJs): ?>
        <script src="js/provider_medical_staff.js" type="text/javascript"></script>
    <?php endif; ?>
</div>
</body>
</html>

<?php
include("include/include.php");
$is_admin = is_role_admin_session();
$role_id = current_role_id();
$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
$service_provider_id = isset($_SESSION['service_provider_id']) ? (int)$_SESSION['service_provider_id'] : 0;

$domain_type = 'none';
$scope_id = 0;
if (in_array((int)$role_id, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true) && $provider_id > 0) {
    $domain_type = 'medical';
    $scope_id = $provider_id;
} elseif (!$is_admin && $provider_id > 0) {
    $domain_type = 'medical';
    $scope_id = $provider_id;
}

// Solo accesible para dominio médico
if ($domain_type !== 'medical') {
    header("Location: mi_empresa.php");
    exit();
}

// Verificar que el prestador exista y esté activo
$provider_check = null;
if ($scope_id > 0) {
    $stmt = mysqli_prepare($conexion, "SELECT id, is_active FROM providers WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $scope_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $provider_check = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}
if (!$provider_check || (isset($provider_check['is_active']) && intval($provider_check['is_active']) !== 1)) {
    header("Location: index.php");
    exit();
}

$is_linked_medical_staff_session = is_provider_linked_medical_staff_session($conexion ?? null);
$can_edit_self = !$is_admin && !$is_linked_medical_staff_session;

$hasMedicalStaffAjax = is_file(__DIR__ . '/ajax/provider_medical_staff.php');
$hasMedicalStaffJs   = is_file(__DIR__ . '/js/provider_medical_staff.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Staff médico</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <div class="wrapper">
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header;?>
                    <?php echo $top_header_2;?>
                </div>
            </nav>
        </header>

        <div class="container-fluid">
            <div class="page-content">
                <div class="breadcrumbs">
                    <h1>Staff médico</h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="mi_empresa.php">Mi Empresa</a></li>
                        <li class="active">Staff médico</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-content-col">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="portlet light">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-user-md font-dark"></i>
                                                <span class="caption-subject font-dark bold uppercase">Staff médico</span>
                                            </div>
                                            <div class="actions">
                                                <span class="text-muted" style="margin-right:12px;">
                                                    Activos: <strong id="staff-active-counter">0</strong>
                                                </span>
                                                <button type="button" class="btn btn-primary btn-sm" id="btn-add-medical-staff" <?php echo $can_edit_self ? '' : 'disabled'; ?>>
                                                    <i class="fa fa-plus"></i> Agregar staff
                                                </button>
                                            </div>
                                        </div>
                                        <div class="portlet-body">
                                            <?php if (!$hasMedicalStaffAjax || !$hasMedicalStaffJs): ?>
                                            <div class="alert alert-danger">
                                                Faltan assets del staff médico. Verifica <code>admin/ajax/provider_medical_staff.php</code> y <code>admin/js/provider_medical_staff.js</code>.
                                            </div>
                                            <?php endif; ?>
                                            <p class="text-muted" style="max-width:840px;">
                                                Registra y gestiona el equipo médico de tu empresa. Cada integrante puede tener cargo, especialidad y foto, y en el futuro podrá asignarse a citas o servicios específicos.
                                            </p>
                                            <?php if (!$can_edit_self): ?>
                                            <div class="alert alert-info">
                                                <i class="fa fa-info-circle"></i> Tu perfil puede consultar el staff médico, pero no administrarlo.
                                            </div>
                                            <?php endif; ?>
                                            <div id="medical-staff-feedback"></div>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-bordered table-hover" id="tbl-provider-medical-staff">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:72px;">Foto</th>
                                                            <th>Nombre</th>
                                                            <th>Cargo / rol</th>
                                                            <th>Especialidad</th>
                                                            <th>Principal</th>
                                                            <th>Activo</th>
                                                            <th>Orden</th>
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
                            </div>
                        </div>
                    </div>
                </div>

                <?php echo $footer;?>
            </div>
        </div>
        <?php echo $sider_bar;?>
    </div>

    <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <?php echo $theme_layout_script;?>

    <!-- Modal staff médico -->
    <div id="providerMedicalStaffModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="providerMedicalStaffModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                    <h4 class="modal-title" id="providerMedicalStaffModalLabel"><strong>Agregar staff médico</strong></h4>
                </div>
                <div class="modal-body">
                    <form id="form-provider-medical-staff">
                        <input type="hidden" id="pms-id" name="id" value="" />
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-full-name">Nombre completo <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="pms-full-name" name="full_name" maxlength="150" required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-role-title">Cargo / rol <small class="text-muted">(en inglés)</small></label>
                                    <select class="form-control" id="pms-role-title" name="role_title">
                                        <option value="">Cargando...</option>
                                    </select>
                                    <span class="help-block"><i class="fa fa-globe"></i> Write in English — this label may be visible to patients.</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-specialty">Especialidad <small class="text-muted">(en inglés)</small></label>
                                    <select class="form-control" id="pms-specialty" name="specialty">
                                        <option value="">Cargando...</option>
                                    </select>
                                    <span class="help-block"><i class="fa fa-globe"></i> Write in English — this label may be visible to patients.</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-sort-order">Orden</label>
                                    <input type="number" class="form-control" id="pms-sort-order" name="sort_order" min="0" step="1" />
                                    <span class="help-block">Menor número = aparece primero.</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pms-photo">Foto (URL o ruta interna)</label>
                            <input type="text" class="form-control" id="pms-photo" name="photo" maxlength="255" />
                        </div>
                        <div class="form-group">
                            <label for="pms-bio-short">Bio corta <small class="text-muted">(en inglés)</small></label>
                            <textarea class="form-control" id="pms-bio-short" name="bio_short" rows="3"></textarea>
                            <span class="help-block"><i class="fa fa-globe"></i> Write in English — this text may be shown to patients on the platform.</span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-email">Correo</label>
                                    <input type="email" class="form-control" id="pms-email" name="email" maxlength="120" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-phone">Teléfono</label>
                                    <input type="text" class="form-control" id="pms-phone" name="phone" maxlength="60" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="mt-checkbox mt-checkbox-outline">
                                    <input type="checkbox" id="pms-is-primary-doctor" name="is_primary_doctor" value="1" />
                                    Marcar como médico principal
                                    <span></span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="mt-checkbox mt-checkbox-outline">
                                    <input type="checkbox" id="pms-is-active" name="is_active" value="1" checked />
                                    Registro activo
                                    <span></span>
                                </label>
                            </div>
                        </div>
                        <hr />
                        <h5 class="bold">Información adicional</h5>
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
                                    <select class="form-control" id="pms-clinic" name="clinic_name">
                                        <option value="">Cargando...</option>
                                    </select>
                                    <input type="text" class="form-control" id="pms-clinic-other" name="" maxlength="180" placeholder="Escribir nombre de sede" style="margin-top:6px; display:none;" />
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pms-service-ids">Servicios habilitados</label>
                            <select class="form-control" id="pms-service-ids" name="service_ids[]" multiple size="8"></select>
                            <span class="help-block">Opcional. Indica qué servicios cl&iacute;nicos de <strong>Mis Servicios</strong> puede atender este profesional. Solo se listan los servicios habilitados del prestador. Mant&eacute;n <kbd>Ctrl</kbd> (o <kbd>&lceil;</kbd>) para selecci&oacute;n m&uacute;ltiple.</span>
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
                    </form>
                </div>
                <div class="modal-footer">
                    <span id="pms-save-msg" class="pull-left" style="display:none;"></span>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-save-medical-staff" <?php echo $can_edit_self ? '' : 'disabled'; ?>>
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.PROVIDER_ID = <?php echo (int)$scope_id; ?>;
    </script>
    <?php if ($hasMedicalStaffJs): ?>
    <script src="js/provider_medical_staff.js" type="text/javascript"></script>
    <?php endif; ?>
</body>
</html>

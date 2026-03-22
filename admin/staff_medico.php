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
$can_manage_staff = !$is_linked_medical_staff_session;

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
    <link href="../../assets/global/plugins/jquery-multi-select/css/multi-select.css" rel="stylesheet" type="text/css" />
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <style>
        .staff-form-section {
            border: 1px solid #e7ecf1;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 18px;
            background: #fff;
        }

        .staff-form-section-title {
            margin: 0 0 6px;
            font-size: 16px;
            font-weight: 600;
            color: #2f4050;
        }

        .staff-form-section-help {
            margin: 0 0 16px;
            color: #6b7c93;
            font-size: 13px;
        }

        .staff-permission-option {
            display: block;
            border: 1px solid #dfe6ee;
            border-radius: 6px;
            padding: 12px 14px 12px 40px;
            margin-bottom: 10px;
            background: #fafbfd;
            position: relative;
            cursor: pointer;
        }

        .staff-permission-option input {
            position: absolute;
            left: 14px;
            top: 15px;
        }

        .staff-permission-option strong {
            display: block;
            margin-bottom: 2px;
            color: #2f4050;
        }

        .staff-permission-option span {
            display: block;
            color: #6b7c93;
            font-size: 13px;
            line-height: 1.45;
        }

        .staff-permission-option.is-active {
            border-color: #5b9bd1;
            background: #f4f8fc;
            box-shadow: inset 0 0 0 1px rgba(91, 155, 209, 0.15);
        }

        .staff-clinic-deemphasized {
            display: none;
        }

        #pms-linked-user-id-wrap {
            display: none !important;
        }

        .staff-services-field .ms-container {
            width: 100%;
        }

        .staff-services-field .ms-selectable,
        .staff-services-field .ms-selection {
            width: calc(50% - 10px);
        }

        .staff-services-field .ms-list {
            min-height: 280px;
            max-height: 280px;
            border-radius: 4px;
        }

        .staff-services-header {
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7c93;
            letter-spacing: .04em;
        }

        .staff-services-empty {
            margin-top: 8px;
            color: #6b7c93;
            font-size: 13px;
        }
    </style>
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
                                                <button type="button" class="btn btn-primary btn-sm" id="btn-add-medical-staff" <?php echo $can_manage_staff ? '' : 'disabled'; ?>>
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
                                            <div class="alert alert-info" id="staff-service-context-note" style="display:none; margin-bottom:16px;"></div>
                                            <?php if (!$can_manage_staff): ?>
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
    <script src="../../assets/global/plugins/jquery-multi-select/js/jquery.multi-select.js" type="text/javascript"></script>

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
                        <div class="staff-form-section">
                            <h5 class="staff-form-section-title">Datos del profesional</h5>
                            <p class="staff-form-section-help">Completa la información principal del staff que se mostrará y gestionará en el panel.</p>
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
                                        <label for="pms-sort-order">Orden de visualización</label>
                                        <input type="number" class="form-control" id="pms-sort-order" name="sort_order" min="0" step="1" />
                                        <span class="help-block">Opcional. Usa un número menor si quieres que este profesional aparezca antes en la lista. Si lo dejas vacío, se ubicará automáticamente al final.</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Foto del profesional</label>
                                <div id="pms-photo-preview-wrap" style="margin-bottom:8px; display:none;">
                                    <img id="pms-photo-preview" src="" alt="Foto actual"
                                         style="width:80px; height:80px; object-fit:cover; border-radius:4px; border:1px solid #ddd;" />
                                    <button type="button" class="btn btn-xs btn-danger" id="pms-photo-clear"
                                            style="vertical-align:top; margin-left:6px;"
                                            title="Quitar foto">
                                        <i class="fa fa-times"></i> Quitar
                                    </button>
                                </div>
                                <input type="file" id="pms-photo-file" name="photo_file"
                                       accept="image/jpeg,image/png,image/webp,image/gif"
                                       style="display:block;" />
                                <input type="hidden" id="pms-photo" name="photo" value="" />
                                <span class="help-block">JPG, PNG o WebP. Máximo 2 MB. Dimensión recomendada: 400×400 px.</span>
                            </div>
                            <div class="form-group">
                                <label for="pms-bio-short">Bio corta <small class="text-muted">(en inglés)</small></label>
                                <textarea class="form-control" id="pms-bio-short" name="bio_short" rows="3"></textarea>
                                <span class="help-block"><i class="fa fa-globe"></i> Write in English — this text may be shown to patients on the platform.</span>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pms-email" id="pms-email-label">Email del profesional</label>
                                        <input type="email" class="form-control" id="pms-email" name="email" maxlength="120" />
                                        <span class="help-block" id="pms-email-help">Se requiere un email válido para aprovisionar acceso al panel. El correo de bienvenida se envía tanto para perfiles de solo asignaciones como para perfiles con permisos administrativos.</span>
                                        <div id="pms-email-validation" class="small" style="display:none; margin-top:6px;"></div>
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
                        </div>

                        <div class="staff-form-section">
                            <h5 class="staff-form-section-title">Servicios que puede atender</h5>
                            <p class="staff-form-section-help">Selecciona los servicios habilitados del prestador que este profesional puede atender.</p>
                            <div class="form-group staff-services-field" style="margin-bottom:8px;">
                                <label for="pms-service-ids">Servicios que puede atender</label>
                                <select class="form-control" id="pms-service-ids" name="provider_catalog_service_ids[]" multiple></select>
                                <span class="help-block">Selecciona los servicios habilitados del prestador que este profesional puede atender.</span>
                                <div id="pms-service-selection-summary" class="small text-muted">Sin servicios seleccionados.</div>
                                <div id="pms-service-empty-state" class="staff-services-empty" style="display:none;">Este prestador no tiene servicios habilitados disponibles.</div>
                            </div>
                        </div>

                        <div id="pms-access-section" class="staff-form-section" style="display:none;">
                            <h5 class="staff-form-section-title">Acceso y permisos</h5>
                            <p class="staff-form-section-help">Define el nivel de acceso del staff dentro del panel según sus responsabilidades. Para aprovisionar acceso al panel se requiere un email válido y el correo de bienvenida se envía con cualquiera de los niveles.</p>
                            <div class="form-group" style="margin-bottom:14px;">
                                <label class="mt-checkbox mt-checkbox-outline" style="margin-bottom:6px;">
                                    <input type="checkbox" id="pms-enable-user-access" value="1" checked />
                                    Habilitar acceso al panel para este staff
                                    <span></span>
                                </label>
                                <div class="small text-muted" id="pms-access-toggle-help">Actívalo si este profesional debe poder ingresar al panel. Desactívalo si solo debe quedar registrado como staff sin acceso.</div>
                            </div>
                            <div class="form-group" style="margin-bottom:14px;">
                                <label>Nivel de acceso del staff</label>
                                <div id="pms-access-level-options">
                                <label class="staff-permission-option" data-access-level="scoped">
                                    <input type="radio" name="pms_access_level" value="scoped" checked />
                                    <strong>Solo sus asignaciones</strong>
                                    <span>Puede ingresar al panel para gestionar únicamente la información y tareas que le correspondan. También recibirá su correo de bienvenida/acceso.</span>
                                </label>
                                <label class="staff-permission-option" data-access-level="admin">
                                    <input type="radio" name="pms_access_level" value="admin" />
                                    <strong>Permisos administrativos</strong>
                                    <span>Además de sus asignaciones, tendrá permisos ampliados dentro del panel según el modelo interno del prestador. También recibirá su correo de bienvenida/acceso.</span>
                                </label>
                                </div>
                            </div>
                            <div class="alert alert-info" id="pms-access-summary" style="margin-bottom:12px;">
                                Se requiere un email válido para aprovisionar acceso al panel. El correo de bienvenida se enviará tanto para perfiles de solo asignaciones como para perfiles con permisos administrativos.
                            </div>
                            <div class="small text-muted">Estado del acceso: <strong id="pms-access-status">Acceso al panel: solo sus asignaciones</strong></div>
                            <input type="checkbox" id="pms-can-access-admin" name="can_access_admin" value="1" style="display:none;" />
                            <div id="pms-linked-user-id-wrap">
                                <select class="form-control" id="pms-linked-user-id" name="linked_user_id">
                                    <option value="">Sin usuario vinculado</option>
                                </select>
                            </div>
                        </div>

                        <div class="staff-form-section" style="margin-bottom:0;">
                            <h5 class="staff-form-section-title">Información adicional</h5>
                            <p class="staff-form-section-help">Campos complementarios para registro interno. La sede queda en segundo plano y solo se usa si realmente aplica.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pms-license">Registro profesional</label>
                                        <input type="text" class="form-control" id="pms-license" name="professional_license" maxlength="120" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group staff-clinic-deemphasized" id="pms-clinic-field">
                                        <label for="pms-clinic">Clínica / sede</label>
                                        <select class="form-control" id="pms-clinic" name="clinic_name">
                                            <option value="">Cargando...</option>
                                        </select>
                                        <input type="text" class="form-control" id="pms-clinic-other" name="" maxlength="180" placeholder="Escribir nombre de sede" style="margin-top:6px; display:none;" />
                                    </div>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="pms-notes">Notas</label>
                                <textarea class="form-control" id="pms-notes" name="notes" rows="4"></textarea>
                                <span class="help-block">Opcional. Úsalo para observaciones internas del equipo.</span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <span id="pms-save-msg" class="pull-left" style="display:none;"></span>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-save-medical-staff" <?php echo $can_manage_staff ? '' : 'disabled'; ?>>
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.PROVIDER_ID = <?php echo (int)$scope_id; ?>;
        window.CAN_MANAGE_STAFF = <?php echo $can_manage_staff ? 'true' : 'false'; ?>;
    </script>
    <?php if ($hasMedicalStaffJs): ?>
    <script src="js/provider_medical_staff.js" type="text/javascript"></script>
    <?php endif; ?>
</body>
</html>

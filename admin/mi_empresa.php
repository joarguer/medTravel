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
} elseif ((int)$role_id === ROLE_COMPLEMENTARY_ADMIN && $service_provider_id > 0) {
    $domain_type = 'complementary';
    $scope_id = $service_provider_id;
} elseif (!$is_admin) {
    if ($provider_id > 0) {
        $domain_type = 'medical';
        $scope_id = $provider_id;
    } elseif ($service_provider_id > 0) {
        $domain_type = 'complementary';
        $scope_id = $service_provider_id;
    }
}

if ($domain_type === 'none' && !$is_admin) {
    header("Location: index.php");
    exit();
}

$can_edit_self = (!$is_admin && ($domain_type === 'medical' || $domain_type === 'complementary'));
$can_upload_logo = ($can_edit_self && $domain_type === 'medical');
$is_linked_medical_staff_session = ($domain_type === 'medical') ? is_provider_linked_medical_staff_session($conexion ?? null) : false;
if ($is_linked_medical_staff_session) {
    $can_edit_self = false;
    $can_upload_logo = false;
}
$hasMedicalStaffAjax = is_file(__DIR__ . '/ajax/provider_medical_staff.php');
$hasMedicalStaffJs = is_file(__DIR__ . '/js/provider_medical_staff.js');

$company = [
    'type' => '',
    'name' => '',
    'city' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'website' => '',
    'description' => '',
    'logo' => '',
    'is_active' => 0,
    'calendar_capacity' => 1
];

// Cargar estado de verificación (solo dominio médico)
$verification = [
    'status' => 'pending',
    'verification_level' => 'basic',
    'trust_score' => 0,
    'verified_at' => null,
    'expires_at' => null,
    'completion_percent' => 0,
    'checked_items' => 0,
    'total_items' => 0
];

if ($domain_type === 'medical') {
    $sql = "SELECT * FROM providers WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $scope_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $provider = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$provider || (isset($provider['is_active']) && intval($provider['is_active']) !== 1)) {
        header("Location: index.php");
        exit();
    }

    $company['type'] = isset($provider['type']) ? $provider['type'] : 'medico';
    $company['name'] = isset($provider['name']) ? $provider['name'] : '';
    $company['city'] = isset($provider['city']) ? $provider['city'] : '';
    $company['phone'] = isset($provider['phone']) ? $provider['phone'] : '';
    $company['email'] = isset($provider['email']) ? $provider['email'] : '';
    $company['address'] = isset($provider['address']) ? $provider['address'] : '';
    $company['website'] = isset($provider['website']) ? $provider['website'] : '';
    $company['description'] = isset($provider['description']) ? $provider['description'] : '';
    $company['logo'] = isset($provider['logo']) ? $provider['logo'] : '';
    $company['is_active'] = isset($provider['is_active']) ? intval($provider['is_active']) : 0;
    $company['calendar_capacity'] = (isset($provider['calendar_capacity']) && (int)$provider['calendar_capacity'] > 0)
        ? (int)$provider['calendar_capacity']
        : 1;

    $ver_sql = "SELECT 
                    COALESCE(pv.status,'pending') AS status,
                    COALESCE(pv.verification_level,'basic') AS verification_level,
                    COALESCE(pv.trust_score,0) AS trust_score,
                    pv.verified_at,
                    pv.expires_at,
                    COUNT(pvi.id) AS total_items,
                    SUM(CASE WHEN pvi.is_checked = 1 THEN 1 ELSE 0 END) AS checked_items
                FROM providers p
                LEFT JOIN provider_verification pv ON pv.provider_id = p.id
                LEFT JOIN provider_verification_items pvi ON pvi.provider_id = p.id
                WHERE p.id = ?
                GROUP BY pv.status, pv.verification_level, pv.trust_score, pv.verified_at, pv.expires_at";

    if ($vstmt = mysqli_prepare($conexion, $ver_sql)) {
        mysqli_stmt_bind_param($vstmt, 'i', $scope_id);
        if (mysqli_stmt_execute($vstmt)) {
            $vres = mysqli_stmt_get_result($vstmt);
            if ($row = mysqli_fetch_assoc($vres)) {
                $verification['status'] = $row['status'];
                $verification['verification_level'] = $row['verification_level'];
                $verification['trust_score'] = (int)$row['trust_score'];
                $verification['verified_at'] = $row['verified_at'];
                $verification['expires_at'] = $row['expires_at'];
                $verification['checked_items'] = (int)$row['checked_items'];
                $verification['total_items'] = (int)$row['total_items'];
                $verification['completion_percent'] = ($verification['total_items'] > 0)
                    ? round(($verification['checked_items'] / $verification['total_items']) * 100)
                    : 0;
            }
        }
        mysqli_stmt_close($vstmt);
    }
} elseif ($domain_type === 'complementary') {
    $sql = "SELECT * FROM service_providers WHERE id = ? AND is_active = 1 LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $scope_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $provider = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$provider) {
        header("Location: index.php");
        exit();
    }

    $company['type'] = isset($provider['provider_type']) ? $provider['provider_type'] : 'other';
    $company['name'] = isset($provider['provider_name']) ? $provider['provider_name'] : '';
    $company['city'] = isset($provider['city']) ? $provider['city'] : '';
    $company['phone'] = isset($provider['contact_phone']) ? $provider['contact_phone'] : '';
    $company['email'] = isset($provider['contact_email']) ? $provider['contact_email'] : '';
    $company['address'] = '';
    $company['website'] = isset($provider['website']) ? $provider['website'] : '';
    $company['description'] = isset($provider['notes']) ? $provider['notes'] : '';
    $company['logo'] = '';
    $company['is_active'] = isset($provider['is_active']) ? intval($provider['is_active']) : 0;
    $company['calendar_capacity'] = (isset($provider['calendar_capacity']) && (int)$provider['calendar_capacity'] > 0)
        ? (int)$provider['calendar_capacity']
        : 1;
}

$company_title = ($domain_type === 'complementary') ? 'Mi Empresa / Proveedor Complementario' : 'Mi Empresa';
$type_label = ($domain_type === 'complementary') ? 'Tipo de Proveedor' : 'Tipo';
$name_label = ($domain_type === 'complementary') ? 'Proveedor Complementario *' : 'Nombre *';
$description_label = ($domain_type === 'complementary') ? 'Notas del Proveedor' : 'Descripción';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Mi Empresa</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style;?>
    <link href="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css" rel="stylesheet" type="text/css" />
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
                    <h1><?php echo htmlspecialchars($company_title, ENT_QUOTES); ?></h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Home</a></li>
                        <li class="active">Mi Empresa</li>
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
                                                <i class="icon-organization font-dark"></i>
                                                <span class="caption-subject font-dark bold uppercase"><?php echo htmlspecialchars($company_title, ENT_QUOTES); ?></span>
                                            </div>
                                        </div>
                                        <div class="portlet-body form">
                                            <form id="form-empresa" class="form-horizontal">
                                                <input type="hidden" id="company_scope_id" value="<?php echo (int)$scope_id; ?>" />
                                                
                                                <div class="form-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label"><?php echo htmlspecialchars($type_label, ENT_QUOTES); ?></label>
                                                                <div class="col-md-9">
                                                                    <p class="form-control-static" id="company-type-text"><?php echo htmlspecialchars(ucfirst((string)$company['type']), ENT_QUOTES); ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Estado</label>
                                                                <div class="col-md-9">
                                                                    <p class="form-control-static" id="company-status-badges">
                                                                        <?php if ($domain_type === 'medical'): ?>
                                                                            <?php
                                                                            $status = $verification['status'];
                                                                            $badge_map = [
                                                                                'verified' => 'badge-success',
                                                                                'in_review' => 'badge-warning',
                                                                                'pending' => 'badge-default',
                                                                                'rejected' => 'badge-danger'
                                                                            ];
                                                                            $label_map = [
                                                                                'verified' => 'Verificado',
                                                                                'in_review' => 'En revisión',
                                                                                'pending' => 'Pendiente',
                                                                                'rejected' => 'Rechazado'
                                                                            ];
                                                                            $badge_class = isset($badge_map[$status]) ? $badge_map[$status] : 'badge-default';
                                                                            $label = isset($label_map[$status]) ? $label_map[$status] : ucfirst($status);
                                                                            ?>
                                                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></span>
                                                                            <?php if ((int)$company['is_active'] === 1): ?>
                                                                                <span class="badge badge-info">Activo</span>
                                                                            <?php else: ?>
                                                                                <span class="badge badge-default">Inactivo</span>
                                                                            <?php endif; ?>
                                                                        <?php elseif ($domain_type === 'complementary'): ?>
                                                                            <span class="badge badge-info">Proveedor Complementario</span>
                                                                            <?php if ((int)$company['is_active'] === 1): ?>
                                                                                <span class="badge badge-success">Activo</span>
                                                                            <?php else: ?>
                                                                                <span class="badge badge-default">Inactivo</span>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <span class="badge badge-default">Admin Global</span>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                    <?php if ($domain_type === 'medical'): ?>
                                                                        <p class="form-control-static" id="company-status-meta">
                                                                            Nivel: <strong><?php echo htmlspecialchars($verification['verification_level'], ENT_QUOTES); ?></strong>
                                                                            &nbsp;·&nbsp; Avance checklist: <strong><?php echo $verification['completion_percent']; ?>%</strong>
                                                                            <?php if ($verification['verified_at']) { echo ' &nbsp;·&nbsp; Verificado: '.htmlspecialchars($verification['verified_at'], ENT_QUOTES); } ?>
                                                                        </p>
                                                                    <?php elseif ($domain_type === 'complementary'): ?>
                                                                        <p class="form-control-static" id="company-status-meta">
                                                                            Gestión de empresa limitada al proveedor complementario asociado a tu sesión.
                                                                        </p>
                                                                    <?php else: ?>
                                                                        <p class="form-control-static" id="company-status-meta">
                                                                            Admin global puede consultar esta vista, pero no guardar cambios como empresa propia.
                                                                        </p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label"><?php echo htmlspecialchars($name_label, ENT_QUOTES); ?></label>
                                                                <div class="col-md-9">
                                                                    <input type="text" id="name" name="name" class="form-control" 
                                                                           value="<?php echo htmlspecialchars((string)$company['name'], ENT_QUOTES); ?>" 
                                                                           required maxlength="200" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Ciudad</label>
                                                                <div class="col-md-9">
                                                                    <input type="text" id="city" name="city" class="form-control" 
                                                                           value="<?php echo htmlspecialchars((string)$company['city'], ENT_QUOTES); ?>" 
                                                                           maxlength="120" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Teléfono</label>
                                                                <div class="col-md-9">
                                                                    <input type="text" id="phone" name="phone" class="form-control" 
                                                                           value="<?php echo htmlspecialchars((string)$company['phone'], ENT_QUOTES); ?>" 
                                                                           maxlength="60" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Email</label>
                                                                <div class="col-md-9">
                                                                    <input type="email" id="email" name="email" class="form-control" 
                                                                           value="<?php echo htmlspecialchars((string)$company['email'], ENT_QUOTES); ?>" 
                                                                           maxlength="160" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label">Dirección</label>
                                                                <div class="col-md-10">
                                                                    <input type="text" id="address" name="address" class="form-control" 
                                                                           value="<?php echo htmlspecialchars((string)$company['address'], ENT_QUOTES); ?>" 
                                                                           <?php echo $domain_type === 'medical' ? '' : 'readonly'; ?>
                                                                           maxlength="200" />
                                                                    <?php if ($domain_type !== 'medical'): ?>
                                                                    <span class="help-block" id="address-unavailable-hint">No disponible para proveedores complementarios.</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label">Website</label>
                                                                <div class="col-md-10">
                                                                    <input type="url" id="website" name="website" class="form-control" 
                                                                           value="<?php echo htmlspecialchars((string)$company['website'], ENT_QUOTES); ?>" 
                                                                           maxlength="200" placeholder="https://..." />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label"><?php echo htmlspecialchars($description_label, ENT_QUOTES); ?></label>
                                                                <div class="col-md-10">
                                                                    <textarea id="description" name="description" class="form-control" 
                                                                              rows="5"><?php echo htmlspecialchars((string)$company['description'], ENT_QUOTES); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label">Simultaneous appointments capacity</label>
                                                                <div class="col-md-10">
                                                                    <div class="input-group">
                                                                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                                                        <input type="number" id="calendar_capacity" name="calendar_capacity" class="form-control"
                                                                               min="1" max="50" step="1"
                                                                               value="<?php echo max(1, (int)$company['calendar_capacity']); ?>" />
                                                                    </div>
                                                                    <span class="help-block">1 = single doctor/vehicle (no overlaps). Higher values for clinics/fleets.</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row" <?php echo $can_upload_logo ? '' : 'style="display:none;"'; ?>>
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label">Logo</label>
                                                                <div class="col-md-10">
                                                                    <div class="fileinput fileinput-new" data-provides="fileinput">
                                                                        <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                                                                            <?php 
                                                                            $logo_path = 'https://via.placeholder.com/200x150?text=Sin+Logo';
                                                                            if (!empty($company['logo'])) {
                                                                                // Construir path correcto
                                                                                $logo_file = 'img/providers/' . $scope_id . '/' . $company['logo'];
                                                                                if (file_exists('../' . $logo_file)) {
                                                                                    $logo_path = '../' . $logo_file . '?v=' . time();
                                                                                }
                                                                            }
                                                                            ?>
                                                                            <img id="logo-preview" src="<?php echo $logo_path; ?>" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                                                                        </div>
                                                                        <div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 200px; max-height: 150px;"></div>
                                                                        <div>
                                                                            <span class="btn default btn-file">
                                                                                <span class="fileinput-new">Seleccionar imagen</span>
                                                                                <span class="fileinput-exists">Cambiar</span>
                                                                                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" />
                                                                            </span>
                                                                            <a href="javascript:;" class="btn red fileinput-exists" data-dismiss="fileinput">Eliminar</a>
                                                                        </div>
                                                                        <span class="help-block">Formatos permitidos: JPG, PNG, WEBP. Máximo 2MB.</span>
                                                                        <?php if (!empty($company['logo'])): ?>
                                                                        <span class="help-block">Archivo actual: <?php echo htmlspecialchars((string)$company['logo']); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (!$can_upload_logo): ?>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="alert alert-info">
                                                                <i class="fa fa-info-circle"></i> La gestión de logo no está disponible para este dominio de empresa.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-offset-2 col-md-10">
                                                            <button type="submit" class="btn blue" id="btn-guardar" <?php echo $can_edit_self ? '' : 'disabled'; ?>>
                                                                <i class="fa fa-save"></i> Guardar Cambios
                                                            </button>
                                                            <button type="button" class="btn default" onclick="location.reload();">
                                                                <i class="fa fa-refresh"></i> Cancelar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php if ($domain_type === 'medical'): ?>
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
                                                La cuenta principal del prestador administra su staff médico y su orden operativo, manteniendo separada la entidad provider del médico/staff interno. Esta base prepara la futura asignación clínica por item sin activar todavía agenda compleja.
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php echo $footer;?>
            </div>
        </div>
        <?php echo $sider_bar;?>
    </div>

    <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
    <?php echo $theme_layout_script;?>
    <?php if ($domain_type === 'medical'): ?>
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
                                    <label for="pms-role-title">Cargo / rol</label>
                                    <input type="text" class="form-control" id="pms-role-title" name="role_title" maxlength="120" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pms-specialty">Especialidad</label>
                                    <input type="text" class="form-control" id="pms-specialty" name="specialty" maxlength="120" />
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
                            <label for="pms-bio-short">Bio corta</label>
                            <textarea class="form-control" id="pms-bio-short" name="bio_short" rows="3"></textarea>
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
                        <h5 class="bold">Campos complementarios / compatibilidad</h5>
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
                        <div class="form-group">
                            <label for="pms-service-ids">Servicios habilitados</label>
                            <select class="form-control" id="pms-service-ids" name="service_ids[]" multiple size="8"></select>
                            <span class="help-block">Opcional. Sirve como base para futura asignación clínica por item.</span>
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
    <?php endif; ?>
    <script>
        window.MI_EMPRESA_CTX = {
            domainType: <?php echo json_encode($domain_type); ?>,
            roleId: <?php echo $role_id !== null ? (int)$role_id : 'null'; ?>,
            scopeId: <?php echo (int)$scope_id; ?>,
            canEditSelf: <?php echo $can_edit_self ? 'true' : 'false'; ?>,
            canUploadLogo: <?php echo $can_upload_logo ? 'true' : 'false'; ?>
        };
        window.PROVIDER_ID = <?php echo $domain_type === 'medical' ? (int)$scope_id : 0; ?>;
    </script>
    <script src="js/mi_empresa.js" type="text/javascript"></script>
    <?php if ($domain_type === 'medical' && $hasMedicalStaffJs): ?>
    <script src="js/provider_medical_staff.js" type="text/javascript"></script>
    <?php endif; ?>
</body>
</html>

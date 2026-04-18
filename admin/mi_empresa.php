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

$company = [
    'type' => '',
    'name' => '',
    'city' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'website' => '',
    'instagram_url' => '',
    'facebook_url' => '',
    'linkedin_url' => '',
    'youtube_url' => '',
    'whatsapp_url' => '',
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
    $company['instagram_url'] = isset($provider['instagram_url']) ? $provider['instagram_url'] : '';
    $company['facebook_url'] = isset($provider['facebook_url']) ? $provider['facebook_url'] : '';
    $company['linkedin_url'] = isset($provider['linkedin_url']) ? $provider['linkedin_url'] : '';
    $company['youtube_url'] = isset($provider['youtube_url']) ? $provider['youtube_url'] : '';
    $company['whatsapp_url'] = isset($provider['whatsapp_url']) ? $provider['whatsapp_url'] : '';
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
                        <li><a href="index.php">Inicio</a></li>
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
                                                    <ul class="nav nav-tabs" role="tablist">
                                                        <li role="presentation" class="active">
                                                            <a href="#tab-general" data-toggle="tab">Información general</a>
                                                        </li>
                                                        <?php if ($domain_type === 'medical'): ?>
                                                        <li role="presentation">
                                                            <a href="#tab-canales" data-toggle="tab">Canales institucionales</a>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li role="presentation">
                                                            <a href="#tab-operacion" data-toggle="tab">Operación</a>
                                                        </li>
                                                        <?php if ($domain_type === 'medical'): ?>
                                                        <li role="presentation">
                                                            <a href="#tab-identidad" data-toggle="tab">Identidad visual</a>
                                                        </li>
                                                        <?php endif; ?>
                                                    </ul>

                                                    <div class="tab-content" style="padding-top: 20px;">

                                                        <!-- Tab 1: Información general -->
                                                        <div class="tab-pane active" id="tab-general" role="tabpanel">
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
                                                        </div>

                                                        <!-- Tab 2: Canales institucionales (medical only) -->
                                                        <?php if ($domain_type === 'medical'): ?>
                                                        <div class="tab-pane" id="tab-canales" role="tabpanel">
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label class="col-md-2 control-label">Aviso</label>
                                                                        <div class="col-md-10">
                                                                            <p class="form-control-static" style="padding-top: 0; color: #6b7280;">
                                                                                Usa solo enlaces oficiales del prestador o clínica. No incluyas perfiles personales del staff médico.
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label class="col-md-3 control-label">Instagram</label>
                                                                        <div class="col-md-9">
                                                                            <input type="url" id="instagram_url" name="instagram_url" class="form-control"
                                                                                   value="<?php echo htmlspecialchars((string)$company['instagram_url'], ENT_QUOTES); ?>"
                                                                                   maxlength="255" placeholder="https://www.instagram.com/..." />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label class="col-md-3 control-label">Facebook</label>
                                                                        <div class="col-md-9">
                                                                            <input type="url" id="facebook_url" name="facebook_url" class="form-control"
                                                                                   value="<?php echo htmlspecialchars((string)$company['facebook_url'], ENT_QUOTES); ?>"
                                                                                   maxlength="255" placeholder="https://www.facebook.com/..." />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label class="col-md-3 control-label">LinkedIn</label>
                                                                        <div class="col-md-9">
                                                                            <input type="url" id="linkedin_url" name="linkedin_url" class="form-control"
                                                                                   value="<?php echo htmlspecialchars((string)$company['linkedin_url'], ENT_QUOTES); ?>"
                                                                                   maxlength="255" placeholder="https://www.linkedin.com/..." />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label class="col-md-3 control-label">YouTube</label>
                                                                        <div class="col-md-9">
                                                                            <input type="url" id="youtube_url" name="youtube_url" class="form-control"
                                                                                   value="<?php echo htmlspecialchars((string)$company['youtube_url'], ENT_QUOTES); ?>"
                                                                                   maxlength="255" placeholder="https://www.youtube.com/..." />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label class="col-md-2 control-label">WhatsApp comercial</label>
                                                                        <div class="col-md-10">
                                                                            <input type="url" id="whatsapp_url" name="whatsapp_url" class="form-control"
                                                                                   value="<?php echo htmlspecialchars((string)$company['whatsapp_url'], ENT_QUOTES); ?>"
                                                                                   maxlength="255" placeholder="https://wa.me/..." />
                                                                            <span class="help-block">Estos enlaces refuerzan la credibilidad institucional pública del prestador.</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Tab 3: Operación -->
                                                        <div class="tab-pane" id="tab-operacion" role="tabpanel">
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label class="col-md-2 control-label">Capacidad de citas simultáneas</label>
                                                                        <div class="col-md-10">
                                                                            <div class="input-group">
                                                                                <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                                                                <input type="number" id="calendar_capacity" name="calendar_capacity" class="form-control"
                                                                                       min="1" max="50" step="1"
                                                                                       value="<?php echo max(1, (int)$company['calendar_capacity']); ?>" />
                                                                            </div>
                                                                            <span class="help-block">Define cuántos eventos de agenda tipo cita pueden solaparse al mismo tiempo para tu empresa. Hoy este valor funciona como un límite global del prestador en la agenda y no equivale todavía a disponibilidad por médico, staff o sede.</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Tab 4: Identidad visual (medical only) -->
                                                        <?php if ($domain_type === 'medical'): ?>
                                                        <div class="tab-pane" id="tab-identidad" role="tabpanel">
                                                            <?php if ($can_upload_logo): ?>
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label class="col-md-2 control-label">Logo</label>
                                                                        <div class="col-md-10">
                                                                            <div class="fileinput fileinput-new" data-provides="fileinput">
                                                                                <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                                                                                    <?php
                                                                                    $logo_path = 'https://via.placeholder.com/200x150?text=Sin+Logo';
                                                                                    if (!empty($company['logo'])) {
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
                                                            <?php else: ?>
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="alert alert-info">
                                                                        <i class="fa fa-info-circle"></i> La gestión de logo no está disponible para este perfil.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php endif; ?>

                                                    </div><!-- /.tab-content -->
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

</body>
</html>

<?php
include("include/include.php");

// Bloquear si NO es prestador
if (!isset($_SESSION['provider_id']) || empty($_SESSION['provider_id'])) {
    header("Location: index.php");
    exit();
}

$provider_id = (int)$_SESSION['provider_id'];

// Cargar datos del prestador
$sql = "SELECT * FROM providers WHERE id = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, 'i', $provider_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$provider = mysqli_fetch_array($result);
mysqli_stmt_close($stmt);

// Cargar estado de verificación
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
    mysqli_stmt_bind_param($vstmt, 'i', $provider_id);
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

if (!$provider) {
    header("Location: index.php");
    exit();
}
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
                    <h1>Mi Empresa</h1>
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
                                                <span class="caption-subject font-dark bold uppercase">Información de la Empresa</span>
                                            </div>
                                        </div>
                                        <div class="portlet-body form">
                                            <form id="form-empresa" class="form-horizontal">
                                                <input type="hidden" id="provider_id" value="<?php echo $provider_id; ?>" />
                                                
                                                <div class="form-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Tipo</label>
                                                                <div class="col-md-9">
                                                                    <p class="form-control-static"><?php echo ucfirst($provider['type']); ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Estado</label>
                                                                <div class="col-md-9">
                                                                    <p class="form-control-static">
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

                                                                        echo '<span class="badge '.$badge_class.'">'.$label.'</span> ';
                                                                        if ($provider['is_active']) {
                                                                            echo '<span class="badge badge-info">Activo</span>';
                                                                        } else {
                                                                            echo '<span class="badge badge-default">Inactivo</span>';
                                                                        }
                                                                        ?>
                                                                    </p>
                                                                    <p class="form-control-static">
                                                                        Nivel: <strong><?php echo htmlspecialchars($verification['verification_level'], ENT_QUOTES); ?></strong>
                                                                        &nbsp;·&nbsp; Avance checklist: <strong><?php echo $verification['completion_percent']; ?>%</strong>
                                                                        <?php if ($verification['verified_at']) { echo ' &nbsp;·&nbsp; Verificado: '.htmlspecialchars($verification['verified_at'], ENT_QUOTES); } ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Nombre *</label>
                                                                <div class="col-md-9">
                                                                    <input type="text" id="name" name="name" class="form-control" 
                                                                           value="<?php echo htmlspecialchars($provider['name'], ENT_QUOTES); ?>" 
                                                                           required maxlength="200" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Ciudad</label>
                                                                <div class="col-md-9">
                                                                    <input type="text" id="city" name="city" class="form-control" 
                                                                           value="<?php echo htmlspecialchars($provider['city'], ENT_QUOTES); ?>" 
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
                                                                           value="<?php echo htmlspecialchars($provider['phone'], ENT_QUOTES); ?>" 
                                                                           maxlength="60" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label class="col-md-3 control-label">Email</label>
                                                                <div class="col-md-9">
                                                                    <input type="email" id="email" name="email" class="form-control" 
                                                                           value="<?php echo htmlspecialchars($provider['email'], ENT_QUOTES); ?>" 
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
                                                                           value="<?php echo htmlspecialchars($provider['address'], ENT_QUOTES); ?>" 
                                                                           maxlength="200" />
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
                                                                           value="<?php echo htmlspecialchars($provider['website'], ENT_QUOTES); ?>" 
                                                                           maxlength="200" placeholder="https://..." />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label">Descripción</label>
                                                                <div class="col-md-10">
                                                                    <textarea id="description" name="description" class="form-control" 
                                                                              rows="5"><?php echo htmlspecialchars($provider['description'], ENT_QUOTES); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="col-md-2 control-label">Logo</label>
                                                                <div class="col-md-10">
                                                                    <div class="fileinput fileinput-new" data-provides="fileinput">
                                                                        <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                                                                            <?php 
                                                                            $logo_path = 'https://via.placeholder.com/200x150?text=Sin+Logo';
                                                                            if (!empty($provider['logo'])) {
                                                                                // Construir path correcto
                                                                                $logo_file = 'img/providers/' . $provider_id . '/' . $provider['logo'];
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
                                                                        <?php if (!empty($provider['logo'])): ?>
                                                                        <span class="help-block">Archivo actual: <?php echo htmlspecialchars($provider['logo']); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-offset-2 col-md-10">
                                                            <button type="submit" class="btn blue" id="btn-guardar">
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
    <script src="js/mi_empresa.js" type="text/javascript"></script>
</body>
</html>

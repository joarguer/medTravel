<?php
include("include/include.php");
if (!is_role_admin_session() && !user_can(PERM_CONTENT_MANAGE)) {
    header('Location: error_403.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Contact Header Edit</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <link href="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <style>
            .page-header-preview {
                min-height: 300px;
                border-radius: 10px;
                margin-bottom: 30px;
                padding: 90px 25px 50px;
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                background-size: cover;
                background-position: center;
                position: relative;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            .page-header-preview:before {
                content: "";
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
            }
            .page-header-preview-inner {
                position: relative;
                z-index: 1;
                max-width: 760px;
            }
            .page-header-preview h1 {
                color: #fff;
                font-weight: 800;
                margin: 0 0 12px;
            }
            .page-header-preview p {
                color: #dbeafe;
                font-size: 18px;
                margin: 0;
            }
        </style>
        <?php echo $theme_layout_style;?>
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
                        <h1>Contact Header Edit</h1>
                        <ol class="breadcrumb">
                            <li><a href="#">Site</a></li>
                            <li class="active">Contact (contact.php)</li>
                        </ol>
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".page-sidebar">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="toggle-icon">
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                            </span>
                        </button>
                    </div>
                    <div class="page-content-container">
                        <div class="page-content-row">
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <h3>Header Settings</h3>
                                    <ul class="nav navbar-nav">
                                        <li class="active"><a href="javascript:;"><i class="icon-picture"></i> Header</a></li>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                                <div class="portlet light">
                                    <div class="portlet-title">
                                        <div class="caption caption-md">
                                            <span class="caption-subject font-blue-madison bold uppercase">Contact Page Header</span>
                                            <span class="caption-helper">Visible en `contact.php`</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <form id="contact-header-form">
                                            <input type="hidden" id="contact_header_id" value="">
                                            <input type="hidden" id="contact_header_bg_image" value="">

                                            <div id="contact-header-preview" class="page-header-preview">
                                                <div class="page-header-preview-inner">
                                                    <h1 id="contact-header-preview-title">Contact Us</h1>
                                                    <p id="contact-header-preview-subtitle">Talk to MedTravel about providers, coordination, and booking support for your medical journey.</p>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label>Título del Header</label>
                                                <input type="text" class="form-control" id="contact_header_title" maxlength="255" placeholder="Contact Us">
                                            </div>
                                            <div class="form-group">
                                                <label>Texto descriptivo</label>
                                                <textarea class="form-control" rows="3" id="contact_header_subtitle" placeholder="Talk to MedTravel about providers, coordination, and booking support for your medical journey."></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label">Imagen del Header</label>
                                                <div id="contact-header-image-preview" style="display:none; margin-bottom:10px;">
                                                    <img id="contact-header-image-preview-img" src="" alt="Header de contacto" class="img-responsive" style="max-height:180px; border-radius:8px;">
                                                </div>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="contact_header_bg_image_display" placeholder="Sin imagen personalizada" readonly>
                                                    <span class="input-group-btn">
                                                        <label class="btn btn-default" for="contact_header_image_file" style="margin:0;">
                                                            <i class="fa fa-upload"></i> Subir imagen
                                                        </label>
                                                        <input type="file" id="contact_header_image_file" accept="image/jpeg,image/png,image/gif,image/webp" style="position:absolute; width:0; height:0; opacity:0; overflow:hidden;">
                                                    </span>
                                                </div>
                                                <div id="contact-header-image-uploading" style="display:none; margin-top:8px; color:#888;">
                                                    <i class="fa fa-spinner fa-spin"></i> Subiendo imagen del header...
                                                </div>
                                                <p class="help-block"><small>Opcional. JPG, PNG, GIF o WEBP hasta 5MB.</small></p>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar Header</button>
                                            </div>
                                        </form>
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
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <?php echo $theme_global_js;?>
        <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <?php echo $theme_layout_js;?>
        <script src="js/contact_header_edit.js" type="text/javascript"></script>
    </body>
</html>

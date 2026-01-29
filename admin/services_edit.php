<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$busca = mysqli_query($conexion,"SELECT * FROM usuarios WHERE id = '".$id_usuario."'");
$rst   = mysqli_fetch_array($busca);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Services Edit</title>
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
                        <h1>Services Edit</h1>
                        <ol class="breadcrumb">
                            <li><a href="#">Site</a></li>
                            <li class="active">Services</li>
                        </ol>
                    </div>
                    <div class="page-content-container">
                        <div class="page-content-row">
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <ul class="nav navbar-nav">
                                        <li>
                                            <a href="services_edit.php">Servicios (plantilla)</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                                <div class="portlet light ">
                                    <div class="portlet-title">
                                        <div class="caption caption-md">
                                            <span class="caption-subject font-blue-madison bold uppercase">Editar Services</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <?php
                                                $busca_services = mysqli_query($conexion,"SELECT * FROM services_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
                                                if(mysqli_num_rows($busca_services) > 0){
                                                    $rst_services = mysqli_fetch_array($busca_services);
                                                    $id_services = $rst_services['id'];
                                                } else {
                                                    $id_services = 0;
                                                }
                                                ?>
                                                <form id="form_services_header">
                                                    <input type="hidden" name="id" id="id" value="<?php echo $id_services; ?>">
                                                    
                                                    <div class="form-group">
                                                        <label>Título Principal</label>
                                                        <input type="text" class="form-control" name="title" id="title" 
                                                               value="<?php echo isset($rst_services['title']) ? htmlspecialchars($rst_services['title']) : ''; ?>" 
                                                               placeholder="Our Medical Services">
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Subtítulo 1 (Superior)</label>
                                                        <input type="text" class="form-control" name="subtitle_1" id="subtitle_1" 
                                                               value="<?php echo isset($rst_services['subtitle_1']) ? htmlspecialchars($rst_services['subtitle_1']) : ''; ?>" 
                                                               placeholder="MEDICAL SERVICES">
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Subtítulo 2 (Descripción)</label>
                                                        <input type="text" class="form-control" name="subtitle_2" id="subtitle_2" 
                                                               value="<?php echo isset($rst_services['subtitle_2']) ? htmlspecialchars($rst_services['subtitle_2']) : ''; ?>" 
                                                               placeholder="Discover quality medical services">
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Imagen de Fondo del Header</label>
                                                        <?php if(isset($rst_services['bg_image']) && !empty($rst_services['bg_image'])): ?>
                                                            <div class="mb-3">
                                                                <img src="../../<?php echo htmlspecialchars($rst_services['bg_image']); ?>" 
                                                                     alt="Header Background" 
                                                                     style="max-width: 300px; height: auto; border: 2px solid #ddd; border-radius: 5px;">
                                                                <p class="text-muted mt-2">
                                                                    <small>Imagen actual: <?php echo htmlspecialchars($rst_services['bg_image']); ?></small>
                                                                </p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" class="form-control" name="bg_image" id="bg_image" accept="image/*">
                                                        <p class="help-block">
                                                            <small>Formato recomendado: JPG, PNG. Tamaño recomendado: 1920x400px</small>
                                                        </p>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-save"></i> Guardar Cambios
                                                        </button>
                                                    </div>
                                                </form>
                                                
                                                <div id="mensaje_services" class="mt-3"></div>
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
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <?php echo $theme_layout_script;?>
        <script src="js/services_edit.js"></script>
    </body>
</html>

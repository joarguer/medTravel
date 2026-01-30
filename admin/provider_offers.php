<?php
include('include/include.php');
$provider_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Mis Ofertas</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <!-- Summernote CSS -->
    <link href="../../assets/global/plugins/bootstrap-summernote/summernote.css" rel="stylesheet" type="text/css" />
    <style>
        .required { color: #e74c3c; font-weight: bold; }
        .modal-dialog { margin-top: 30px; }
        .nav-tabs > li > a { 
            padding: 12px 20px; 
            color: #666; 
            transition: all 0.3s ease;
        }
        .nav-tabs > li.active > a,
        .nav-tabs > li.active > a:hover,
        .nav-tabs > li.active > a:focus {
            background: white;
            border-bottom: 3px solid #667eea;
            color: #667eea;
            font-weight: 600;
        }
        .nav-tabs > li > a:hover {
            background: #f8f9fa;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-group-addon {
            border-color: #d1d5db;
        }
        .help-block {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        #gallery-preview img {
            transition: transform 0.3s ease;
        }
        #gallery-preview img:hover {
            transform: scale(1.05);
        }
    </style>
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
                    <h1>Mis Ofertas</h1>
                    <ol class="breadcrumb">
                        <li><a href="#">Prestador</a></li>
                        <li class="active">Mis Ofertas</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-sidebar">
                            <nav class="navbar" role="navigation">
                                <ul class="nav navbar-nav">
                                    <li class="active"><a href="provider_offers.php"><i class="icon-list"></i> Mis Ofertas</a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="page-content-col">

<?php if (!$provider_id): ?>
    <div class="alert alert-danger">Este usuario no está asignado a un prestador</div>
<?php else: ?>
                            <div class="portlet light ">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-list theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Mis Ofertas</span>
                                    </div>
                                    <div class="actions">
                                        <a id="btn-new-offer" class="btn btn-primary">Nueva oferta</a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered" id="tbl-offers">
                                        <thead>
                                            <tr>
                                                <th>Servicio</th>
                                                <th>Título</th>
                                                <th>Precio desde</th>
                                                <th>Activo</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Fotos y galería simple -->
                            <div class="portlet light ">
                                <div class="portlet-title">
                                    <div class="caption">Galería de la oferta seleccionada</div>
                                </div>
                                <div class="portlet-body">
                                    <div id="offer-gallery">
                                        <p>Seleccione una oferta para ver sus fotos.</p>
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
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <?php echo $theme_layout_script;?>
        <!-- Summernote JS -->
        <script src="../../assets/global/plugins/bootstrap-summernote/summernote.min.js" type="text/javascript"></script>
        <script src="js/provider_offers.js" type="text/javascript"></script>

        <!-- Modal (Metronic-enhanced) -->
        <div id="offerModal" class="modal fade" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-lg" role="document" style="width: 900px;">
            <div class="modal-content">
              <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 20px 25px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1; text-shadow: none;">
                    <i class="fa fa-times"></i>
                </button>
                <h4 class="modal-title" style="color: white; font-weight: 600; font-size: 20px;">
                    <i class="fa fa-file-medical"></i> <span id="modal-title-text">Nueva Oferta de Servicio</span>
                </h4>
              </div>
              <div class="modal-body" style="padding: 0;">
                <form id="form-offer">
                    <input type="hidden" name="id" id="offer-id" />
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" role="tablist" style="padding: 0 25px; margin: 0; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                        <li role="presentation" class="active">
                            <a href="#tab-general" data-toggle="tab" style="font-weight: 600;">
                                <i class="fa fa-info-circle"></i> Información General
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab-description" data-toggle="tab" style="font-weight: 600;">
                                <i class="fa fa-file-text"></i> Descripción
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab-gallery" data-toggle="tab" style="font-weight: 600;">
                                <i class="fa fa-images"></i> Galería
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" style="padding: 25px;">
                        
                        <!-- TAB 1: Información General -->
                        <div role="tabpanel" class="tab-pane active" id="tab-general">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-stethoscope text-primary"></i> Servicio Médico <span class="required">*</span>
                                        </label>
                                        <select id="offer-service" name="service_id" class="form-control select2me" required>
                                        </select>
                                        <span class="help-block">Seleccione el tipo de servicio que ofrece</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-tag text-success"></i> Título de la Oferta <span class="required">*</span>
                                        </label>
                                        <input type="text" class="form-control" name="title" id="offer-title" 
                                               placeholder="Ej: Limpieza Dental Profesional con Fluorización" required />
                                        <span class="help-block">Título atractivo para captar la atención</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-dollar text-warning"></i> Precio Desde <span class="required">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-addon" style="background: #f5f5f5;">
                                                <i class="fa fa-money"></i>
                                            </span>
                                            <input type="number" step="0.01" class="form-control" name="price_from" 
                                                   id="offer-price" placeholder="0.00" required 
                                                   style="font-size: 16px; font-weight: 500;" />
                                        </div>
                                        <span class="help-block">Precio inicial del servicio</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-globe text-info"></i> Moneda
                                        </label>
                                        <select class="form-control" name="currency" id="offer-currency">
                                            <option value="USD">USD - Dólar</option>
                                            <option value="EUR">EUR - Euro</option>
                                            <option value="COP">COP - Peso Colombiano</option>
                                        </select>
                                        <span class="help-block">Seleccione la moneda del precio</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="mt-checkbox mt-checkbox-outline">
                                            <input type="checkbox" name="is_active" id="offer-active" checked> 
                                            Oferta activa y visible para clientes
                                            <span></span>
                                        </label>
                                        <span class="help-block">Desactive si desea ocultar temporalmente esta oferta</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TAB 2: Descripción -->
                        <div role="tabpanel" class="tab-pane" id="tab-description">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-info" style="border-left: 4px solid #667eea;">
                                        <i class="fa fa-lightbulb-o"></i> <strong>Tip:</strong> 
                                        Describa detalladamente su servicio. Incluya beneficios, procedimientos, 
                                        duración, y cualquier información relevante para sus pacientes.
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-file-text"></i> Descripción Completa del Servicio <span class="required">*</span>
                                        </label>
                                        <textarea class="form-control summernote" name="description" id="offer-desc" 
                                                  placeholder="Describa su servicio médico de manera profesional..." required></textarea>
                                        <span class="help-block">
                                            <i class="fa fa-check-circle text-success"></i> 
                                            Use el editor para dar formato profesional a la descripción
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TAB 3: Galería -->
                        <div role="tabpanel" class="tab-pane" id="tab-gallery">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-warning" style="border-left: 4px solid #f39c12;">
                                        <i class="fa fa-exclamation-triangle"></i> <strong>Nota:</strong> 
                                        Primero guarde la oferta para poder subir imágenes.
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-camera"></i> Subir Fotografías del Servicio
                                        </label>
                                        <div class="input-group" style="width: 100%;">
                                            <input type="file" id="offer-file" class="form-control" 
                                                   accept="image/jpeg,image/jpg,image/png,image/webp" />
                                            <span class="input-group-btn">
                                                <button type="button" id="offer-upload" class="btn btn-primary" 
                                                        style="height: 34px;">
                                                    <i class="fa fa-upload"></i> Subir Imagen
                                                </button>
                                            </span>
                                        </div>
                                        <span class="help-block">
                                            <i class="fa fa-info-circle"></i> 
                                            Formatos: JPG, PNG, WEBP | Tamaño máximo: 3MB | 
                                            Recomendado: 1200x800px
                                        </span>
                                    </div>
                                    
                                    <div id="gallery-preview" class="row" style="margin-top: 20px;">
                                        <!-- Aquí se mostrarán las imágenes subidas -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </form>
              </div>
              <div class="modal-footer" style="background: #f8f9fa; border-top: 2px solid #e9ecef; padding: 15px 25px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 8px 20px;">
                    <i class="fa fa-times"></i> Cancelar
                </button>
                <button type="button" id="offer-save" class="btn btn-primary" style="padding: 8px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <i class="fa fa-save"></i> Guardar Oferta
                </button>
              </div>
            </div>
          </div>
        </div>

    </div>
</body>
</html>

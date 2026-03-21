<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
$initial_provider_id = isset($_GET['provider_id']) ? (int)$_GET['provider_id'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title><?php echo $title; ?> - Verificación de Prestadores Médicos</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <!-- DataTables -->
        <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <?php echo $theme_layout_style;?>
        <style>
            .checklist-item {
                padding: 12px 14px;
                margin-bottom: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fbfcfd;
            }
            .checklist-item.checked {
                background: #edf7f0;
                border-color: #69b97f;
            }
            .checklist-checkbox {
                margin-bottom: 4px;
                font-weight: normal;
            }
            .checklist-checkbox > strong {
                margin-right: 8px;
            }
            .checklist-required {
                margin-left: 6px;
                vertical-align: middle;
                position: static;
            }
            .checklist-description {
                margin: 2px 0 0 28px;
            }
            .verification-badge {
                font-size: 14px;
                padding: 5px 10px;
            }
            .trust-score {
                font-size: 24px;
                font-weight: bold;
                color: #0f766e;
            }
        </style>
    </head>
    <body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
        <!-- BEGIN CONTAINER -->
        <div class="wrapper">
            <!-- BEGIN HEADER -->
            <header class="page-header">
                <nav class="navbar mega-menu" role="navigation">
                    <div class="container-fluid">
                        <?php echo $top_header;?>
                        <?php echo $top_header_2;?>
                    </div>
                </nav>
            </header>
            <!-- END HEADER -->
            
            <div class="container-fluid">
                <div class="page-content">
                    <div class="breadcrumbs">
                        <h1>Verificación de Prestadores Médicos</h1>
                        <ol class="breadcrumb">
                            <li><a href="index.php">Inicio</a></li>
                            <li class="active">Verificación de Prestadores Médicos</li>
                        </ol>
                    </div>

                    <div class="page-content-container">
                        <div class="page-content-row">
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <ul class="nav navbar-nav">
                                        <li class="active"><a href="provider_verification.php"><i class="fa fa-shield"></i> Verificación médica</a></li>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                                <div class="portlet light bordered">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <i class="fa fa-shield font-dark"></i>
                                            <span class="caption-subject font-dark bold uppercase">Compliance y confianza del prestador médico</span>
                                            <span class="caption-helper">Consola administrativa posterior al alta del provider</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <div class="alert alert-info" style="margin-bottom:16px;">
                                            <strong>Recurso de administración central:</strong> esta consola gestiona la verificación documental y el nivel de confianza del prestador médico después de su alta en <strong>Prestadores Médicos</strong>.
                                            <br>
                                            <span class="small">No es el onboarding primario del provider ni una vista operativa del prestador. Aquí se administra compliance, checklist y evidencia documental del dominio médico.</span>
                                        </div>
                                        <?php if ($initial_provider_id > 0): ?>
                                        <div class="alert alert-success" id="verification-context-alert" style="margin-bottom:16px;">
                                            Se abrió esta consola desde el flujo de <strong>Prestadores Médicos</strong> para revisar un prestador específico.
                                        </div>
                                        <?php endif; ?>
                                        <table class="table table-striped table-bordered table-hover" id="tabla_verificacion">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Prestador médico</th>
                                                    <th>Email</th>
                                                    <th>Teléfono</th>
                                                    <th>Estado</th>
                                                    <th>Trust score</th>
                                                    <th>Progreso checklist</th>
                                                    <th>Verificado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Se llena vía AJAX -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php echo $footer;?>
                </div>
            </div>
        </div>
        <!-- END CONTAINER -->
        
        <!-- BEGIN QUICK SIDEBAR -->
        <?php echo $sider_bar;?>
        
        <!-- MODAL: Checklist de Verificación -->
        <div class="modal fade" id="modalVerificacion" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title">
                            <i class="fa fa-shield"></i> 
                            Verificación médica: <span id="provider_name"></span>
                        </h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="provider_id">
                        <div class="alert alert-info" id="verification-modal-intro">
                            Esta vista registra la verificación administrativa, documental y de confianza del prestador médico seleccionado.
                        </div>
                        
                        <!-- Resumen de Verificación -->
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h5>Estado</h5>
                                <span id="verification_status_badge" class="label label-default verification-badge">Pendiente</span>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>Trust Score</h5>
                                <div class="trust-score" id="trust_score_display">0</div>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>Progreso</h5>
                                <div class="progress" style="margin-top: 10px;">
                                    <div id="progress_bar" class="progress-bar progress-bar-success" role="progressbar" style="width: 0%">
                                        <span id="progress_text">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Controles de Estado -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cambiar Estado</label>
                                    <select class="form-control" id="verification_status">
                                        <option value="pending">Pendiente</option>
                                        <option value="in_review">En Revisión</option>
                                        <option value="verified">Verificado</option>
                                        <option value="rejected">Rechazado</option>
                                        <option value="suspended">Suspendido</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nivel de Verificación</label>
                                    <select class="form-control" id="verification_level">
                                        <option value="basic">Básico</option>
                                        <option value="standard">Estándar</option>
                                        <option value="premium">Premium</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Notas del Administrador</label>
                                    <textarea class="form-control" id="admin_notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Checklist de Items -->
                        <h5><i class="fa fa-list"></i> Checklist de compliance documental</h5>
                        <div id="checklist_container">
                            <!-- Se llena dinámicamente con items -->
                        </div>
                        
                        <!-- LISTA DE DOCUMENTOS -->
                        <div id="documents_list" class="mt-20">
                            <!-- Se llena dinámicamente con loadProviderDocuments() -->
                        </div>
                        
                        <button type="button" class="btn btn-success btn-block" id="btnInitializeChecklist" onclick="initializeChecklist()">
                            <i class="fa fa-plus"></i> Inicializar Checklist Estándar
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="saveVerificationStatus()">
                            <i class="fa fa-save"></i> Guardar Estado
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END MODAL -->
        
        <!-- BEGIN MODAL - UPLOAD DOCUMENTO -->
        <div class="modal fade" id="modalUploadDocument" tabindex="-1" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                        <h4 class="modal-title">Subir Documento de Evidencia</h4>
                    </div>
                    <form id="uploadDocumentForm" enctype="multipart/form-data">
                        <input type="hidden" id="upload_provider_id" name="provider_id">
                        <input type="hidden" id="upload_item_id" name="item_id">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Tipo de Documento</label>
                                <select class="form-control" id="document_type" name="document_type">
                                    <option value="medical_license">Licencia Médica</option>
                                    <option value="business_registration">Registro Empresarial</option>
                                    <option value="professional_certification">Certificación Profesional</option>
                                    <option value="facility_photos">Fotos de Instalaciones</option>
                                    <option value="insurance_certificate">Certificado de Seguro</option>
                                    <option value="identity_document">Documento de Identidad</option>
                                    <option value="tax_document">Documento Tributario</option>
                                    <option value="accreditation">Acreditación</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Título <small class="text-muted">(opcional)</small></label>
                                <input type="text" class="form-control" id="document_title" name="title" placeholder="Ej: Licencia Médica 2024">
                            </div>
                            
                            <div class="form-group">
                                <label>Descripción <small class="text-muted">(opcional)</small></label>
                                <textarea class="form-control" rows="2" id="document_description" name="description" placeholder="Detalles adicionales del documento"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Archivo <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="document_file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="previewFile()" required>
                                <small class="help-block">Formatos: PDF, JPG, PNG, DOC (Máximo 10MB)</small>
                            </div>
                            
                            <div id="uploadPreview"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnUploadDocument" onclick="uploadDocument()">
                                <i class="fa fa-upload"></i> Subir Documento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- END MODAL -->
        
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL PLUGINS -->
        <script>
            window.PROVIDER_VERIFICATION_CTX = {
                providerId: <?php echo $initial_provider_id > 0 ? $initial_provider_id : 'null'; ?>
            };
        </script>
        <script src="js/provider_verification.js" type="text/javascript"></script>
    </body>
</html>

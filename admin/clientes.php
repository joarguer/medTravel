<?php
include("include/include.php");
$id_usuario = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>MedTravel - Gestión de Clientes</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <!-- DataTables -->
        <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
        <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
        <?php echo $theme_global_style;?>
        <?php echo $theme_layout_style;?>
        <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
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
                    <!-- BEGIN BREADCRUMBS -->
                    <div class="breadcrumbs">
                        <h1>Gestión de Clientes</h1>
                        <ol class="breadcrumb">
                            <li>
                                <a href="index.php">Home</a>
                            </li>
                            <li>
                                <a href="#">Administrativo</a>
                            </li>
                            <li class="active">Clientes</li>
                        </ol>
                    </div>
                    <!-- END BREADCRUMBS -->
                    
                    <!-- BEGIN CONTENT -->
                    <div class="row">
                        <div class="col-md-12">
                            <!-- BEGIN PORTLET -->
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-users font-dark"></i>
                                        <span class="caption-subject font-dark bold uppercase">Lista de Clientes</span>
                                        <span class="caption-helper">Sistema CRM</span>
                                    </div>
                                    <div class="actions">
                                        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                                            <i class="fa fa-plus"></i> Nuevo Cliente
                                        </button>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered table-hover" id="tabla_clientes">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>Teléfono</th>
                                                <th>País</th>
                                                <th>Estado/Ciudad</th>
                                                <th>Status</th>
                                                <th>Origen</th>
                                                <th>Fecha</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Se llena vía AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- END PORTLET -->
                        </div>
                    </div>
                    <!-- END CONTENT -->
                </div>
            </div>
        </div>
        <!-- END CONTAINER -->
        
        <!-- BEGIN QUICK SIDEBAR -->
        <?php echo $sider_bar;?>
        
        <!-- MODAL: Crear/Editar Cliente -->
        <div class="modal fade" id="modalCliente" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title" id="modalClienteTitle">Nuevo Cliente</h4>
                    </div>
                    <div class="modal-body">
                        <form id="formCliente" class="form-horizontal">
                            <input type="hidden" id="cliente_id" name="id">
                            
                            <!-- Información Personal -->
                            <h4 class="form-section">Información Personal</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Nombre <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Apellido <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="apellido" name="apellido" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Email <span class="required">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="+1-561-123-4567">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">WhatsApp</label>
                                        <input type="tel" class="form-control" id="whatsapp" name="whatsapp" placeholder="+1-561-123-4567">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ubicación -->
                            <h4 class="form-section">Ubicación</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">País</label>
                                        <select class="form-control" id="pais" name="pais">
                                            <option value="USA" selected>USA</option>
                                            <option value="Colombia">Colombia</option>
                                            <option value="Canada">Canada</option>
                                            <option value="Mexico">Mexico</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Estado/Provincia</label>
                                        <input type="text" class="form-control" id="estado" name="estado" placeholder="Florida">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Ciudad</label>
                                        <input type="text" class="form-control" id="ciudad" name="ciudad" placeholder="Miami">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="control-label">Dirección</label>
                                        <input type="text" class="form-control" id="direccion" name="direccion">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Código Postal</label>
                                        <input type="text" class="form-control" id="codigo_postal" name="codigo_postal">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Información Adicional -->
                            <h4 class="form-section">Información Adicional</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Tipo de Documento</label>
                                        <select class="form-control" id="tipo_documento" name="tipo_documento">
                                            <option value="passport">Pasaporte</option>
                                            <option value="license">Licencia</option>
                                            <option value="id">ID</option>
                                            <option value="other">Otro</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Número de Documento</label>
                                        <input type="text" class="form-control" id="numero_pasaporte" name="numero_pasaporte">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Idioma Preferido</label>
                                        <select class="form-control" id="idioma_preferido" name="idioma_preferido">
                                            <option value="en">English</option>
                                            <option value="es">Español</option>
                                            <option value="both">Ambos</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="lead">Lead (Interesado)</option>
                                            <option value="cotizado">Cotizado</option>
                                            <option value="confirmado">Confirmado</option>
                                            <option value="en_viaje">En Viaje</option>
                                            <option value="post_tratamiento">Post Tratamiento</option>
                                            <option value="finalizado">Finalizado</option>
                                            <option value="inactivo">Inactivo</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Origen de Contacto</label>
                                        <select class="form-control" id="origen_contacto" name="origen_contacto">
                                            <option value="web">Web</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="telefono">Teléfono</option>
                                            <option value="email">Email</option>
                                            <option value="referido">Referido</option>
                                            <option value="redes_sociales">Redes Sociales</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contacto de Emergencia -->
                            <h4 class="form-section">Contacto de Emergencia</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Nombre</label>
                                        <input type="text" class="form-control" id="contacto_emergencia_nombre" name="contacto_emergencia_nombre">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="contacto_emergencia_telefono" name="contacto_emergencia_telefono">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Relación</label>
                                        <input type="text" class="form-control" id="contacto_emergencia_relacion" name="contacto_emergencia_relacion" placeholder="Esposo/a, Hijo/a, etc.">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Información Médica -->
                            <h4 class="form-section">Información Médica</h4>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="control-label">Condiciones Médicas</label>
                                        <textarea class="form-control" id="condiciones_medicas" name="condiciones_medicas" rows="2" placeholder="Diabetes, Hipertensión, etc."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Alergias</label>
                                        <textarea class="form-control" id="alergias" name="alergias" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Medicamentos Actuales</label>
                                        <textarea class="form-control" id="medicamentos_actuales" name="medicamentos_actuales" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notas -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="control-label">Notas Internas</label>
                                        <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Marketing / Origen -->
                            <h4 class="form-section">Marketing y Tracking</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">UTM Source</label>
                                        <input type="text" class="form-control" id="utm_source" name="utm_source" placeholder="google, facebook, email">
                                        <span class="help-block">Origen del tráfico</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">UTM Medium</label>
                                        <input type="text" class="form-control" id="utm_medium" name="utm_medium" placeholder="cpc, banner, newsletter">
                                        <span class="help-block">Medio de marketing</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">UTM Campaign</label>
                                        <input type="text" class="form-control" id="utm_campaign" name="utm_campaign" placeholder="summer_promo">
                                        <span class="help-block">Nombre de campaña</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">UTM Content</label>
                                        <input type="text" class="form-control" id="utm_content" name="utm_content" placeholder="banner_azul">
                                        <span class="help-block">Variante del anuncio</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">UTM Term</label>
                                        <input type="text" class="form-control" id="utm_term" name="utm_term" placeholder="cirugia plastica">
                                        <span class="help-block">Términos de búsqueda</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">Referido Por</label>
                                        <input type="text" class="form-control" id="referred_by" name="referred_by" placeholder="Nombre o ID">
                                        <span class="help-block">¿Quién lo refirió?</span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="saveCliente()">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END MODAL -->
        
        <!-- BEGIN CORE PLUGINS -->
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <!-- END CORE PLUGINS -->
        <!-- BEGIN PAGE LEVEL PLUGINS -->
        <script src="../../assets/global/scripts/datatable.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
        <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
        <!-- END PAGE LEVEL PLUGINS -->
        <!-- BEGIN THEME GLOBAL SCRIPTS -->
        <?php echo $theme_layout_script;?>
        <!-- END THEME GLOBAL SCRIPTS -->
        <script src="js/clientes.js" type="text/javascript"></script>
    </body>
</html>

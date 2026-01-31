<?php
include('include/include.php');
// TODO: proteger para SUPERADMIN
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Prestadores</title>
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
                    <h1>Prestadores</h1>
                    <ol class="breadcrumb">
                        <li><a href="#">Site</a></li>
                        <li class="active">Prestadores</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-sidebar">
                            <nav class="navbar" role="navigation">
                                <ul class="nav navbar-nav">
                                    <li class="active"><a href="providers.php"><i class="icon-list"></i> Prestadores</a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="page-content-col">
                            <div class="portlet light ">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-list theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Prestadores</span>
                                    </div>
                                    <div class="actions">
                                        <select id="filter-kind" class="form-control input-sm" style="width:auto; display:inline-block;">
                                            <option value="">Todos</option>
                                            <option value="medical">Médicos</option>
                                            <option value="partner">Partners</option>
                                        </select>
                                        <a id="btn-new-provider" class="btn btn-primary">Nuevo prestador</a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered" id="tbl-providers">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Tipo</th>
                                                <th>Clasificación</th>
                                                <th>Ciudad</th>
                                                <th>Verificado</th>
                                                <th>Activo</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
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
        <script src="js/providers.js" type="text/javascript"></script>

        <!-- Modal (Metronic) -->
        <div id="providerModal" class="modal fade" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                <h4 class="modal-title"><strong>Prestador</strong></h4>
              </div>
              <div class="modal-body">
                <form id="form-provider">
                    <input type="hidden" name="id" id="prov-id" />
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo</label>
                                <select id="prov-type" name="type" class="form-control select2me"><option value="medico">Médico</option><option value="clinica">Clínica</option></select>
                            </div>
                            <div class="form-group">
                                <label>Clasificación</label>
                                <select id="prov-kind" name="kind" class="form-control select2me">
                                    <option value="medical">Prestador médico</option>
                                    <option value="partner">Servicio complementario</option>
                                </select>
                                <span class="help-block">Define si es prestador médico o un partner complementario.</span>
                            </div>
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" class="form-control" name="name" id="prov-name" placeholder="Nombre del prestador" required />
                            </div>
                            <div class="form-group">
                                <label>Razón Social</label>
                                <input type="text" class="form-control" name="legal_name" id="prov-legal-name" placeholder="Razón social / Nombre legal" />
                                <span class="help-block">Nombre legal o fiscal de la empresa/profesional</span>
                            </div>
                            <div class="form-group">
                                <label>Ciudad</label>
                                <input type="text" class="form-control" name="city" id="prov-city" />
                            </div>
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" class="form-control" name="address" id="prov-address" />
                            </div>
                            <div class="form-group">
                                <label>Teléfono</label>
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                                    <input type="text" class="form-control" name="phone" id="prov-phone" />
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" id="prov-email" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Website</label>
                                <input type="text" class="form-control" name="website" id="prov-website" />
                            </div>
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea class="form-control" name="description" id="prov-desc" rows="5"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="mr10"><input type="checkbox" name="is_verified" id="prov-verified"> Verificado</label>
                                <label><input type="checkbox" name="is_active" id="prov-active" checked> Activo</label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="bold"><i class="fa fa-user-circle"></i> Acceso al Panel Administrativo</h4>
                            <p class="text-muted">Credenciales para que el prestador pueda ingresar al sistema</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Usuario <span class="required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" id="prov-username" placeholder="Usuario para login" required />
                                </div>
                                <span class="help-block">El prestador usará este usuario para iniciar sesión</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contraseña <span class="required" id="password-required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" id="prov-password" placeholder="Contraseña" />
                                </div>
                                <span class="help-block" id="password-help">Dejar en blanco al editar para mantener la contraseña actual</span>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Categorías</label>
                            <select id="prov-categories" multiple class="form-control select2me" size="6"></select>
                        </div>
                        <div class="col-md-6">
                            <label>Servicios</label>
                            <select id="prov-services" multiple class="form-control select2me" size="6"></select>
                        </div>
                    </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" id="prov-save" class="btn btn-primary">Guardar</button>
              </div>
            </div>
          </div>
        </div>

    </div>
</body>
</html>

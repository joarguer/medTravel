<?php
include('include/include.php');
$is_admin = is_role_admin_session();
$provider_session_id = isset($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Mis Servicios</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
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
                    <h1>Mis Servicios</h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li class="active">Mis Servicios</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-sidebar">
                            <nav class="navbar" role="navigation">
                                <ul class="nav navbar-nav">
                                    <li class="active"><a href="service_catalog.php"><i class="fa fa-th-list"></i> Mis Servicios</a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="page-content-col">
                            <div class="portlet light ">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="fa fa-th-list theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Mis Servicios habilitados</span>
                                    </div>
                                    <div class="actions">
                                        <a id="btn-new-service" class="btn btn-primary">Nuevo servicio</a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <p class="text-muted" style="max-width:840px; margin-bottom:16px;">
                                        <strong>Mis Servicios</strong> son los servicios clínicos reales que tu empresa puede atender.
                                        Habilitarlos aquí es el primer paso: hasta que un servicio esté activo en esta lista, no podrá ser base de ninguna publicación ni asignarse a ningún miembro del staff.
                                        Una vez habilitado, podrás crear <a href="provider_offers.php">Mis Ofertas</a> &mdash; las publicaciones comerciales que verán los pacientes.
                                    </p>
                                    <div class="form-inline margin-bottom-10">
                                        <label>Filtrar por categoría:&nbsp;</label>
                                        <select id="filter-category" class="form-control"></select>
                                    </div>
                                    <table class="table table-striped table-bordered" id="tbl-services">
                                        <thead>
                                            <tr>
                                                <th>Categoría</th>
                                                <th>Nombre</th>
                                                <th>Slug</th>
                                                <th>Orden</th>
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
        <?php echo $theme_layout_script;?>
        <script>
            window.SERVICE_CATALOG_CTX = {
                isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
                providerId: <?php echo $provider_session_id > 0 ? $provider_session_id : 'null'; ?>
            };
        </script>
        <script src="js/service_catalog.js" type="text/javascript"></script>

                <!-- Modal (Metronic) -->
                <div id="serviceModal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                                <h4 class="modal-title"><strong id="service-modal-title">Nuevo servicio habilitado</strong></h4>
                            </div>
                            <div class="modal-body">
                                <form id="form-service">
                                        <input type="hidden" name="id" id="svc-id" />
                                        <div class="row">
                                                <div class="col-md-12">
                                                        <div class="form-group">
                                                                <label>Categoría</label>
                                                                <select id="svc-category" name="category_id" class="form-control select2me"></select>
                                                        </div>
                                                </div>
                                                <div class="col-md-12" id="svc-provider-wrapper" style="display:none;">
                                                        <div class="form-group">
                                                                <label>Prestador médico</label>
                                                                <select id="svc-provider" name="provider_id" class="form-control select2me">
                                                                        <option value="">Seleccionar prestador...</option>
                                                                </select>
                                                        </div>
                                                </div>
                                                <div class="col-md-12">
                                                        <div class="form-group">
                                                                <label>Nombre</label>
                                                                <input type="text" class="form-control" name="name" id="svc-name" placeholder="Nombre del servicio" required />
                                                        </div>
                                                </div>
                                                <div class="col-md-12">
                                                        <div class="form-group">
                                                                <label>Descripción corta</label>
                                                                <textarea class="form-control" name="short_description" id="svc-desc" rows="4" placeholder="Descripción breve"></textarea>
                                                        </div>
                                                </div>
                                                <div class="col-md-6">
                                                        <div class="form-group">
                                                                <label>Orden</label>
                                                                <div class="input-group">
                                                                        <span class="input-group-addon"><i class="fa fa-sort-numeric-asc"></i></span>
                                                                        <input type="number" class="form-control" name="sort_order" id="svc-order" value="1" />
                                                                </div>
                                                        </div>
                                                </div>
                                                <div class="col-md-6">
                                                        <div class="form-group">
                                                                <label class="mt5"><input type="checkbox" name="is_active" id="svc-active" checked> Activo</label>
                                                        </div>
                                                </div>
                                        </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                                <button type="button" id="svc-save" class="btn btn-primary">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>


    </div>
</body>
</html>

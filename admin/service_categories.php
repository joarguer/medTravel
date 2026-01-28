<?php
include('include/include.php');
// TODO: proteger para SUPERADMIN si se dispone de control de roles
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Categorías de servicios</title>
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
                    <h1>Categorías de servicios</h1>
                    <ol class="breadcrumb">
                        <li><a href="#">Site</a></li>
                        <li class="active">Categorías</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-sidebar">
                            <nav class="navbar" role="navigation">
                                <ul class="nav navbar-nav">
                                    <li class="active"><a href="service_categories.php"><i class="icon-list"></i> Categorías de servicios</a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="page-content-col">
                            <div class="portlet light ">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-list theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Categorías</span>
                                    </div>
                                    <div class="actions">
                                        <a id="btn-new-category" class="btn btn-primary">Nueva categoría</a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered" id="tbl-categories">
                                        <thead>
                                            <tr>
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
        <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <?php echo $theme_layout_script;?>
        <script src="js/service_categories.js" type="text/javascript"></script>

                <!-- Modal (Metronic style) -->
                <div id="categoryModal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                                <h4 class="modal-title"><strong>Categoría</strong></h4>
                            </div>
                            <div class="modal-body">
                                <form id="form-category">
                                        <input type="hidden" name="id" id="cat-id" />
                                        <div class="row">
                                                <div class="col-md-12">
                                                        <div class="form-group">
                                                                <label>Nombre</label>
                                                                <input type="text" class="form-control" name="name" id="cat-name" placeholder="Nombre de la categoría" required />
                                                        </div>
                                                </div>
                                                <div class="col-md-12">
                                                        <div class="form-group">
                                                                <label>Descripción</label>
                                                                <textarea class="form-control" name="description" id="cat-desc" rows="4" placeholder="Descripción breve"></textarea>
                                                        </div>
                                                </div>
                                                <div class="col-md-6">
                                                        <div class="form-group">
                                                                <label>Orden</label>
                                                                <div class="input-group">
                                                                        <span class="input-group-addon"><i class="fa fa-sort-numeric-asc"></i></span>
                                                                        <input type="number" class="form-control" name="sort_order" id="cat-order" value="1" />
                                                                </div>
                                                        </div>
                                                </div>
                                                <div class="col-md-6">
                                                        <div class="form-group">
                                                                <label class="mt5"><input type="checkbox" name="is_active" id="cat-active" checked> Activo</label>
                                                        </div>
                                                </div>
                                        </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                                <button type="button" id="cat-save" class="btn btn-primary">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>

    </div>
</body>
</html>

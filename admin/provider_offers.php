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
        <script src="js/provider_offers.js" type="text/javascript"></script>

        <!-- Modal (Metronic-enhanced) -->
        <div id="offerModal" class="modal fade" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                <h4 class="modal-title"><strong>Oferta</strong></h4>
              </div>
              <div class="modal-body">
                <form id="form-offer">
                    <input type="hidden" name="id" id="offer-id" />
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Servicio</label>
                                <select id="offer-service" name="service_id" class="form-control select2me"></select>
                            </div>
                            <div class="form-group">
                                <label>Título</label>
                                <input type="text" class="form-control" name="title" id="offer-title" placeholder="Título de la oferta" />
                            </div>
                            <div class="form-group">
                                <label>Precio desde</label>
                                <div class="input-group">
                                    <span class="input-group-addon">$</span>
                                    <input type="number" step="0.01" class="form-control" name="price_from" id="offer-price" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Moneda</label>
                                <input type="text" class="form-control" name="currency" id="offer-currency" value="USD" />
                            </div>
                            <div class="form-group">
                                <label class="mt5"><input type="checkbox" name="is_active" id="offer-active" checked> Activo</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea class="form-control" name="description" id="offer-desc" rows="7" placeholder="Descripción breve"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Subir foto</label>
                                <div>
                                    <input type="file" id="offer-file" style="display:inline-block;" />
                                    <button type="button" id="offer-upload" class="btn btn-sm btn-primary" style="margin-left:8px;">Subir</button>
                                </div>
                                <p class="help-block">Formatos jpg/png. Máx 3MB.</p>
                            </div>
                        </div>
                    </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" id="offer-save" class="btn btn-primary">Guardar</button>
              </div>
            </div>
          </div>
        </div>

    </div>
</body>
</html>

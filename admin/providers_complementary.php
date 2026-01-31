<?php
include('include/include.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Proveedores Complementarios</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style;?>
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/bootstrap-toastr/toastr.min.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <style>
        .badge-type { font-size: 11px; padding: 4px 8px; border-radius: 10px; text-transform: uppercase; }
        .badge-airline { background: #3498db; color: #fff; }
        .badge-hotel { background: #e67e22; color: #fff; }
        .badge-transport { background: #1abc9c; color: #fff; }
        .badge-restaurant { background: #c0392b; color: #fff; }
        .badge-tour_operator { background: #9b59b6; color: #fff; }
        .badge-other { background: #7f8c8d; color: #fff; }
        .rating { font-weight: bold; color: #f1c40f; }
    </style>
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
                    <h1>Proveedores Complementarios
                        <small>Catálogo de aerolíneas, hoteles, transporte, restaurantes</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li class="active">Proveedores Complementarios</li>
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
                        <div class="page-content-col">
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-briefcase font-blue"></i>
                                        <span class="caption-subject font-blue bold uppercase">Listado de Proveedores Complementarios</span>
                                        <span class="caption-helper">Catálogo reutilizable para servicios comerciales</span>
                                    </div>
                                    <div class="actions">
                                        <button id="btnNewProvider" class="btn btn-primary">
                                            <i class="fa fa-plus"></i> Nuevo Proveedor
                                        </button>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <table class="table table-striped table-bordered table-hover" id="providers_table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Proveedor</th>
                                                <th>Tipo</th>
                                                <th>Contacto</th>
                                                <th>Email</th>
                                                <th>Teléfono</th>
                                                <th>Ubicación</th>
                                                <th>Rating</th>
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
            </div>
            <?php echo $footer;?>
        </div>
        <?php echo $sider_bar;?>
    </div>

    <div class="modal fade" id="providerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="providerForm" class="form-horizontal">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="providerModalTitle">Nuevo Proveedor</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="provider_id" name="id">
                        <div class="form-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Nombre *</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="provider_name" name="provider_name" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Tipo *</label>
                                        <div class="col-md-8">
                                            <select class="form-control" id="provider_type" name="provider_type" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="airline">Aerolínea</option>
                                                <option value="hotel">Hotel</option>
                                                <option value="transport">Transporte</option>
                                                <option value="restaurant">Restaurante</option>
                                                <option value="tour_operator">Tour Operador</option>
                                                <option value="other">Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">NIT / Tax ID</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="tax_id" name="tax_id">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">País</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="country" name="country" value="Colombia">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Ciudad</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="city" name="city">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Dirección</label>
                                        <div class="col-md-8">
                                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Contacto</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="contact_name" name="contact_name">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Cargo</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="contact_position" name="contact_position">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Email</label>
                                        <div class="col-md-8">
                                            <input type="email" class="form-control" id="contact_email" name="contact_email">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Teléfono</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="contact_phone" name="contact_phone">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Móvil</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="contact_mobile" name="contact_mobile">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Website</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="website" name="website">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Rating</label>
                                        <div class="col-md-8">
                                            <input type="number" class="form-control" id="rating" name="rating" min="0" max="5" step="0.1" placeholder="0 a 5">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Estado</label>
                                        <div class="col-md-8">
                                            <label class="mt-checkbox mt-checkbox-outline">
                                                <input type="checkbox" id="is_active" name="is_active" value="1" checked> Activo
                                                <span></span>
                                            </label>
                                            <label class="mt-checkbox mt-checkbox-outline" style="margin-left:15px;">
                                                <input type="checkbox" id="is_preferred" name="is_preferred" value="1"> Preferido
                                                <span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Términos de Pago</label>
                                        <div class="col-md-8">
                                            <textarea class="form-control" id="payment_terms" name="payment_terms" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Banco / Cuenta</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" id="bank_account" name="bank_account">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Método Pago Preferido</label>
                                        <div class="col-md-8">
                                            <select class="form-control" id="preferred_payment_method" name="preferred_payment_method">
                                                <option value="transfer">Transferencia</option>
                                                <option value="credit_card">Tarjeta Crédito</option>
                                                <option value="cash">Efectivo</option>
                                                <option value="other">Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Notas</label>
                                        <div class="col-md-8">
                                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-4">Contrato / Acuerdos</label>
                                        <div class="col-md-8">
                                            <textarea class="form-control" id="contract_details" name="contract_details" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
+                    </div>
+                    <div class="modal-footer">
+                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
+                        <button type="submit" class="btn btn-primary" id="btnSaveProvider">
+                            <i class="fa fa-save"></i> Guardar
+                        </button>
+                    </div>
+                </form>
+            </div>
+        </div>
+    </div>
+
+    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
+    <script src="../../assets/global/plugins/bootstrap-toastr/toastr.min.js" type="text/javascript"></script>
+    <?php echo $theme_layout_script;?>
+    <script src="js/providers_complementary.js" type="text/javascript"></script>
+</body>
+</html>

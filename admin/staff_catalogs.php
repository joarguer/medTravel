<?php
include("include/include.php");
$is_admin = is_role_admin_session();
$role_id  = current_role_id();
$provider_id          = isset($_SESSION['provider_id'])          ? (int)$_SESSION['provider_id']          : 0;
$service_provider_id  = isset($_SESSION['service_provider_id'])  ? (int)$_SESSION['service_provider_id']  : 0;

$domain_type = 'none';
$scope_id    = 0;
if (in_array((int)$role_id, [ROLE_PROVIDER, ROLE_PROVIDER_ADMIN], true) && $provider_id > 0) {
    $domain_type = 'medical';
    $scope_id    = $provider_id;
} elseif (!$is_admin && $provider_id > 0) {
    $domain_type = 'medical';
    $scope_id    = $provider_id;
}

if ($domain_type !== 'medical') {
    header("Location: mi_empresa.php");
    exit();
}

$provider_check = null;
if ($scope_id > 0) {
    $stmt = mysqli_prepare($conexion, "SELECT id, is_active FROM providers WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $scope_id);
    mysqli_stmt_execute($stmt);
    $res            = mysqli_stmt_get_result($stmt);
    $provider_check = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}
if (!$provider_check || (isset($provider_check['is_active']) && intval($provider_check['is_active']) !== 1)) {
    header("Location: index.php");
    exit();
}

$hasMedicalStaffAjax = is_file(__DIR__ . '/ajax/provider_medical_staff.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - Catálogos del staff</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <style>
        .catalog-table td, .catalog-table th { vertical-align: middle !important; }
        .catalog-table .badge-system {
            background: #e0ecff; color: #2d6ccc;
            border-radius: 3px; padding: 2px 7px; font-size: 11px; font-weight: 600;
        }
        .catalog-table .badge-custom {
            background: #e6faf0; color: #1a8c55;
            border-radius: 3px; padding: 2px 7px; font-size: 11px; font-weight: 600;
        }
        .catalog-section { margin-bottom: 36px; }
        .catalog-add-row { background: #f9fbff; }
    </style>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <div class="wrapper">
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header; ?>
                    <?php echo $top_header_2; ?>
                </div>
            </nav>
        </header>

        <div class="container-fluid">
            <div class="page-content">
                <div class="breadcrumbs">
                    <h1>Catálogos del staff</h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="mi_empresa.php">Mi Empresa</a></li>
                        <li><a href="staff_medico.php">Staff médico</a></li>
                        <li class="active">Catálogos</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-content-col">

                            <?php if (!$hasMedicalStaffAjax): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle"></i>
                                El módulo AJAX del staff no está disponible.
                            </div>
                            <?php endif; ?>

                            <div class="alert alert-info" style="font-size:13px;">
                                <i class="fa fa-info-circle"></i>
                                <strong>Catálogos compartidos y personalizados.</strong>
                                Las entradas marcadas como <span class="badge-system" style="display:inline;">Sistema</span>
                                son visibles para todos los proveedores y no se pueden editar.
                                Puedes añadir tus propias opciones <span class="badge-custom" style="display:inline;">Personalizada</span>
                                que sólo aparecerán en tu cuenta.
                            </div>

                            <div class="row">

                                <!-- ── ROLES ─────────────────────────────────────────────── -->
                                <div class="col-md-6 catalog-section">
                                    <div class="portlet light bordered">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-id-badge font-green-sharp"></i>
                                                <span class="caption-subject bold uppercase">Roles del staff</span>
                                                <span class="caption-helper"> — cargos y funciones</span>
                                            </div>
                                            <div class="actions">
                                                <button type="button" class="btn btn-xs btn-primary catalog-add-btn"
                                                        data-catalog="roles">
                                                    <i class="fa fa-plus"></i> Añadir rol
                                                </button>
                                            </div>
                                        </div>
                                        <div class="portlet-body">
                                            <div id="catalog-roles-loading" style="text-align:center; padding:20px;">
                                                <i class="fa fa-spinner fa-spin"></i> Cargando…
                                            </div>
                                            <table class="table table-condensed table-hover catalog-table"
                                                   id="catalog-roles-table" style="display:none;">
                                                <thead>
                                                    <tr>
                                                        <th>Nombre</th>
                                                        <th style="width:90px;">Tipo</th>
                                                        <th style="width:70px;">Estado</th>
                                                        <th style="width:60px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="catalog-roles-body"></tbody>
                                                <tfoot>
                                                    <tr class="catalog-add-row" id="catalog-roles-add-row" style="display:none;">
                                                        <td>
                                                            <input type="text" class="form-control input-sm catalog-new-name"
                                                                   id="catalog-roles-new-name"
                                                                   placeholder="Nombre del nuevo rol (en inglés)"
                                                                   maxlength="120" />
                                                        </td>
                                                        <td colspan="2" style="vertical-align:middle; color:#888; font-size:12px;">
                                                            Personalizada
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-xs btn-success catalog-save-new"
                                                                    data-catalog="roles">
                                                                <i class="fa fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-default catalog-cancel-new"
                                                                    data-catalog="roles">
                                                                <i class="fa fa-times"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── ESPECIALIDADES ────────────────────────────────────── -->
                                <div class="col-md-6 catalog-section">
                                    <div class="portlet light bordered">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <i class="fa fa-stethoscope font-blue-sharp"></i>
                                                <span class="caption-subject bold uppercase">Especialidades médicas</span>
                                                <span class="caption-helper"> — áreas de práctica</span>
                                            </div>
                                            <div class="actions">
                                                <button type="button" class="btn btn-xs btn-primary catalog-add-btn"
                                                        data-catalog="specialties">
                                                    <i class="fa fa-plus"></i> Añadir especialidad
                                                </button>
                                            </div>
                                        </div>
                                        <div class="portlet-body">
                                            <div id="catalog-specialties-loading" style="text-align:center; padding:20px;">
                                                <i class="fa fa-spinner fa-spin"></i> Cargando…
                                            </div>
                                            <table class="table table-condensed table-hover catalog-table"
                                                   id="catalog-specialties-table" style="display:none;">
                                                <thead>
                                                    <tr>
                                                        <th>Nombre</th>
                                                        <th style="width:90px;">Tipo</th>
                                                        <th style="width:70px;">Estado</th>
                                                        <th style="width:60px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="catalog-specialties-body"></tbody>
                                                <tfoot>
                                                    <tr class="catalog-add-row" id="catalog-specialties-add-row" style="display:none;">
                                                        <td>
                                                            <input type="text" class="form-control input-sm catalog-new-name"
                                                                   id="catalog-specialties-new-name"
                                                                   placeholder="Nombre de la nueva especialidad (en inglés)"
                                                                   maxlength="120" />
                                                        </td>
                                                        <td colspan="2" style="vertical-align:middle; color:#888; font-size:12px;">
                                                            Personalizada
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-xs btn-success catalog-save-new"
                                                                    data-catalog="specialties">
                                                                <i class="fa fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-default catalog-cancel-new"
                                                                    data-catalog="specialties">
                                                                <i class="fa fa-times"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /.row -->

                        </div><!-- /.page-content-col -->
                    </div><!-- /.page-content-row -->
                </div><!-- /.page-content-container -->

            </div><!-- /.page-content -->
        </div><!-- /.container-fluid -->

        <?php echo $footer; ?>
    </div><!-- /.wrapper -->

    <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <?php echo $theme_layout_script; ?>

    <script>
    (function ($) {
        'use strict';

        var PROVIDER_ID  = <?php echo (int)$scope_id; ?>;
        var ENDPOINT     = '<?php echo htmlspecialchars(
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/ajax/provider_medical_staff.php',
            ENT_QUOTES
        ); ?>';

        // ── Utilidades ───────────────────────────────────────────────────────

        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function showAlert(message, type) {
            type = type || 'danger';
            var $alert = $('<div class="alert alert-' + type + ' alert-dismissible" role="alert" style="margin:10px 0;">'
                + '<button type="button" class="close" data-dismiss="alert">&times;</button>'
                + escapeHtml(message)
                + '</div>');
            $('.page-content-col').prepend($alert);
            setTimeout(function () { $alert.fadeOut(400, function () { $(this).remove(); }); }, 4000);
        }

        // ── Renderizado de tabla ─────────────────────────────────────────────

        function renderCatalogTable(catalogType, items) {
            var $body = $('#catalog-' + catalogType + '-body');
            $body.empty();

            if (!items || items.length === 0) {
                $body.append(
                    '<tr><td colspan="4" class="text-center text-muted" style="padding:14px;">Sin entradas todavía.</td></tr>'
                );
            } else {
                $.each(items, function (i, item) {
                    var typeBadge = item.is_system
                        ? '<span class="badge-system">Sistema</span>'
                        : '<span class="badge-custom">Personalizada</span>';
                    var statusLabel = item.is_active
                        ? '<span class="label label-sm label-success">Activo</span>'
                        : '<span class="label label-sm label-default">Inactivo</span>';

                    var actions = '';
                    if (!item.is_system) {
                        actions +=
                            '<button type="button" class="btn btn-xs btn-default catalog-toggle-btn"'
                            + ' data-catalog="' + escapeHtml(catalogType) + '"'
                            + ' data-id="' + item.id + '"'
                            + ' title="' + (item.is_active ? 'Desactivar' : 'Activar') + '">'
                            + '<i class="fa fa-' + (item.is_active ? 'toggle-on' : 'toggle-off') + '"></i>'
                            + '</button> '
                            + '<button type="button" class="btn btn-xs btn-danger catalog-delete-btn"'
                            + ' data-catalog="' + escapeHtml(catalogType) + '"'
                            + ' data-id="' + item.id + '"'
                            + ' data-name="' + escapeHtml(item.name) + '"'
                            + ' title="Eliminar">'
                            + '<i class="fa fa-trash"></i>'
                            + '</button>';
                    }

                    $body.append(
                        '<tr data-id="' + item.id + '">'
                        + '<td>' + escapeHtml(item.name) + '</td>'
                        + '<td>' + typeBadge + '</td>'
                        + '<td>' + statusLabel + '</td>'
                        + '<td style="white-space:nowrap;">' + actions + '</td>'
                        + '</tr>'
                    );
                });
            }

            $('#catalog-' + catalogType + '-loading').hide();
            $('#catalog-' + catalogType + '-table').show();
        }

        // ── Carga inicial ────────────────────────────────────────────────────

        function loadCatalog(catalogType) {
            $('#catalog-' + catalogType + '-loading').show();
            $('#catalog-' + catalogType + '-table').hide();

            $.ajax({
                url:      ENDPOINT,
                method:   'GET',
                dataType: 'json',
                data: {
                    action:       'list_staff_catalog_items',
                    provider_id:  PROVIDER_ID,
                    catalog_type: catalogType
                }
            }).done(function (res) {
                if (res && res.ok) {
                    renderCatalogTable(catalogType, res.items);
                } else {
                    $('#catalog-' + catalogType + '-loading').html(
                        '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> '
                        + escapeHtml((res && res.message) || 'Error al cargar') + '</span>'
                    ).show();
                }
            }).fail(function () {
                $('#catalog-' + catalogType + '-loading').html(
                    '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> Error de conexión.</span>'
                ).show();
            });
        }

        // ── Botón "Añadir" ───────────────────────────────────────────────────

        $(document).on('click', '.catalog-add-btn', function () {
            var cat = $(this).data('catalog');
            $('#catalog-' + cat + '-add-row').show();
            $('#catalog-' + cat + '-new-name').focus();
        });

        $(document).on('click', '.catalog-cancel-new', function () {
            var cat = $(this).data('catalog');
            $('#catalog-' + cat + '-add-row').hide();
            $('#catalog-' + cat + '-new-name').val('');
        });

        $(document).on('click', '.catalog-save-new', function () {
            var cat  = $(this).data('catalog');
            var name = $.trim($('#catalog-' + cat + '-new-name').val());
            if (!name) {
                showAlert('El nombre no puede estar vacío.');
                return;
            }
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url:      ENDPOINT,
                method:   'POST',
                dataType: 'json',
                data: {
                    action:       'save_staff_catalog_item',
                    provider_id:  PROVIDER_ID,
                    catalog_type: cat,
                    name:         name
                }
            }).done(function (res) {
                if (res && res.ok) {
                    $('#catalog-' + cat + '-add-row').hide();
                    $('#catalog-' + cat + '-new-name').val('');
                    loadCatalog(cat);
                } else {
                    showAlert((res && res.message) ? res.message : 'Error al guardar.');
                }
            }).fail(function () {
                showAlert('Error de conexión al guardar.');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // Guardar con Enter en el input
        $(document).on('keydown', '.catalog-new-name', function (e) {
            if (e.which === 13) {
                var cat = $(this).attr('id').replace('catalog-', '').replace('-new-name', '');
                $('.catalog-save-new[data-catalog="' + cat + '"]').trigger('click');
            }
        });

        // ── Toggle (activar/desactivar) ──────────────────────────────────────

        $(document).on('click', '.catalog-toggle-btn', function () {
            var cat    = $(this).data('catalog');
            var itemId = $(this).data('id');
            var $btn   = $(this).prop('disabled', true);
            $.ajax({
                url:      ENDPOINT,
                method:   'POST',
                dataType: 'json',
                data: {
                    action:       'toggle_staff_catalog_item',
                    provider_id:  PROVIDER_ID,
                    catalog_type: cat,
                    item_id:      itemId
                }
            }).done(function (res) {
                if (res && res.ok) {
                    loadCatalog(cat);
                } else {
                    showAlert((res && res.message) ? res.message : 'Error al cambiar estado.');
                    $btn.prop('disabled', false);
                }
            }).fail(function () {
                showAlert('Error de conexión.');
                $btn.prop('disabled', false);
            });
        });

        // ── Eliminar ─────────────────────────────────────────────────────────

        $(document).on('click', '.catalog-delete-btn', function () {
            var cat    = $(this).data('catalog');
            var itemId = $(this).data('id');
            var name   = $(this).data('name');
            if (!confirm('¿Eliminar "' + name + '"? Esta acción no se puede deshacer.')) {
                return;
            }
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url:      ENDPOINT,
                method:   'POST',
                dataType: 'json',
                data: {
                    action:       'delete_staff_catalog_item',
                    provider_id:  PROVIDER_ID,
                    catalog_type: cat,
                    item_id:      itemId
                }
            }).done(function (res) {
                if (res && res.ok) {
                    loadCatalog(cat);
                } else {
                    showAlert((res && res.message) ? res.message : 'Error al eliminar.');
                    $btn.prop('disabled', false);
                }
            }).fail(function () {
                showAlert('Error de conexión.');
                $btn.prop('disabled', false);
            });
        });

        // ── Inicialización ────────────────────────────────────────────────────

        loadCatalog('roles');
        loadCatalog('specialties');

    })(jQuery);
    </script>

</body>
</html>

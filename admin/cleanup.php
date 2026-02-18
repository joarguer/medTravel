<?php
include('include/include.php');

if (!is_role_admin_session()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acceso denegado';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - Limpieza (DEV)</title>
    <?php echo $global_first_style; ?>
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
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
                <h1>Limpieza (DEV) <small>Soft delete / restore</small></h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Inicio</a></li>
                    <li class="active">Limpieza (DEV)</li>
                </ol>
            </div>

            <div class="portlet light bordered">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="icon-trash font-red"></i>
                        <span class="caption-subject font-red bold uppercase">Limpieza (DEV)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="active"><a href="#tab-users" data-toggle="tab">Usuarios</a></li>
                        <li><a href="#tab-providers" data-toggle="tab">Providers médicos</a></li>
                        <li><a href="#tab-service-providers" data-toggle="tab">Service Providers</a></li>
                        <li><a href="#tab-medtravel-services" data-toggle="tab">MedTravel Services</a></li>
                    </ul>

                    <div class="tab-content" style="padding-top:15px;">
                        <div class="tab-pane active" id="tab-users">
                            <div class="checkbox" style="margin-bottom:10px;">
                                <label><input type="checkbox" id="users-show-deleted"> Ver eliminados</label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-users-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-providers">
                            <div class="checkbox" style="margin-bottom:10px;">
                                <label><input type="checkbox" id="providers-show-deleted"> Ver eliminados</label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-providers-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Ciudad</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-service-providers">
                            <div class="checkbox" style="margin-bottom:10px;">
                                <label><input type="checkbox" id="service-providers-show-deleted"> Ver eliminados</label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-service-providers-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Proveedor</th>
                                    <th>Tipo</th>
                                    <th>Email</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-medtravel-services">
                            <div class="checkbox" style="margin-bottom:10px;">
                                <label><input type="checkbox" id="medtravel-services-show-deleted"> Ver eliminados</label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-medtravel-services-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Servicio</th>
                                    <th>Tipo</th>
                                    <th>Disponibilidad</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>

    <?php echo $sider_bar; ?>
</div>

<?php echo $theme_layout_script; ?>
<script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
<script>
(function(){
    var usersShowDeleted = false;
    var providersShowDeleted = false;
    var serviceProvidersShowDeleted = false;
    var medtravelServicesShowDeleted = false;

    function notifyError(msg){
        if (window.toastr) { toastr.error(msg); return; }
        alert(msg);
    }

    function notifySuccess(msg){
        if (window.toastr) { toastr.success(msg); return; }
        alert(msg);
    }

    function actionButton(showDeleted, actionClass, dataId){
        var text = showDeleted ? 'Restaurar' : 'Eliminar (Soft)';
        var toneClass = showDeleted ? 'btn-info' : 'btn-danger';
        return '<button class="btn btn-xs ' + toneClass + ' ' + actionClass + '" data-id="' + dataId + '">' + text + '</button>';
    }

    var usersTable = $('#cleanup-users-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'usuario' },
            { data: 'nombre' },
            { data: 'email' },
            { data: 'activo', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(usersShowDeleted, 'btn-soft-delete-user', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var providersTable = $('#cleanup-providers-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'type' },
            { data: 'city' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(providersShowDeleted, 'btn-soft-delete-provider', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var serviceProvidersTable = $('#cleanup-service-providers-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'provider_name' },
            { data: 'provider_type' },
            { data: 'contact_email' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(serviceProvidersShowDeleted, 'btn-soft-delete-service-provider', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var medtravelServicesTable = $('#cleanup-medtravel-services-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'service_name' },
            { data: 'service_type' },
            { data: 'availability_status' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(medtravelServicesShowDeleted, 'btn-soft-delete-medtravel-service', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    function loadUsers(){
        $.getJSON('ajax/cleanup_users.php', { action: 'list_users', show_deleted: usersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            usersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar usuarios';
            notifyError(msg);
        });
    }

    function loadProviders(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_providers', show_deleted: providersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            providersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar providers';
            notifyError(msg);
        });
    }

    function loadServiceProviders(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_service_providers', show_deleted: serviceProvidersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            serviceProvidersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar service providers';
            notifyError(msg);
        });
    }

    function loadMedtravelServices(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_medtravel_services', show_deleted: medtravelServicesShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            medtravelServicesTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar MedTravel Services';
            notifyError(msg);
        });
    }

    function confirmAction(isRestore){
        return window.confirm(isRestore ? '¿Deseas restaurar?' : '¿Deseas eliminar (soft)?');
    }

    $('#users-show-deleted').on('change', function(){
        usersShowDeleted = $(this).is(':checked');
        loadUsers();
    });

    $('#providers-show-deleted').on('change', function(){
        providersShowDeleted = $(this).is(':checked');
        loadProviders();
    });

    $('#service-providers-show-deleted').on('change', function(){
        serviceProvidersShowDeleted = $(this).is(':checked');
        loadServiceProviders();
    });

    $('#medtravel-services-show-deleted').on('change', function(){
        medtravelServicesShowDeleted = $(this).is(':checked');
        loadMedtravelServices();
    });

    $('#cleanup-users-table').on('click', '.btn-soft-delete-user', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = usersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_users.php', {
            action: isRestore ? 'restore_user' : 'soft_delete_user',
            user_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Usuario restaurado' : 'Usuario eliminado (soft)'));
                loadUsers();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-providers-table').on('click', '.btn-soft-delete-provider', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = providersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_provider' : 'soft_delete_provider',
            provider_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Provider restaurado' : 'Provider eliminado (soft)'));
                loadProviders();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-service-providers-table').on('click', '.btn-soft-delete-service-provider', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = serviceProvidersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_service_provider' : 'soft_delete_service_provider',
            service_provider_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Service provider restaurado' : 'Service provider eliminado (soft)'));
                loadServiceProviders();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-medtravel-services-table').on('click', '.btn-soft-delete-medtravel-service', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = medtravelServicesShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_medtravel_service' : 'soft_delete_medtravel_service',
            medtravel_service_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Servicio restaurado' : 'Servicio eliminado (soft)'));
                loadMedtravelServices();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    loadUsers();
    loadProviders();
    loadServiceProviders();
    loadMedtravelServices();
})();
</script>
</body>
</html>

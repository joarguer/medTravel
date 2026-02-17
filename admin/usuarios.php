<?php
include('include/include.php');
$can_manage_users = user_can(PERM_USERS_MANAGE) || user_can('users.edit') || user_can('users.create');
if (!user_can('users.view') && !$can_manage_users) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acceso denegado';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Usuarios</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
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
                    <h1>Usuarios
                        <small>Listado y roles</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li class="active">Usuarios</li>
                    </ol>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light ">
                            <div class="portlet-title">
                                <div class="caption">
                                    <span class="caption-subject font-dark bold">Usuarios</span>
                                    <span class="caption-helper">Gestiona roles y estado</span>
                                </div>
                                <div class="actions">
                                    <select id="filter-kind-users" class="form-control input-sm" style="width:auto; display:inline-block;">
                                        <option value="">Todos</option>
                                        <option value="medical">Prestadores médicos</option>
                                        <option value="partner">Partners</option>
                                        <option value="sin">Sin prestador</option>
                                    </select>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <table class="table table-striped table-bordered" id="users-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Rol</th>
                                            <th>Prestador / Empresa</th>
                                            <th>Estado</th>
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
            <?php echo $footer; ?>
        </div>
        <?php echo $sider_bar; ?>
    </div>

    <div class="modal fade" id="user-edit-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Editar Usuario</h4>
                </div>
                <div class="modal-body">
                    <form id="user-edit-form">
                        <input type="hidden" id="edit-id" name="id">
                        <div class="form-group">
                            <label for="edit-email">Email</label>
                            <input type="email" class="form-control" id="edit-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-usuario">Usuario</label>
                            <input type="text" class="form-control" id="edit-usuario" name="usuario" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-role-id">Rol</label>
                            <select class="form-control" id="edit-role-id" name="role_id" required></select>
                        </div>
                        <div class="form-group" id="edit-provider-group" style="display:none;">
                            <label for="edit-provider-id">Prestador médico</label>
                            <select class="form-control" id="edit-provider-id" name="provider_id"></select>
                        </div>
                        <div class="form-group" id="edit-service-provider-group" style="display:none;">
                            <label for="edit-service-provider-id">Proveedor complementario</label>
                            <select class="form-control" id="edit-service-provider-id" name="service_provider_id"></select>
                        </div>
                        <div class="form-group">
                            <label for="edit-activo">Estado</label>
                            <select class="form-control" id="edit-activo" name="activo" required>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn blue" id="btn-save-user-edit">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <?php echo $theme_layout_script; ?>
    <script>
        window.USERS_CTX = {
            canEdit: <?php echo $can_manage_users ? 'true' : 'false'; ?>,
            complementaryRoleId: <?php echo ROLE_PROVIDER_ADMIN; ?>,
            providerRoleId: <?php echo ROLE_PROVIDER; ?>,
            adminRoleId: <?php echo ROLE_ADMIN; ?>
        };
    </script>
    <script src="js/usuarios.js"></script>
</body>
</html>

<?php
include('include/include.php');
$is_admin = is_role_admin_session();
$can_manage_users = $is_admin || user_can(PERM_USERS_MANAGE) || user_can('users.manage') || user_can('users.edit') || user_can('users.create');
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
                    <h1>Administración de Cuentas
                        <small>Accesos, roles y mantenimiento de cuentas existentes</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="#">Administración</a></li>
                        <li class="active">Cuentas y Accesos</li>
                    </ol>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light ">
                            <div class="portlet-title">
                                <div class="caption">
                                        <span class="caption-subject font-dark bold">Cuentas y Accesos del Sistema</span>
                                        <span class="caption-helper">Consola global para administrar cuentas ya existentes, roles y estado operativo</span>
                                </div>
                                <div class="actions">
                                        <label for="filter-kind-users" class="sr-only">Filtrar por dominio asociado</label>
                                        <select id="filter-kind-users" class="form-control input-sm" style="width:auto; display:inline-block;">
                                        <option value="">Todos</option>
                                            <option value="medical">Con vínculo a prestador médico</option>
                                            <option value="partner">Con vínculo a proveedor complementario</option>
                                            <option value="sin">Sin vínculo empresarial</option>
                                    </select>
                                </div>
                            </div>
                            <div class="portlet-body">
                                    <div class="alert alert-info">
                                        <strong>Alcance del módulo:</strong> esta pantalla administra <strong>cuentas ya existentes</strong> del sistema, sus <strong>roles</strong>, su <strong>estado</strong> y su <strong>scope de acceso</strong>.
                                        <br>
                                        <span class="small">Aquí pueden aparecer cuentas administrativas globales, cuentas asociadas a prestadores médicos, cuentas vinculadas a proveedores complementarios y otros usuarios internos existentes.</span>
                                    </div>
                                    <div class="alert alert-warning">
                                        <strong>Esta consola no reemplaza otros flujos:</strong> el onboarding médico se gestiona en <strong>Prestadores Médicos</strong>, el alta manual de nuevas cuentas en <strong>Crear Usuarios</strong>, el equipo asistencial en <strong>Staff médico</strong> y el perfil propio en <strong>Mi Perfil</strong>.
                                        <br>
                                        <span class="small">Usa esta pantalla para mantenimiento transversal de cuentas ya creadas. Usa los módulos anteriores cuando lo que cambia es la entidad de negocio o el flujo de alta.</span>
                                    </div>
                                    <div class="row" style="margin-bottom:15px;">
                                        <div class="col-md-8">
                                            <p class="text-muted" style="margin:0; max-width:860px;">
                                                El filtro de la derecha ayuda a revisar el <strong>dominio asociado</strong> de la cuenta, pero esta pantalla no se limita a providers. El recurso principal sigue siendo la <strong>cuenta de acceso</strong> dentro del sistema.
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <span class="text-muted small">Filtro por dominio asociado</span>
                                        </div>
                                    </div>
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
                                <?php if ($can_manage_users): ?>
                                <div id="users-edit-btn-template" class="hide">
                                    <button type="button" class="btn btn-xs btn-primary btn-user-edit edit-user" data-id="" style="margin-right:6px;">Editar cuenta</button>
                                </div>
                                <?php endif; ?>
                                <?php if ($is_admin): ?>
                                <div id="users-reset-btn-template" class="hide">
                                    <button type="button" class="btn btn-xs btn-warning btn-user-reset-pass" data-id="" style="margin-right:6px;">Restablecer acceso</button>
                                </div>
                                <?php endif; ?>
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
                    <h4 class="modal-title">Editar cuenta existente</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="margin-bottom:15px;">
                        Aquí editas la <strong>cuenta de acceso</strong> del sistema. Si necesitas crear una cuenta nueva, usar onboarding de prestador o gestionar staff/perfil propio, debes hacerlo desde su módulo correspondiente.
                    </div>
                    <form id="user-edit-form">
                        <input type="hidden" id="edit-id" name="id">
                        <div class="form-group">
                            <label for="edit-email">Email</label>
                            <input type="email" class="form-control" id="edit-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-usuario">Usuario de acceso</label>
                            <input type="text" class="form-control" id="edit-usuario" name="usuario" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-role-id">Rol</label>
                            <select class="form-control" id="edit-role-id" name="role_id" required></select>
                            <span class="help-block">Cambia aquí el alcance de la cuenta dentro del sistema. El rol no sustituye el onboarding ni la entidad de negocio asociada.</span>
                        </div>
                        <div class="form-group" id="edit-provider-group" style="display:none;">
                            <label for="edit-provider-id">Prestador médico</label>
                            <select class="form-control" id="edit-provider-id" name="provider_id"></select>
                            <span class="help-block">Usa este campo solo para cuentas ya existentes que deban quedar asociadas a un prestador médico.</span>
                        </div>
                        <div class="form-group" id="edit-service-provider-group" style="display:none;">
                            <label for="edit-service-provider-id">Proveedor complementario</label>
                            <select class="form-control" id="edit-service-provider-id" name="service_provider_id"></select>
                            <span class="help-block">Usa este campo solo para cuentas ya existentes del dominio complementario.</span>
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
            isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
            complementaryRoleId: <?php echo ROLE_COMPLEMENTARY_ADMIN; ?>,
            providerRoleId: <?php echo ROLE_PROVIDER; ?>,
            providerAdminRoleId: <?php echo ROLE_PROVIDER_ADMIN; ?>,
            adminRoleId: <?php echo ROLE_ADMIN; ?>,
            administrativeRoleId: <?php echo ROLE_ADMINISTRATIVE; ?>
        };
    </script>
    <!-- Root cause note: force-refresh usuarios.js to avoid stale cached legacy script (inline-only actions). -->
    <script src="/admin/js/usuarios.js?v=<?php echo @filemtime(__DIR__ . '/js/usuarios.js'); ?>"></script>
</body>
</html>

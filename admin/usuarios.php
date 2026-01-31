<?php
include('include/include.php');
if (!user_can('users.view')) {
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

    <?php echo $theme_layout_script; ?>
    <script>
        window.USERS_CTX = {
            canEdit: <?php echo user_can('users.edit') ? 'true' : 'false'; ?>
        };
    </script>
    <script src="js/usuarios.js"></script>
</body>
</html>

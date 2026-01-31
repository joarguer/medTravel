<?php
include("include/include.php");
// simple admin roles management page
if (!is_role_admin_session()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - Roles</title>
    <?php echo $global_first_style; echo $theme_global_style; echo $theme_layout_style; ?>
    <style>.table-fixed { table-layout: fixed; }</style>
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
                    <h1>Roles
                        <small>Gestión de roles de usuario</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li class="active">Roles</li>
                    </ol>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light ">
                            <div class="portlet-title">
                                <div class="caption">
                                    <span class="caption-subject font-dark bold">Lista de Roles</span>
                                </div>
                                <div class="actions">
                                    <a href="javascript:;" class="btn btn-primary" id="btn-new-role"><i class="fa fa-plus"></i> Nuevo Role</a>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <table class="table table-striped table-fixed" id="roles-table">
                                    <thead>
                                        <tr>
                                            <th style="width:80px">ID</th>
                                            <th style="width:160px">Slug</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th style="width:140px">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- loaded by AJAX -->
                                    </tbody>
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

<!-- Modal -->
<div id="roleModal" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Role</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="role-form">
            <input type="hidden" name="id" id="role-id" />
            <div class="form-group">
                <label>Slug</label>
                <input class="form-control" name="slug" id="role-slug" required />
            </div>
            <div class="form-group">
                <label>Nombre</label>
                <input class="form-control" name="name" id="role-name" required />
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea class="form-control" name="description" id="role-desc"></textarea>
            </div>
            <div class="form-group">
                <label>Permisos</label>
                <div id="role-permissions" class="row"></div>
                <small class="text-muted">Selecciona las acciones permitidas para este rol.</small>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="save-role">Guardar</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?php echo $theme_layout_script; ?>
<script>
var PERMISSIONS = [];

function renderPermissions(selected){
    var container = $('#role-permissions');
    container.empty();
    var selSet = {};
    (selected || []).forEach(function(id){ selSet[id] = true; });
    PERMISSIONS.forEach(function(p){
        var col = $('<div class="col-sm-6">');
        var id = 'perm-'+p.id;
        var cb = $('<div class="form-check mb-1">')
            .append('<input class="form-check-input perm-checkbox" type="checkbox" id="'+id+'" value="'+p.id+'" '+(selSet[p.id]?'checked':'')+' />')
            .append('<label class="form-check-label" for="'+id+'">'+p.name+' <small class="text-muted">'+(p.slug)+'</small></label>');
        col.append(cb);
        container.append(col);
    });
}

function loadPermissionsCatalog(cb){
    if(PERMISSIONS.length){ if(cb) cb(); return; }
    $.get('ajax/roles.php',{action:'list_permissions'}, function(res){
        PERMISSIONS = res && res.data ? res.data : [];
        if(cb) cb();
    },'json');
}

function fetchRoles(){
    $.get('ajax/roles.php',{action:'list'}, function(res){
        if(!res || !res.data) return;
        var tbody = $('#roles-table tbody').empty();
        res.data.forEach(function(r){
            var row = $('<tr>');
            row.append($('<td>').text(r.id));
            row.append($('<td>').text(r.slug));
            row.append($('<td>').text(r.name));
            row.append($('<td>').text(r.description));
            var actions = $('<td>');
            actions.append('<a href="javascript:;" class="btn btn-sm btn-info me-1 edit-role" data-id="'+r.id+'">Editar</a>');
            actions.append('<a href="javascript:;" class="btn btn-sm btn-danger delete-role" data-id="'+r.id+'">Borrar</a>');
            row.append(actions);
            tbody.append(row);
        });
    },'json');
}

$(function(){
    loadPermissionsCatalog();
    fetchRoles();
    $('#btn-new-role').on('click', function(){
        $('#role-form')[0].reset(); $('#role-id').val('');
        loadPermissionsCatalog(function(){ renderPermissions([]); $('#roleModal').modal('show'); });
    });

    $(document).on('click', '.edit-role', function(){
        var id = $(this).data('id');
        $.get('ajax/roles.php',{action:'get', id:id}, function(res){
            if(res && res.data){
                $('#role-id').val(res.data.id);
                $('#role-slug').val(res.data.slug);
                $('#role-name').val(res.data.name);
                $('#role-desc').val(res.data.description);
                loadPermissionsCatalog(function(){
                    $.get('ajax/roles.php',{action:'role_permissions', role_id:id}, function(rp){
                        var sel = rp && rp.permission_ids ? rp.permission_ids : [];
                        renderPermissions(sel);
                        $('#roleModal').modal('show');
                    },'json');
                });
            }
        },'json');
    });

    $('#save-role').on('click', function(){
        var form = $('#role-form').serializeArray();
        var payload = {};
        form.forEach(function(f){ payload[f.name]=f.value; });
        var action = payload.id ? 'update' : 'create';
        payload.action = action;
        var perms = [];
        $('.perm-checkbox:checked').each(function(){ perms.push($(this).val()); });
        payload.permissions = perms.join(',');
        $.post('ajax/roles.php', payload, function(res){
            if(res && res.success){
                var rid = payload.id || res.id;
                if(rid){
                    $.post('ajax/roles.php', {action:'save_permissions', role_id: rid, permissions: perms.join(',')}, function(rp){
                        if(rp && rp.success){ $('#roleModal').modal('hide'); fetchRoles(); }
                        else alert(rp && rp.error ? rp.error : 'Error guardando permisos');
                    },'json');
                } else {
                    $('#roleModal').modal('hide'); fetchRoles();
                }
            }
            else alert(res && res.error ? res.error : 'Error');
        },'json');
    });

    $(document).on('click', '.delete-role', function(){
        if(!confirm('Borrar este rol?')) return;
        var id = $(this).data('id');
        $.post('ajax/roles.php',{action:'delete', id:id}, function(res){
            if(res && res.success) fetchRoles(); else alert(res && res.error?res.error:'Error');
        },'json');
    });
});
</script>
</body>
</html>

<?php
include("include/include.php");
require_once __DIR__ . '/include/roles.php';
$is_admin = is_role_admin_session();
$provider_id = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : null;
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>medTravel - Blog Management</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <?php echo $global_first_style;?>
        <?php echo $theme_global_style;?>
        <link href="../../assets/global/plugins/bootstrap-summernote/summernote.css" rel="stylesheet" type="text/css" />
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
                        <h1>Blog</h1>
                        <ol class="breadcrumb">
                            <li><a href="#">Site</a></li>
                            <li class="active">Blog (blog.php)</li>
                        </ol>
                    </div>
                    <div class="page-content-container">
                        <div class="page-content-row">
                            <div class="page-sidebar">
                                <nav class="navbar" role="navigation">
                                    <ul class="nav navbar-nav">
                                        <li class="active"><a href="blog_edit.php"><i class="icon-note"></i> Entradas</a></li>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                                <div class="portlet light">
                                    <div class="portlet-title">
                                        <div class="caption caption-md">
                                            <span class="caption-subject font-blue-madison bold uppercase">Entradas del Blog</span>
                                            <span class="caption-helper"><?php echo $is_admin ? 'Admin principal' : 'Proveedor'; ?></span>
                                        </div>
                                        <div class="actions">
                                            <a class="btn btn-primary" id="btn-new"><i class="fa fa-plus"></i> Nueva entrada</a>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <table class="table table-striped table-bordered" id="tbl-posts">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Título</th>
                                                    <th>Estado</th>
                                                    <th>Autor / Prestador</th>
                                                    <th>Creado</th>
                                                    <th>Publicado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="portlet light">
                                    <div class="portlet-title">
                                        <div class="caption caption-md">
                                            <span class="caption-subject font-blue-madison bold uppercase" id="form-title">Nueva entrada</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <form id="post-form">
                                            <input type="hidden" name="id" id="post-id" value="">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="form-group">
                                                        <label>Título</label>
                                                        <input type="text" class="form-control" name="title" id="title" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Estado</label>
                                                        <select class="form-control" name="status" id="status">
                                                            <option value="draft">Borrador</option>
                                                            <option value="published">Publicado</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Slug (opcional)</label>
                                                <input type="text" class="form-control" name="slug" id="slug" placeholder="auto-generado si se deja vacío">
                                            </div>
                                            <div class="form-group">
                                                <label>Extracto</label>
                                                <textarea class="form-control" name="excerpt" id="excerpt" rows="3"></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label>Contenido</label>
                                                <textarea class="form-control summernote" name="body" id="body" rows="8"></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="form-group">
                                                        <label>Autor (visible)</label>
                                                        <input type="text" class="form-control" name="author_name" id="author_name" value="<?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'MedTravel', ENT_QUOTES, 'UTF-8'); ?>">
                                                    </div>
                                                </div>
                                                <?php if ($is_admin): ?>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Provider ID (opcional)</label>
                                                        <input type="number" class="form-control" name="provider_id" id="provider_id" placeholder="Asociar a prestador">
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="form-group">
                                                <label>Imagen de portada</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="cover_image" id="cover_image" placeholder="Sube una imagen o pega URL">
                                                    <span class="input-group-btn">
                                                        <span class="btn btn-default btn-file">
                                                            Subir <input type="file" id="file_cover">
                                                        </span>
                                                    </span>
                                                </div>
                                                <small class="text-muted">Formatos: jpg, png, gif, webp</small>
                                                <div id="cover_preview" style="margin-top:10px; display:none;">
                                                    <img src="" alt="cover" class="img-responsive" style="max-height:180px;">
                                                </div>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                                                <button type="button" class="btn btn-default" id="btn-reset">Limpiar</button>
                                            </div>
                                        </form>
                                    </div>
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
        <script src="../../assets/global/plugins/bootstrap-summernote/summernote.min.js" type="text/javascript"></script>
        <script>
            var $tbl = $('#tbl-posts tbody');
            var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

            function loadPosts(){
                $.post('ajax/blog_posts.php', {tipo:'list'}, function(res){
                    $tbl.empty();
                    if(res.status === 'ok' && res.posts){
                        res.posts.forEach(function(p){
                            var published = p.published_at ? p.published_at : '-';
                            var provider = p.provider_name ? p.provider_name : '';
                            var statusClass = (p.status === 'published') ? 'label-success' : 'label-default';
                            var row = '' +
                                '<tr>' +
                                    '<td>' + p.id + '</td>' +
                                    '<td>' + p.title + '</td>' +
                                    '<td><span class="label ' + statusClass + '">' + p.status + '</span></td>' +
                                    '<td>' + provider + '</td>' +
                                    '<td>' + p.created_at + '</td>' +
                                    '<td>' + published + '</td>' +
                                    '<td>' +
                                        '<button class="btn btn-xs btn-info btn-edit" data-id="' + p.id + '"><i class="fa fa-edit"></i></button> ' +
                                        '<button class="btn btn-xs btn-danger btn-del" data-id="' + p.id + '"><i class="fa fa-trash"></i></button>' +
                                    '</td>' +
                                '</tr>';
                            $tbl.append(row);
                        });
                    }
                }, 'json');
            }

            function resetForm(){
                $('#post-id').val('');
                $('#title').val('');
                $('#slug').val('');
                $('#excerpt').val('');
                $('#body').summernote('code', '');
                $('#status').val('draft');
                $('#cover_image').val('');
                $('#cover_preview').hide();
                $('#form-title').text('Nueva entrada');
            }

            $(function(){
                $('.summernote').summernote({height: 250});
                loadPosts();

                $('#btn-new').on('click', resetForm);
                $('#btn-reset').on('click', resetForm);

                $tbl.on('click', '.btn-edit', function(){
                    var id = $(this).data('id');
                    $.post('ajax/blog_posts.php', {tipo:'get', id:id}, function(res){
                        if(res.status === 'ok'){
                            var p = res.post;
                            $('#form-title').text('Editar entrada #' + p.id);
                            $('#post-id').val(p.id);
                            $('#title').val(p.title);
                            $('#slug').val(p.slug);
                            $('#excerpt').val(p.excerpt);
                            $('#body').summernote('code', p.body);
                            $('#status').val(p.status);
                            $('#author_name').val(p.author_name);
                            $('#provider_id').val(p.provider_id);
                            $('#cover_image').val(p.cover_image);
                            if(p.cover_image){
                                $('#cover_preview img').attr('src', '../' + p.cover_image.replace(/^\/+/, ''));
                                $('#cover_preview').show();
                            } else {
                                $('#cover_preview').hide();
                            }
                        } else {
                            alert(res.message || 'Error');
                        }
                    }, 'json');
                });

                $tbl.on('click', '.btn-del', function(){
                    if(!confirm('¿Eliminar entrada?')) return;
                    var id = $(this).data('id');
                    $.post('ajax/blog_posts.php', {tipo:'delete', id:id}, function(res){
                        if(res.status === 'ok') { loadPosts(); } else { alert(res.message || 'Error'); }
                    }, 'json');
                });

                $('#file_cover').on('change', function(){
                    var file = this.files[0];
                    if(!file) return;
                    var formData = new FormData();
                    formData.append('tipo','upload_cover');
                    var currentId = $('#post-id').val();
                    if(currentId) formData.append('post_id', currentId);
                    formData.append('file', file);
                    $.ajax({
                        url:'ajax/blog_posts.php',
                        type:'POST',
                        data: formData,
                        contentType:false,
                        processData:false,
                        dataType:'json',
                        success:function(res){
                            if(res.status === 'ok'){
                                $('#cover_image').val(res.path);
                                $('#cover_preview img').attr('src','../'+res.path);
                                $('#cover_preview').show();
                            } else {
                                alert(res.message || 'Error al subir');
                            }
                        }
                    });
                });

                $('#post-form').on('submit', function(e){
                    e.preventDefault();
                    var payload = {};
                    $(this).serializeArray().forEach(function(cur){
                        payload[cur.name] = cur.value;
                    });
                    payload.tipo = 'save';
                    $.post('ajax/blog_posts.php', payload, function(res){
                        if(res.status === 'ok'){
                            alert('Guardado');
                            resetForm();
                            loadPosts();
                        } else {
                            alert(res.message || 'Error');
                        }
                    }, 'json');
                });
            });
        </script>
    </body>
</html>

<?php
include("include/include.php");
require_once __DIR__ . '/include/roles.php';
$is_admin = is_role_admin_session();
$can_manage_all_posts = $is_admin || user_can(PERM_CONTENT_MANAGE);
$provider_id = isset($_SESSION['provider_id']) ? intval($_SESSION['provider_id']) : null;
$provider_scope_name = '';
$provider_options = [];
$default_admin_author_name = 'MedTravel Editorial Team';
$default_provider_author_name = trim((string)($_SESSION['nombre_usuario'] ?? ''));

if (!$can_manage_all_posts && $provider_id && isset($conexion) && $conexion) {
    $stmt = mysqli_prepare($conexion, "SELECT name FROM providers WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $provider_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $provider_scope_name);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
}

if ($default_provider_author_name === '') {
    $default_provider_author_name = $provider_scope_name !== '' ? $provider_scope_name : 'Specialist Contributor';
}

if ($can_manage_all_posts && isset($conexion) && $conexion) {
    $providers_query = mysqli_query($conexion, "SELECT id, name, city FROM providers ORDER BY name ASC");
    if ($providers_query) {
        while ($provider_row = mysqli_fetch_assoc($providers_query)) {
            $provider_options[] = $provider_row;
        }
        mysqli_free_result($providers_query);
    }
}
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
        <link href="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css" rel="stylesheet" type="text/css" />
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
                                        <li class="active" id="nav-posts"><a href="javascript:;" id="btn-nav-posts"><i class="icon-note"></i> Entradas</a></li>
                                        <?php if ($can_manage_all_posts): ?>
                                        <li id="nav-blog-header"><a href="javascript:;" id="btn-nav-blog-header"><i class="icon-picture"></i> Header del Blog</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <div class="page-content-col">
                                <div id="posts-section">
                                <div class="portlet light">
                                    <div class="portlet-title">
                                        <div class="caption caption-md">
                                            <span class="caption-subject font-blue-madison bold uppercase">Entradas del Blog</span>
                                            <span class="caption-helper"><?php echo $can_manage_all_posts ? 'Admin principal' : 'Proveedor'; ?><?php echo (!$can_manage_all_posts && $provider_scope_name !== '') ? ' · ' . htmlspecialchars($provider_scope_name, ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                        </div>
                                        <div class="actions">
                                            <a class="btn btn-primary" id="btn-new"><i class="fa fa-plus"></i> Nueva entrada</a>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <?php if (!$can_manage_all_posts && !$provider_id): ?>
                                        <div class="alert alert-danger">Este usuario no está asignado a un prestador médico.</div>
                                        <?php endif; ?>
                                        <table class="table table-striped table-bordered" id="tbl-posts">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Título</th>
                                                    <th>Estado</th>
                                                    <th>Autor / contribuidor</th>
                                                    <th>Creado</th>
                                                    <th>Publicado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="portlet light" id="post-form-portlet" style="display:none;">
                                    <div class="portlet-title">
                                        <div class="caption caption-md">
                                            <span class="caption-subject font-blue-madison bold uppercase" id="form-title">Nueva entrada</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <form id="post-form">
                                            <input type="hidden" name="id" id="post-id" value="">
                                            <input type="hidden" name="author_user_id" id="author_user_id" value="">
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
                                                        <label>Autor visible</label>
                                                        <input type="text" class="form-control" name="author_name" id="author_name" value="<?php echo htmlspecialchars($can_manage_all_posts ? $default_admin_author_name : $default_provider_author_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $can_manage_all_posts ? '' : ' readonly'; ?>>
                                                        <small class="text-muted">
                                                            <?php if ($can_manage_all_posts): ?>
                                                            Firma editorial visible del artículo. Si no se cambia, se publicará como MedTravel Editorial Team.
                                                            <?php else: ?>
                                                            La firma visible del prestador se normaliza automáticamente para mantener coherencia editorial.
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <?php if ($can_manage_all_posts): ?>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Contribuidor médico (opcional)</label>
                                                        <select class="form-control" name="provider_id" id="provider_id">
                                                            <option value="">Sin contribuidor médico</option>
                                                            <?php foreach ($provider_options as $provider_option): ?>
                                                            <option value="<?php echo (int)$provider_option['id']; ?>">
                                                                <?php
                                                                    $provider_option_label = trim((string)($provider_option['name'] ?? ''));
                                                                    $provider_option_city = trim((string)($provider_option['city'] ?? ''));
                                                                    if ($provider_option_city !== '') {
                                                                        $provider_option_label .= ' · ' . $provider_option_city;
                                                                    }
                                                                    echo htmlspecialchars($provider_option_label, ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="text-muted">El blog sigue siendo de MedTravel. Este campo solo añade afiliación o contribución médica pública.</small>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label">Imagen de portada</label>
                                                <input type="hidden" name="cover_image" id="cover_image">
                                                <!-- Preview imagen guardada en servidor -->
                                                <div id="cover_preview" style="display:none; margin-bottom:10px;">
                                                    <div style="position:relative; display:inline-block;">
                                                        <img src="" alt="Portada actual" id="cover_preview_img" class="img-thumbnail" style="max-height:140px; max-width:280px; border-radius:4px;">
                                                        <span style="position:absolute;top:4px;left:4px;background:#26C281;color:#fff;padding:2px 7px;font-size:10px;border-radius:3px;font-weight:600;"><i class="fa fa-check"></i> Guardada</span>
                                                    </div>
                                                </div>
                                                <!-- Widget Metronic fileinput -->
                                                <div class="fileinput fileinput-new" data-provides="fileinput" id="cover-fileinput">
                                                    <div class="fileinput-new thumbnail" style="width:200px; height:130px; display:table-cell; vertical-align:middle; text-align:center; background:#fafafa; border:2px dashed #d1d1d1; border-radius:4px; color:#aaa;">
                                                        <div><i class="fa fa-camera fa-2x"></i><br><small style="font-size:11px;">Sin imagen nueva</small></div>
                                                    </div>
                                                    <div class="fileinput-preview fileinput-exists thumbnail" style="max-width:200px; max-height:130px;"></div>
                                                    <div style="margin-top:8px;">
                                                        <span class="btn btn-default btn-sm btn-file">
                                                            <span class="fileinput-new"><i class="fa fa-upload"></i> Subir imagen</span>
                                                            <span class="fileinput-exists"><i class="fa fa-refresh"></i> Cambiar</span>
                                                            <input type="file" id="file_cover" accept="image/jpeg,image/png,image/gif,image/webp">
                                                        </span>
                                                        <a href="javascript:;" class="btn btn-default btn-sm fileinput-exists" data-dismiss="fileinput"><i class="fa fa-times"></i> Cancelar</a>
                                                        <span id="cover-uploading" style="display:none; margin-left:8px; color:#888;"><i class="fa fa-spinner fa-spin"></i> Subiendo...</span>
                                                    </div>
                                                </div>
                                                <p class="help-block" style="margin-top:6px;"><small>Formatos: jpg, png, gif, webp. Tamaño recomendado: 1200&times;630 px.</small></p>
                                            </div>
                                            <div class="form-group">
                                                <label>Video / Post URL</label>
                                                <input type="url" class="form-control" name="video_url" id="video_url" placeholder="https://www.youtube.com/watch?v=... o https://vimeo.com/... o https://www.instagram.com/p/...">
                                                <small class="text-muted">Accepts public YouTube, Vimeo, or Instagram links (post or reel).</small>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label">Video MP4 subido</label>
                                                <input type="hidden" name="video_file" id="video_file">
                                                <!-- Preview video guardado -->
                                                <div id="video_file_preview" style="display:none; margin-bottom:8px;">
                                                    <div class="alert alert-info" style="padding:8px 12px; border-radius:4px; margin:0; display:flex; align-items:center; justify-content:space-between;">
                                                        <span><i class="fa fa-film"></i> &nbsp;<strong>Video guardado:</strong> &nbsp;<a href="#" id="video_file_link" target="_blank" rel="noopener" class="alert-link" id="video_file_link"><span id="video_file_basename">ver archivo</span></a></span>
                                                        <button type="button" class="btn btn-xs btn-danger" id="btn-remove-video" style="margin-left:10px; flex-shrink:0;"><i class="fa fa-times"></i> Quitar</button>
                                                    </div>
                                                </div>
                                                <!-- Input estilizado -->
                                                <div class="input-group">
                                                    <span class="input-group-addon" style="background:#fafafa;"><i class="fa fa-film" style="color:#888;"></i></span>
                                                    <input type="text" class="form-control" id="video_file_display" placeholder="Sin video subido" readonly style="cursor:default; background:#fff;">
                                                    <span class="input-group-btn">
                                                        <label class="btn btn-default" for="file_video" style="margin:0; cursor:pointer; font-weight:normal; border-left:none;">
                                                            <i class="fa fa-upload"></i> Subir MP4
                                                        </label>
                                                        <input type="file" id="file_video" accept="video/mp4,.mp4" style="position:absolute; width:0; height:0; opacity:0; overflow:hidden;">
                                                    </span>
                                                </div>
                                                <!-- Barra de progreso -->
                                                <div id="video-upload-progress" style="display:none; margin-top:6px;">
                                                    <div class="progress" style="margin:0; height:20px; border-radius:3px;">
                                                        <div class="progress-bar progress-bar-striped active" role="progressbar" style="width:100%; line-height:20px; font-size:11px;">
                                                            <i class="fa fa-spinner fa-spin"></i> Subiendo video MP4...
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="help-block" style="margin-top:6px;"><small>Opcional. Solo MP4 hasta 25MB. Si existe, este video local tendrá prioridad sobre el enlace externo.</small></p>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                                                <button type="button" class="btn btn-default" id="btn-reset">Limpiar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                </div>

                                <?php if ($can_manage_all_posts): ?>
                                <div id="blog-header-section" style="display:none;">
                                    <div class="portlet light">
                                        <div class="portlet-title">
                                            <div class="caption caption-md">
                                                <span class="caption-subject font-blue-madison bold uppercase">Header Público del Blog</span>
                                                <span class="caption-helper">Visible en `blog.php` y `blog_post.php`</span>
                                            </div>
                                        </div>
                                        <div class="portlet-body">
                                            <form id="blog-header-form">
                                                <input type="hidden" id="blog_header_id" value="">
                                                <input type="hidden" id="blog_header_bg_image" value="">

                                                <div id="blog-header-preview" style="min-height:260px; border-radius:10px; margin-bottom:25px; padding:50px 30px; background:linear-gradient(135deg,#1e3c72 0%,#2a5298 100%); background-size:cover; background-position:center; display:flex; align-items:center; justify-content:center; text-align:center; position:relative; overflow:hidden;">
                                                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.35);"></div>
                                                    <div style="position:relative; z-index:1; max-width:760px;">
                                                        <div id="blog-header-preview-subtitle" style="color:#dbeafe; font-size:16px; margin-bottom:12px;">Discover experiences and updates from our medical travel community.</div>
                                                        <h1 id="blog-header-preview-title" style="color:#fff; font-size:38px; line-height:1.2; margin:0;">Our Blog</h1>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Título del Header</label>
                                                    <input type="text" class="form-control" id="blog_header_title" maxlength="255" placeholder="Our Blog">
                                                </div>
                                                <div class="form-group">
                                                    <label>Texto descriptivo</label>
                                                    <textarea class="form-control" rows="3" id="blog_header_subtitle" placeholder="Discover experiences and updates from our medical travel community."></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label">Imagen del Header</label>
                                                    <div id="blog-header-image-preview" style="display:none; margin-bottom:10px;">
                                                        <img id="blog-header-image-preview-img" src="" alt="Header del blog" class="img-responsive" style="max-height:180px; border-radius:8px;">
                                                    </div>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="blog_header_bg_image_display" placeholder="Sin imagen personalizada" readonly>
                                                        <span class="input-group-btn">
                                                            <label class="btn btn-default" for="blog_header_image_file" style="margin:0;">
                                                                <i class="fa fa-upload"></i> Subir imagen
                                                            </label>
                                                            <input type="file" id="blog_header_image_file" accept="image/jpeg,image/png,image/gif,image/webp" style="position:absolute; width:0; height:0; opacity:0; overflow:hidden;">
                                                        </span>
                                                    </div>
                                                    <div id="blog-header-image-uploading" style="display:none; margin-top:8px; color:#888;">
                                                        <i class="fa fa-spinner fa-spin"></i> Subiendo imagen del header...
                                                    </div>
                                                    <p class="help-block"><small>Opcional. JPG, PNG, GIF o WEBP hasta 5MB.</small></p>
                                                </div>
                                                <div class="form-actions">
                                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar Header</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

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
        <script src="../../assets/global/plugins/bootstrap-fileinput/bootstrap-fileinput.js" type="text/javascript"></script>
        <script>
            var $tbl = $('#tbl-posts tbody');
            var $postsSection = $('#posts-section');
            var $formPortlet = $('#post-form-portlet');
            var $navPosts = $('#nav-posts');
            var $navBlogHeader = $('#nav-blog-header');
            var isAdmin = <?php echo $can_manage_all_posts ? 'true' : 'false'; ?>;
            var defaultAuthorName = <?php echo json_encode($can_manage_all_posts ? $default_admin_author_name : $default_provider_author_name); ?>;

            function blogToast(type, message, title) {
                if (typeof toastr === 'undefined') {
                    return;
                }

                toastr.options = {
                    closeButton: true,
                    debug: false,
                    positionClass: 'toast-top-right',
                    onclick: null,
                    showDuration: '300',
                    hideDuration: '300',
                    timeOut: '4000',
                    extendedTimeOut: '1000',
                    showEasing: 'swing',
                    hideEasing: 'linear',
                    showMethod: 'fadeIn',
                    hideMethod: 'fadeOut'
                };

                var toastTitle = title || 'Blog';
                if (typeof toastr[type] === 'function') {
                    toastr[type](message, toastTitle);
                } else {
                    toastr.info(message, toastTitle);
                }
            }

            function loadPosts(){
                $.post('ajax/blog_posts.php', {tipo:'list'}, function(res){
                    $tbl.empty();
                    if(res.status === 'ok' && res.posts){
                        res.posts.forEach(function(p){
                            var published = p.published_at ? p.published_at : '-';
                            var author = p.author_name ? p.author_name : 'MedTravel Editorial Team';
                            var provider = p.provider_name ? '<br><small class="text-muted">Contributor: ' + p.provider_name + '</small>' : '';
                            var statusClass = (p.status === 'published') ? 'label-success' : 'label-default';
                            var row = '' +
                                '<tr>' +
                                    '<td>' + p.id + '</td>' +
                                    '<td>' + p.title + '</td>' +
                                    '<td><span class="label ' + statusClass + '">' + p.status + '</span></td>' +
                                    '<td>' + author + provider + '</td>' +
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
                $('#provider_id').val('');
                $('#author_user_id').val('');
                $('#author_name').val(defaultAuthorName);
                $('#cover_image').val('');
                $('#video_url').val('');
                $('#video_file').val('');
                $('#video_file_display').val('');
                $('#cover_preview').hide();
                $('#cover_preview_img').attr('src', '');
                $('#video_file_preview').hide();
                $('#video-upload-progress').hide();
                $('#cover-uploading').hide();
                if ($('#cover-fileinput').data('fileinput')) {
                    $('#cover-fileinput').fileinput('clear');
                }
                $('#form-title').text('Nueva entrada');
            }

            function openForm(){
                openPostsSection(false);
                if(!$formPortlet.is(':visible')){
                    $formPortlet.stop(true, true).slideDown(180);
                }
                $('html, body').animate({ scrollTop: $formPortlet.offset().top - 20 }, 220);
            }

            function closeForm(){
                $formPortlet.stop(true, true).slideUp(180);
            }

            function setActiveNav(section){
                $navPosts.removeClass('active');
                $navBlogHeader.removeClass('active');
                if(section === 'header'){
                    $navBlogHeader.addClass('active');
                } else {
                    $navPosts.addClass('active');
                }
            }

            function openPostsSection(closeEditor){
                setActiveNav('posts');
                if(!$postsSection.is(':visible')){
                    $postsSection.show();
                }
                $('#blog-header-section').hide();
                if(closeEditor !== false){
                    closeForm();
                }
            }

            function renderBlogHeaderPreview(data){
                var title = $.trim(data.title || '') || 'Our Blog';
                var subtitle = $.trim(data.subtitle || '') || 'Discover experiences and updates from our medical travel community.';
                var bgImage = $.trim(data.bg_image || '');
                $('#blog-header-preview-title').text(title);
                $('#blog-header-preview-subtitle').text(subtitle);
                if(bgImage){
                    $('#blog-header-preview').css('background-image', 'linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url(../' + bgImage.replace(/^\/+/, '') + ')');
                    $('#blog-header-image-preview-img').attr('src', '../' + bgImage.replace(/^\/+/, ''));
                    $('#blog-header-image-preview').show();
                    $('#blog_header_bg_image_display').val(bgImage.split('/').pop());
                } else {
                    $('#blog-header-preview').css('background-image', 'linear-gradient(135deg,#1e3c72 0%,#2a5298 100%)');
                    $('#blog-header-image-preview').hide();
                    $('#blog-header-image-preview-img').attr('src', '');
                    $('#blog_header_bg_image_display').val('');
                }
            }

            function fillBlogHeaderForm(header){
                $('#blog_header_id').val(header.id || '');
                $('#blog_header_title').val(header.title || '');
                $('#blog_header_subtitle').val(header.subtitle || '');
                $('#blog_header_bg_image').val(header.bg_image || '');
                renderBlogHeaderPreview(header || {});
            }

            function loadBlogHeader(){
                $.post('ajax/blog_header.php', {tipo:'get_header'}, function(res){
                    if(res.status === 'ok' && res.header){
                        fillBlogHeaderForm(res.header);
                    } else {
                        blogToast('error', res.message || 'No se pudo cargar el header del blog.', 'Error');
                    }
                }, 'json').fail(function(){
                    blogToast('error', 'No se pudo cargar el header del blog.', 'Error');
                });
            }

            function openBlogHeaderSection(){
                if(!isAdmin){
                    return;
                }
                setActiveNav('header');
                closeForm();
                $postsSection.hide();
                $('#blog-header-section').show();
                loadBlogHeader();
            }

            $(function(){
                $('.summernote').summernote({height: 250});
                loadPosts();
                openPostsSection(true);

                $('#btn-new').on('click', function(){
                    resetForm();
                    openForm();
                });

                $('#btn-nav-posts').on('click', function(){
                    openPostsSection(true);
                });

                $('#btn-nav-blog-header').on('click', function(){
                    openBlogHeaderSection();
                });

                $('#btn-reset').on('click', function(){
                    resetForm();
                    closeForm();
                });

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
                            $('#author_name').val(isAdmin ? p.author_name : defaultAuthorName);
                            $('#provider_id').val(p.provider_id);
                            $('#author_user_id').val(p.author_user_id || '');
                            $('#cover_image').val(p.cover_image);
                            $('#video_url').val(p.video_url || '');
                            $('#video_file').val(p.video_file || '');
                            if(p.cover_image){
                                $('#cover_preview_img').attr('src', '../' + p.cover_image.replace(/^\/+/, ''));
                                $('#cover_preview').show();
                            } else {
                                $('#cover_preview').hide();
                            }
                            if(p.video_file){
                                var vPath = p.video_file.replace(/^\/+/, '');
                                var vBasename = vPath.split('/').pop();
                                $('#video_file_link').attr('href', '../' + vPath);
                                $('#video_file_basename').text(vBasename);
                                $('#video_file_display').val(vBasename);
                                $('#video_file_preview').show();
                            } else {
                                $('#video_file_display').val('');
                                $('#video_file_preview').hide();
                            }
                            openForm();
                        } else {
                            blogToast('error', res.message || 'No se pudo cargar la entrada.', 'Error');
                        }
                    }, 'json');
                });

                $tbl.on('click', '.btn-del', function(){
                    if(!confirm('¿Eliminar entrada?')) return;
                    var id = $(this).data('id');
                    $.post('ajax/blog_posts.php', {tipo:'delete', id:id}, function(res){
                        if(res.status === 'ok') {
                            loadPosts();
                            blogToast('success', 'La entrada fue eliminada correctamente.', 'Blog');
                        } else {
                            blogToast('error', res.message || 'No se pudo eliminar la entrada.', 'Error');
                        }
                    }, 'json');
                });

                $('#file_cover').on('change', function(){
                    var file = this.files[0];
                    if(!file) return;
                    $('#cover-uploading').show();
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
                                $('#cover_preview_img').attr('src','../'+res.path);
                                $('#cover_preview').show();
                                blogToast('success', 'La imagen de portada se subio correctamente.', 'Blog');
                            } else {
                                blogToast('error', res.message || 'No se pudo subir la imagen de portada.', 'Error');
                            }
                        },
                        error:function(){
                            blogToast('error', 'No se pudo subir la imagen de portada.', 'Error');
                        },
                        complete:function(){
                            $('#cover-uploading').hide();
                        }
                    });
                });

                $('#file_video').on('change', function(){
                    var file = this.files[0];
                    if(!file) return;
                    $('#video-upload-progress').show();
                    var formData = new FormData();
                    formData.append('tipo', 'upload_video');
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
                                var vPath = res.path.replace(/^\/+/, '');
                                var vBasename = vPath.split('/').pop();
                                $('#video_file').val(res.path);
                                $('#video_file_display').val(vBasename);
                                $('#video_file_link').attr('href', '../' + vPath);
                                $('#video_file_basename').text(vBasename);
                                $('#video_file_preview').show();
                                blogToast('success', 'El video MP4 se subio correctamente.', 'Blog');
                            } else {
                                blogToast('error', res.message || 'No se pudo subir el video MP4.', 'Error');
                            }
                        },
                        error:function(){
                            blogToast('error', 'No se pudo subir el video MP4.', 'Error');
                        },
                        complete:function(){
                            $('#video-upload-progress').hide();
                            $('#file_video').val('');
                        }
                    });
                });

                $(document).on('click', '#btn-remove-video', function(){
                    $('#video_file').val('');
                    $('#video_file_display').val('');
                    $('#video_file_preview').hide();
                });

                $('#blog_header_title, #blog_header_subtitle').on('input', function(){
                    renderBlogHeaderPreview({
                        title: $('#blog_header_title').val(),
                        subtitle: $('#blog_header_subtitle').val(),
                        bg_image: $('#blog_header_bg_image').val()
                    });
                });

                $('#blog_header_image_file').on('change', function(){
                    var file = this.files[0];
                    if(!file) return;
                    $('#blog-header-image-uploading').show();
                    var formData = new FormData();
                    formData.append('tipo', 'upload_header_image');
                    formData.append('image', file);
                    $.ajax({
                        url:'ajax/blog_header.php',
                        type:'POST',
                        data: formData,
                        processData:false,
                        contentType:false,
                        dataType:'json',
                        success:function(res){
                            if(res.status === 'ok' && res.header){
                                fillBlogHeaderForm(res.header);
                                blogToast('success', 'La imagen del header se actualizo correctamente.', 'Blog');
                            } else {
                                blogToast('error', res.message || 'No se pudo subir la imagen del header.', 'Error');
                            }
                        },
                        error:function(){
                            blogToast('error', 'No se pudo subir la imagen del header.', 'Error');
                        },
                        complete:function(){
                            $('#blog-header-image-uploading').hide();
                            $('#blog_header_image_file').val('');
                        }
                    });
                });

                $('#blog-header-form').on('submit', function(e){
                    e.preventDefault();
                    $.post('ajax/blog_header.php', {
                        tipo: 'save_header',
                        title: $('#blog_header_title').val(),
                        subtitle: $('#blog_header_subtitle').val(),
                        bg_image: $('#blog_header_bg_image').val()
                    }, function(res){
                        if(res.status === 'ok' && res.header){
                            fillBlogHeaderForm(res.header);
                            blogToast('success', 'El header del blog se guardo correctamente.', 'Blog');
                        } else {
                            blogToast('error', res.message || 'No se pudo guardar el header del blog.', 'Error');
                        }
                    }, 'json').fail(function(){
                        blogToast('error', 'No se pudo guardar el header del blog.', 'Error');
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
                            blogToast('success', 'La entrada se guardo correctamente.', 'Blog');
                            resetForm();
                            closeForm();
                            loadPosts();
                        } else {
                            blogToast('error', res.message || 'No se pudo guardar la entrada.', 'Error');
                        }
                    }, 'json').fail(function(){
                        blogToast('error', 'No se pudo guardar la entrada.', 'Error');
                    });
                });
            });
        </script>
    </body>
</html>

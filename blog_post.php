<?php
include('inc/include.php');

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$post = null;
if ($slug !== '') {
    $stmt = mysqli_prepare($conexion, "SELECT id, title, slug, excerpt, body, cover_image, author_name, DATE_FORMAT(COALESCE(published_at, created_at), '%b %e, %Y') as published_on FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $post = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$post) {
    http_response_code(404);
}

// SEO overrides if post exists
$head_override = $head;
if ($post) {
    $meta_title = htmlspecialchars($post['title'] . ' | MedTravel Blog', ENT_QUOTES, 'UTF-8');
    $teaser = $post['excerpt'] ?: strip_tags($post['body']);
    $teaser = substr($teaser, 0, 155);
    $meta_desc = htmlspecialchars($teaser, ENT_QUOTES, 'UTF-8');
    $head_override = str_replace(
        ['<title>MedTravel - Tourism and Health </title>', '<meta content="" name="description">'],
        ['<title>'.$meta_title.'</title>', '<meta content="'.$meta_desc.'" name="description">'],
        $head
    );
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php echo $head_override; ?>
    </head>
    <body>
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Navbar & Hero Start -->
        <div class="container-fluid position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
                <?php echo $logo; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <?php echo $menu; ?>
            </nav>
        </div>
        <!-- Navbar & Hero End -->

        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h3 class="text-white display-3 mb-4"><?php echo $post ? 'Blog' : 'Not Found'; ?></h3>
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="blog.php">Blog</a></li>
                    <li class="breadcrumb-item active text-white"><?php echo $post ? htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') : '404'; ?></li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->

        <div class="container py-5">
            <?php if (!$post): ?>
                <div class="text-center py-5">
                    <h1>Post not found</h1>
                    <p class="text-muted">The article you are looking for is not available.</p>
                    <a class="btn btn-primary rounded-pill px-4" href="/blog.php">Back to Blog</a>
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <?php
                            $coverPath = $post['cover_image'] ?: 'img/blog-1.jpg';
                            if (!preg_match('~^https?://~', $coverPath)) {
                                $coverPath = ltrim($coverPath, './');
                                if (strpos($coverPath, '../') === 0) {
                                    $coverPath = substr($coverPath, 3);
                                }
                                $coverPath = '/' . ltrim($coverPath, '/');
                            }
                        ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <img src="<?php echo htmlspecialchars($coverPath, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" style="object-fit: cover; max-height: 420px;">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between text-muted small mb-3 flex-column flex-sm-row">
                                    <span class="mb-2 mb-sm-0"><i class="fa fa-calendar-alt text-primary me-2"></i><?php echo htmlspecialchars($post['published_on'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><i class="fa fa-user text-primary me-2"></i><?php echo htmlspecialchars($post['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <h1 class="h3 mb-3"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                                <?php if (!empty($post['excerpt'])): ?>
                                    <p class="lead text-muted"><?php echo htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body p-4">
                                        <div class="post-body">
                                            <?php echo $post['body']; ?>
                                        </div>
                                        <div class="mt-4">
                                            <a class="btn btn-outline-primary rounded-pill px-4" href="/blog.php"><i class="fa fa-arrow-left me-2"></i>Back to Blog</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body">
                                        <h5 class="mb-3">Contact the author</h5>
                                        <p class="mb-1 text-muted"><i class="fa fa-user text-primary me-2"></i><?php echo htmlspecialchars($post['author_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <a class="btn btn-success w-100 mb-2" href="https://wa.me/573502431667" target="_blank"><i class="fab fa-whatsapp me-2"></i>WhatsApp</a>
                                        <a class="btn btn-outline-primary w-100 mb-3" href="mailto:info@medtravel.com"><i class="fa fa-envelope me-2"></i>Email</a>
                                        <h6 class="text-uppercase text-muted small mb-2">Resources</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-1"><i class="fa fa-file-alt text-primary me-2"></i><a href="/privacy/" class="text-decoration-none">Privacy Policy</a></li>
                                            <li class="mb-1"><i class="fa fa-phone text-primary me-2"></i>+561 698 8069</li>
                                            <li class="mb-1"><i class="fa fa-map-marker-alt text-primary me-2"></i>Remote coordination</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php echo $footer; ?>
        <?php echo $copyright; ?>

        <a href="#" class="btn btn-primary btn-primary-outline-0 btn-md-square back-to-top"><i class="fa fa-arrow-up"></i></a>   

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <?php echo $script; ?>
        <script src="js/main.js"></script>
    </body>
</html>

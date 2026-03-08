<?php
$page_title = 'Medical Travel Blog | MedTravel';
$page_description = 'Discover updates, patient-oriented guidance, and medical travel stories from MedTravel.';
$page_canonical = 'https://medtravel.com.co/blog.php';
include('inc/include.php'); 
require_once __DIR__ . '/inc/blog_header.php';

if (!function_exists('blog_author_avatar_href')) {
    function blog_author_avatar_href($avatar)
    {
        $normalized = trim((string)$avatar);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $normalized) || strpos($normalized, '//') === 0) {
            return $normalized;
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('~^(\.\./)+~', '', $normalized);

        if (preg_match('~^img/perfil/[^/]+$~i', $normalized)) {
            return '/admin/' . $normalized;
        }

        if (preg_match('~^admin/img/perfil/[^/]+$~i', $normalized)) {
            return '/' . $normalized;
        }

        return '/' . $normalized;
    }
}

// Fetch published blog posts
$posts = [];
$blogHeader = mt_blog_fetch_header($conexion);
$blogHeaderTitle = trim((string)($blogHeader['title'] ?? '')) ?: 'Our Blog';
$blogHeaderSubtitle = trim((string)($blogHeader['subtitle'] ?? '')) ?: 'Discover experiences and updates from our medical travel community.';
$blogHeaderImage = trim((string)($blogHeader['bg_image'] ?? ''));
$hasAuthorUserId = false;
$authorUserIdCheck = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'author_user_id'");
if ($authorUserIdCheck && mysqli_num_rows($authorUserIdCheck) > 0) {
    $hasAuthorUserId = true;
}
if ($authorUserIdCheck) {
    mysqli_free_result($authorUserIdCheck);
}
$authorUserSelect = $hasAuthorUserId
    ? "bp.author_user_id, COALESCE(au.avatar, '') AS author_avatar, COALESCE(NULLIF(TRIM(au.nombre), ''), '') AS author_user_name,"
    : "NULL AS author_user_id, '' AS author_avatar, '' AS author_user_name,";
$authorUserJoin = $hasAuthorUserId
    ? "LEFT JOIN usuarios au ON au.id = bp.author_user_id"
    : "";
$sql_posts = "SELECT bp.id, bp.title, bp.slug, bp.excerpt, bp.body, bp.cover_image, bp.author_name, bp.provider_id,
                     {$authorUserSelect}
                     COALESCE(p.name, '') AS provider_name,
                     COALESCE(p.city, '') AS provider_city,
                     DATE_FORMAT(COALESCE(bp.published_at, bp.created_at), '%b %e, %Y') as published_on
              FROM blog_posts bp
              LEFT JOIN providers p ON p.id = bp.provider_id
              {$authorUserJoin}
              WHERE bp.status = 'published'
              ORDER BY COALESCE(bp.published_at, bp.created_at) DESC
              LIMIT 9";
$res_posts = mysqli_query($conexion, $sql_posts);
if ($res_posts) {
    while ($row = mysqli_fetch_assoc($res_posts)) {
        $posts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <?php echo $head; ?>
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
        <div class="container-fluid bg-breadcrumb" <?php if ($blogHeaderImage !== ''): ?>style="background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('<?php echo htmlspecialchars($blogHeaderImage, ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
            <div class="container text-center py-5" style="max-width: 900px;">
                <h3 class="text-white display-3 mb-4"><?php echo htmlspecialchars($blogHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="text-white mb-4"><?php echo htmlspecialchars($blogHeaderSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Pages</a></li>
                    <li class="breadcrumb-item active text-white">Blog</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->

        <!-- Blog Start -->
        <div class="container-fluid blog py-5">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Our Blog</h5>
                    <h1 class="mb-4">Latest stories</h1>
                    <p class="mb-0">Discover experiences and updates from our medical travel community.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php if (count($posts) === 0): ?>
                        <div class="col-12 text-center text-muted">No posts published yet.</div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                        <?php
                            $coverPath = $post['cover_image'] ?: 'img/blog-1.jpg';
                            if (!preg_match('~^https?://~', $coverPath)) {
                                // Normalizar rutas relativas que vienen con ../ o ./ desde el admin
                                $coverPath = ltrim($coverPath, './');
                                if (strpos($coverPath, '../') === 0) {
                                    $coverPath = substr($coverPath, 3);
                                }
                                $coverPath = '/' . ltrim($coverPath, '/');
                            }
                            $teaser = $post['excerpt'];
                            if (!$teaser) {
                                $teaser = strip_tags($post['body']);
                                $words = explode(' ', $teaser);
                                if (count($words) > 40) {
                                    $teaser = implode(' ', array_slice($words, 0, 40)) . '...';
                                }
                            }
                            $excerpt_safe = htmlspecialchars($teaser ?: '...', ENT_QUOTES, 'UTF-8');
                            $authorName = trim((string)($post['author_name'] ?? ''));
                            $authorUserName = trim((string)($post['author_user_name'] ?? ''));
                            $providerName = trim((string)($post['provider_name'] ?? ''));
                            $providerCity = trim((string)($post['provider_city'] ?? ''));
                            $authorName = $authorName !== '' ? $authorName : $authorUserName;
                            $authorName = $authorName !== '' ? $authorName : 'MedTravel Editorial Team';
                            $authorAvatarPath = blog_author_avatar_href($post['author_avatar'] ?? '');
                            $hasProviderContributor = ((int)($post['provider_id'] ?? 0) > 0 && $providerName !== '');
                        ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="blog-item h-100 shadow-sm border rounded-3 overflow-hidden d-flex flex-column">
                                <div class="blog-img">
                                    <div class="blog-img-inner">
                                        <img class="img-fluid w-100 rounded-top" src="<?php echo htmlspecialchars($coverPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="blog-icon">
                                            <a href="#" class="my-auto"><i class="fas fa-link fa-2x text-white"></i></a>
                                        </div>
                                    </div>
                                    <div class="blog-info d-flex align-items-center border border-start-0 border-end-0">
                                        <small class="flex-fill text-center border-end py-2"><i class="fa fa-calendar-alt text-primary me-2"></i><?php echo htmlspecialchars($post['published_on'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <span class="btn-hover flex-fill text-center text-white py-2"><?php echo htmlspecialchars($hasProviderContributor ? 'Specialist Contributor' : 'MedTravel Editorial', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="blog-content border border-top-0 rounded-bottom p-4 flex-grow-1 d-flex flex-column">
                                    <div class="<?php echo $hasProviderContributor ? 'mb-1' : 'mb-3'; ?> d-flex align-items-center">
                                        <?php if ($authorAvatarPath !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($authorAvatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle me-2" style="width: 36px; height: 36px; object-fit: cover;">
                                        <?php endif; ?>
                                        <p class="mb-0">Written by: <?php echo htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <?php if ($hasProviderContributor): ?>
                                        <p class="text-muted small mb-1">Specialist contributor: <?php echo htmlspecialchars($providerName, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <?php if ($hasProviderContributor && $providerCity !== ''): ?>
                                        <p class="text-muted small mb-3"><i class="fa fa-map-marker-alt text-primary me-2"></i><?php echo htmlspecialchars($providerCity, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <h4 class="h4"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="my-3 flex-grow-1"><?php echo $excerpt_safe; ?></p>
                                    <a href="/blog_post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-primary rounded-pill py-2 px-4 mt-auto">Read More</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Blog End -->

        <!-- Booking Widget Start -->
        <?php echo $booking_widget; ?>
        <!-- Booking Widget End -->

        <!-- Subscribe Start -->
        <div class="container-fluid subscribe py-5">
            <div class="container text-center py-5">
                <div class="mx-auto text-center" style="max-width: 900px;">
                    <h5 class="subscribe-title px-3">Subscribe</h5>
                    <h1 class="text-white mb-4">Our Newsletter</h1>
                    <p class="text-white mb-5">Lorem ipsum dolor sit amet consectetur adipisicing elit. Laborum tempore nam, architecto doloremque velit explicabo? Voluptate sunt eveniet fuga eligendi! Expedita laudantium fugiat corrupti eum cum repellat a laborum quasi.
                    </p>
                    <div class="position-relative mx-auto">
                        <input class="form-control border-primary rounded-pill w-100 py-3 ps-4 pe-5" type="text" placeholder="Your email">
                        <button type="button" class="btn btn-primary rounded-pill position-absolute top-0 end-0 py-2 px-4 mt-2 me-2">Subscribe</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Subscribe End -->

        <!-- Footer Start -->
        <?php echo $footer; ?>
        <!-- Footer End -->
        
        <!-- Copyright Start -->
        <?php echo $copyright; ?>
        <!-- Copyright End -->

        <!-- Back to Top -->
        <a href="#" class="btn btn-primary btn-primary-outline-0 btn-md-square back-to-top"><i class="fa fa-arrow-up"></i></a>   

        
        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <?php echo $script; ?>

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
    </body>

</html>

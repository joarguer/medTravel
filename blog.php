<?php
include('inc/include.php'); 

// Fetch published blog posts
$posts = [];
$sql_posts = "SELECT id, title, slug, excerpt, body, cover_image, author_name, DATE_FORMAT(COALESCE(published_at, created_at), '%b %e, %Y') as published_on
              FROM blog_posts
              WHERE status = 'published'
              ORDER BY COALESCE(published_at, created_at) DESC
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
        <div class="container-fluid bg-breadcrumb">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h3 class="text-white display-3 mb-4">Our Blog</h1>
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
                                        <span class="btn-hover flex-fill text-center text-white py-2"><?php echo htmlspecialchars($post['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="blog-content border border-top-0 rounded-bottom p-4 flex-grow-1 d-flex flex-column">
                                    <p class="mb-3">Posted by: <?php echo htmlspecialchars($post['author_name'], ENT_QUOTES, 'UTF-8'); ?></p>
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

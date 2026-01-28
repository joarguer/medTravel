<?php
include('inc/include.php'); ; 
$busca_header = mysqli_query($conexion,"SELECT * FROM about_header WHERE activo = '0' ORDER BY id ASC LIMIT 1");
$rst_header = mysqli_fetch_array($busca_header);
$busca_us = mysqli_query($conexion,"SELECT * FROM about_us WHERE activo = '0' ORDER BY id ASC LIMIT 1");
$rst_us = mysqli_fetch_array($busca_us);
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
                <h3 class="text-white display-3 mb-4"><?php echo $rst_header['title'];?></h1>
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item" style="color:orange;"><?php echo $rst_header['subtitle_1'];?></li>
                    <li class="breadcrumb-item active text-white"><?php echo $rst_header['subtitle_2'];?></li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->

        <!-- About Start -->
        <div class="container-fluid about py-5">
            <div class="container py-5">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-5">
                        <div class="h-100" style="border: 50px solid; border-color: transparent #13357B transparent #13357B;">
                            <img src="<?php echo $rst_us['img'];?>" class="img-fluid w-100 h-100" alt="">
                        </div>
                    </div>
                    <div class="col-lg-7" style="background: linear-gradient(rgba(255, 255, 255, .8), rgba(255, 255, 255, .8)), url(<?php echo $rst_us['bg'];?>);">
                        <h5 class="section-about-title pe-3"><?php echo $rst_us['titulo_small'];?></h5>
                        <h1 class="mb-4"><?php echo $rst_us['titulo_1'];?> <span class="text-primary"><?php echo $rst_us['titulo_2'];?></span></h1>
                        <?php echo $rst_us['paragrafo'];?>
                        <div class="row gy-2 gx-4 mb-4">
                            <?php
                            $fila = $rst_us['list'];
                            $list = json_decode($fila, true);
                            foreach ($list as $key => $value) {
                                echo '<div class="col-sm-6">
                                        <p class="mb-0"><i class="fa fa-arrow-right text-primary me-2"></i>'.$value.'</p>
                                    </div>';
                            ?>
                            <?php
                            }
                            ?>
                        </div>
                        <a class="btn btn-primary rounded-pill py-3 px-5 mt-2" href="">Read More</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- About End -->

        <!-- Travel Guide Start -->
        <div class="container-fluid guide py-5">
            <div class="container py-5">
                <div class="mx-auto text-center mb-5" style="max-width: 900px;">
                    <h5 class="section-title px-3">Our Specialist</h5>
                    <h1 class="mb-0">Meet Our Specialist</h1>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php 
                    $busca_specialist = mysqli_query($conexion,"SELECT * FROM specialist_list WHERE activo = '0' ORDER BY id ASC");
                    while($rst_specialist = mysqli_fetch_array($busca_specialist)){
                    ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="guide-item h-100 d-flex flex-column">
                            <div class="guide-img flex-shrink-0">
                                <div class="guide-img-efects">
                                    <img src="<?php echo $rst_specialist['img'];?>" class="img-fluid w-100 rounded-top" alt="<?php echo $rst_specialist['titulo'];?>" style="height: 320px; object-fit: cover;">
                                </div>
                                <div class="guide-icon rounded-pill p-2">
                                    <a class="btn btn-square btn-primary rounded-circle mx-1" href="<?php echo $rst_specialist['facebook'];?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                                    <a class="btn btn-square btn-primary rounded-circle mx-1" href="<?php echo $rst_specialist['twiter'];?>" target="_blank"><i class="fab fa-twitter"></i></a>
                                    <a class="btn btn-square btn-primary rounded-circle mx-1" href="<?php echo $rst_specialist['instagram'];?>" target="_blank"><i class="fab fa-instagram"></i></a>
                                </div>
                            </div>
                            <div class="guide-title text-center rounded-bottom p-4 flex-grow-1 d-flex flex-column justify-content-center">
                                <div class="guide-title-inner">
                                    <h4 class="mt-2 mb-3"><?php echo $rst_specialist['titulo'];?></h4>
                                    <p class="mb-0 text-muted"><?php echo $rst_specialist['subtitulo'];?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <!-- Travel Guide End -->

        <!-- Subscribe Start -->
        <?php echo $newsletter; ?>
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
        

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
        <script src="js/about.js"></script>
    </body>

</html>
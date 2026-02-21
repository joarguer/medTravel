<?php
include __DIR__ . '/include/include.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Testimonial</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
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
                <h1>Testimonial</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">Testimonial</li>
                </ol>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="portlet light bordered">
                        <div class="portlet-title">
                            <div class="caption">
                                <i class="icon-speech font-blue"></i>
                                <span class="caption-subject font-blue bold uppercase">Share your experience</span>
                            </div>
                        </div>
                        <div class="portlet-body">
                            <div id="testimonial-status" class="alert alert-info">Loading testimonial status...</div>
                            <form id="testimonialForm">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" class="form-control" id="testimonial_name" value="<?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Location (City, State)</label>
                                    <input type="text" class="form-control" id="testimonial_location" placeholder="Orlando, Florida">
                                </div>
                                <div class="form-group">
                                    <label>Rating</label>
                                    <select class="form-control" id="testimonial_rating">
                                        <option value="5">5 - Excellent</option>
                                        <option value="4">4 - Very good</option>
                                        <option value="3">3 - Good</option>
                                        <option value="2">2 - Fair</option>
                                        <option value="1">1 - Poor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Comment</label>
                                    <textarea class="form-control" id="testimonial_comment" rows="5" placeholder="Tell us about your experience..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" id="testimonial_submit">
                                    <i class="fa fa-paper-plane"></i> Submit testimonial
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="/client/js/testimonial.js" type="text/javascript"></script>
</body>
</html>

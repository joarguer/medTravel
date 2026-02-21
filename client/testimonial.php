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
    <style>
        #client-testimonial-preview {
            margin-bottom: 20px;
        }
        #client-testimonial-preview .testimonial-item {
            max-width: 520px;
            margin: 0 auto;
        }
        #client-testimonial-preview .testimonial-img {
            position: relative;
            width: 100px;
            height: 100px;
            top: 0;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 3px solid #1f3c88;
            border-style: dotted;
            border-radius: 50%;
        }
        #client-testimonial-preview .testimonial-avatar-default {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #1f3c88;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: 700;
            text-transform: uppercase;
        }
        #client-testimonial-preview .testimonial-comment {
            background: #f8f9fa;
        }
        #client-testimonial-preview .testimonial-stars i {
            margin: 0 1px;
        }
    </style>
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
                            <div id="client-testimonial-preview">
                                <h4 class="font-blue" style="margin-bottom: 15px;">Preview</h4>
                                <div class="testimonial-item text-center rounded pb-4">
                                    <div class="testimonial-comment bg-light rounded p-4">
                                        <p class="text-center mb-5" id="testimonial_preview_comment">Your testimonial will appear like this.</p>
                                    </div>
                                    <div class="testimonial-img p-1">
                                        <div class="testimonial-avatar-default" id="testimonial_preview_initial">M</div>
                                    </div>
                                    <div style="margin-top: -35px;">
                                        <h5 class="mb-0" id="testimonial_preview_name"><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></h5>
                                        <p class="mb-0" id="testimonial_preview_location"></p>
                                        <div class="d-flex justify-content-center testimonial-stars" id="testimonial_preview_stars"></div>
                                    </div>
                                </div>
                            </div>
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

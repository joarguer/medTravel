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
        /* ---- Testimonial live preview (self-contained, no owl/BS5 vars) ---- */
        #client-testimonial-preview { margin-top: 8px; }

        .preview-card {
            width: 100%;
            text-align: center;
            padding-bottom: 16px;
        }
        .preview-comment {
            background: #1f3c88;
            color: #fff;
            border-radius: 6px;
            padding: 24px 20px 60px;   /* bottom padding makes room for the avatar */
        }
        .preview-comment p {
            color: #fff;
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-word;
        }
        .preview-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #1f3c88;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: 700;
            border: 3px dotted #fff;
            margin: -50px auto 0;      /* pull up to overlap comment box */
            position: relative;
        }
        .preview-info {
            margin-top: 8px;
        }
        .preview-info h5 {
            margin: 0 0 2px;
            font-size: 15px;
            font-weight: 700;
        }
        .preview-info p {
            margin: 0 0 4px;
            font-size: 13px;
            color: #666;
        }
        .preview-stars i {
            color: #1f3c88;
            font-size: 13px;
        }
        .preview-stars i.muted {
            color: #ccc;
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
                            <div class="row">
                                <div class="col-md-7">
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
                                <div class="col-md-5">
                                    <div id="client-testimonial-preview">
                                        <h4 class="font-blue" style="margin-bottom: 6px;">Preview</h4>
                                        <p class="text-muted" style="font-size:12px;margin-bottom:12px;">Your testimonial will appear like this.</p>
                                        <div class="preview-card">
                                            <div class="preview-comment">
                                                <p id="testimonial_preview_comment"></p>
                                            </div>
                                            <div class="preview-avatar" id="testimonial_preview_initial">M</div>
                                            <div class="preview-info">
                                                <h5 id="testimonial_preview_name"><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></h5>
                                                <p id="testimonial_preview_location"></p>
                                                <div class="preview-stars" id="testimonial_preview_stars"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

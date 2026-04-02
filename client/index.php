<?php
include __DIR__ . '/include/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Dashboard</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <style type="text/css">
        .mt-patient-hero {
            background: linear-gradient(135deg, #f7fbff 0%, #edf6ff 100%);
            border: 1px solid #d6e8f7;
            border-radius: 12px;
            padding: 28px;
            min-height: 320px;
        }
        .mt-patient-phase {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #d9ecff;
            color: #1b5e91;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .mt-patient-hero h2 {
            margin: 16px 0 10px;
            font-size: 30px;
            line-height: 1.25;
            color: #23425f;
        }
        .mt-patient-description {
            font-size: 16px;
            line-height: 1.6;
            color: #49667f;
            margin-bottom: 18px;
        }
        .mt-next-step-box {
            background: #fff;
            border: 1px solid #dbe8f3;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .mt-next-step-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6e879b;
            margin-bottom: 6px;
        }
        .mt-next-step-text {
            font-size: 15px;
            line-height: 1.5;
            color: #28465f;
        }
        .mt-appointment-box {
            display: none;
            background: #0f3554;
            color: #fff;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 18px;
        }
        .mt-appointment-box strong {
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
        }
        .mt-appointment-meta {
            font-size: 13px;
            color: rgba(255,255,255,.82);
        }
        .mt-primary-actions .btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .mt-summary-card {
            border-radius: 12px;
            border: 1px solid #e7edf3;
            padding: 20px 18px;
            margin-bottom: 16px;
            background: #fff;
        }
        .mt-summary-card .mt-summary-label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #7a8ea3;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .mt-summary-card .mt-summary-value {
            font-size: 32px;
            line-height: 1.1;
            color: #20384f;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .mt-summary-card .mt-summary-text {
            font-size: 14px;
            line-height: 1.5;
            color: #60788e;
            margin-bottom: 0;
        }
        .mt-case-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .mt-case-card {
            border: 1px solid #e6edf4;
            border-radius: 12px;
            background: #fff;
            padding: 18px;
        }
        .mt-case-card h4 {
            margin: 10px 0 8px;
            font-size: 18px;
            color: #24384c;
        }
        .mt-case-card .mt-case-subtitle {
            color: #6f8295;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .mt-case-card .mt-case-next {
            color: #49667f;
            font-size: 14px;
            line-height: 1.5;
            min-height: 42px;
            margin-bottom: 14px;
        }
        .mt-case-card .btn {
            margin-right: 8px;
            margin-bottom: 8px;
        }
        .mt-empty-state {
            border: 1px dashed #c9d9e8;
            border-radius: 12px;
            padding: 30px 24px;
            text-align: center;
            background: #fbfdff;
            color: #5f778d;
        }
        @media (max-width: 767px) {
            .mt-patient-hero {
                padding: 22px 18px;
                min-height: 0;
            }
            .mt-patient-hero h2 {
                font-size: 24px;
            }
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
                <h1>Client Dashboard</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">Dashboard</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="row">
                    <div class="col-md-8">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-direction font-blue"></i>
                                    <span class="caption-subject font-blue bold uppercase">Your Care Journey</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div id="client-dashboard-empty" class="mt-empty-state" style="display:none;">
                                    <h3 style="margin-top:0; color:#24435f;">Your portal is ready</h3>
                                    <p style="margin-bottom:0;">When your MedTravel requests become active, you will see your next step here.</p>
                                </div>
                                <div id="client-dashboard-primary" class="mt-patient-hero" style="display:none;">
                                    <span class="mt-patient-phase" id="client-journey-phase">Care journey</span>
                                    <h2 id="client-journey-title">We are reviewing your case</h2>
                                    <p class="mt-patient-description" id="client-journey-description"></p>
                                    <div class="mt-next-step-box">
                                        <span class="mt-next-step-label">Next step</span>
                                        <div class="mt-next-step-text" id="client-journey-next-step"></div>
                                    </div>
                                    <div class="mt-appointment-box" id="client-journey-appointment">
                                        <strong id="client-journey-appointment-title"></strong>
                                        <div class="mt-appointment-meta" id="client-journey-appointment-meta"></div>
                                    </div>
                                    <div class="mt-primary-actions">
                                        <a href="/client/my_requests.php" class="btn btn-lg blue" id="client-journey-primary-cta">Open case</a>
                                        <span id="client-journey-secondary-actions"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mt-summary-card">
                            <span class="mt-summary-label">Requests in portal</span>
                            <div class="mt-summary-value" id="client-dashboard-total">0</div>
                            <p class="mt-summary-text">All your active and past MedTravel requests in one place.</p>
                        </div>
                        <div class="mt-summary-card">
                            <span class="mt-summary-label">Messages to review</span>
                            <div class="mt-summary-value" id="client-dashboard-notifications">0</div>
                            <p class="mt-summary-text">Open your messages if MedTravel or your provider has sent a new update.</p>
                        </div>
                        <div class="mt-summary-card">
                            <span class="mt-summary-label">Action needed</span>
                            <div class="mt-summary-value" id="client-dashboard-action-required">0</div>
                            <p class="mt-summary-text">If something needs your attention, it will appear here with one clear next step.</p>
                        </div>
                        <div class="mt-summary-card" style="margin-bottom:0;">
                            <span class="mt-summary-label">Need help?</span>
                            <p class="mt-summary-text" style="margin-bottom:15px;">Use your portal as a guided view. Messages and appointments remain available whenever you need them.</p>
                            <a href="/client/app_inbox.php" class="btn btn-default btn-block">Open messages</a>
                            <a href="/client/app_calendar.php" class="btn btn-default btn-block" style="margin-top:8px;">Open calendar</a>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="icon-layers font-green"></i>
                                    <span class="caption-subject font-green bold uppercase">Your Cases</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div id="client-dashboard-cases-empty" class="mt-empty-state" style="display:none;">
                                    <p style="margin-bottom:0;">No cases are available in your portal yet.</p>
                                </div>
                                <div id="client-dashboard-request-cards" class="mt-case-grid"></div>
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
<script src="/client/js/notifications.js" type="text/javascript"></script>
<script src="/client/js/dashboard.js" type="text/javascript"></script>
</body>
</html>

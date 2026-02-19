<?php
include __DIR__ . '/include/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - Inbox</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <link href="/assets/apps/css/inbox.css" rel="stylesheet" type="text/css" />
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
                <h1>Inbox</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">Inbox</li>
                </ol>
            </div>

            <div class="page-content-container">
                <div class="inbox">
                    <div class="row">
                        <div class="col-md-3 inbox-sidebar">
                            <button type="button" class="btn btn-sm btn-default compose-btn btn-block" id="client-inbox-refresh">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <ul class="inbox-nav" id="client-inbox-thread-list">
                                <li><a href="javascript:;">Loading threads...</a></li>
                            </ul>
                        </div>
                        <div class="col-md-9 inbox-body">
                            <div class="inbox-header">
                                <h1 id="client-inbox-title">Select a thread</h1>
                            </div>
                            <div class="inbox-content" id="client-inbox-content" style="display:none;">
                                <div id="client-inbox-messages" style="max-height:420px;overflow:auto;border:1px solid #eef1f5;padding:12px;background:#fff;"></div>
                                <form id="client-inbox-send-form" style="margin-top:12px;">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label for="client-inbox-message">Write a message</label>
                                        <textarea class="form-control" id="client-inbox-message" rows="3" maxlength="2000" placeholder="Write your message..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Send</button>
                                </form>
                            </div>
                            <div class="inbox-content" id="client-inbox-empty">
                                <div class="note note-info" style="margin:0;">Select a thread from the left panel.</div>
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
<script src="/client/js/app_inbox.js" type="text/javascript"></script>
</body>
</html>

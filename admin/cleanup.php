<?php
include('include/include.php');

if (!is_role_admin_session()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acceso denegado';
    exit;
}

$cleanupRootDir = realpath(__DIR__ . '/..');
$cleanupAction = isset($_POST['cleanup_action']) ? trim((string)$_POST['cleanup_action']) : '';
$cleanupMessages = [];
$cleanupErrors = [];
$cleanupPreview = null;
$cleanupExecution = null;

function cleanup_bool_post($key)
{
    return isset($_POST[$key]) && in_array((string)$_POST[$key], ['1', 'on', 'true'], true);
}

function cleanup_table_exists($conexion, $table)
{
    $tableEsc = mysqli_real_escape_string($conexion, $table);
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '{$tableEsc}'");
    return ($res && mysqli_num_rows($res) > 0);
}

function cleanup_table_count($conexion, $table)
{
    $sql = "SELECT COUNT(*) AS total FROM `{$table}`";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['total'] ?? 0);
}

function cleanup_collect_external_child_fk_edges($conexion, $tables)
{
    if (empty($tables)) {
        return [];
    }
    $quoted = [];
    foreach ($tables as $table) {
        $quoted[] = "'" . mysqli_real_escape_string($conexion, $table) . "'";
    }
    $in = implode(',', $quoted);

    $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND REFERENCED_TABLE_NAME IN ({$in})
              AND TABLE_NAME NOT IN ({$in})
            ORDER BY REFERENCED_TABLE_NAME, TABLE_NAME, CONSTRAINT_NAME";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return [];
    }
    $edges = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $edges[] = [
            'child' => (string)($row['TABLE_NAME'] ?? ''),
            'parent' => (string)($row['REFERENCED_TABLE_NAME'] ?? ''),
            'constraint' => (string)($row['CONSTRAINT_NAME'] ?? ''),
            'column' => (string)($row['COLUMN_NAME'] ?? ''),
            'ref_column' => (string)($row['REFERENCED_COLUMN_NAME'] ?? ''),
        ];
    }
    return $edges;
}

function cleanup_collect_fk_edges($conexion, $tables)
{
    if (empty($tables)) {
        return [];
    }
    $quoted = [];
    foreach ($tables as $table) {
        $quoted[] = "'" . mysqli_real_escape_string($conexion, $table) . "'";
    }
    $in = implode(',', $quoted);

    $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_NAME IN ({$in})
              AND REFERENCED_TABLE_NAME IN ({$in})
            ORDER BY TABLE_NAME, CONSTRAINT_NAME";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return [];
    }
    $edges = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $edges[] = [
            'child' => (string)($row['TABLE_NAME'] ?? ''),
            'parent' => (string)($row['REFERENCED_TABLE_NAME'] ?? ''),
            'constraint' => (string)($row['CONSTRAINT_NAME'] ?? ''),
            'column' => (string)($row['COLUMN_NAME'] ?? ''),
            'ref_column' => (string)($row['REFERENCED_COLUMN_NAME'] ?? ''),
        ];
    }
    return $edges;
}

function cleanup_delete_order_from_fk($tables, $edges)
{
    $inDegree = [];
    $adj = [];
    foreach ($tables as $table) {
        $inDegree[$table] = 0;
        $adj[$table] = [];
    }
    foreach ($edges as $edge) {
        $child = $edge['child'];
        $parent = $edge['parent'];
        if (!isset($inDegree[$child]) || !isset($inDegree[$parent])) {
            continue;
        }
        $adj[$child][] = $parent;
        $inDegree[$parent]++;
    }

    $queue = [];
    foreach ($tables as $table) {
        if ($inDegree[$table] === 0) {
            $queue[] = $table;
        }
    }

    $order = [];
    while (!empty($queue)) {
        $node = array_shift($queue);
        $order[] = $node;
        foreach ($adj[$node] as $parent) {
            $inDegree[$parent]--;
            if ($inDegree[$parent] === 0) {
                $queue[] = $parent;
            }
        }
    }

    if (count($order) !== count($tables)) {
        foreach ($tables as $table) {
            if (!in_array($table, $order, true)) {
                $order[] = $table;
            }
        }
    }
    return $order;
}

function cleanup_detect_attachment_dirs($rootDir)
{
    $candidates = [
        'uploads/bookings',
        'upload/bookings',
        'booking/uploads',
        'booking/attachments',
        'booking/files',
    ];
    $found = [];
    foreach ($candidates as $relative) {
        $path = $rootDir . DIRECTORY_SEPARATOR . $relative;
        if (is_dir($path)) {
            $found[] = ['relative' => $relative, 'path' => $path];
        }
    }
    return $found;
}

function cleanup_count_files_recursive($path)
{
    if (!is_dir($path)) {
        return 0;
    }
    $count = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $entry) {
        if ($entry->isFile()) {
            $count++;
        }
    }
    return $count;
}

function cleanup_delete_files_recursive($path)
{
    if (!is_dir($path)) {
        return 0;
    }
    $deleted = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $entry) {
        if ($entry->isFile()) {
            if (@unlink($entry->getPathname())) {
                $deleted++;
            }
        } elseif ($entry->isDir()) {
            @rmdir($entry->getPathname());
        }
    }
    return $deleted;
}

function cleanup_log_message($message)
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    error_log('[CLEANUP] ' . $message);
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/cleanup.log', $line, FILE_APPEND | LOCK_EX);
}

function cleanup_build_reset_plan($conexion, $include)
{
    $groups = [
        'bookings' => ['commission_payments', 'booking_request_items', 'booking_requests'],
        'inbox' => ['inbox_thread_reads', 'inbox_messages'],
        'calendar' => ['calendar_events'],
        'full_catalog' => [
            'provider_catalog_services',
            'provider_categories',
            'provider_service_offers',
            'medtravel_services_catalog',
            'service_providers',
            'providers',
        ],
    ];

    $selected = [];
    if (!empty($include['bookings'])) {
        $selected = array_merge($selected, $groups['bookings']);
    }
    if (!empty($include['inbox'])) {
        $selected = array_merge($selected, $groups['inbox']);
    }
    if (!empty($include['calendar'])) {
        $selected = array_merge($selected, $groups['calendar']);
    }
    if (!empty($include['full_catalog'])) {
        $selected = array_merge($selected, $groups['full_catalog']);
    }

    $selected = array_values(array_unique($selected));
    $existing = [];
    $counts = [];
    foreach ($selected as $table) {
        if (!cleanup_table_exists($conexion, $table)) {
            continue;
        }
        $existing[] = $table;
        $counts[$table] = cleanup_table_count($conexion, $table);
    }

    $edges = cleanup_collect_fk_edges($conexion, $existing);
    $externalEdges = cleanup_collect_external_child_fk_edges($conexion, $existing);
    $order = cleanup_delete_order_from_fk($existing, $edges);
    $warnings = [];
    if (!empty($externalEdges)) {
        $warnings[] = 'External FK dependencies detected. Delete order is safe only inside the selected tables.';
    }

    return [
        'selected' => $selected,
        'tables' => $existing,
        'counts' => $counts,
        'fk_edges' => $edges,
        'external_fk_edges' => $externalEdges,
        'delete_order' => $order,
        'warnings' => $warnings,
    ];
}

$envName = defined('APP_ENV') ? (string)APP_ENV : 'prod';
$allowResetRaw = strtolower(trim((string)getenv('ALLOW_DEV_RESET')));
$allowResetFlag = in_array($allowResetRaw, ['1', 'true', 'yes', 'on'], true);
$resetEnabled = ($envName === 'dev' && $allowResetFlag);

$includeOptions = [
    'bookings' => cleanup_bool_post('include_bookings'),
    'inbox' => cleanup_bool_post('include_inbox'),
    'calendar' => cleanup_bool_post('include_calendar'),
    'full_catalog' => cleanup_bool_post('include_full_catalog'),
    'include_files' => cleanup_bool_post('include_files'),
    'reset_autoincrement' => cleanup_bool_post('reset_autoincrement'),
];
if ($cleanupAction === '') {
    $includeOptions['bookings'] = true;
    $includeOptions['inbox'] = true;
    $includeOptions['calendar'] = true;
    $includeOptions['include_files'] = false;
    $includeOptions['reset_autoincrement'] = true;
}

$attachmentDirs = cleanup_detect_attachment_dirs($cleanupRootDir ?: dirname(__DIR__));
foreach ($attachmentDirs as $k => $dirMeta) {
    $attachmentDirs[$k]['files'] = cleanup_count_files_recursive($dirMeta['path']);
}

if ($cleanupAction === 'preview' || $cleanupAction === 'execute') {
    $cleanupPreview = cleanup_build_reset_plan($conexion, $includeOptions);
    $hasFileTargets = ($includeOptions['include_files'] && !empty($attachmentDirs));
    if (empty($cleanupPreview['tables']) && !$hasFileTargets) {
        $cleanupErrors[] = 'Nothing to reset with the selected options.';
    }
}

if ($cleanupAction === 'execute' && empty($cleanupErrors)) {
    if (!$resetEnabled) {
        $cleanupErrors[] = 'Reset is blocked. Enable ALLOW_DEV_RESET=true and APP_ENV=dev.';
    }

    $confirmWord = trim((string)($_POST['confirm_word'] ?? ''));
    $confirmIrreversible = cleanup_bool_post('confirm_irreversible');
    $confirmFullReset = cleanup_bool_post('confirm_full_reset');

    if ($confirmWord !== 'RESET') {
        $cleanupErrors[] = 'Type RESET to execute.';
    }
    if (!$confirmIrreversible) {
        $cleanupErrors[] = 'You must confirm irreversible execution.';
    }
    if ($includeOptions['full_catalog'] && !$confirmFullReset) {
        $cleanupErrors[] = 'You must explicitly confirm full reset.';
    }

    if (empty($cleanupErrors)) {
        $startedAt = microtime(true);
        $deletedRows = [];
        $deletedFiles = [];
        $executedTables = $cleanupPreview['delete_order'];

        mysqli_begin_transaction($conexion);
        try {
            foreach ($executedTables as $table) {
                $sql = "DELETE FROM `{$table}`";
                if (!mysqli_query($conexion, $sql)) {
                    throw new Exception('Delete failed for ' . $table . ': ' . mysqli_error($conexion));
                }
                $deletedRows[$table] = mysqli_affected_rows($conexion);
                if ($includeOptions['reset_autoincrement']) {
                    $aiSql = "ALTER TABLE `{$table}` AUTO_INCREMENT = 1";
                    if (!mysqli_query($conexion, $aiSql)) {
                        throw new Exception('Auto-increment reset failed for ' . $table . ': ' . mysqli_error($conexion));
                    }
                }
            }
            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            $cleanupErrors[] = 'Execute failed: ' . $e->getMessage();
        }

        if (empty($cleanupErrors) && $includeOptions['include_files']) {
            foreach ($attachmentDirs as $dirMeta) {
                $deletedFiles[$dirMeta['relative']] = cleanup_delete_files_recursive($dirMeta['path']);
            }
        }

        if (empty($cleanupErrors)) {
            $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);
            $cleanupExecution = [
                'deleted_rows' => $deletedRows,
                'deleted_files' => $deletedFiles,
                'elapsed_ms' => $elapsedMs,
            ];
            cleanup_log_message(
                'user_id=' . (int)($_SESSION['id_usuario'] ?? 0)
                . ' action=execute'
                . ' options=' . json_encode($includeOptions)
                . ' deleted_rows=' . json_encode($deletedRows)
                . ' deleted_files=' . json_encode($deletedFiles)
                . ' elapsed_ms=' . $elapsedMs
            );
            $cleanupMessages[] = 'Reset executed successfully.';
            $cleanupPreview = cleanup_build_reset_plan($conexion, $includeOptions);
        } else {
            cleanup_log_message(
                'user_id=' . (int)($_SESSION['id_usuario'] ?? 0)
                . ' action=execute_failed'
                . ' options=' . json_encode($includeOptions)
                . ' errors=' . json_encode($cleanupErrors)
            );
        }
    }
} elseif ($cleanupAction === 'preview' && empty($cleanupErrors)) {
    cleanup_log_message(
        'user_id=' . (int)($_SESSION['id_usuario'] ?? 0)
        . ' action=preview'
        . ' options=' . json_encode($includeOptions)
        . ' tables=' . json_encode($cleanupPreview['tables'])
    );
    $cleanupMessages[] = 'Preview generated. Review counts and delete order before executing.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title; ?> - Limpieza (DEV)</title>
    <?php echo $global_first_style; ?>
    <link href="../../assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
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
                <h1>Limpieza (DEV) <small>Soft delete / restore</small></h1>
                <ol class="breadcrumb">
                    <li><a href="index.php">Inicio</a></li>
                    <li class="active">Limpieza (DEV)</li>
                </ol>
            </div>

            <div class="portlet light bordered">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="icon-refresh font-yellow-gold"></i>
                        <span class="caption-subject font-yellow-gold bold uppercase">Environment Reset (Development)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <?php if (!empty($cleanupMessages)): ?>
                        <?php foreach ($cleanupMessages as $msg): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($cleanupErrors)): ?>
                        <?php foreach ($cleanupErrors as $err): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="alert <?php echo $resetEnabled ? 'alert-success' : 'alert-warning'; ?>">
                        <strong>Guard:</strong>
                        APP_ENV=<?php echo htmlspecialchars($envName, ENT_QUOTES, 'UTF-8'); ?> ·
                        ALLOW_DEV_RESET=<?php echo htmlspecialchars($allowResetRaw !== '' ? $allowResetRaw : '(not set)', ENT_QUOTES, 'UTF-8'); ?> ·
                        Execute enabled: <strong><?php echo $resetEnabled ? 'YES' : 'NO'; ?></strong>
                    </div>

                    <form method="post" class="form-horizontal">
                        <div class="form-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Operational reset (recommended)</h4>
                                    <div class="mt-checkbox-list">
                                        <label class="mt-checkbox mt-checkbox-outline"> Include bookings and items
                                            <input type="checkbox" name="include_bookings" value="1" <?php echo $includeOptions['bookings'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> Include inbox messages
                                            <input type="checkbox" name="include_inbox" value="1" <?php echo $includeOptions['inbox'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> Include calendar events
                                            <input type="checkbox" name="include_calendar" value="1" <?php echo $includeOptions['calendar'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> Reset AUTO_INCREMENT (dev only)
                                            <input type="checkbox" name="reset_autoincrement" value="1" <?php echo $includeOptions['reset_autoincrement'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h4>Full reset (dangerous)</h4>
                                    <div class="mt-checkbox-list">
                                        <label class="mt-checkbox mt-checkbox-outline"> Also delete providers/services demo data
                                            <input type="checkbox" name="include_full_catalog" value="1" <?php echo $includeOptions['full_catalog'] ? 'checked' : ''; ?>>
                                            <span></span>
                                        </label>
                                    </div>
                                    <?php if (!empty($attachmentDirs)): ?>
                                        <div class="mt-checkbox-list">
                                            <label class="mt-checkbox mt-checkbox-outline"> Also delete generated booking files
                                                <input type="checkbox" name="include_files" value="1" <?php echo $includeOptions['include_files'] ? 'checked' : ''; ?>>
                                                <span></span>
                                            </label>
                                        </div>
                                        <p class="help-block">
                                            Detected folders:
                                            <?php
                                            $folderLabels = [];
                                            foreach ($attachmentDirs as $dirMeta) {
                                                $folderLabels[] = htmlspecialchars($dirMeta['relative'], ENT_QUOTES, 'UTF-8') . ' (' . (int)$dirMeta['files'] . ' files)';
                                            }
                                            echo implode(' · ', $folderLabels);
                                            ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="help-block">No specific booking attachment folders detected in this environment.</p>
                                    <?php endif; ?>

                                    <hr>
                                    <h4>Execute confirmation</h4>
                                    <div class="form-group">
                                        <label class="col-md-4 control-label">Type RESET</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" name="confirm_word" value="" placeholder="RESET">
                                        </div>
                                    </div>
                                    <div class="mt-checkbox-list">
                                        <label class="mt-checkbox mt-checkbox-outline"> I understand this cannot be undone
                                            <input type="checkbox" name="confirm_irreversible" value="1">
                                            <span></span>
                                        </label>
                                        <label class="mt-checkbox mt-checkbox-outline"> I also confirm full reset of demo catalog data
                                            <input type="checkbox" name="confirm_full_reset" value="1">
                                            <span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="cleanup_action" value="preview" class="btn default">
                                <i class="fa fa-search"></i> Preview
                            </button>
                            <button type="submit" name="cleanup_action" value="execute" class="btn red" <?php echo $resetEnabled ? '' : 'disabled'; ?>>
                                <i class="fa fa-warning"></i> Execute
                            </button>
                        </div>
                    </form>

                    <?php if (is_array($cleanupPreview)): ?>
                        <hr>
                        <h4>Preview result</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Counts by table</h5>
                                <table class="table table-bordered table-condensed">
                                    <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th>Rows</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($cleanupPreview['tables'])): ?>
                                        <tr><td colspan="2">No matching tables selected.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($cleanupPreview['tables'] as $table): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string)($cleanupPreview['counts'][$table] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Delete order (within selected tables)</h5>
                                <?php if (!empty($cleanupPreview['warnings'])): ?>
                                    <?php foreach ($cleanupPreview['warnings'] as $warning): ?>
                                        <div class="alert alert-warning" style="margin-bottom:10px;">
                                            <?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($cleanupPreview['external_fk_edges'])): ?>
                                    <div class="alert alert-warning" style="margin-bottom:10px;">
                                        <strong>External child tables not included in this reset:</strong>
                                        <ul style="margin:8px 0 0 18px;">
                                            <?php foreach ($cleanupPreview['external_fk_edges'] as $edge): ?>
                                                <li><?php echo htmlspecialchars($edge['child'] . ' -> ' . $edge['parent'] . ' (' . $edge['constraint'] . ')', ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <ol>
                                    <?php if (empty($cleanupPreview['delete_order'])): ?>
                                        <li>No tables selected.</li>
                                    <?php else: ?>
                                        <?php foreach ($cleanupPreview['delete_order'] as $table): ?>
                                            <li><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ol>
                            </div>
                        </div>

                        <h5>Detected FK relations inside selected tables</h5>
                        <table class="table table-striped table-bordered table-condensed">
                            <thead>
                            <tr>
                                <th>Child table</th>
                                <th>Parent table</th>
                                <th>Constraint</th>
                                <th>Column</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($cleanupPreview['fk_edges'])): ?>
                                <tr><td colspan="4">No FK relations detected among selected tables.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cleanupPreview['fk_edges'] as $edge): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($edge['child'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($edge['parent'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($edge['constraint'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($edge['column'] . ' -> ' . $edge['ref_column'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (is_array($cleanupExecution)): ?>
                        <hr>
                        <h4>Execute report</h4>
                        <p><strong>Execution time:</strong> <?php echo (int)$cleanupExecution['elapsed_ms']; ?> ms</p>
                        <table class="table table-bordered table-condensed">
                            <thead>
                            <tr>
                                <th>Table</th>
                                <th>Deleted rows</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cleanupExecution['deleted_rows'] as $table => $deleted): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$deleted; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!empty($cleanupExecution['deleted_files'])): ?>
                            <h5>Deleted files</h5>
                            <ul>
                                <?php foreach ($cleanupExecution['deleted_files'] as $path => $deleted): ?>
                                    <li><?php echo htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int)$deleted; ?> file(s)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="portlet light bordered">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="icon-trash font-red"></i>
                        <span class="caption-subject font-red bold uppercase">Limpieza (DEV)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="active"><a href="#tab-users" data-toggle="tab">Usuarios</a></li>
                        <li><a href="#tab-providers" data-toggle="tab">Providers médicos</a></li>
                        <li><a href="#tab-service-providers" data-toggle="tab">Service Providers</a></li>
                        <li><a href="#tab-medtravel-services" data-toggle="tab">MedTravel Services</a></li>
                    </ul>

                    <div class="tab-content" style="padding-top:15px;">
                        <div class="tab-pane active" id="tab-users">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="users-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-users-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-providers">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="providers-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-providers-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Ciudad</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-service-providers">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="service-providers-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-service-providers-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Proveedor</th>
                                    <th>Tipo</th>
                                    <th>Email</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-medtravel-services">
                            <div style="margin-bottom:10px;">
                                <label class="mt-checkbox mt-checkbox-outline"> Ver eliminados
                                    <input type="checkbox" id="medtravel-services-show-deleted">
                                    <span></span>
                                </label>
                            </div>
                            <table class="table table-striped table-bordered" id="cleanup-medtravel-services-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Servicio</th>
                                    <th>Tipo</th>
                                    <th>Disponibilidad</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>

    <?php echo $sider_bar; ?>
</div>

<?php echo $theme_layout_script; ?>
<script src="../../assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
<script src="../../assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
<script>
(function(){
    var usersShowDeleted = false;
    var providersShowDeleted = false;
    var serviceProvidersShowDeleted = false;
    var medtravelServicesShowDeleted = false;

    function notifyError(msg){
        if (window.toastr) { toastr.error(msg); return; }
        alert(msg);
    }

    function notifySuccess(msg){
        if (window.toastr) { toastr.success(msg); return; }
        alert(msg);
    }

    function actionButton(showDeleted, actionClass, dataId){
        var text = showDeleted ? 'Restaurar' : 'Eliminar (Soft)';
        var toneClass = showDeleted ? 'btn-info' : 'btn-danger';
        return '<button class="btn btn-xs ' + toneClass + ' ' + actionClass + '" data-id="' + dataId + '">' + text + '</button>';
    }

    var usersTable = $('#cleanup-users-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'usuario' },
            { data: 'nombre' },
            { data: 'email' },
            { data: 'activo', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(usersShowDeleted, 'btn-soft-delete-user', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var providersTable = $('#cleanup-providers-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'type' },
            { data: 'city' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(providersShowDeleted, 'btn-soft-delete-provider', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var serviceProvidersTable = $('#cleanup-service-providers-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'provider_name' },
            { data: 'provider_type' },
            { data: 'contact_email' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(serviceProvidersShowDeleted, 'btn-soft-delete-service-provider', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    var medtravelServicesTable = $('#cleanup-medtravel-services-table').DataTable({
        data: [],
        columns: [
            { data: 'id' },
            { data: 'service_name' },
            { data: 'service_type' },
            { data: 'availability_status' },
            { data: 'is_active', render: function(v){ return parseInt(v, 10) === 1 ? 'Activo' : 'Inactivo'; } },
            { data: null, orderable: false, render: function(row){
                return actionButton(medtravelServicesShowDeleted, 'btn-soft-delete-medtravel-service', row.id);
            } }
        ],
        order: [[0, 'desc']]
    });

    function loadUsers(){
        $.getJSON('ajax/cleanup_users.php', { action: 'list_users', show_deleted: usersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            usersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar usuarios';
            notifyError(msg);
        });
    }

    function loadProviders(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_providers', show_deleted: providersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            providersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar providers';
            notifyError(msg);
        });
    }

    function loadServiceProviders(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_service_providers', show_deleted: serviceProvidersShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            serviceProvidersTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar service providers';
            notifyError(msg);
        });
    }

    function loadMedtravelServices(){
        $.getJSON('ajax/cleanup_companies.php', { action: 'list_medtravel_services', show_deleted: medtravelServicesShowDeleted ? 1 : 0 }, function(res){
            var rows = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
            medtravelServicesTable.clear().rows.add(rows).draw();
        }).fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'No se pudo cargar MedTravel Services';
            notifyError(msg);
        });
    }

    function confirmAction(isRestore){
        return window.confirm(isRestore ? '¿Deseas restaurar?' : '¿Deseas eliminar (soft)?');
    }

    $('#users-show-deleted').on('change', function(){
        usersShowDeleted = $(this).is(':checked');
        loadUsers();
    });

    $('#providers-show-deleted').on('change', function(){
        providersShowDeleted = $(this).is(':checked');
        loadProviders();
    });

    $('#service-providers-show-deleted').on('change', function(){
        serviceProvidersShowDeleted = $(this).is(':checked');
        loadServiceProviders();
    });

    $('#medtravel-services-show-deleted').on('change', function(){
        medtravelServicesShowDeleted = $(this).is(':checked');
        loadMedtravelServices();
    });

    $('#cleanup-users-table').on('click', '.btn-soft-delete-user', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = usersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_users.php', {
            action: isRestore ? 'restore_user' : 'soft_delete_user',
            user_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Usuario restaurado' : 'Usuario eliminado (soft)'));
                loadUsers();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-providers-table').on('click', '.btn-soft-delete-provider', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = providersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_provider' : 'soft_delete_provider',
            provider_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Provider restaurado' : 'Provider eliminado (soft)'));
                loadProviders();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-service-providers-table').on('click', '.btn-soft-delete-service-provider', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = serviceProvidersShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_service_provider' : 'soft_delete_service_provider',
            service_provider_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Service provider restaurado' : 'Service provider eliminado (soft)'));
                loadServiceProviders();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    $('#cleanup-medtravel-services-table').on('click', '.btn-soft-delete-medtravel-service', function(){
        var id = parseInt($(this).data('id') || 0, 10);
        if (id <= 0) return;

        var isRestore = medtravelServicesShowDeleted;
        if (!confirmAction(isRestore)) return;

        $.post('ajax/cleanup_companies.php', {
            action: isRestore ? 'restore_medtravel_service' : 'soft_delete_medtravel_service',
            medtravel_service_id: id
        }, function(res){
            if (res && res.ok) {
                notifySuccess(res.message || (isRestore ? 'Servicio restaurado' : 'Servicio eliminado (soft)'));
                loadMedtravelServices();
            } else {
                notifyError((res && (res.message || res.error)) ? (res.message || res.error) : 'Error');
            }
        }, 'json').fail(function(xhr){
            var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error';
            notifyError(msg);
        });
    });

    loadUsers();
    loadProviders();
    loadServiceProviders();
    loadMedtravelServices();
})();
</script>
</body>
</html>

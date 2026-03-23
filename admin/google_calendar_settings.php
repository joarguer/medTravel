<?php
include('include/include.php');
require_once __DIR__ . '/../inc/google_calendar.php';

if (!google_calendar_admin_can_manage()) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$adminUserId = current_admin_user_id();
$config = google_calendar_get_config();
$connection = google_calendar_get_connection($conexion, $adminUserId, false);
$flash = google_calendar_pop_flash();
$testEventResult = isset($flash['details']['test_event']) && is_array($flash['details']['test_event'])
    ? $flash['details']['test_event']
    : null;
$scopeList = $connection && !empty($connection['scope_text'])
    ? preg_split('/\s+/', trim((string)$connection['scope_text']))
    : $config['scopes'];
$isConnected = !empty($connection['is_connected']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>MedTravel - Google Calendar y Google Meet</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <link rel="shortcut icon" href="favicon.ico" />
    <style>
        .google-status-card {
            border-left: 4px solid #166534;
            margin-bottom: 24px;
        }
        .google-status-card.is-disconnected {
            border-left-color: #b91c1c;
        }
        .status-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .status-pill.ok {
            background: #dcfce7;
            color: #166534;
        }
        .status-pill.off {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }
        .status-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }
        .status-item .label {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .scope-list {
            margin: 0;
            padding-left: 18px;
        }
        .scope-list li {
            margin-bottom: 6px;
        }
        .action-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .meta-note {
            margin-top: 10px;
            color: #64748b;
        }
        .test-result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 15px;
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
                    <h1>Google Calendar y Google Meet
                        <small>Conexión OAuth por admin para preparar la creación de eventos y videollamadas</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="#">Administración</a></li>
                        <li><a href="#">Configuración Operativa</a></li>
                        <li class="active">Google Calendar / Meet</li>
                    </ol>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger'); ?>">
                        <?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($testEventResult): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="fa fa-flask"></i>
                                        <span class="caption-subject bold">Resultado del evento de prueba</span>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="test-result-grid">
                                        <div class="status-item">
                                            <span class="label">Estado</span>
                                            <strong><?php echo !empty($testEventResult['ok']) ? 'Éxito' : 'Error'; ?></strong>
                                        </div>
                                        <div class="status-item">
                                            <span class="label">Event ID</span>
                                            <strong><?php echo !empty($testEventResult['event_id']) ? htmlspecialchars((string)$testEventResult['event_id'], ENT_QUOTES, 'UTF-8') : 'No disponible'; ?></strong>
                                        </div>
                                        <div class="status-item">
                                            <span class="label">HTML Link</span>
                                            <?php if (!empty($testEventResult['html_link'])): ?>
                                                <strong><a href="<?php echo htmlspecialchars((string)$testEventResult['html_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Abrir evento</a></strong>
                                            <?php else: ?>
                                                <strong>No disponible</strong>
                                            <?php endif; ?>
                                        </div>
                                        <div class="status-item">
                                            <span class="label">Meet URL</span>
                                            <?php if (!empty($testEventResult['meet_url'])): ?>
                                                <strong><a href="<?php echo htmlspecialchars((string)$testEventResult['meet_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Abrir Meet</a></strong>
                                            <?php else: ?>
                                                <strong>No disponible</strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered google-status-card <?php echo $isConnected ? '' : 'is-disconnected'; ?>">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-google"></i>
                                    <span class="caption-subject bold">Estado de la conexión Google por admin</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <p>
                                    Esta conexión queda asociada al admin autenticado y será la base de los eventos de Google Calendar con enlace de Google Meet en la fase operativa siguiente.
                                </p>
                                <span class="status-pill <?php echo ($config['enabled'] && $isConnected) ? 'ok' : 'off'; ?>">
                                    <?php
                                    if (!$config['enabled']) {
                                        echo 'Backend incompleto';
                                    } elseif ($isConnected) {
                                        echo 'Conectado';
                                    } else {
                                        echo 'No conectado';
                                    }
                                    ?>
                                </span>

                                <div class="status-grid">
                                    <div class="status-item">
                                        <span class="label">Admin autenticado</span>
                                        <strong><?php echo htmlspecialchars((string)($_SESSION['nombre_usuario'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div class="meta-note">Usuario interno #<?php echo (int)$adminUserId; ?></div>
                                    </div>
                                    <div class="status-item">
                                        <span class="label">Cuenta Google conectada</span>
                                        <strong><?php echo $isConnected ? htmlspecialchars((string)($connection['google_email'] ?? 'Sin email'), ENT_QUOTES, 'UTF-8') : 'Sin conexión activa'; ?></strong>
                                        <div class="meta-note">
                                            <?php if (!empty($connection['connected_at'])): ?>
                                                Conectada desde <?php echo htmlspecialchars((string)$connection['connected_at'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else: ?>
                                                Todavía no hay autorización OAuth persistida.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="status-item">
                                        <span class="label">Expiración del access token</span>
                                        <strong><?php echo !empty($connection['token_expires_at']) ? htmlspecialchars((string)$connection['token_expires_at'], ENT_QUOTES, 'UTF-8') . ' UTC' : 'Se refresca cuando aplique'; ?></strong>
                                        <div class="meta-note">El refresh token se conserva cifrado y aislado por admin.</div>
                                    </div>
                                    <div class="status-item">
                                        <span class="label">Redirect URI backend</span>
                                        <strong><?php echo $config['enabled'] ? htmlspecialchars((string)$config['redirect_uri'], ENT_QUOTES, 'UTF-8') : 'No configurada'; ?></strong>
                                        <div class="meta-note">Debe coincidir exactamente con la URL autorizada en Google Cloud.</div>
                                    </div>
                                </div>

                                <div class="action-row">
                                    <?php if ($config['enabled']): ?>
                                        <a class="btn btn-primary" href="google_calendar_oauth.php?action=start">
                                            <i class="fa fa-link"></i>
                                            <?php echo $isConnected ? 'Reconectar con Google' : 'Conectar con Google'; ?>
                                        </a>
                                        <?php if ($isConnected): ?>
                                            <form method="post" action="google_calendar_oauth.php" style="display:inline-block;">
                                                <input type="hidden" name="action" value="create_test_event">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fa fa-flask"></i> Crear evento de prueba con Meet
                                                </button>
                                            </form>
                                            <form method="post" action="google_calendar_oauth.php" style="display:inline-block;">
                                                <input type="hidden" name="action" value="disconnect">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Se eliminará la conexión Google guardada para este admin. ¿Deseas continuar?');">
                                                    <i class="fa fa-unlink"></i> Desconectar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$config['enabled']): ?>
                                    <div class="alert alert-warning" style="margin-top:20px;">
                                        <strong>Faltan prerequisitos backend:</strong>
                                        <ul class="scope-list">
                                            <?php foreach ($config['missing'] as $missingItem): ?>
                                                <li><?php echo htmlspecialchars((string)$missingItem, ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($connection['last_error'])): ?>
                                    <div class="alert alert-danger" style="margin-top:20px;">
                                        <strong>Último error registrado:</strong>
                                        <?php echo htmlspecialchars((string)$connection['last_error'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isConnected): ?>
                                    <div class="alert alert-info" style="margin-top:20px;">
                                        <strong>Prueba técnica controlada:</strong> el botón de prueba crea un evento real llamado <strong>MedTravel Test Meeting</strong> en el Google Calendar del admin conectado, con inicio en ahora + 30 minutos, fin en ahora + 60 minutos y generación de Google Meet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-shield"></i>
                                    <span class="caption-subject bold">Seguridad y aislamiento</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <ul class="scope-list">
                                    <li>La autorización OAuth usa state validado contra sesión, admin y navegador.</li>
                                    <li>Los tokens se guardan solo en backend; el refresh token queda cifrado antes de persistirse.</li>
                                    <li>Cada registro queda aislado por id_usuario del admin autenticado.</li>
                                    <li>No se expone client secret ni secretos OAuth al frontend.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-calendar"></i>
                                    <span class="caption-subject bold">Scopes y backend listo</span>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <p>Esta base ya deja preparado el servicio backend reusable para crear eventos de Calendar con Google Meet cuando se conecte el flujo de agenda o booking.</p>
                                <ul class="scope-list">
                                    <?php foreach ($scopeList as $scope): ?>
                                        <?php if (trim((string)$scope) !== ''): ?>
                                            <li><?php echo htmlspecialchars((string)$scope, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="meta-note">El servicio reusable vive en inc/google_calendar.php y expone refresco de token más creación de evento con Meet.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php echo $theme_global_js; ?>
    <?php echo $theme_layout_js; ?>
</body>
</html>
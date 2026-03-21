<?php
include("include/include.php");
// RBAC explícito para configuración sensible.
if (!user_can(PERM_SETTINGS_MANAGE)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
$id_usuario = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>MedTravel - Configuración operativa de correo saliente</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <link rel="shortcut icon" href="favicon.ico" />
    <style>
        .account-card {
            border-left: 4px solid #0f766e;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .account-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .account-card.inactive {
            border-left-color: #ccc;
            opacity: 0.7;
        }
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .test-result {
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .test-result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 40px;
            top: 8px;
            color: #999;
        }
        .config-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .caption-helper {
            display: block;
            margin-top: 4px;
            color: #7b8a97;
            font-size: 13px;
            font-weight: 400;
        }
        .settings-context-note {
            margin: 0;
            color: #6c7a89;
            max-width: 980px;
        }
        .section-kicker {
            display: block;
            margin-bottom: 6px;
            color: #0f766e;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <!-- BEGIN CONTAINER -->
    <div class="wrapper">
        <!-- BEGIN HEADER -->
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header;?>
                    <?php echo $top_header_2;?>
                </div>
            </nav>
        </header>
        <!-- END HEADER -->
        
        <div class="container-fluid">
            <div class="page-content">
                <!-- BEGIN BREADCRUMBS -->
                <div class="breadcrumbs">
                    <h1>Configuración operativa del correo saliente
                        <small>Consola central para cuentas remitentes y validación operativa del sistema</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li>
                            <a href="index.php">Inicio</a>
                        </li>
                        <li>
                            <a href="#">Administración</a>
                        </li>
                        <li>
                            <a href="#">Configuración Operativa</a>
                        </li>
                        <li class="active">Correo saliente</li>
                    </ol>
                </div>
                <!-- END BREADCRUMBS -->

                <!-- END BREADCRUMBS -->
                
                <!-- Alertas -->
                <div id="alert-container"></div>

                <!-- BEGIN CONTENT -->
                <div class="row">
                    <div class="col-md-12">
                        <!-- Configuración Global -->
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-cog"></i>
                                    <span class="caption-subject bold">Consola global del correo saliente</span>
                                    <span class="caption-helper">Administra cuentas remitentes del sistema y valida su disponibilidad operativa desde administración central</span>
                                </div>
                                <div class="actions">
                                    <button class="btn btn-sm btn-primary" onclick="testAllAccounts()">
                                        <i class="fa fa-check-circle"></i> Validar cuentas activas
                                    </button>
                                </div>
                            </div>
                            <div class="portlet-body">
                                <div class="alert alert-info">
                                    <strong>Alcance del módulo:</strong> esta pantalla administra la <strong>configuración operativa global del correo saliente</strong> y las <strong>cuentas remitentes</strong> utilizadas por flujos críticos del sistema.
                                    <br>
                                    <span class="small">Su uso corresponde a <strong>administración central</strong>. No es una consola de envío masivo ni un panel de pruebas aislado.</span>
                                </div>
                                <div class="alert alert-warning">
                                    <strong>Impacto transversal:</strong> cambios en cuentas activas, credenciales o estado pueden afectar comunicaciones de reservas, accesos, paquetes, interacciones operativas y otros envíos automáticos.
                                    <br>
                                    <span class="small">Antes de guardar o validar cuentas, revisa el alcance del cambio y confirma que la cuenta correcta siga operativa para los flujos dependientes.</span>
                                </div>
                                <div class="alert alert-danger">
                                    <strong>Recurso sensible:</strong> este módulo gestiona configuraciones globales de correo y debe operarse con criterio de continuidad y control.
                                    <br>
                                    <span class="small">Aquí se mantienen remitentes del sistema y su validación operativa. No reemplaza herramientas de diagnóstico técnico profundo ni de auditoría SMTP avanzada.</span>
                                </div>
                                <div class="row" style="margin-bottom:15px;">
                                    <div class="col-md-12">
                                        <p class="settings-context-note">
                                            Usa esta consola para gobernar la disponibilidad del correo saliente del sistema. El foco está en <strong>cuentas remitentes</strong>, su configuración operativa y su validación básica, no en campañas de envío ni en pruebas técnicas exhaustivas de infraestructura.
                                        </p>
                                    </div>
                                </div>
                                <div class="config-section">
                                    <span class="section-kicker">Configuración base del servicio</span>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label><i class="fa fa-server"></i> Servidor de salida:</label>
                                            <p class="form-control-static"><strong>mail.medtravel.com.co</strong></p>
                                        </div>
                                        <div class="col-md-4">
                                            <label><i class="fa fa-plug"></i> Puerto operativo:</label>
                                            <p class="form-control-static"><strong>465 (SSL)</strong></p>
                                        </div>
                                        <div class="col-md-4">
                                            <label><i class="fa fa-lock"></i> Capa de seguridad:</label>
                                            <p class="form-control-static"><strong>SSL/TLS</strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="config-section" style="background:#fcfcfc; border:1px solid #e8ecef;">
                                    <span class="section-kicker">Qué administra esta consola</span>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <p class="mb-0"><strong>Cuentas remitentes</strong><br><small class="text-muted">Identidad de envío, credenciales, reply-to y estado operativo.</small></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-0"><strong>Validación operativa</strong><br><small class="text-muted">Pruebas de conexión y envío de prueba para verificar disponibilidad básica.</small></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-0"><strong>Continuidad del sistema</strong><br><small class="text-muted">Impacta comunicaciones automáticas y operativas dependientes del correo saliente.</small></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cuentas de Email -->
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-envelope"></i>
                                    <span class="caption-subject bold">Cuentas remitentes del sistema</span>
                                    <span class="caption-helper">Cada tarjeta representa una cuenta operativa, su rol funcional y sus acciones de validación disponibles</span>
                                </div>
                            </div>
                            <div class="portlet-body" style="padding-top:0;">
                                <div class="alert alert-info" style="margin-bottom:20px;">
                                    <strong>Diferenciación interna:</strong> esta sección combina mantenimiento de cuentas remitentes con acciones de validación básica.
                                    <br>
                                    <span class="small">Editar cambia configuración activa. Validar conexión comprueba disponibilidad. Enviar correo de prueba confirma el comportamiento de salida con una cuenta concreta.</span>
                                </div>
                                <div class="row" id="accounts-container">
                                    <!-- Cargadas vía AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END CONTENT -->
            </div>
        </div>
        
        <!-- Modal de Edición -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">
                            <i class="fa fa-edit"></i> <span id="modal-title-text">Editar cuenta remitente</span>
                        </h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info" style="margin-bottom:20px;">
                            Estás modificando una <strong>cuenta remitente operativa</strong> del sistema.
                            <br>
                            <span class="small">Revisa con cuidado credenciales, dirección remitente, reply-to y estado antes de guardar. Estos cambios pueden impactar envíos automáticos y operativos.</span>
                        </div>
                        <form id="form-account" class="form-horizontal">
                            <input type="hidden" id="account_id" name="account_id">
                            <input type="hidden" id="account_type" name="account_type">

                            <div class="form-group">
                                <label class="col-md-3 control-label">Rol de la cuenta:</label>
                                <div class="col-md-9">
                                    <p class="form-control-static" id="display_account_type"></p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Dirección remitente: *</label>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" id="email_address" name="email_address" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Nombre visible del remitente: *</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="display_name" name="display_name" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Usuario de autenticación: *</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" required>
                                    <span class="help-block">Generalmente coincide con la dirección remitente completa usada para autenticarse.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Contraseña operativa: *</label>
                                <div class="col-md-9">
                                    <div style="position: relative;">
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" required>
                                        <i class="fa fa-eye password-toggle" onclick="togglePassword()"></i>
                                    </div>
                                    <span class="help-block">Se guardará encriptada en la base de datos. Si la dejas vacía, se conserva la actual.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Dirección de respuesta:</label>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" id="reply_to" name="reply_to">
                                    <span class="help-block">Dirección a la que se dirigirán las respuestas del destinatario.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Descripción funcional:</label>
                                <div class="col-md-9">
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Estado operativo:</label>
                                <div class="col-md-9">
                                    <label class="mt-checkbox">
                                        <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                        Cuenta habilitada para uso operativo
                                        <span></span>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">
                            <i class="fa fa-times"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveAccount()">
                            <i class="fa fa-save"></i> Guardar configuración
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    <!-- END CONTAINER -->

    </div>

    <!-- Scripts -->
    <script src="../../assets/global/plugins/jquery.min.js" type="text/javascript"></script>
    <script src="../../assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <?php echo $theme_global_js;?>
    <?php echo $theme_layout_js;?>
    <script type="text/javascript">
    window.MT_REALTIME = {
        baseUrl: <?php echo json_encode($realtime_base_url); ?>,
        socketPath: <?php echo json_encode($realtime_socket_path); ?>,
        adminTokenUrl: <?php echo json_encode($realtime_admin_token_url); ?>,
        isAdmin: <?php echo json_encode($es_admin ? 1 : 0); ?>
    };
    </script>
    <script src="https://medtravel.com.co/realtime/socket.io/socket.io.js" type="text/javascript"></script>
    <script src="js/header_notifications.js" type="text/javascript"></script>
    
    <script>
    $(document).ready(function() {
        loadAccounts();
    });

    function loadAccounts() {
        $.ajax({
            url: 'ajax/email_settings.php',
            type: 'POST',
            data: { action: 'list' },
            dataType: 'json',
            success: function(response) {
                if(response.ok) {
                    renderAccounts(response.data);
                } else {
                    // Verificar si es error de tabla no existente
                    if(response.error === 'TABLE_NOT_EXISTS') {
                        const errorHtml = `
                        <div class="portlet light bordered">
                            <div class="portlet-body">
                                <div class="alert alert-danger">
                                    <h4><i class="fa fa-exclamation-triangle"></i> Configuración de correo no disponible</h4>
                                    <p>${response.message}</p>
                                    <hr>
                                    <p><strong>Pasos para habilitar el módulo:</strong></p>
                                    <ol>
                                        <li>Abre phpMyAdmin o tu gestor de base de datos</li>
                                        <li>Selecciona la base de datos <code>bolsacar_medtravel</code></li>
                                        <li>Ve a la pestaña "SQL"</li>
                                        <li>Copia y pega el contenido del archivo: <code>sql/email_settings_table.sql</code></li>
                                        <li>Ejecuta la consulta</li>
                                        <li>Recarga esta consola</li>
                                    </ol>
                                    <p class="margin-top-20">
                                        <a href="sql/email_settings_table.sql" target="_blank" class="btn btn-primary">
                                            <i class="fa fa-download"></i> Ver archivo SQL
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>`;
                        $('#accounts-container').html(errorHtml);
                    } else {
                        showAlert('error', response.message);
                    }
                }
            },
            error: function() {
                showAlert('error', 'Error al cargar las cuentas de email');
            }
        });
    }

    function renderAccounts(accounts) {
        if(!accounts || !accounts.length) {
            $('#accounts-container').html('<div class="col-md-12"><div class="alert alert-info">No hay cuentas remitentes configuradas para esta consola.</div></div>');
            return;
        }

        let html = '';
        const icons = {
            'patientcare': 'fa-heart',
            'info': 'fa-info-circle',
            'noreply': 'fa-bell',
            'providers': 'fa-hospital-o'
        };

        accounts.forEach(function(account) {
            const icon = icons[account.account_type] || 'fa-envelope';
            const activeClass = account.is_active == 1 ? '' : 'inactive';
            const statusBadge = account.is_active == 1 
                ? '<span class="label label-success status-badge">Activa</span>'
                : '<span class="label label-default status-badge">Inactiva</span>';
            
            const lastTest = account.last_test_date 
                ? '<small><i class="fa fa-clock-o"></i> Última prueba: ' + account.last_test_date + '</small>'
                : '<small class="text-muted">Sin probar</small>';

            const testStatus = account.last_test_status == 'success'
                ? '<span class="label label-sm label-success"><i class="fa fa-check"></i> Exitosa</span>'
                : (account.last_test_status == 'failed' 
                    ? '<span class="label label-sm label-danger"><i class="fa fa-times"></i> Fallida</span>'
                    : '');

            html += `
            <div class="col-md-6">
                <div class="portlet light bordered account-card ${activeClass}">
                    ${statusBadge}
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="fa ${icon} font-green"></i>
                            <span class="caption-subject bold uppercase">${account.display_name}</span>
                            <br>
                            <small class="text-muted">${account.email_address}</small>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <p>${account.description || 'Cuenta remitente sin descripción funcional registrada.'}</p>
                        <div class="row">
                            <div class="col-xs-6">
                                <small class="text-muted">Usuario de autenticación:</small><br>
                                <strong>${account.smtp_username}</strong>
                            </div>
                            <div class="col-xs-6">
                                <small class="text-muted">Responder a:</small><br>
                                <strong>${account.reply_to || 'N/A'}</strong>
                            </div>
                        </div>
                        <div class="margin-top-10">
                            ${lastTest} ${testStatus}
                        </div>
                        <div class="test-result" id="test-result-${account.id}"></div>
                        <div class="margin-top-10">
                            <button class="btn btn-sm btn-info" onclick="editAccount(${account.id})">
                                <i class="fa fa-edit"></i> Editar cuenta
                            </button>
                            <button class="btn btn-sm btn-success" onclick="testAccount(${account.id})">
                                <i class="fa fa-check-circle"></i> Validar conexión
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="sendTestEmail(${account.id})">
                                <i class="fa fa-paper-plane"></i> Enviar prueba
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        });

        $('#accounts-container').html(html);
    }

    function editAccount(id) {
        $.ajax({
            url: 'ajax/email_settings.php',
            type: 'POST',
            data: { action: 'get', id: id },
            dataType: 'json',
            success: function(response) {
                if(response.ok) {
                    const account = response.data;
                    $('#account_id').val(account.id);
                    $('#account_type').val(account.account_type);
                    $('#display_account_type').text(account.display_name);
                    $('#email_address').val(account.email_address);
                    $('#display_name').val(account.display_name);
                    $('#smtp_username').val(account.smtp_username);
                    $('#smtp_password').val(''); // No mostrar contraseña
                    $('#reply_to').val(account.reply_to);
                    $('#description').val(account.description);
                    $('#is_active').prop('checked', account.is_active == 1);
                    $('#editModal').modal('show');
                } else {
                    showAlert('error', response.message);
                }
            }
        });
    }

    function saveAccount() {
        const formData = $('#form-account').serialize() + '&action=update';
        
        $.ajax({
            url: 'ajax/email_settings.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if(response.ok) {
                    showAlert('success', 'Configuración de la cuenta actualizada exitosamente');
                    $('#editModal').modal('hide');
                    loadAccounts();
                } else {
                    showAlert('error', response.message);
                }
            },
            error: function() {
                showAlert('error', 'Error al guardar la configuración');
            }
        });
    }

    function testAccount(id) {
        const $btn = event.target;
        const originalHtml = $btn.innerHTML;
        $btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Validando...';
        $btn.disabled = true;

        $.ajax({
            url: 'ajax/email_settings.php',
            type: 'POST',
            data: { action: 'test_connection', id: id },
            dataType: 'json',
            success: function(response) {
                const $result = $('#test-result-' + id);
                $result.removeClass('success error').show();
                
                if(response.ok) {
                    $result.addClass('success').html('<i class="fa fa-check-circle"></i> ' + response.message);
                    showAlert('success', response.message);
                } else {
                    $result.addClass('error').html('<i class="fa fa-times-circle"></i> ' + response.message);
                    showAlert('error', response.message);
                }
                
                setTimeout(function() { $result.fadeOut(); }, 5000);
                loadAccounts(); // Recargar para actualizar fecha de test
            },
            complete: function() {
                $btn.innerHTML = originalHtml;
                $btn.disabled = false;
            }
        });
    }

    function sendTestEmail(id) {
        const email = prompt('Ingresa el email de destino para la validación de salida:');
        if(!email) return;

        const $btn = event.target;
        const originalHtml = $btn.innerHTML;
        $btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando prueba...';
        $btn.disabled = true;

        $.ajax({
            url: 'ajax/email_settings.php',
            type: 'POST',
            data: { 
                action: 'send_test_email', 
                id: id,
                test_email: email
            },
            dataType: 'json',
            success: function(response) {
                if(response.ok) {
                    showAlert('success', 'Correo de prueba enviado exitosamente a ' + email);
                } else {
                    showAlert('error', response.message);
                }
            },
            complete: function() {
                $btn.innerHTML = originalHtml;
                $btn.disabled = false;
            }
        });
    }

    function testAllAccounts() {
        showAlert('info', 'Validando todas las cuentas activas...');
        
        $.ajax({
            url: 'ajax/email_settings.php',
            type: 'POST',
            data: { action: 'test_all' },
            dataType: 'json',
            success: function(response) {
                if(response.ok) {
                    let message = `Resultados de validación:<br>
                        ✅ Exitosas: ${response.data.success}<br>
                        ❌ Fallidas: ${response.data.failed}`;
                    showAlert(response.data.failed == 0 ? 'success' : 'warning', message);
                    loadAccounts();
                } else {
                    showAlert('error', response.message);
                }
            }
        });
    }

    function togglePassword() {
        const $input = $('#smtp_password');
        const $icon = $('.password-toggle');
        
        if($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    }

    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const html = `
        <div class="alert ${alertClass} alert-dismissible fade in">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            ${message}
        </div>`;
        
        $('#alert-container').html(html);
        
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
    </script>
</body>
</html>

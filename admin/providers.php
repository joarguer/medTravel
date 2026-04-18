<?php
include('include/include.php');
if (!function_exists('is_role_admin_session') || !is_role_admin_session()) {
    http_response_code(403);
    require __DIR__ . '/error_403.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?php echo $title;?> - Prestadores Médicos</title>
    <?php echo $global_first_style;?>
    <?php echo $theme_global_style;?>
    <?php echo $theme_layout_style;?>
    <link href="../../assets/global/plugins/jquery-multi-select/css/multi-select.css" rel="stylesheet" type="text/css" />
    <style>
        .provider-section {
            margin-bottom: 24px;
            padding: 18px 18px 8px;
            border: 1px solid #e7ecf1;
            border-radius: 6px;
            background: #fcfdff;
        }

        .provider-section-title {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 700;
            color: #2f4050;
        }

        .provider-section-help {
            margin: 0 0 16px;
            color: #6c8296;
        }

        .provider-multiselect-header {
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #2f4050;
            background: #f8fafc;
            border-bottom: 1px solid #e7ecf1;
        }

        .provider-multiselect-field .ms-container {
            width: 100%;
        }

        .provider-multiselect-field .ms-selectable,
        .provider-multiselect-field .ms-selection {
            width: calc(50% - 8px);
        }

        .provider-multiselect-field .ms-list {
            min-height: 220px;
        }

        .provider-inline-status {
            margin-top: 10px;
        }

        .provider-doc-checklist {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -8px;
        }

        .provider-doc-item {
            width: 50%;
            padding: 0 8px 12px;
        }

        .provider-doc-card {
            min-height: 92px;
            padding: 12px 14px;
            border: 1px solid #e7ecf1;
            border-radius: 5px;
            background: #fff;
        }

        .provider-doc-card strong {
            display: block;
            margin-bottom: 4px;
            color: #2f4050;
        }

        .provider-doc-card p {
            margin: 0;
            font-size: 12px;
            color: #6c8296;
        }

        .provider-doc-meta {
            margin-bottom: 6px;
        }

        .provider-doc-meta .label {
            margin-right: 6px;
        }

        .provider-doc-actions {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .providers-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .providers-filter-group .btn {
            min-width: 110px;
        }

        .providers-view-note {
            margin-bottom: 16px;
        }

        .provider-archived-row {
            background: #fcf8e3;
        }

        .archive-impact-grid {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -8px;
        }

        .archive-impact-item {
            width: 50%;
            padding: 0 8px 12px;
        }

        .archive-impact-card {
            min-height: 88px;
            padding: 12px 14px;
            border: 1px solid #e7ecf1;
            border-radius: 5px;
            background: #fff;
        }

        .archive-impact-card strong {
            display: block;
            margin-bottom: 4px;
            color: #2f4050;
        }

        .archive-impact-value {
            font-size: 22px;
            font-weight: 700;
            color: #d35400;
            line-height: 1.1;
        }

        .archive-impact-help {
            margin-top: 4px;
            font-size: 12px;
            color: #6c8296;
        }

        .archive-confirm-help {
            margin-top: 6px;
            font-size: 12px;
            color: #6c8296;
        }

        @media (max-width: 991px) {
            .provider-doc-item {
                width: 100%;
            }

            .archive-impact-item {
                width: 100%;
            }
        }
    </style>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
    <div class="wrapper">
        <header class="page-header">
            <nav class="navbar mega-menu" role="navigation">
                <div class="container-fluid">
                    <?php echo $top_header;?>
                    <?php echo $top_header_2;?>
                </div>
            </nav>
        </header>

        <div class="container-fluid">
            <div class="page-content">
                <div class="breadcrumbs">
                    <h1>Prestadores Médicos</h1>
                    <ol class="breadcrumb">
                        <li><a href="#">Site</a></li>
                        <li class="active">Prestadores Médicos</li>
                    </ol>
                </div>

                <div class="page-content-container">
                    <div class="page-content-row">
                        <div class="page-sidebar">
                            <nav class="navbar" role="navigation">
                                <ul class="nav navbar-nav">
                                    <li class="active"><a href="providers.php"><i class="icon-list"></i> Prestadores Médicos</a></li>
                                </ul>
                            </nav>
                        </div>
                        <div class="page-content-col">
                            <div class="portlet light ">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <i class="icon-list theme-font"></i>
                                        <span class="caption-subject font-dark bold uppercase">Prestadores Médicos</span>
                                    </div>
                                    <div class="actions">
                                        <a id="btn-new-provider" class="btn btn-primary">Nuevo prestador médico</a>
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="alert alert-info">
                                        <strong>Onboarding médico canónico:</strong> aquí se crea el prestador médico y su cuenta owner/admin inicial.
                                        <br>
                                        <span class="small">Los prestadores complementarios se administran en <strong>Proveedores Complementarios</strong>; esta pantalla queda reservada al dominio médico.</span>
                                        <br>
                                        <span class="small">Si el provider es de tipo médico/persona, el espejo técnico owner/admin → provider_medical_staff se materializa internamente como efecto del onboarding administrativo central. No sustituye la gestión operativa de staff.</span>
                                    </div>
                                    <div class="providers-toolbar">
                                        <div class="btn-group providers-filter-group" data-toggle="buttons">
                                            <label class="btn btn-default active">
                                                <input type="radio" name="provider-view-filter" value="active" autocomplete="off" checked> Activos
                                            </label>
                                            <label class="btn btn-default">
                                                <input type="radio" name="provider-view-filter" value="archived" autocomplete="off"> Archivados
                                            </label>
                                            <label class="btn btn-default">
                                                <input type="radio" name="provider-view-filter" value="all" autocomplete="off"> Todos
                                            </label>
                                        </div>
                                        <div class="text-muted small" id="providers-filter-caption">Vista actual: prestadores activos.</div>
                                    </div>
                                    <div class="alert alert-warning providers-view-note" id="providers-view-note">
                                        <strong>Prestadores activos:</strong> aquí ves la operación vigente. Archivar saca al prestador de esta vista sin borrar su historial, documentos, bookings ni relaciones.
                                    </div>
                                    <table class="table table-striped table-bordered" id="tbl-providers">
                                        <thead>
                                            <tr>
                                                <th>Prestador / Owner admin</th>
                                                <th>Tipo</th>
                                                <th>Clasificación</th>
                                                <th>Ciudad</th>
                                                <th>Verificado</th>
                                                <th>Activo</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php echo $footer;?>
            </div>
        </div>

        <?php echo $sider_bar;?>
        <?php echo $theme_layout_script;?>
        <script src="../../assets/global/plugins/jquery-multi-select/js/jquery.multi-select.js" type="text/javascript"></script>
        <script src="js/providers.js" type="text/javascript"></script>

        <div id="providerModal" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background:#f7f7f7; border-bottom:1px solid #ebebeb;">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                        <h4 class="modal-title"><strong id="provider-modal-title">Alta de prestador médico</strong></h4>
                    </div>
                    <div class="modal-body">
                        <form id="form-provider">
                            <input type="hidden" name="id" id="prov-id" />
                            <div class="alert alert-info" id="provider-modal-intro">
                                Este flujo crea o actualiza el <strong>prestador médico</strong> y su <strong>cuenta owner/admin inicial</strong>.
                            </div>

                            <div class="provider-section">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4 class="provider-section-title"><i class="fa fa-hospital-o"></i> A. Datos del prestador médico</h4>
                                        <p class="provider-section-help">Información institucional y operativa del prestador dentro del dominio médico canónico.</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Tipo de prestador</label>
                                            <select id="prov-type" name="type" class="form-control select2me"><option value="medico">Médico / persona</option><option value="clinica">Clínica / institución</option></select>
                                            <span class="help-block">Define si el onboarding corresponde a una persona médica o a una clínica/institución.</span>
                                        </div>
                                        <div class="form-group">
                                            <label>Dominio</label>
                                            <input type="text" class="form-control" value="Prestador médico" disabled />
                                            <input type="hidden" name="kind" id="prov-kind" value="medical" />
                                            <span class="help-block" id="prov-kind-help">Esta pantalla administra exclusivamente prestadores médicos.</span>
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre del prestador médico</label>
                                            <input type="text" class="form-control" name="name" id="prov-name" placeholder="Nombre comercial o visible del prestador" required />
                                        </div>
                                        <div class="form-group">
                                            <label>Razón social</label>
                                            <input type="text" class="form-control" name="legal_name" id="prov-legal-name" placeholder="Razón social / nombre legal" />
                                            <span class="help-block">Nombre legal o fiscal de la empresa o del profesional.</span>
                                        </div>
                                        <div class="form-group">
                                            <label>Ciudad principal</label>
                                            <input type="text" class="form-control" name="city" id="prov-city" placeholder="Ej. Armenia, Quindío" />
                                        </div>
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <input type="text" class="form-control" name="address" id="prov-address" placeholder="Dirección operativa o sede principal" />
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono institucional</label>
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                                                <input type="text" class="form-control" name="phone" id="prov-phone" placeholder="Número visible del prestador" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Email general del prestador</label>
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-envelope"></i></span>
                                                <input type="email" class="form-control" name="email" id="prov-email" placeholder="contacto@prestador.com" />
                                            </div>
                                            <span class="help-block">Email general o institucional del prestador. No reemplaza el acceso del owner/admin.</span>
                                        </div>
                                        <div class="form-group">
                                            <label>Sitio web</label>
                                            <input type="text" class="form-control" name="website" id="prov-website" placeholder="https://..." />
                                        </div>
                                        <div class="form-group">
                                            <label>Descripción operativa</label>
                                            <textarea class="form-control" name="description" id="prov-desc" rows="5" placeholder="Resumen visible o administrativo del prestador"></textarea>
                                        </div>
                                        <div class="form-group provider-inline-status">
                                            <label class="mr10"><input type="checkbox" name="is_verified" id="prov-verified"> Verificado</label>
                                            <label><input type="checkbox" name="is_active" id="prov-active" checked> Activo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="provider-section">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4 class="provider-section-title"><i class="fa fa-user-circle"></i> B. Owner/admin inicial</h4>
                                        <p class="provider-section-help">Identidad administrativa principal del prestador. Este usuario nace desde providers.php y recibe la invitación segura de acceso.</p>
                                        <div class="alert alert-info" id="owner-summary">
                                            <strong id="owner-summary-title">Cuenta owner/admin inicial</strong>
                                            <div id="owner-summary-text" class="small">Al guardar este alta se creará también la cuenta owner/admin inicial del prestador médico.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nombre completo owner/admin <span class="required" id="owner-name-required">*</span></label>
                                            <input type="text" class="form-control" name="owner_name" id="prov-owner-name" placeholder="Nombre y apellido del owner/admin inicial" />
                                        </div>
                                        <div class="form-group">
                                            <label>Email owner/admin inicial <span class="required" id="owner-email-required">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-envelope"></i></span>
                                                <input type="email" class="form-control" name="owner_email" id="prov-owner-email" placeholder="Email personal del owner/admin inicial" />
                                            </div>
                                            <span class="help-block" id="owner-email-help">Este email será la identidad de acceso del owner/admin y se usará para enviar la invitación segura de set-password. No reemplaza el email general del prestador.</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Cargo o rol visible</label>
                                            <input type="text" class="form-control" name="owner_role" id="prov-owner-role" placeholder="Ej. Director médico, gerente, founder" />
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono de contacto del owner/admin</label>
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                                                <input type="text" class="form-control" name="owner_phone" id="prov-owner-phone" placeholder="Teléfono directo del responsable" />
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Ciudad del owner/admin</label>
                                            <input type="text" class="form-control" name="owner_city" id="prov-owner-city" placeholder="Ciudad base del responsable" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="provider-section">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4 class="provider-section-title"><i class="fa fa-tags"></i> C. Categorías</h4>
                                        <p class="provider-section-help">Selecciona las categorías médicas con las que se clasificará inicialmente este prestador.</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group provider-multiselect-field" style="margin-bottom:0;">
                                            <select id="prov-categories" multiple class="form-control" size="6"></select>
                                        </div>
                                        <span class="help-block">Estas categorías ayudan a ubicar el prestador dentro del catálogo médico y su alcance comercial.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="provider-section">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4 class="provider-section-title"><i class="fa fa-stethoscope"></i> D. Servicios habilitados del prestador</h4>
                                        <p class="provider-section-help">Selecciona los servicios médicos habilitados con los que arranca el prestador en MedTravel.</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group provider-multiselect-field" style="margin-bottom:0;">
                                            <select id="prov-services" multiple class="form-control" size="6"></select>
                                        </div>
                                        <span class="help-block">Cada servicio habilitado puede usarse después en ofertas, staff y operación clínica del prestador.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="provider-section">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4 class="provider-section-title"><i class="fa fa-folder-open"></i> E. Archivos y compliance documental</h4>
                                        <p class="provider-section-help">La carga documental vive en <strong>Verificación de Prestadores</strong>. Aquí se muestran las opciones que el prestador debe completar y el acceso directo a esa consola.</p>
                                        <div class="alert alert-warning" id="provider-documents-summary">
                                            <strong>Checklist documental estándar:</strong> después del alta, completa y evidencia estos documentos desde la consola de verificación.
                                        </div>
                                        <div class="provider-doc-checklist" id="provider-doc-checklist"></div>
                                        <div class="provider-doc-actions">
                                            <button type="button" class="btn btn-default" id="prov-docs-manage" disabled>
                                                <i class="fa fa-shield"></i> Ir a Verificación de Prestadores
                                            </button>
                                            <span class="text-muted small" id="prov-docs-help">Guarda primero el prestador para habilitar la carga y validación de documentos.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="button" id="prov-save" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="providerArchiveModal" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background:#fff6e5; border-bottom:1px solid #f0d8a8;">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
                        <h4 class="modal-title"><strong>Archivar prestador médico</strong></h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="archive-provider-id" />
                        <input type="hidden" id="archive-provider-name" />

                        <div class="alert alert-danger">
                            <strong id="archive-provider-label">Este prestador dejará de aparecer en la operación activa.</strong>
                            <ul style="margin-top:10px; padding-left:18px;">
                                <li>Su acceso al panel puede quedar inhabilitado por quedar fuera de la operación activa.</li>
                                <li>Sus datos históricos, documentos, bookings y relaciones NO se borran.</li>
                                <li>La acción se puede revertir desde la vista <strong>Archivados</strong>.</li>
                                <li>Esta acción no elimina físicamente el prestador ni sus archivos.</li>
                            </ul>
                        </div>

                        <div class="provider-section" style="margin-bottom:18px;">
                            <h4 class="provider-section-title"><i class="fa fa-exclamation-triangle"></i> Impacto detectado</h4>
                            <p class="provider-section-help">Resumen real de relaciones encontradas antes de archivar.</p>
                            <div id="archive-impact-grid" class="archive-impact-grid"></div>
                        </div>

                        <div class="form-group">
                            <label for="archive-reason">Motivo de archivado <span class="required">*</span></label>
                            <textarea id="archive-reason" class="form-control" rows="4" placeholder="Explica por qué este prestador sale de operación activa" required></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label for="archive-confirm-text">Confirmación fuerte <span class="required">*</span></label>
                            <input type="text" id="archive-confirm-text" class="form-control" placeholder="Escribe ARCHIVAR o el nombre exacto del prestador" />
                            <div class="archive-confirm-help">Debes escribir <strong>ARCHIVAR</strong> o el nombre exacto del prestador para confirmar.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-warning" id="confirm-provider-archive">Archivar prestador</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

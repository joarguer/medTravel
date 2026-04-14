// Variables globales
var tablaVerificacion;
var verificationCtx = window.PROVIDER_VERIFICATION_CTX || {};
var initialProviderOpened = false;

// Inicializar cuando el documento esté listo
$(document).ready(function() {
    initDataTable();
    initToastr();
});

function escapeHtml(text) {
    return $('<div>').text(text == null ? '' : String(text)).html();
}

function renderSummaryMetrics(rows) {
    var safeRows = Array.isArray(rows) ? rows : [];
    var verified = 0;
    var pending = 0;
    var trustAccum = 0;

    safeRows.forEach(function(row) {
        var status = String(row.verification_status || 'pending');
        var trust = parseInt(row.trust_score || 0, 10);
        trustAccum += isNaN(trust) ? 0 : trust;

        if (status === 'verified') {
            verified += 1;
        } else if (status === 'pending' || status === 'in_review') {
            pending += 1;
        }
    });

    var averageTrust = safeRows.length ? Math.round(trustAccum / safeRows.length) : 0;

    $('#verification-metric-total').text(safeRows.length);
    $('#verification-metric-verified').text(verified);
    $('#verification-metric-pending').text(pending);
    $('#verification-metric-trust').text(averageTrust + '%');
}

function renderProviderContactCell(row) {
    var email = row.email ? escapeHtml(row.email) : '<span class="text-muted">Sin email</span>';
    var phone = row.telefono ? escapeHtml(row.telefono) : '<span class="text-muted">Sin teléfono</span>';
    return ''
        + '<div class="verification-provider-cell">'
        + '  <strong>' + escapeHtml(row.provider_name || 'Prestador sin nombre') + '</strong>'
        + '  <span class="verification-provider-meta"><i class="fa fa-hashtag"></i>ID ' + escapeHtml(row.id || '') + '</span>'
        + '  <span class="verification-provider-meta"><i class="fa fa-envelope"></i>' + email + '</span>'
        + '  <span class="verification-provider-meta"><i class="fa fa-phone"></i>' + phone + '</span>'
        + '</div>';
}

function renderStatusTrustCell(row) {
    var trust = parseInt(row.trust_score || 0, 10);
    if (isNaN(trust)) trust = 0;
    var level = row.verification_level ? escapeHtml(row.verification_level) : 'basic';
    return ''
        + '<div class="verification-status-cell">'
        + getStatusBadge(row.verification_status)
        + '<span class="verification-status-meta">Nivel: <strong>' + level + '</strong></span>'
        + '<span class="label label-' + getTrustColor(trust) + '">Trust ' + trust + '%</span>'
        + '</div>';
}

function renderChecklistCell(row) {
    var checked = parseInt(row.checked_items || 0, 10);
    var total = parseInt(row.total_items || 0, 10);
    var percent = parseInt(row.completion_percent || 0, 10);
    if (isNaN(checked)) checked = 0;
    if (isNaN(total)) total = 0;
    if (isNaN(percent)) percent = 0;

    return ''
        + '<div class="verification-checklist-cell">'
        + '  <div class="progress">'
        + '    <div class="progress-bar progress-bar-' + getProgressColor(percent) + '" role="progressbar" style="width: ' + percent + '%"></div>'
        + '  </div>'
        + '  <span class="label label-default">' + percent + '% completado</span>'
        + '  <span class="verification-checklist-meta">' + checked + '/' + total + ' ítems documentales</span>'
        + '</div>';
}

function renderVerificationDateCell(row) {
    var dateLabel = row.verified_at ? formatDate(row.verified_at) : 'Sin verificación final';
    var expiresLabel = row.expires_at ? formatDate(row.expires_at) : 'Sin expiración registrada';
    return ''
        + '<div class="verification-date-cell">'
        + '  <span class="verification-date-meta"><i class="fa fa-calendar-check-o"></i>' + escapeHtml(dateLabel) + '</span>'
        + '  <span class="verification-date-meta"><i class="fa fa-hourglass-half"></i>' + escapeHtml(expiresLabel) + '</span>'
        + '</div>';
}

function providerContactSortValue(row) {
    return [row.provider_name || '', row.email || '', row.telefono || '', row.id || 0].join(' ');
}

function statusTrustSortValue(row) {
    return [row.verification_status || '', row.verification_level || '', row.trust_score || 0].join(' ');
}

function checklistSortValue(row) {
    return [row.completion_percent || 0, row.checked_items || 0, row.total_items || 0].join(' ');
}

function verificationDateSortValue(row) {
    return [row.verified_at || '', row.expires_at || ''].join(' ');
}

// Inicializar toastr
function initToastr() {
    toastr.options = {
        "closeButton": true,
        "positionClass": "toast-top-right",
        "timeOut": "5000"
    };
}

// Inicializar DataTable
function initDataTable() {
    tablaVerificacion = $('#tabla_verificacion').DataTable({
        "processing": true,
        "ajax": {
            "url": "ajax/provider_verification.php",
            "type": "POST",
            "data": function(d) {
                d.tipo = 'get';
                if (verificationCtx.providerId) {
                    d.provider_id = verificationCtx.providerId;
                }
            },
            "dataSrc": function(json) {
                if (json.success) {
                    renderSummaryMetrics(json.data || []);
                    if (verificationCtx.providerId && !initialProviderOpened && json.data && json.data.length > 0) {
                        initialProviderOpened = true;
                        setTimeout(function() {
                            openVerificationModal(json.data[0].id, json.data[0].provider_name);
                        }, 0);
                    }
                    return json.data;
                }

                toastr.error(json.message || 'Error al cargar datos');
                renderSummaryMetrics([]);
                return [];
            }
        },
        "columns": [
            {
                "data": null,
                "render": function(data, type, row) {
                    if (type !== 'display') {
                        return providerContactSortValue(row);
                    }
                    return renderProviderContactCell(row);
                }
            },
            {
                "data": null,
                "render": function(data, type, row) {
                    if (type !== 'display') {
                        return statusTrustSortValue(row);
                    }
                    return renderStatusTrustCell(row);
                }
            },
            {
                "data": null,
                "render": function(data, type, row) {
                    if (type !== 'display') {
                        return checklistSortValue(row);
                    }
                    return renderChecklistCell(row);
                }
            },
            {
                "data": null,
                "render": function(data, type, row) {
                    if (type !== 'display') {
                        return verificationDateSortValue(row);
                    }
                    return renderVerificationDateCell(row);
                }
            },
            {
                "data": null,
                "orderable": false,
                "render": function(data, type, row) {
                    var providerName = JSON.stringify(String(row.provider_name || ''));
                    return ''
                        + '<div class="verification-actions-cell">'
                        + '  <button class="btn btn-xs btn-primary" onclick=\'openVerificationModal(' + row.id + ', ' + providerName + ')\' title="Gestionar compliance">'
                        + '    <i class="fa fa-shield"></i> Gestionar'
                        + '  </button>'
                        + '</div>';
                }
            }
        ],
        "autoWidth": false,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
        },
        "order": [[0, "asc"]],
        "pageLength": 25,
        "columnDefs": [
            { "targets": 0, "width": "34%" },
            { "targets": 1, "width": "18%" },
            { "targets": 2, "width": "22%" },
            { "targets": 3, "width": "16%" },
            { "targets": 4, "width": "10%", "className": "text-center" }
        ]
    });
}

function updateChecklistInitState(items) {
    var btn = $('#btnInitializeChecklist');
    if (!btn.length) return;

    if (items.length > 0) {
        btn.prop('disabled', true)
            .removeClass('btn-success')
            .addClass('btn-default')
            .html('<i class="fa fa-check"></i> Checklist estándar ya inicializado');
    } else {
        btn.prop('disabled', false)
            .removeClass('btn-default')
            .addClass('btn-success')
            .html('<i class="fa fa-plus"></i> Inicializar checklist estándar');
    }
}

// Obtener badge de status
function getStatusBadge(status) {
    var badges = {
        'pending': '<span class="label label-default">Pendiente</span>',
        'in_review': '<span class="label label-warning">En Revisión</span>',
        'verified': '<span class="label label-success"><i class="fa fa-check"></i> Verificado</span>',
        'rejected': '<span class="label label-danger">Rechazado</span>',
        'suspended': '<span class="label label-dark">Suspendido</span>'
    };
    return badges[status] || '<span class="label label-default">' + status + '</span>';
}

// Color según trust score
function getTrustColor(score) {
    if (score >= 80) return 'success';
    if (score >= 50) return 'warning';
    return 'danger';
}

// Color de barra de progreso
function getProgressColor(percent) {
    if (percent >= 80) return 'success';
    if (percent >= 50) return 'info';
    return 'danger';
}

// Formatear fecha
function formatDate(dateString) {
    if (!dateString) return '';
    var date = new Date(dateString);
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var year = date.getFullYear();
    return day + '/' + month + '/' + year;
}

// Abrir modal de verificación
function openVerificationModal(providerId, providerName) {
    $('#provider_id').val(providerId);
    $('#provider_name').text(providerName);
    
    // Cargar datos de verificación
    $.ajax({
        url: 'ajax/provider_verification.php',
        type: 'POST',
        data: { tipo: 'get_verification', provider_id: providerId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var verification = response.verification;
                var items = response.items;
                
                // Llenar datos de verificación
                $('#verification_status').val(verification.status);
                $('#verification_level').val(verification.verification_level);
                $('#admin_notes').val(verification.admin_notes || '');
                
                // Actualizar badge y score
                $('#verification_status_badge').removeClass().addClass('label verification-badge ' + getStatusClass(verification.status));
                $('#verification_status_badge').text(getStatusText(verification.status));
                $('#trust_score_display').text(verification.trust_score || 0);
                $('#progress_bar').css('width', '0%');
                $('#progress_text').text('0%');
                
                // Actualizar barra de progreso
                if (items.length > 0) {
                    var checked = items.filter(item => item.is_checked == 1).length;
                    var percent = Math.round((checked / items.length) * 100);
                    $('#progress_bar').css('width', percent + '%');
                    $('#progress_text').text(percent + '%');
                }
                
                // Renderizar checklist
                renderChecklist(items);
                updateChecklistInitState(items);
                
                // Cargar documentos del proveedor
                loadProviderDocuments(providerId);
                
                $('#modalVerificacion').modal('show');
            } else {
                toastr.error(response.message || 'Error al cargar verificación');
            }
        },
        error: function() {
            toastr.error('Error de conexión');
        }
    });
}

// Renderizar checklist
function renderChecklist(items) {
    var html = '';
    
    if (items.length === 0) {
        html = '<div class="alert alert-info">Este prestador médico aún no tiene checklist de compliance inicializado.</div>';
    } else {
        var categories = {
            'legal': 'Legal',
            'medical': 'Médico',
            'facilities': 'Instalaciones',
            'identity': 'Identidad',
            'insurance': 'Seguros',
            'other': 'Otros'
        };
        
        var currentCategory = '';
        
        items.forEach(function(item) {
            // Encabezado de categoría
            if (item.item_category !== currentCategory) {
                currentCategory = item.item_category;
                html += '<h5 class="form-section"><i class="fa fa-folder"></i> ' + (categories[currentCategory] || currentCategory) + '</h5>';
            }
            
            var checkedClass = item.is_checked == 1 ? 'checked' : '';
            var requiredLabel = item.is_required == 1 ? '<small class="label label-sm label-danger checklist-required">Obligatorio</small>' : '';
            
            html += `
                <div class="checklist-item ${checkedClass}">
                    <div class="row">
                        <div class="col-md-8 col-sm-8">
                            <label class="mt-checkbox mt-checkbox-outline checklist-checkbox">
                                <input type="checkbox" 
                                       class="checklist-toggle"
                                       data-item-id="${item.id}"
                                       ${item.is_checked == 1 ? 'checked' : ''}
                                       onchange="toggleItem(${item.id}, this.checked, this)">
                                <span></span>
                                <strong>${item.item_label}</strong>
                                ${requiredLabel}
                            </label>
                            <p class="font-grey-mint checklist-description">${item.item_description || ''}</p>
                            ${item.checked_at ? '<small class="font-green-jungle"><i class="fa fa-check"></i> Verificado: ' + formatDate(item.checked_at) + '</small>' : ''}
                        </div>
                        <div class="col-md-4 col-sm-4 text-right">
                            <button class="btn btn-sm blue btn-outline" onclick="attachEvidence(${item.id})" title="Adjuntar evidencia">
                                <i class="fa fa-paperclip"></i> Evidencia
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $('#checklist_container').html(html);
}

// Clase de badge según status
function getStatusClass(status) {
    var classes = {
        'pending': 'label-default',
        'in_review': 'label-warning',
        'verified': 'label-success',
        'rejected': 'label-danger',
        'suspended': 'label-dark'
    };
    return classes[status] || 'label-default';
}

// Texto de status
function getStatusText(status) {
    var texts = {
        'pending': 'Pendiente',
        'in_review': 'En Revisión',
        'verified': 'Verificado',
        'rejected': 'Rechazado',
        'suspended': 'Suspendido'
    };
    return texts[status] || status;
}

// Inicializar checklist estándar
function initializeChecklist() {
    var providerId = $('#provider_id').val();
    
    if (!providerId) {
        toastr.error('ID de proveedor no válido');
        return;
    }
    
    if (!confirm('¿Crear checklist estándar con 11 items de verificación?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/provider_verification.php',
        type: 'POST',
        data: { tipo: 'initialize_checklist', provider_id: providerId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                // Recargar modal
                var providerName = $('#provider_name').text();
                openVerificationModal(providerId, providerName);
            } else {
                toastr.error(response.message);
            }
        },
        error: function() {
            toastr.error('Error de conexión');
        }
    });
}

// Toggle item del checklist
function toggleItem(itemId, isChecked, checkboxEl) {
    if (checkboxEl) {
        $(checkboxEl).closest('.checklist-item').toggleClass('checked', !!isChecked);
    }

    $.ajax({
        url: 'ajax/provider_verification.php',
        type: 'POST',
        data: { 
            tipo: 'toggle_item', 
            item_id: itemId,
            is_checked: isChecked ? 1 : 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Item actualizado');
                
                // Actualizar trust score
                if (response.trust_score !== undefined) {
                    $('#trust_score_display').text(response.trust_score);
                    
                    // Actualizar barra de progreso
                    var percent = Math.round((response.checked / response.total) * 100);
                    $('#progress_bar').css('width', percent + '%');
                    $('#progress_text').text(percent + '%');
                }
            } else {
                if (checkboxEl) {
                    checkboxEl.checked = !isChecked;
                    $(checkboxEl).closest('.checklist-item').toggleClass('checked', !!checkboxEl.checked);
                }
                toastr.error(response.message);
            }
        },
        error: function() {
            if (checkboxEl) {
                checkboxEl.checked = !isChecked;
                $(checkboxEl).closest('.checklist-item').toggleClass('checked', !!checkboxEl.checked);
            }
            toastr.error('Error de conexión');
        }
    });
}

// Guardar estado de verificación
function saveVerificationStatus() {
    var providerId = $('#provider_id').val();
    var status = $('#verification_status').val();
    var level = $('#verification_level').val();
    var notes = $('#admin_notes').val();
    
    $.ajax({
        url: 'ajax/provider_verification.php',
        type: 'POST',
        data: { 
            tipo: 'update_status',
            provider_id: providerId,
            status: status,
            verification_level: level,
            admin_notes: notes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#modalVerificacion').modal('hide');
                tablaVerificacion.ajax.reload();
            } else {
                toastr.error(response.message);
            }
        },
        error: function() {
            toastr.error('Error de conexión');
        }
    });
}

// Adjuntar evidencia - Abrir modal de upload
function attachEvidence(itemId) {
    var providerId = $('#provider_id').val();
    if (!providerId) {
        toastr.error('ID de proveedor no válido');
        return;
    }
    
    // Guardar itemId para referenciar después del upload
    $('#upload_item_id').val(itemId);
    $('#upload_provider_id').val(providerId);
    
    // Limpiar formulario
    $('#uploadDocumentForm')[0].reset();
    $('#uploadPreview').html('');
    
    // Abrir modal
    $('#modalUploadDocument').modal('show');
}

// Previsualizar archivo seleccionado
function previewFile() {
    var input = document.getElementById('document_file');
    var preview = $('#uploadPreview');
    var file = input.files[0];
    
    if (file) {
        var fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
        var fileName = file.name;
        var fileType = file.type;
        
        var icon = 'fa-file';
        if (fileType.includes('pdf')) icon = 'fa-file-pdf-o';
        else if (fileType.includes('image')) icon = 'fa-file-image-o';
        else if (fileType.includes('word')) icon = 'fa-file-word-o';
        
        preview.html(`
            <div class="alert alert-info">
                <i class="fa ${icon} fa-2x pull-left" style="margin-right: 10px;"></i>
                <strong>${fileName}</strong><br>
                <small>Tamaño: ${fileSize} MB | Tipo: ${fileType}</small>
            </div>
        `);
    }
}

// Subir documento
function uploadDocument() {
    var form = $('#uploadDocumentForm')[0];
    var formData = new FormData(form);
    
    // Agregar datos adicionales
    formData.append('provider_id', $('#upload_provider_id').val());
    formData.append('item_id', $('#upload_item_id').val());
    
    // Validar que hay archivo
    if (!$('#document_file')[0].files[0]) {
        toastr.error('Debe seleccionar un archivo');
        return;
    }
    
    // Deshabilitar botón
    var btnUpload = $('#btnUploadDocument');
    btnUpload.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Subiendo...');
    
    $.ajax({
        url: 'ajax/upload_document.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                toastr.success(response.message);
                $('#modalUploadDocument').modal('hide');
                
                // Recargar el modal de verificación para mostrar el documento
                var providerId = $('#upload_provider_id').val();
                var providerName = $('#provider_name').text();
                openVerificationModal(providerId, providerName);
            } else {
                toastr.error(response.message || 'Error al subir documento');
            }
        },
        error: function(xhr, status, error) {
            toastr.error('Error de conexión al subir documento');
            console.error('Upload error:', error);
        },
        complete: function() {
            btnUpload.prop('disabled', false).html('<i class="fa fa-upload"></i> Subir Documento');
        }
    });
}

// Cargar lista de documentos del proveedor
function loadProviderDocuments(providerId) {
    $.ajax({
        url: 'ajax/provider_documents.php?action=list&provider_id=' + providerId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.ok && response.data.length > 0) {
                var html = '<div class="mt-20"><h4>Documentos Adjuntos</h4><div class="table-responsive">';
                html += '<table class="table table-condensed table-hover">';
                html += '<thead><tr><th>Documento</th><th>Tipo</th><th>Tamaño</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
                
                $.each(response.data, function(i, doc) {
                    var verifiedBadge = doc.is_verified == 1 
                        ? '<span class="label label-success"><i class="fa fa-check"></i> Verificado</span>'
                        : '<span class="label label-default">Pendiente</span>';
                    
                    html += '<tr>';
                    html += '<td><i class="fa fa-file-o"></i> ' + doc.original_filename + '</td>';
                    html += '<td>' + doc.document_type + '</td>';
                    html += '<td>' + doc.file_size_formatted + '</td>';
                    html += '<td>' + verifiedBadge + '</td>';
                    html += '<td>';
                    html += '<a href="' + doc.download_url + '" target="_blank" class="btn btn-xs btn-primary" title="Descargar">';
                    html += '<i class="fa fa-download"></i></a> ';
                    html += '<button class="btn btn-xs btn-danger" onclick="deleteDocument(' + doc.id + ')" title="Eliminar">';
                    html += '<i class="fa fa-trash"></i></button>';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div></div>';
                $('#documents_list').html(html);
            } else {
                $('#documents_list').html('<div class="alert alert-info mt-20">No hay documentos adjuntos</div>');
            }
        },
        error: function() {
            $('#documents_list').html('<div class="alert alert-danger mt-20">Error al cargar documentos</div>');
        }
    });
}

// Eliminar documento
function deleteDocument(docId) {
    if (!confirm('¿Está seguro de eliminar este documento? Esta acción no se puede deshacer.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/provider_documents.php',
        type: 'POST',
        data: {
            action: 'delete',
            id: docId
        },
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                toastr.success(response.message);
                // Recargar lista de documentos
                var providerId = $('#provider_id').val();
                loadProviderDocuments(providerId);
            } else {
                toastr.error(response.message);
            }
        },
        error: function() {
            toastr.error('Error de conexión al eliminar');
        }
    });
}

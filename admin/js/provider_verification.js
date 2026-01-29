// Variables globales
var tablaVerificacion;

// Inicializar cuando el documento esté listo
$(document).ready(function() {
    initDataTable();
    initToastr();
});

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
            "data": { tipo: 'get' },
            "dataSrc": function(json) {
                if (json.success) {
                    return json.data;
                } else {
                    toastr.error(json.message || 'Error al cargar datos');
                    return [];
                }
            }
        },
        "columns": [
            { "data": "id" },
            { "data": "provider_name" },
            { "data": "email" },
            { 
                "data": "telefono",
                "render": function(data) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            { 
                "data": "verification_status",
                "render": function(data) {
                    return getStatusBadge(data);
                }
            },
            { 
                "data": "trust_score",
                "render": function(data) {
                    return '<span class="label label-' + getTrustColor(data) + '">' + data + '%</span>';
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    var percent = row.completion_percent || 0;
                    return `
                        <div class="progress" style="margin-bottom: 0;">
                            <div class="progress-bar progress-bar-${getProgressColor(percent)}" 
                                 role="progressbar" 
                                 style="width: ${percent}%">
                                ${percent}%
                            </div>
                        </div>
                        <small>${row.checked_items || 0}/${row.total_items || 0} items</small>
                    `;
                }
            },
            { 
                "data": "verified_at",
                "render": function(data) {
                    return data ? formatDate(data) : '<span class="text-muted">No verificado</span>';
                }
            },
            { 
                "data": null,
                "orderable": false,
                "render": function(data, type, row) {
                    return `
                        <button class="btn btn-xs btn-primary" onclick="openVerificationModal(${row.id}, '${row.provider_name}')" title="Verificar">
                            <i class="fa fa-shield"></i> Verificar
                        </button>
                    `;
                }
            }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
        },
        "order": [[5, "desc"]],
        "pageLength": 25
    });
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
                
                // Actualizar barra de progreso
                if (items.length > 0) {
                    var checked = items.filter(item => item.is_checked == 1).length;
                    var percent = Math.round((checked / items.length) * 100);
                    $('#progress_bar').css('width', percent + '%');
                    $('#progress_text').text(percent + '%');
                }
                
                // Renderizar checklist
                renderChecklist(items);
                
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
        html = '<div class="alert alert-info">No hay items en el checklist. Inicialice el checklist estándar.</div>';
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
            var checkedIcon = item.is_checked == 1 ? 'fa-check-square-o' : 'fa-square-o';
            var requiredLabel = item.is_required == 1 ? '<span class="label label-danger">Obligatorio</span>' : '';
            
            html += `
                <div class="checklist-item ${checkedClass}">
                    <div class="row">
                        <div class="col-md-8">
                            <label style="font-weight: normal; cursor: pointer;">
                                <input type="checkbox" 
                                       ${item.is_checked == 1 ? 'checked' : ''}
                                       onchange="toggleItem(${item.id}, this.checked)">
                                <i class="fa ${checkedIcon}" style="margin-left: 5px; margin-right: 5px;"></i>
                                <strong>${item.item_label}</strong> ${requiredLabel}
                            </label>
                            <p class="text-muted" style="margin-left: 25px; margin-bottom: 0;">${item.item_description || ''}</p>
                            ${item.checked_at ? '<small class="text-success">✓ Verificado: ' + formatDate(item.checked_at) + '</small>' : ''}
                        </div>
                        <div class="col-md-4 text-right">
                            <button class="btn btn-xs btn-info" onclick="attachEvidence(${item.id})" title="Adjuntar evidencia">
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
function toggleItem(itemId, isChecked) {
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
                toastr.error(response.message);
            }
        },
        error: function() {
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

// Adjuntar evidencia (placeholder - requiere implementación de upload)
function attachEvidence(itemId) {
    toastr.info('Funcionalidad de carga de documentos en desarrollo');
    // TODO: Implementar modal de upload de documentos
    // Vincular con provider_documents table
}

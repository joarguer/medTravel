// admin/js/medtravel_services.js - Frontend logic para gestión de servicios MedTravel
var servicesTable;
var serviceModal = $('#serviceModal');
var serviceForm = $('#serviceForm');
var medtravelCtx = window.MEDTRAVEL_SERVICES_CTX || {};
var canListProviders = !!medtravelCtx.canListProviders;
var scopedComplementaryProviderId = (medtravelCtx.isComplementary && medtravelCtx.serviceProviderId)
    ? parseInt(medtravelCtx.serviceProviderId, 10)
    : 0;

$(document).ready(function() {
    applyProviderScopeUI();
    initDataTable();
    initEventHandlers();
    loadCurrentExchangeRate(); // Cargar tasa desde BD
    loadProviders(); // Cargar catálogo de proveedores
    updateCommissionPreview();
});

function applyProviderScopeUI() {
    if (!scopedComplementaryProviderId) return;
    $('#btnNewProvider').hide();
    $('#provider_selector_wrapper .input-group').hide();
    $('#provider_selector_wrapper .help-block').first().hide();
    $('#provider_scope_hint').show();
}

// ===================================================================
// DATATABLE
// ===================================================================
function initDataTable() {
    servicesTable = $('#services_table').DataTable({
        ajax: {
            url: 'ajax/medtravel_services.php?action=list',
            dataSrc: 'data'
        },
        columns: [
            { data: 'id' },
            { 
                data: 'service_type',
                render: function(data, type, row) {
                    var badges = {
                        'flight': '<span class="service-type-badge badge-flight">✈️ Flight</span>',
                        'accommodation': '<span class="service-type-badge badge-accommodation">🏨 Hotel</span>',
                        'transport': '<span class="service-type-badge badge-transport">🚗 Transport</span>',
                        'meals': '<span class="service-type-badge badge-meals">🍽️ Meals</span>',
                        'support': '<span class="service-type-badge badge-support">🎧 Support</span>',
                        'other': '<span class="service-type-badge badge-other">📦 Other</span>'
                    };
                    return badges[data] || data;
                }
            },
            { 
                data: 'service_name',
                render: function(data, type, row) {
                    var featured = row.featured == 1 ? ' <i class="fa fa-star text-warning" title="Featured"></i>' : '';
                    return '<strong>' + data + '</strong>' + featured;
                }
            },
            { data: 'provider_name', defaultContent: '<em>N/A</em>' },
            { 
                data: 'cost_price',
                render: function(data, type, row) {
                    return row.currency + ' $' + parseFloat(data).toFixed(2);
                }
            },
            { 
                data: 'sale_price',
                render: function(data, type, row) {
                    return row.currency + ' $' + parseFloat(data).toFixed(2);
                }
            },
            { 
                data: 'commission_amount',
                render: function(data, type, row) {
                    var commission = parseFloat(data);
                    var percentage = parseFloat(row.commission_percentage);
                    var cssClass = commission >= 0 ? 'commission-positive' : 'commission-negative';
                    return '<span class="' + cssClass + '">' + 
                           row.currency + ' $' + commission.toFixed(2) + 
                           ' (' + percentage.toFixed(1) + '%)</span>';
                }
            },
            { 
                data: 'is_active',
                render: function(data, type, row) {
                    if (type === 'filter' || type === 'sort') {
                        return String(data);
                    }
                    return data == 1
                        ? '<span class="label label-success">Activo</span>'
                        : '<span class="label label-default">Inactivo</span>';
                }
            },
            { 
                data: 'availability_status',
                render: function(data, type, row) {
                    var statuses = {
                        'available': '<span class="status-available">● Available</span>',
                        'limited': '<span class="status-limited">● Limited</span>',
                        'unavailable': '<span class="status-unavailable">● Unavailable</span>',
                        'seasonal': '<span class="status-seasonal">● Seasonal</span>'
                    };
                    return statuses[data] || data;
                }
            },
            { 
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    var isActive = parseInt(row.is_active, 10) === 1;
                    var nextVal = isActive ? 0 : 1;
                    var toggleLabel = isActive ? 'DESACTIVAR' : 'ACTIVAR';
                    var toggleClass = isActive ? 'btn-warning' : 'btn-success';
                    return '<button class="btn btn-xs btn-primary" onclick="editService(' + row.id + ')" title="Edit">' +
                           '<i class="fa fa-edit"></i></button> ' +
                           '<button class="btn btn-xs ' + toggleClass + '" onclick="toggleStatus(' + row.id + ', ' + nextVal + ')" title="' + toggleLabel + '">' + toggleLabel + '</button> ' +
                           '<button class="btn btn-xs btn-danger" onclick="deleteService(' + row.id + ')" title="Delete">' +
                           '<i class="fa fa-trash"></i></button>';
                }
            }
        ],
        order: [[1, 'asc'], [7, 'desc'], [2, 'asc']],
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/English.json'
        }
    });
}

// ===================================================================
// EVENT HANDLERS
// ===================================================================
function initEventHandlers() {
    // Botón nuevo servicio
    $('#btnNewService').click(function() {
        openCreateModal();
    });
    
    // Botón nuevo proveedor
    $('#btnNewProvider').click(function() {
        openProviderModal();
    });
    
    // Selector de proveedor - auto-fill datos
    $('#provider_id').change(function() {
        onProviderSelect();
    });
    
    // Submit formulario
    serviceForm.submit(function(e) {
        e.preventDefault();
        saveService();
    });
    
    // Calcular costo USD y preview de comisión en tiempo real
    $('#cost_price_cop, #exchange_rate, #sale_price').on('input', function() {
        calculateCostUSD();
        updateCommissionPreview();
        validateFormRealTime();
    });
    
    // Validación en tiempo real de campos obligatorios
    $('#service_type, #service_name').on('input change', function() {
        validateFormRealTime();
    });
    
    // Validación de campos numéricos
    $('#stock_quantity, #booking_lead_time').on('input', function() {
        validateFormRealTime();
    });
    
    // Validación de email
    $('#provider_email').on('blur', function() {
        validateFormRealTime();
    });

    // Imagen del servicio
    $('#btnUploadImage').on('click', function() {
        openImagePicker();
    });

    $('#btnClearImage').on('click', function() {
        clearServiceImage();
    });
}

// ===================================================================
// APLICAR FILTROS
// ===================================================================
function applyFilters() {
    var type = $('#filter_type').val();
    var status = $('#filter_status').val();
    var availability = $('#filter_availability').val();
    
    servicesTable.columns(1).search(type);
    servicesTable.columns(7).search(status);
    servicesTable.columns(8).search(availability);
    servicesTable.draw();
}

// ===================================================================
// ABRIR MODAL PARA CREAR
// ===================================================================
function openCreateModal() {
    serviceForm[0].reset();
    $('#service_id').val('');
    $('#serviceModalTitle').text('Nuevo Servicio');
    $('#is_active').prop('checked', true);
    $('#image_url').val('');
    updateImagePreview('');
    
    // Cargar tasa actual desde BD
    loadCurrentExchangeRate();
    
    $('#cost_price_cop').val('0.00');
    $('#cost_price').val('0.00');
    $('#sale_price').val('0.00');
    $('#currency').val('USD');
    
    // Limpiar errores visuales
    $('.has-error').removeClass('has-error');
    $('.tab-error').removeClass('tab-error');
    
    // Deshabilitar botón Save hasta que se valide
    $('#btnSaveService').prop('disabled', true);

    if (scopedComplementaryProviderId) {
        $('#provider_id').val(String(scopedComplementaryProviderId)).prop('disabled', true);
        onProviderSelect();
    } else {
        $('#provider_id').prop('disabled', false);
    }
    
    updateCommissionPreview();
    serviceModal.modal('show');
}

// ===================================================================
// EDITAR SERVICIO
// ===================================================================
function editService(id) {
    $.ajax({
        url: 'ajax/medtravel_services.php?action=get&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                populateForm(response.data);
                $('#serviceModalTitle').text('Edit Service');
                serviceModal.modal('show');
            } else {
                toastr.error(response.message || 'Error loading service');
            }
        },
        error: function() {
            toastr.error('Server communication error');
        }
    });
}

// ===================================================================
// POPULAR FORMULARIO CON DATOS
// ===================================================================
function populateForm(data) {
    $('#service_id').val(data.id);
    
    // Basic Info
    $('#service_type').val(data.service_type);
    $('#service_name').val(data.service_name);
    $('#service_code').val(data.service_code);
    $('#short_description').val(data.short_description);
    $('#description').val(data.description);
    
    // Provider - cargar ID y auto-fill
    if(data.provider_id) {
        $('#provider_id').val(data.provider_id).trigger('change');
    } else {
        // Retrocompatibilidad: si no tiene provider_id, mostrar los datos que tenía
        $('#provider_id').val('');
        $('#provider_name_display').val(data.provider_name || '');
        $('#provider_contact_display').val(data.provider_contact || '');
        $('#provider_email_display').val(data.provider_email || '');
        $('#provider_phone_display').val(data.provider_phone || '');
    }
    if (scopedComplementaryProviderId) {
        $('#provider_id').val(String(scopedComplementaryProviderId)).prop('disabled', true);
        onProviderSelect();
    } else {
        $('#provider_id').prop('disabled', false);
    }
    $('#provider_notes').val(data.provider_notes);
    
    // Pricing
    $('#currency').val('USD');
    
    // Si tiene cost_price_cop guardado, usarlo; si no, calcular desde cost_price
    if(data.cost_price_cop && parseFloat(data.cost_price_cop) > 0) {
        $('#cost_price_cop').val(data.cost_price_cop);
        $('#exchange_rate').val(data.exchange_rate || '4150.00');
    } else {
        // Retrocompatibilidad: si solo tiene cost_price, asumir tasa actual
        var exchangeRate = parseFloat(data.exchange_rate) || 4150.00;
        var costUSD = parseFloat(data.cost_price) || 0;
        var costCOP = costUSD * exchangeRate;
        $('#cost_price_cop').val(costCOP.toFixed(2));
        $('#exchange_rate').val(exchangeRate.toFixed(2));
    }
    
    calculateCostUSD();
    $('#sale_price').val(data.sale_price);
    updateCommissionPreview();
    
    // Details
    $('#availability_status').val(data.availability_status);
    $('#stock_quantity').val(data.stock_quantity);
    $('#booking_lead_time').val(data.booking_lead_time);
    $('#service_details').val(data.service_details);
    $('#tags').val(data.tags);
    $('#internal_notes').val(data.internal_notes);
    
    // Display
    $('#icon_class').val(data.icon_class);
    $('#image_url').val(data.image_url);
    updateImagePreview(data.image_url);
    $('#display_order').val(data.display_order);
    $('#is_active').prop('checked', data.is_active == 1);
    $('#featured').prop('checked', data.featured == 1);
    
    // Validar formulario para habilitar/deshabilitar botón
    validateFormRealTime();
}

// ===================================================================
// CARGAR TASA DE CAMBIO DESDE BD
// ===================================================================
function loadCurrentExchangeRate() {
    $.ajax({
        url: 'ajax/exchange_rate.php?action=get_current',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                $('#exchange_rate').val(response.rate.toFixed(2));
                $('#exchange_rate').attr('data-source', response.source);
                $('#exchange_rate').attr('data-date', response.effective_date);
                
                // Mostrar info de la tasa
                var rateInfo = 'Tasa vigente: $' + response.rate.toFixed(2) + ' COP';
                if(response.source !== 'default') {
                    rateInfo += ' (Fuente: ' + response.source + ', Fecha: ' + response.effective_date + ')';
                }
                $('#exchange_rate').attr('title', rateInfo);
            }
        },
        error: function() {
            // Si falla, usar tasa por defecto
            $('#exchange_rate').val('4150.00');
            toastr.warning('No se pudo cargar la tasa de cambio. Usando tasa por defecto.');
        }
    });
}

// ===================================================================
// CALCULAR COSTO EN USD DESDE COP
// ===================================================================
function calculateCostUSD() {
    var costCOP = parseFloat($('#cost_price_cop').val()) || 0;
    var exchangeRate = parseFloat($('#exchange_rate').val()) || 1;
    
    if(exchangeRate > 0) {
        var costUSD = costCOP / exchangeRate;
        $('#cost_price').val(costUSD.toFixed(2));
    }
}

// ===================================================================
// VALIDACIÓN EN TIEMPO REAL
// ===================================================================
function validateFormRealTime() {
    var errors = validateServiceForm();
    
    // Habilitar/deshabilitar botón Save
    if(errors.length === 0) {
        $('#btnSaveService').prop('disabled', false).removeClass('btn-default').addClass('btn-primary');
    } else {
        $('#btnSaveService').prop('disabled', true).removeClass('btn-primary').addClass('btn-default');
    }
    
    return errors.length === 0;
}

// ===================================================================
// VALIDAR FORMULARIO
// ===================================================================
function validateServiceForm() {
    var errors = [];
    
    // Limpiar errores visuales previos
    $('.has-error').removeClass('has-error');
    $('.nav-tabs li').removeClass('tab-error');
    
    // TAB 1: Basic Info - Campos obligatorios
    var serviceType = $('#service_type').val();
    var serviceName = $('#service_name').val().trim();
    
    if(!serviceType) {
        errors.push({tab: 'Basic Info', field: 'Service Type is required', element: '#service_type'});
    }
    
    if(!serviceName) {
        errors.push({tab: 'Basic Info', field: 'Service Name is required', element: '#service_name'});
    }

    // TAB 2: Provider - requerido para admin/global. Usuarios scoped se fuerzan server-side.
    var selectedProviderId = parseInt($('#provider_id').val(), 10) || 0;
    if(!scopedComplementaryProviderId && selectedProviderId <= 0) {
        errors.push({tab: 'Provider', field: 'Provider is required', element: '#provider_id'});
    }
    
    // TAB 3: Pricing - Validaciones de precios y tasa de cambio
    var exchangeRate = parseFloat($('#exchange_rate').val()) || 0;
    var costCOP = parseFloat($('#cost_price_cop').val()) || 0;
    var salePrice = parseFloat($('#sale_price').val()) || 0;
    
    if(exchangeRate <= 0) {
        errors.push({tab: 'Pricing', field: 'Exchange Rate must be greater than 0', element: '#exchange_rate'});
    }
    
    if(costCOP < 0) {
        errors.push({tab: 'Pricing', field: 'Cost in COP cannot be negative', element: '#cost_price_cop'});
    }
    
    if(salePrice <= 0) {
        errors.push({tab: 'Pricing', field: 'Sale Price USD must be greater than 0', element: '#sale_price'});
    }
    
    // Advertencia si la comisión es negativa (no bloquea el guardado)
    var costUSD = parseFloat($('#cost_price').val()) || 0;
    if(salePrice < costUSD && salePrice > 0) {
        // No agregar a errors para no bloquear, solo mostrar warning visual
        toastr.warning('Warning: Sale Price is lower than Cost Price (negative commission)', 'Commission Alert', {
            timeOut: 3000,
            preventDuplicates: true
        });
    }
    
    // TAB 4: Details - Validaciones de números
    var stockQuantity = $('#stock_quantity').val();
    var leadTime = $('#booking_lead_time').val();
    
    if(stockQuantity && (isNaN(stockQuantity) || parseInt(stockQuantity) < 0)) {
        errors.push({tab: 'Details', field: 'Stock Quantity must be a positive number', element: '#stock_quantity'});
    }
    
    if(leadTime && (isNaN(leadTime) || parseInt(leadTime) < 0)) {
        errors.push({tab: 'Details', field: 'Booking Lead Time must be a positive number', element: '#booking_lead_time'});
    }
    
    // Validación de email del proveedor (si se proporciona)
    var providerEmailField = $('#provider_email');
    var providerEmailVal = providerEmailField.length ? (providerEmailField.val() || '').trim() : '';
    if(providerEmailVal) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(!emailRegex.test(providerEmailVal)) {
            errors.push({tab: 'Provider', field: 'Provider Email format is invalid', element: '#provider_email'});
        }
    }
    
    // Marcar campos con error
    errors.forEach(function(error) {
        if(error.element) {
            $(error.element).closest('.form-group').addClass('has-error');
        }
    });
    
    // Marcar tabs con errores
    if(errors.some(e => e.tab === 'Basic Info')) {
        $('.nav-tabs a[href="#tab_basic"]').parent().addClass('tab-error');
    }
    if(errors.some(e => e.tab === 'Provider')) {
        $('.nav-tabs a[href="#tab_provider"]').parent().addClass('tab-error');
    }
    if(errors.some(e => e.tab === 'Pricing')) {
        $('.nav-tabs a[href="#tab_pricing"]').parent().addClass('tab-error');
    }
    if(errors.some(e => e.tab === 'Details')) {
        $('.nav-tabs a[href="#tab_details"]').parent().addClass('tab-error');
    }
    
    return errors;
}

// ===================================================================
// MOSTRAR ERRORES DE VALIDACIÓN
// ===================================================================
function showValidationErrors(errors) {
    var message = '<strong>Please correct the following errors:</strong><br><ul style="margin: 10px 0; padding-left: 20px;">';
    
    errors.forEach(function(error) {
        message += '<li><strong>' + error.tab + ':</strong> ' + error.field + '</li>';
    });
    
    message += '</ul>';
    
    toastr.error(message, 'Validation Error', {
        timeOut: 0,
        extendedTimeOut: 0,
        closeButton: true,
        tapToDismiss: false,
        allowHtml: true
    });
}

// ===================================================================
// GUARDAR SERVICIO (CREATE/UPDATE)
// ===================================================================
function saveService() {
    // Validar formulario antes de enviar
    var errors = validateServiceForm();
    
    if(errors.length > 0) {
        showValidationErrors(errors);
        return false;
    }
    
    var formData = serviceForm.serialize();
    var serviceId = $('#service_id').val();
    var action = serviceId ? 'update' : 'create';
    if (scopedComplementaryProviderId) {
        formData += '&provider_id=' + encodeURIComponent(String(scopedComplementaryProviderId));
    }
    
    formData += '&action=' + action;
    
    $.ajax({
        url: 'ajax/medtravel_services.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                toastr.success(response.message || 'Service saved successfully');
                serviceModal.modal('hide');
                servicesTable.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error saving service');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            toastr.error('Server communication error');
        }
    });
}

// ===================================================================
// ELIMINAR SERVICIO
// ===================================================================
function deleteService(id) {
    if(!confirm('¿Deseas eliminar (soft)?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/medtravel_services.php',
        method: 'POST',
        data: { action: 'delete', id: id },
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                toastr.success(response.message || 'Servicio eliminado (soft)');
                servicesTable.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error eliminando servicio');
            }
        },
        error: function() {
            toastr.error('Server communication error');
        }
    });
}

// ===================================================================
// TOGGLE ESTADO
// ===================================================================
function toggleStatus(id, val) {
    var hasExplicitValue = (typeof val !== 'undefined' && val !== null && val !== '');
    var payload = { action: 'toggle_status', id: id };
    if (hasExplicitValue) {
        var normalized = parseInt(val, 10);
        if (normalized !== 0 && normalized !== 1) {
            toastr.error('Valor de estado inválido');
            return;
        }
        if (normalized === 0 && !window.confirm('¿Deseas desactivar?')) {
            return;
        }
        payload.val = normalized;
    }

    $.ajax({
        url: 'ajax/medtravel_services.php',
        method: 'POST',
        data: payload,
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                var statusText = response.is_active == 1 ? 'activado' : 'desactivado';
                toastr.success('Servicio ' + statusText);
                servicesTable.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error updating status');
                servicesTable.ajax.reload(null, false);
            }
        },
        error: function() {
            toastr.error('Server communication error');
            servicesTable.ajax.reload(null, false);
        }
    });
}

// ===================================================================
// ACTUALIZAR PREVIEW DE COMISIÓN
// ===================================================================
function updateCommissionPreview() {
    var cost = parseFloat($('#cost_price').val()) || 0;
    var sale = parseFloat($('#sale_price').val()) || 0;
    var commission = sale - cost;
    var percentage = sale > 0 ? (commission / sale) * 100 : 0;
    
    var cssClass = commission >= 0 ? 'commission-positive' : 'commission-negative';
    
    $('#preview_amount').text('$' + commission.toFixed(2)).attr('class', cssClass);
    $('#preview_percentage').text(percentage.toFixed(2) + '%').attr('class', cssClass);
}

// ===================================================================
// MANEJO DE IMAGEN
// ===================================================================
function updateImagePreview(path) {
    var preview = $('#service_image_preview');
    if(!preview.length) {
        return;
    }
    if(path) {
        var isAbsolute = /^https?:\/\//i.test(path);
        var src = isAbsolute ? path : '../../' + path;
        preview.html('<img src="' + src + '" alt="Service image">');
    } else {
        preview.text('No image selected');
    }
}

function openImagePicker() {
    var file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    file.onchange = function() {
        if(!file.files || !file.files[0]) {
            return;
        }
        var formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('image', file.files[0]);
        $.ajax({
            url: 'ajax/medtravel_services.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if(response.ok) {
                    $('#image_url').val(response.path);
                    updateImagePreview(response.path);
                    toastr.success('Image uploaded. Remember to save the service.');
                } else {
                    toastr.error(response.message || 'Error uploading image');
                }
            },
            error: function() {
                toastr.error('Server communication error');
            }
        });
    };
}

function clearServiceImage() {
    $('#image_url').val('');
    updateImagePreview('');
}

// ===================================================================
// CARGAR PROVEEDORES ACTIVOS
// ===================================================================
function loadProviders() {
    if (!canListProviders) {
        loadScopedProvider();
        return;
    }

    $.ajax({
        url: 'ajax/service_providers.php?action=list&active_only=1',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                var select = $('#provider_id');
                select.html('<option value="">Seleccionar proveedor...</option>');
                
                $.each(response.data, function(i, provider) {
                    var typeIcon = getProviderTypeIcon(provider.provider_type);
                    select.append(
                        '<option value="' + provider.id + '" ' +
                        'data-name="' + provider.provider_name + '" ' +
                        'data-contact="' + (provider.contact_name || '') + '" ' +
                        'data-email="' + (provider.contact_email || '') + '" ' +
                        'data-phone="' + (provider.contact_phone || '') + '">' +
                        typeIcon + ' ' + provider.provider_name + 
                        '</option>'
                    );
                });
                if (scopedComplementaryProviderId) {
                    select.val(String(scopedComplementaryProviderId));
                    select.prop('disabled', true);
                    onProviderSelect();
                } else {
                    select.prop('disabled', false);
                }
            } else {
                toastr.warning('No se pudieron cargar los proveedores');
            }
        },
        error: function() {
            toastr.error('Error al cargar proveedores');
        }
    });
}

function loadScopedProvider() {
    if (!scopedComplementaryProviderId) {
        return;
    }

    $.ajax({
        url: 'ajax/service_providers.php?action=get_self',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            var select = $('#provider_id');
            if (response.ok && response.data) {
                var provider = response.data;
                var typeIcon = getProviderTypeIcon(provider.provider_type);
                select.html(
                    '<option value="' + provider.id + '" ' +
                    'data-name="' + (provider.provider_name || '') + '" ' +
                    'data-contact="' + (provider.contact_name || '') + '" ' +
                    'data-email="' + (provider.contact_email || '') + '" ' +
                    'data-phone="' + (provider.contact_phone || '') + '">' +
                    typeIcon + ' ' + (provider.provider_name || ('Proveedor #' + provider.id)) +
                    '</option>'
                );
            } else {
                select.html('<option value="' + scopedComplementaryProviderId + '">Proveedor #' + scopedComplementaryProviderId + '</option>');
            }
            select.val(String(scopedComplementaryProviderId)).prop('disabled', true);
            onProviderSelect();
        },
        error: function() {
            var select = $('#provider_id');
            select.html('<option value="' + scopedComplementaryProviderId + '">Proveedor #' + scopedComplementaryProviderId + '</option>');
            select.val(String(scopedComplementaryProviderId)).prop('disabled', true);
            onProviderSelect();
        }
    });
}

// ===================================================================
// ICONOS POR TIPO DE PROVEEDOR
// ===================================================================
function getProviderTypeIcon(type) {
    var icons = {
        'airline': '✈️',
        'hotel': '🏨',
        'transport': '🚗',
        'restaurant': '🍽️',
        'tour_operator': '🎯',
        'other': '📦'
    };
    return icons[type] || '📦';
}

// ===================================================================
// AL SELECCIONAR PROVEEDOR - AUTO-FILL
// ===================================================================
function onProviderSelect() {
    var selected = $('#provider_id option:selected');
    var providerId = selected.val();
    
    if(providerId) {
        // Cargar datos desde los atributos data-*
        $('#provider_name_display').val(selected.data('name'));
        $('#provider_contact_display').val(selected.data('contact'));
        $('#provider_email_display').val(selected.data('email'));
        $('#provider_phone_display').val(selected.data('phone'));
        
        // Validar formulario
        validateFormRealTime();
    } else {
        // Limpiar campos
        $('#provider_name_display, #provider_contact_display, #provider_email_display, #provider_phone_display').val('');
    }
}

// ===================================================================
// ABRIR MODAL DE NUEVO PROVEEDOR
// ===================================================================
function openProviderModal() {
    toastr.info('Funcionalidad de creación rápida de proveedor próximamente.<br>' + 
                'Por ahora, crea proveedores en el menú <strong>Proveedores</strong>.', 
                'Información', {timeOut: 5000, enableHtml: true});
    // TODO: Implementar modal inline o redirigir a página de proveedores
}

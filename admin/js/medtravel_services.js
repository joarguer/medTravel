// admin/js/medtravel_services.js - Frontend logic para gestión de servicios MedTravel
var servicesTable;
var serviceModal = $('#serviceModal');
var serviceForm = $('#serviceForm');

$(document).ready(function() {
    initDataTable();
    initEventHandlers();
    updateCommissionPreview();
});

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
                    var checked = data == 1 ? 'checked' : '';
                    return '<label class="mt-checkbox mt-checkbox-outline">' +
                           '<input type="checkbox" ' + checked + ' onchange="toggleStatus(' + row.id + ')">' +
                           '<span></span></label>';
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
                    return '<button class="btn btn-xs btn-primary" onclick="editService(' + row.id + ')" title="Edit">' +
                           '<i class="fa fa-edit"></i></button> ' +
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
    
    // Submit formulario
    serviceForm.submit(function(e) {
        e.preventDefault();
        saveService();
    });
    
    // Actualizar preview de comisión en tiempo real
    $('#cost_price, #sale_price').on('input', function() {
        updateCommissionPreview();
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
    $('#serviceModalTitle').text('New Service');
    $('#is_active').prop('checked', true);
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
    
    // Provider
    $('#provider_name').val(data.provider_name);
    $('#provider_contact').val(data.provider_contact);
    $('#provider_email').val(data.provider_email);
    $('#provider_phone').val(data.provider_phone);
    $('#provider_notes').val(data.provider_notes);
    
    // Pricing
    $('#currency').val(data.currency);
    $('#cost_price').val(data.cost_price);
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
    $('#display_order').val(data.display_order);
    $('#is_active').prop('checked', data.is_active == 1);
    $('#featured').prop('checked', data.featured == 1);
}

// ===================================================================
// GUARDAR SERVICIO (CREATE/UPDATE)
// ===================================================================
function saveService() {
    var formData = serviceForm.serialize();
    var serviceId = $('#service_id').val();
    var action = serviceId ? 'update' : 'create';
    
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
    if(!confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/medtravel_services.php',
        method: 'POST',
        data: { action: 'delete', id: id },
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                toastr.success(response.message || 'Service deleted successfully');
                servicesTable.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error deleting service');
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
function toggleStatus(id) {
    $.ajax({
        url: 'ajax/medtravel_services.php',
        method: 'POST',
        data: { action: 'toggle_status', id: id },
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                var statusText = response.is_active == 1 ? 'activated' : 'deactivated';
                toastr.success('Service ' + statusText);
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

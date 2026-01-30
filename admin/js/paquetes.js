// admin/js/paquetes.js - Frontend logic para gestión de paquetes
var tablaPaquetes;
var modalPaquete = $('#modalPaquete');
var formPaquete = $('#formPaquete');

$(document).ready(function() {
    initDataTable();
    initEventHandlers();
    loadClientes();
});

// ===================================================================
// DATATABLE
// ===================================================================
function initDataTable() {
    tablaPaquetes = $('#tabla_paquetes').DataTable({
        "processing": true,
        "ajax": {
            "url": "ajax/paquetes.php?action=list",
            "type": "GET",
            "dataSrc": function(json) {
                if(!json.ok) {
                    toastr.error(json.message || 'Error al cargar paquetes');
                    return [];
                }
                return json.data;
            },
            "error": function(xhr, error, thrown) {
                toastr.error('Error de conexión al cargar paquetes');
                console.error('Error:', error, thrown);
            }
        },
        "columns": [
            { "data": "id" },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return row.package_name || '<em>Sin nombre</em>';
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return row.client_nombre + ' ' + row.client_apellido;
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return formatDate(row.start_date) + ' → ' + formatDate(row.end_date);
                }
            },
            { "data": "total_days" },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return formatCurrency(row.total_package_cost, row.currency);
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    var margin = parseFloat(row.net_margin) || 0;
                    var cssClass = margin < 0 ? 'text-danger' : 'text-success';
                    return '<span class="' + cssClass + '">' + 
                           formatCurrency(margin, row.currency) + '</span>';
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return getStatusBadge(row.status);
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return getPaymentStatusBadge(row.payment_status);
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    var buttons = '<div class="btn-group">';
                    
                    // Botón enviar cotización (solo si está en estado quoted o confirmed)
                    if(row.status === 'quoted' || row.status === 'confirmed') {
                        buttons += '<button class="btn btn-xs btn-success" onclick="sendQuote(' + row.id + ')" title="Enviar Cotización">' +
                                  '<i class="fa fa-envelope"></i></button> ';
                    }
                    
                    // Botón editar
                    buttons += '<button class="btn btn-xs btn-primary" onclick="editPaquete(' + row.id + ')" title="Editar">' +
                              '<i class="fa fa-edit"></i></button> ';
                    
                    // Botón eliminar
                    buttons += '<button class="btn btn-xs btn-danger" onclick="deletePaquete(' + row.id + ')" title="Eliminar">' +
                              '<i class="fa fa-trash"></i></button>';
                    
                    buttons += '</div>';
                    return buttons;
                }
            }
        ],
        "order": [[0, "desc"]],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
        },
        "responsive": true,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        "pageLength": 25
    });
}

// ===================================================================
// EVENT HANDLERS
// ===================================================================
function initEventHandlers() {
    // Botón nuevo paquete
    $('#btnNuevoPaquete').click(function() {
        openCreateModal();
    });
    
    // Submit formulario
    formPaquete.submit(function(e) {
        e.preventDefault();
        savePaquete();
    });
    
    // Toggle secciones opcionales
    $('#flight_included').change(function() {
        $('#flight_details').toggle(this.checked);
    });
    
    $('#hotel_included').change(function() {
        $('#hotel_details').toggle(this.checked);
    });
    
    $('#transport_included').change(function() {
        $('#transport_details').toggle(this.checked);
    });
    
    // Calcular hotel total automáticamente
    $('#hotel_nights, #hotel_cost_per_night').on('input', function() {
        var nights = parseFloat($('#hotel_nights').val()) || 0;
        var costPerNight = parseFloat($('#hotel_cost_per_night').val()) || 0;
        var total = nights * costPerNight;
        $('#hotel_total_cost').val(total.toFixed(2));
        calculateMargins();
    });
    
    // Cambiar unidad de tarifa (% o $)
    $('#medtravel_fee_type').change(function() {
        var unit = $(this).val() === 'percent' ? '%' : '$';
        $('#fee_unit').text(unit);
        calculateMargins();
    });
    
    // Recalcular márgenes cuando cambien campos de costo
    $('.calculate-cost, #medtravel_fee_value, #provider_commission_value, #total_package_cost').on('input', function() {
        calculateMargins();
    });
    
    // Botón para auto-calcular precio
    $('#btn-auto-price').click(function() {
        autoCalculatePrice();
    });
    
    // Auto-calcular precio cuando cambien costos o margen (si el precio está en 0 o vacío)
    $('.calculate-cost, #medtravel_fee_value, #medtravel_fee_type').on('change', function() {
        var currentPrice = parseFloat($('#total_package_cost').val()) || 0;
        // Solo auto-calcular si el precio está vacío o en 0
        if(currentPrice === 0) {
            autoCalculatePrice();
        }
    });
}

// ===================================================================
// CARGAR CLIENTES PARA SELECT
// ===================================================================
function loadClientes() {
    $.ajax({
        url: 'ajax/paquetes.php?action=get_clientes',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                var select = $('#client_id');
                select.find('option:not(:first)').remove();
                
                $.each(response.data, function(i, cliente) {
                    var displayText = cliente.nombre + ' ' + cliente.apellido;
                    if(cliente.email) {
                        displayText += ' (' + cliente.email + ')';
                    }
                    select.append($('<option>', {
                        value: cliente.id,
                        text: displayText
                    }));
                });
                
                // Refrescar selectpicker si está inicializado
                if(select.hasClass('selectpicker')) {
                    select.selectpicker('refresh');
                }
            }
        },
        error: function() {
            toastr.error('Error al cargar lista de clientes');
        }
    });
}

// ===================================================================
// ABRIR MODAL PARA CREAR
// ===================================================================
function openCreateModal() {
    formPaquete[0].reset();
    $('#paquete_id').val('');
    $('#modalPaqueteTitle').text('Nuevo Paquete');
    
    // Resetear secciones opcionales
    $('#flight_details, #hotel_details, #transport_details').hide();
    $('#flight_included, #hotel_included, #transport_included').prop('checked', false);
    
    // Valores por defecto
    $('#status').val('quoted');
    $('#payment_status').val('pending');
    $('#currency').val('USD');
    $('#medtravel_fee_type').val('percent');
    $('#fee_unit').text('%');
    
    // Limpiar márgenes
    clearMarginDisplay();
    
    // Refrescar selectpicker
    if($('.selectpicker').length) {
        $('.selectpicker').selectpicker('refresh');
    }
    
    modalPaquete.modal('show');
}

// ===================================================================
// EDITAR PAQUETE
// ===================================================================
function editPaquete(id) {
    $.ajax({
        url: 'ajax/paquetes.php?action=get&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                populateForm(response.data);
                $('#modalPaqueteTitle').text('Editar Paquete #' + id);
                modalPaquete.modal('show');
            } else {
                toastr.error(response.message || 'Error al cargar paquete');
            }
        },
        error: function() {
            toastr.error('Error de conexión al cargar paquete');
        }
    });
}

// ===================================================================
// POPULAR FORMULARIO CON DATOS
// ===================================================================
function populateForm(data) {
    // General
    $('#paquete_id').val(data.id);
    $('#client_id').val(data.client_id);
    $('#package_name').val(data.package_name);
    $('#start_date').val(data.start_date);
    $('#end_date').val(data.end_date);
    $('#status').val(data.status);
    $('#currency').val(data.currency);
    $('#internal_notes').val(data.internal_notes);
    
    // Vuelo
    var flightIncluded = parseInt(data.flight_included) === 1;
    $('#flight_included').prop('checked', flightIncluded);
    $('#flight_details').toggle(flightIncluded);
    $('#flight_airline').val(data.flight_airline);
    $('#flight_departure_airport').val(data.flight_departure_airport);
    $('#flight_arrival_airport').val(data.flight_arrival_airport);
    $('#flight_departure_date').val(data.flight_departure_date);
    $('#flight_return_date').val(data.flight_return_date);
    $('#flight_cost').val(data.flight_cost);
    $('#flight_notes').val(data.flight_notes);
    
    // Hotel
    var hotelIncluded = parseInt(data.hotel_included) === 1;
    $('#hotel_included').prop('checked', hotelIncluded);
    $('#hotel_details').toggle(hotelIncluded);
    $('#hotel_name').val(data.hotel_name);
    $('#hotel_city').val(data.hotel_city);
    $('#hotel_nights').val(data.hotel_nights);
    $('#hotel_cost_per_night').val(data.hotel_cost_per_night);
    $('#hotel_total_cost').val(data.hotel_total_cost);
    $('#hotel_notes').val(data.hotel_notes);
    
    // Transporte
    var transportIncluded = parseInt(data.transport_included) === 1;
    $('#transport_included').prop('checked', transportIncluded);
    $('#transport_details').toggle(transportIncluded);
    $('#transport_type').val(data.transport_type);
    $('#transport_routes').val(data.transport_routes);
    $('#transport_cost').val(data.transport_cost);
    
    // Costos
    $('#medical_service_cost').val(data.medical_service_cost);
    $('#meals_cost').val(data.meals_cost);
    $('#additional_services_cost').val(data.additional_services_cost);
    $('#total_package_cost').val(data.total_package_cost);
    
    // Monetización
    $('#medtravel_fee_type').val(data.medtravel_fee_type);
    $('#medtravel_fee_value').val(data.medtravel_fee_value);
    $('#provider_commission_value').val(data.provider_commission_value);
    $('#fee_unit').text(data.medtravel_fee_type === 'percent' ? '%' : '$');
    
    // Pagos
    $('#payment_status').val(data.payment_status);
    $('#payment_method').val(data.payment_method);
    $('#deposit_amount').val(data.deposit_amount);
    $('#amount_paid').val(data.amount_paid);
    $('#payment_reference').val(data.payment_reference);
    $('#payment_notes').val(data.payment_notes);
    
    // Refrescar selectpicker
    if($('.selectpicker').length) {
        $('.selectpicker').selectpicker('refresh');
    }
    
    // Calcular márgenes con datos actuales
    calculateMargins();
}

// ===================================================================
// GUARDAR PAQUETE (CREATE/UPDATE)
// ===================================================================
function savePaquete() {
    var formData = formPaquete.serialize();
    var id = $('#paquete_id').val();
    var action = id ? 'update' : 'create';
    
    formData += '&action=' + action;
    
    // Deshabilitar botón mientras procesa
    var btnGuardar = $('#btnGuardarPaquete');
    btnGuardar.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
    
    $.ajax({
        url: 'ajax/paquetes.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                toastr.success(response.message || 'Paquete guardado exitosamente');
                modalPaquete.modal('hide');
                tablaPaquetes.ajax.reload();
                
                // Mostrar alerta si el margen neto es negativo
                if(response.data && parseFloat(response.data.net_margin) < 0) {
                    toastr.warning('Advertencia: El paquete tiene un margen neto negativo de ' + 
                                   formatCurrency(response.data.net_margin, response.data.currency), 
                                   'Margen Negativo', {timeOut: 5000});
                }
            } else {
                toastr.error(response.message || 'Error al guardar paquete');
            }
        },
        error: function(xhr, status, error) {
            toastr.error('Error de conexión al guardar');
            console.error('Error:', error);
        },
        complete: function() {
            btnGuardar.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar Paquete');
        }
    });
}

// ===================================================================
// ELIMINAR PAQUETE
// ===================================================================
function deletePaquete(id) {
    if(!confirm('¿Está seguro de eliminar este paquete? Esta acción no se puede deshacer.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/paquetes.php',
        type: 'POST',
        data: {
            action: 'delete',
            id: id
        },
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                toastr.success(response.message || 'Paquete eliminado exitosamente');
                tablaPaquetes.ajax.reload();
            } else {
                toastr.error(response.message || 'Error al eliminar paquete');
            }
        },
        error: function() {
            toastr.error('Error de conexión al eliminar');
        }
    });
}

// ===================================================================
// AUTO-CALCULAR PRECIO BASADO EN COSTOS + MARGEN
// ===================================================================
function autoCalculatePrice() {
    // Recopilar costos operativos
    var flightCost = parseFloat($('#flight_cost').val()) || 0;
    var hotelCost = parseFloat($('#hotel_total_cost').val()) || 0;
    var transportCost = parseFloat($('#transport_cost').val()) || 0;
    var mealsCost = parseFloat($('#meals_cost').val()) || 0;
    var medicalCost = parseFloat($('#medical_service_cost').val()) || 0;
    var additionalCost = parseFloat($('#additional_services_cost').val()) || 0;
    
    var totalCosts = flightCost + hotelCost + transportCost + mealsCost + medicalCost + additionalCost;
    
    // Obtener margen deseado
    var feeType = $('#medtravel_fee_type').val();
    var feeValue = parseFloat($('#medtravel_fee_value').val()) || 0;
    
    var suggestedPrice = 0;
    
    if(feeType === 'fixed') {
        // Si es monto fijo, sumar al costo
        suggestedPrice = totalCosts + feeValue;
    } else {
        // Si es porcentaje, calcular precio para obtener ese margen
        // Fórmula: Precio = Costos / (1 - Margen%)
        // Ejemplo: $5,755 / (1 - 0.25) = $5,755 / 0.75 = $7,673
        if(feeValue > 0 && feeValue < 100) {
            suggestedPrice = totalCosts / (1 - (feeValue / 100));
        } else {
            suggestedPrice = totalCosts;
        }
    }
    
    // Actualizar campo de precio
    $('#total_package_cost').val(suggestedPrice.toFixed(2));
    
    // Recalcular márgenes para mostrar resultado
    calculateMargins();
    
    // Mostrar notificación
    toastr.info('Precio calculado automáticamente: ' + formatCurrency(suggestedPrice, $('#currency').val() || 'USD'), 'Auto-cálculo');
}

// ===================================================================
// CÁLCULO DE MÁRGENES (CLIENT-SIDE - SOLO VISUAL)
// ===================================================================
function calculateMargins() {
    // Recopilar costos operativos
    var flightCost = parseFloat($('#flight_cost').val()) || 0;
    var hotelCost = parseFloat($('#hotel_total_cost').val()) || 0;
    var transportCost = parseFloat($('#transport_cost').val()) || 0;
    var mealsCost = parseFloat($('#meals_cost').val()) || 0;
    var medicalCost = parseFloat($('#medical_service_cost').val()) || 0;
    var additionalCost = parseFloat($('#additional_services_cost').val()) || 0;
    
    var totalCosts = flightCost + hotelCost + transportCost + mealsCost + medicalCost + additionalCost;
    
    // Costo total del paquete
    var totalPackageCost = parseFloat($('#total_package_cost').val()) || 0;
    
    // Calcular tarifa MedTravel
    var feeType = $('#medtravel_fee_type').val();
    var feeValue = parseFloat($('#medtravel_fee_value').val()) || 0;
    var medtravelFee = 0;
    
    if(feeType === 'fixed') {
        medtravelFee = feeValue;
    } else {
        medtravelFee = (totalPackageCost * feeValue) / 100;
    }
    
    // Comisión al proveedor
    var providerCommission = parseFloat($('#provider_commission_value').val()) || 0;
    
    // Calcular márgenes
    var grossMargin = totalPackageCost - totalCosts;
    var netMargin = grossMargin - providerCommission;
    
    // Mostrar en UI
    var currency = $('#currency').val() || 'USD';
    $('#display_total_costs').text(formatCurrency(totalCosts, currency));
    $('#display_client_price').text(formatCurrency(totalPackageCost, currency));
    $('#display_medtravel_fee').text(formatCurrency(medtravelFee, currency));
    $('#display_gross_margin').text(formatCurrency(grossMargin, currency));
    $('#display_net_margin').text(formatCurrency(netMargin, currency));
    
    // Calcular porcentaje de margen neto
    var netMarginPercent = totalPackageCost > 0 ? ((netMargin / totalPackageCost) * 100).toFixed(2) : 0;
    $('#net_margin_percent').text('(' + netMarginPercent + '%)');
    
    // Mostrar warning si es negativo
    if(netMargin < 0) {
        $('#margin_warning').show();
        $('#display_net_margin').addClass('text-danger').removeClass('text-success');
    } else {
        $('#margin_warning').hide();
        $('#display_net_margin').addClass('text-success').removeClass('text-danger');
    }
}

// ===================================================================
// LIMPIAR DISPLAY DE MÁRGENES
// ===================================================================
function clearMarginDisplay() {
    $('#display_total_costs').text('$0.00');
    $('#display_client_price').text('$0.00');
    $('#display_medtravel_fee').text('$0.00');
    $('#display_gross_margin').text('$0.00');
    $('#display_net_margin').text('$0.00').removeClass('text-danger text-success');
    $('#net_margin_percent').text('');
    $('#margin_warning').hide();
}

// ===================================================================
// HELPERS
// ===================================================================
function formatDate(dateString) {
    if(!dateString) return '-';
    var date = new Date(dateString + 'T00:00:00');
    var options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('es-ES', options);
}

function formatCurrency(amount, currency) {
    currency = currency || 'USD';
    var symbol = currency === 'USD' ? '$' : (currency === 'EUR' ? '€' : currency + ' ');
    return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function getStatusBadge(status) {
    var badges = {
        'quoted': '<span class="label label-default">Cotizado</span>',
        'confirmed': '<span class="label label-info">Confirmado</span>',
        'in_progress': '<span class="label label-primary">En Progreso</span>',
        'completed': '<span class="label label-success">Completado</span>',
        'cancelled': '<span class="label label-danger">Cancelado</span>',
        'refunded': '<span class="label label-warning">Reembolsado</span>'
    };
    return badges[status] || '<span class="label label-default">' + status + '</span>';
}

function getPaymentStatusBadge(status) {
    var badges = {
        'pending': '<span class="label label-default">Pendiente</span>',
        'deposit_paid': '<span class="label label-info">Depósito</span>',
        'partial_paid': '<span class="label label-warning">Parcial</span>',
        'fully_paid': '<span class="label label-success">Pagado</span>',
        'refunded': '<span class="label label-warning">Reembolsado</span>',
        'cancelled': '<span class="label label-danger">Cancelado</span>'
    };
    return badges[status] || '<span class="label label-default">' + status + '</span>';
}

// ===================================================================
// ENVIAR COTIZACIÓN AL CLIENTE
// ===================================================================
function sendQuote(packageId) {
    // Cargar datos del paquete
    $.ajax({
        url: 'ajax/paquetes.php?action=get&id=' + packageId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                var data = response.data;
                
                // Cargar datos del cliente
                $.ajax({
                    url: 'ajax/paquetes.php?action=get_client_info&client_id=' + data.client_id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(clientResponse) {
                        if(clientResponse.ok) {
                            var client = clientResponse.data;
                            
                            // Poblar modal
                            $('#quote_package_id').val(packageId);
                            $('#quote_client_name').text(client.nombre + ' ' + client.apellido);
                            $('#quote_client_email').val(client.email);
                            $('#quote_package_name').text(data.package_name || 'Paquete #' + packageId);
                            $('#quote_total_price').text(formatCurrency(data.total_package_cost, data.currency));
                            
                            // Mostrar modal
                            $('#modalEnviarCotizacion').modal('show');
                        } else {
                            toastr.error(clientResponse.message || 'Error al cargar datos del cliente');
                        }
                    }
                });
            } else {
                toastr.error(response.message || 'Error al cargar paquete');
            }
        },
        error: function() {
            toastr.error('Error de conexión al cargar datos');
        }
    });
}

// Confirmar envío de cotización
$(document).on('click', '#btnConfirmarEnvio', function() {
    var packageId = $('#quote_package_id').val();
    var email = $('#quote_client_email').val();
    var subject = $('#quote_subject').val();
    var message = $('#quote_message').val();
    var includeDetails = $('#quote_include_details').is(':checked') ? 1 : 0;
    
    if(!email || !subject) {
        toastr.warning('Por favor completa todos los campos requeridos');
        return;
    }
    
    var $btn = $(this);
    var btnText = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Enviando...');
    
    $.ajax({
        url: 'ajax/paquetes.php',
        type: 'POST',
        data: {
            action: 'send_quote',
            package_id: packageId,
            email: email,
            subject: subject,
            message: message,
            include_details: includeDetails
        },
        dataType: 'json',
        success: function(response) {
            if(response.ok) {
                toastr.success('Cotización enviada exitosamente', 'Éxito');
                $('#modalEnviarCotizacion').modal('hide');
            } else {
                toastr.error(response.message || 'Error al enviar cotización', 'Error');
            }
        },
        error: function() {
            toastr.error('Error de conexión al enviar cotización', 'Error');
        },
        complete: function() {
            $btn.prop('disabled', false).html(btnText);
        }
    });
});

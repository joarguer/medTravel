/**
 * Booking Requests Management
 */

$(document).ready(function() {
    loadBookingRequests();
});

function loadBookingRequests() {
    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'get_all' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderBookingRequestsTable(response.data);
            } else {
                toastr.error(response.message || 'Error loading booking requests');
            }
        },
        error: function() {
            toastr.error('Connection error loading booking requests');
        }
    });
}

function renderBookingRequestsTable(data) {
    var table = $('#booking_requests_table').DataTable({
        destroy: true,
        data: data,
        columns: [
            { data: 'id' },
            { 
                data: 'created_at',
                render: function(data) {
                    return data ? new Date(data).toLocaleString() : '';
                }
            },
            { data: 'name' },
            { data: 'email' },
            { data: 'destination' },
            { 
                data: 'selected_offers',
                render: function(data) {
                    try {
                        var offers = JSON.parse(data);
                        return '<span class="badge badge-primary">' + offers.length + ' service(s)</span>';
                    } catch(e) {
                        return '<span class="badge badge-default">0</span>';
                    }
                }
            },
            { 
                data: 'status',
                render: function(data) {
                    var badges = {
                        'pending': '<span class="badge badge-warning">Pending</span>',
                        'contacted': '<span class="badge badge-info">Contacted</span>',
                        'confirmed': '<span class="badge badge-success">Confirmed</span>',
                        'cancelled': '<span class="badge badge-danger">Cancelled</span>'
                    };
                    return badges[data] || '<span class="badge badge-default">' + data + '</span>';
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-xs btn-primary" onclick="viewBookingDetail(${row.id})">
                            <i class="fa fa-eye"></i> View
                        </button>
                        <button class="btn btn-xs btn-success" onclick="updateStatus(${row.id}, 'contacted')">
                            <i class="fa fa-phone"></i> Contact
                        </button>
                        <button class="btn btn-xs btn-danger" onclick="deleteBooking(${row.id})">
                            <i class="fa fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });
}

function viewBookingDetail(id) {
    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'get_detail', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderBookingDetail(response);
                $('#booking_detail_modal').modal('show');
            } else {
                toastr.error(response.message || 'Error loading booking details');
            }
        },
        error: function() {
            toastr.error('Connection error');
        }
    });
}

function renderBookingDetail(response) {
    var booking = response.booking || response.data || {};
    var itemsPayload = response.items || {};
    var legacy = response.legacy || {};

    var medicalItems = Array.isArray(itemsPayload.medical) ? itemsPayload.medical : [];
    var complementaryItems = Array.isArray(itemsPayload.complementary) ? itemsPayload.complementary : [];
    var hasStructuredItems = (medicalItems.length + complementaryItems.length) > 0;
    var selectedOffersLegacy = parseSelectedOffersLegacy(legacy.selected_offers, booking.selected_offers);

    var html = `
        <div class="row">
            <div class="col-md-6">
                <h4>Booking Information</h4>
                <p><strong>ID:</strong> #${escapeHtml(booking.id || '')}</p>
                <p><strong>Name:</strong> ${escapeHtml(booking.name || '')}</p>
                <p><strong>Email:</strong> ${escapeHtml(booking.email || '')}</p>
                ${booking.phone ? '<p><strong>Phone:</strong> ' + escapeHtml(booking.phone) + '</p>' : ''}
                ${booking.destination ? '<p><strong>Destination:</strong> ' + escapeHtml(booking.destination) + '</p>' : ''}
                ${booking.timeline ? '<p><strong>Timeline:</strong> ' + escapeHtml(booking.timeline) + '</p>' : ''}
            </div>
            <div class="col-md-6">
                <h4>Request Metadata</h4>
                ${booking.booking_datetime ? '<p><strong>Preferred Date:</strong> ' + escapeHtml(booking.booking_datetime) + '</p>' : ''}
                ${booking.persons ? '<p><strong>Persons:</strong> ' + escapeHtml(booking.persons) + '</p>' : ''}
                ${booking.budget ? '<p><strong>Budget:</strong> $' + parseFloat(booking.budget).toLocaleString() + ' USD</p>' : ''}
                ${booking.status ? '<p><strong>Status:</strong> <span class="label label-info">' + escapeHtml(booking.status) + '</span></p>' : ''}
                ${booking.origin ? '<p><strong>Origin:</strong> ' + escapeHtml(booking.origin) + '</p>' : ''}
                ${booking.created_at ? '<p><strong>Created:</strong> ' + new Date(booking.created_at).toLocaleString() + '</p>' : ''}
            </div>
        </div>
        ${booking.special_request ? '<hr><p><strong>Special Request:</strong><br>' + nl2brSafe(booking.special_request) + '</p>' : ''}
        ${booking.additional_notes ? '<hr><p><strong>Additional Notes:</strong><br>' + nl2brSafe(booking.additional_notes) + '</p>' : ''}
        <hr>
        <div class="row">
            <div class="col-md-12">
                <h4>Servicios Médicos</h4>
                ${renderStructuredItemsTable(medicalItems, 'No medical services found')}
            </div>
            <div class="col-md-12" style="margin-top:12px;">
                <h4>Servicios Complementarios</h4>
                ${renderStructuredItemsTable(complementaryItems, 'No complementary services found')}
            </div>
        </div>
    `;

    if (hasStructuredItems) {
        html += buildTotalsHtml(itemsPayload.totals || {});
        // TODO no crítico: gating pending_admin/pending_provider depende del flujo real de estados.
        if (itemsPayload.can_authorize_pending_admin && booking.id) {
            html += `
                <hr>
                <button type="button" class="btn btn-warning" id="btn-authorize-items" data-booking-id="${escapeHtml(booking.id)}">
                    <i class="fa fa-unlock"></i> Autorizar para proveedor
                </button>
            `;
        }
    } else {
        html += `
            <hr>
            <h4>Legacy selected offers (${selectedOffersLegacy.length})</h4>
            <div id="selected_offers_list">Loading...</div>
            ${legacy.additional_notes ? '<p class="text-muted" style="margin-top:8px;"><strong>Legacy notes:</strong><br>' + nl2brSafe(legacy.additional_notes) + '</p>' : ''}
        `;
    }

    $('#booking_detail_content').html(html);

    if (!hasStructuredItems) {
        if (selectedOffersLegacy.length > 0) {
            loadSelectedOffersDetails(selectedOffersLegacy, '#selected_offers_list');
        } else {
            $('#selected_offers_list').html('<p class="text-muted">No services selected</p>');
        }
    }

    $('#booking_detail_content').off('click', '#btn-authorize-items').on('click', '#btn-authorize-items', function() {
        var bookingId = parseInt($(this).data('booking-id'), 10) || 0;
        if (bookingId > 0) {
            authorizeItems(bookingId);
        }
    });
}

function renderStructuredItemsTable(items, emptyMessage) {
    if (!Array.isArray(items) || items.length === 0) {
        return '<p class="text-muted">' + escapeHtml(emptyMessage || 'No data') + '</p>';
    }

    var html = '' +
        '<div class="table-responsive">' +
        '<table class="table table-striped table-bordered table-condensed">' +
        '<thead><tr>' +
        '<th>Tipo</th>' +
        '<th>Servicio</th>' +
        '<th>Proveedor</th>' +
        '<th>Precio</th>' +
        '<th>Estado item</th>' +
        '</tr></thead><tbody>';

    items.forEach(function(item) {
        var typeLabel = item.item_type === 'medical_offer' ? 'Medical' :
            (item.item_type === 'complementary_service' ? 'Complementary' : (item.item_type_label || item.item_type || 'N/A'));
        var priceDisplay = item.price_display || 'On request';
        html += '<tr>' +
            '<td>' + escapeHtml(typeLabel) + '</td>' +
            '<td>' + escapeHtml(item.name || '') + '</td>' +
            '<td>' + escapeHtml(item.provider || '') + '</td>' +
            '<td>' + escapeHtml(priceDisplay) + '</td>' +
            '<td><span class="label label-default">' + escapeHtml(item.item_status || '') + '</span></td>' +
            '</tr>';
    });

    html += '</tbody></table></div>';
    return html;
}

function buildTotalsHtml(totals) {
    if (!totals || typeof totals !== 'object') {
        return '';
    }
    var subtotal = parseFloat(totals.subtotal || 0);
    var currency = totals.currency || '';
    var currencyMix = !!totals.currency_mix;
    var byCurrency = (totals.by_currency && typeof totals.by_currency === 'object') ? totals.by_currency : {};

    var html = '<hr><h4>Total estimado</h4>';
    if (subtotal <= 0) {
        html += '<p class="text-muted">Price on request</p>';
        return html;
    }

    if (!currencyMix && currency) {
        html += '<p><strong>' + escapeHtml(currency) + ' $' + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong></p>';
        return html;
    }

    html += '<p><strong>Subtotal combinado:</strong> ' + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</p>';
    html += '<ul class="list-unstyled">';
    Object.keys(byCurrency).forEach(function(curr) {
        var value = parseFloat(byCurrency[curr] || 0);
        if (value > 0) {
            html += '<li>' + escapeHtml(curr) + ' $' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</li>';
        }
    });
    html += '</ul>';
    return html;
}

function parseSelectedOffersLegacy(preParsedList, rawSelectedOffers) {
    if (Array.isArray(preParsedList) && preParsedList.length > 0) {
        return preParsedList;
    }
    try {
        var parsed = JSON.parse(rawSelectedOffers || '[]');
        if (!Array.isArray(parsed)) return [];
        return parsed.map(function(v) { return parseInt(v, 10) || 0; }).filter(function(v) { return v > 0; });
    } catch (e) {
        return [];
    }
}

function loadSelectedOffersDetails(offerIds, targetSelector) {
    var target = targetSelector || '#selected_offers_list';
    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'get_offers_details', offer_ids: JSON.stringify(offerIds) },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var html = '<ul class="list-group">';
                response.data.forEach(function(offer) {
                    html += `
                        <li class="list-group-item">
                            <strong>${escapeHtml(offer.title)}</strong> - ${escapeHtml(offer.provider_name)}
                            <br><small>${escapeHtml(offer.description || '')}</small>
                            ${offer.price_from > 0 ? '<br><span class="badge badge-success">From $' + parseFloat(offer.price_from).toLocaleString() + ' ' + escapeHtml(offer.currency) + '</span>' : ''}
                        </li>
                    `;
                });
                html += '</ul>';
                $(target).html(html);
            } else {
                $(target).html('<p class="text-danger">Error loading services</p>');
            }
        },
        error: function() {
            $(target).html('<p class="text-danger">Connection error</p>');
        }
    });
}

function authorizeItems(bookingId) {
    if (!confirm('¿Autorizar items pendientes para visibilidad del proveedor?')) {
        return;
    }
    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'authorize_items', booking_id: bookingId },
        success: function(response) {
            if (response && response.success) {
                toastr.success(response.message || 'Items autorizados');
                viewBookingDetail(bookingId);
            } else {
                toastr.error((response && response.message) ? response.message : 'No se pudo autorizar');
            }
        },
        error: function() {
            toastr.error('Connection error');
        }
    });
}

function updateStatus(id, status) {
    if (!confirm('Update booking status to: ' + status + '?')) {
        return;
    }

    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'update_status', id: id, status: status },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Status updated successfully');
                loadBookingRequests();
            } else {
                toastr.error(response.message || 'Error updating status');
            }
        },
        error: function() {
            toastr.error('Connection error');
        }
    });
}

function deleteBooking(id) {
    if (!confirm('Are you sure you want to delete (soft) this booking request?')) {
        return;
    }

    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'delete', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Booking request deleted (soft)');
                loadBookingRequests();
            } else {
                toastr.error(response.message || 'Error deleting booking');
            }
        },
        error: function() {
            toastr.error('Connection error');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function nl2brSafe(text) {
    return escapeHtml(text || '').replace(/\n/g, '<br>');
}

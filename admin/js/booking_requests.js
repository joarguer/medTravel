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
                    var feeRequired = parseInt(row.fee_required, 10) || 0;
                    var feeStatus = (row.fee_status || '').toString().toLowerCase();
                    var feeButton = '';
                    if (feeRequired === 1 && feeStatus !== 'paid') {
                        feeButton = `
                            <button class="btn btn-xs btn-warning" onclick="markFeePaid(${row.id})">
                                <i class="fa fa-check"></i> Mark Fee Paid
                            </button>
                        `;
                    }
                    return `
                        <button class="btn btn-xs btn-primary" onclick="viewBookingDetail(${row.id})">
                            <i class="fa fa-eye"></i> View
                        </button>
                        <button class="btn btn-xs btn-success" onclick="updateStatus(${row.id}, 'contacted')">
                            <i class="fa fa-phone"></i> Contact
                        </button>
                        ${feeButton}
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
    var commissionItems = buildCommissionItemsList(medicalItems, complementaryItems);

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
        if (commissionItems.length) {
            html += buildCommissionSection(commissionItems);
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

    initCommissionSection(booking.id, commissionItems);
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

function isAdminSession() {
    if (window.MT_REALTIME && typeof window.MT_REALTIME.isAdmin !== 'undefined') {
        return !!window.MT_REALTIME.isAdmin;
    }
    return false;
}

function buildCommissionItemsList(medicalItems, complementaryItems) {
    var items = [];
    (medicalItems || []).forEach(function(item) {
        items.push({
            id: item.id,
            label: 'Medical: ' + (item.name || ('Item #' + item.id)) + ' — ' + (item.provider || 'Provider')
        });
    });
    (complementaryItems || []).forEach(function(item) {
        items.push({
            id: item.id,
            label: 'Complementary: ' + (item.name || ('Item #' + item.id)) + ' — ' + (item.provider || 'Provider')
        });
    });
    return items;
}

function buildCommissionSection(items) {
    if (!isAdminSession() || !items || !items.length) {
        return '';
    }
    var options = items.map(function(item) {
        return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.label) + '</option>';
    }).join('');

    return `
        <hr>
        <div id="commission-payment-admin" class="alert alert-info">
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;">
                <strong>Commission Payment (Phase 2)</strong>
                <select id="commission-item-select" class="form-control input-sm" style="max-width:320px;">
                    ${options}
                </select>
            </div>
            <div id="commission-payment-content" style="margin-top:8px;">Loading...</div>
        </div>
    `;
}

function initCommissionSection(requestId, items) {
    if (!isAdminSession() || !items || !items.length) {
        return;
    }
    var $select = $('#commission-item-select');
    if (!$select.length) {
        return;
    }
    var firstItemId = parseInt($select.val() || 0, 10);
    if (requestId > 0 && firstItemId > 0) {
        loadCommissionPaymentStatus(requestId, firstItemId);
    }

    $('#booking_detail_content').off('change', '#commission-item-select').on('change', '#commission-item-select', function() {
        var itemId = parseInt($(this).val() || 0, 10);
        if (requestId > 0 && itemId > 0) {
            loadCommissionPaymentStatus(requestId, itemId);
        }
    });

    $('#booking_detail_content').off('click', '#btn-commission-create').on('click', '#btn-commission-create', function() {
        var itemId = parseInt($('#commission-item-select').val() || 0, 10);
        if (requestId > 0 && itemId > 0) {
            createCommissionPayment(requestId, itemId);
        }
    });

    $('#booking_detail_content').off('click', '#btn-commission-mark-paid').on('click', '#btn-commission-mark-paid', function() {
        var itemId = parseInt($('#commission-item-select').val() || 0, 10);
        if (requestId > 0 && itemId > 0) {
            if (!confirm('¿Marcar este pago como PAID?')) {
                return;
            }
            markCommissionPaymentPaid(requestId, itemId);
        }
    });

    $('#booking_detail_content').off('click', '#btn-commission-delete').on('click', '#btn-commission-delete', function() {
        var paymentId = parseInt($(this).data('payment-id') || 0, 10);
        if (paymentId <= 0) {
            return;
        }
        if (!confirm('¿Eliminar este registro de pago?')) {
            return;
        }
        deleteCommissionPayment(paymentId, requestId);
    });

    $('#booking_detail_content').off('click', '.btn-commission-copy-link').on('click', '.btn-commission-copy-link', function() {
        var url = ($(this).data('url') || '').toString();
        if (!url) {
            return;
        }
        copyToClipboard(url);
        toastr.success('Link copiado');
    });
}

function loadCommissionPaymentStatus(requestId, itemId) {
    var $content = $('#commission-payment-content');
    if (!$content.length) {
        return;
    }
    $content.html('Loading...');
    $.ajax({
        url: 'ajax/commission_payments.php',
        method: 'GET',
        dataType: 'json',
        data: {
            action: 'get_status',
            request_id: requestId,
            item_id: itemId
        },
        success: function(response) {
            if (!response || !response.ok) {
                $content.html('No se pudo cargar la comisión.');
                return;
            }
            $content.html(renderCommissionPaymentBlock(response));
        },
        error: function() {
            $content.html('Error de conexión al cargar comisión.');
        }
    });
}

function renderCommissionPaymentBlock(data) {
    var gateEnabled = parseInt(data.gate_enabled || 0, 10) === 1;
    var status = (data.payment_status || 'NONE').toString().toUpperCase();
    var payment = data.payment || {};
    var checkoutUrl = (payment.checkout_url || '').toString().trim();
    var paidAt = (payment.paid_at || '').toString();
    var amount = (payment.amount !== undefined && payment.amount !== null) ? payment.amount : data.amount_preview;
    var currency = (payment.currency || data.amount_currency || '').toString().toUpperCase();

    var html = '' +
        '<div><strong>Gate status:</strong> ' + (gateEnabled ? 'ON' : 'OFF') + '</div>' +
        '<div><strong>Payment status:</strong> ' + escapeHtml(status) + '</div>';

    if (amount !== undefined && amount !== null && amount !== '') {
        html += '<div><strong>Amount:</strong> ' + escapeHtml(String(amount)) + (currency ? ' ' + escapeHtml(currency) : '') + '</div>';
    }

    if (status === 'PENDING') {
        if (checkoutUrl) {
            html += '<div style="margin-top:6px;"><strong>Checkout:</strong> ' +
                '<span style="word-break:break-all;">' + escapeHtml(checkoutUrl) + '</span> ' +
                '<button type="button" class="btn btn-default btn-xs btn-commission-copy-link" data-url="' + escapeHtml(checkoutUrl) + '">Copy Link</button>' +
                '</div>';
        } else {
            html += '<div style="margin-top:6px;"><strong>Checkout:</strong> manual</div>';
        }
    }
    if (status === 'PAID' && paidAt) {
        html += '<div style="margin-top:6px;"><strong>Paid at:</strong> ' + escapeHtml(paidAt) + '</div>';
    }

    var actions = '';
    if (gateEnabled && status === 'NONE') {
        actions += '<button type="button" id="btn-commission-create" class="btn btn-primary btn-xs">Create payment link</button> ';
    }
    if (gateEnabled && status === 'PENDING') {
        actions += '<button type="button" id="btn-commission-mark-paid" class="btn btn-success btn-xs">Mark as paid</button> ';
    }
    if (status === 'PENDING' || status === 'PAID') {
        actions += '<button type="button" id="btn-commission-delete" class="btn btn-danger btn-xs" data-payment-id="' + escapeHtml(payment.id || '') + '">Delete payment record</button>';
    }
    if (actions) {
        html += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">' + actions + '</div>';
    }
    return html;
}

function createCommissionPayment(requestId, itemId) {
    $.ajax({
        url: 'ajax/commission_payments.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'create_link',
            request_id: requestId,
            item_id: itemId
        },
        success: function(response) {
            if (!response || !response.ok) {
                toastr.error((response && response.message) ? response.message : 'No se pudo crear el pago');
                return;
            }
            toastr.success('Pago creado');
            loadCommissionPaymentStatus(requestId, itemId);
        },
        error: function() {
            toastr.error('Error de conexión al crear pago');
        }
    });
}

function markCommissionPaymentPaid(requestId, itemId) {
    $.ajax({
        url: 'ajax/commission_payments.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'mark_paid',
            request_id: requestId,
            item_id: itemId
        },
        success: function(response) {
            if (!response || !response.ok) {
                toastr.error((response && response.message) ? response.message : 'No se pudo marcar como pagado');
                return;
            }
            toastr.success('Pago marcado como PAID');
            loadCommissionPaymentStatus(requestId, itemId);
        },
        error: function() {
            toastr.error('Error de conexión al marcar pago');
        }
    });
}

function deleteCommissionPayment(paymentId, requestId) {
    $.ajax({
        url: 'ajax/commission_payments.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'delete',
            payment_id: paymentId
        },
        success: function(response) {
            if (!response || !response.ok) {
                toastr.error((response && response.message) ? response.message : 'No se pudo eliminar el pago');
                return;
            }
            toastr.success('Pago eliminado');
            var itemId = parseInt($('#commission-item-select').val() || 0, 10);
            if (requestId > 0 && itemId > 0) {
                loadCommissionPaymentStatus(requestId, itemId);
            }
        },
        error: function() {
            toastr.error('Error de conexión al eliminar pago');
        }
    });
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
        return;
    }
    var $tmp = $('<textarea>');
    $tmp.val(text).appendTo('body').select();
    document.execCommand('copy');
    $tmp.remove();
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

function markFeePaid(bookingId) {
    var confirmation = prompt('Type "PAID" to confirm marking the fee as paid:');
    if (!confirmation || confirmation.trim().toUpperCase() !== 'PAID') {
        toastr.info('Confirmation not provided.');
        return;
    }

    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'mark_fee_paid', booking_id: bookingId },
        dataType: 'json',
        success: function(response) {
            if (response && response.success) {
                toastr.success('Fee marked as paid');
                loadBookingRequests();
            } else {
                toastr.error((response && response.message) ? response.message : 'Error marking fee as paid');
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

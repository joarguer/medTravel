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
                renderBookingDetail(response.data);
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

function renderBookingDetail(data) {
    var selectedOffers = [];
    try {
        selectedOffers = JSON.parse(data.selected_offers || '[]');
    } catch(e) {
        selectedOffers = [];
    }

    var html = `
        <div class="row">
            <div class="col-md-6">
                <h4>Client Information</h4>
                <p><strong>Name:</strong> ${escapeHtml(data.name)}</p>
                <p><strong>Email:</strong> ${escapeHtml(data.email)}</p>
                ${data.phone ? '<p><strong>Phone:</strong> ' + escapeHtml(data.phone) + '</p>' : ''}
            </div>
            <div class="col-md-6">
                <h4>Travel Information</h4>
                ${data.destination ? '<p><strong>Destination:</strong> ' + escapeHtml(data.destination) + '</p>' : ''}
                ${data.booking_datetime ? '<p><strong>Preferred Date:</strong> ' + escapeHtml(data.booking_datetime) + '</p>' : ''}
                ${data.persons ? '<p><strong>Persons:</strong> ' + escapeHtml(data.persons) + '</p>' : ''}
                ${data.timeline ? '<p><strong>Timeline:</strong> ' + escapeHtml(data.timeline) + '</p>' : ''}
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-12">
                <h4>Selected Services (${selectedOffers.length})</h4>
                <div id="selected_offers_list">Loading...</div>
            </div>
        </div>
        ${data.budget ? '<hr><p><strong>Budget:</strong> $' + parseFloat(data.budget).toLocaleString() + ' USD</p>' : ''}
        ${data.special_request ? '<hr><p><strong>Special Request:</strong><br>' + escapeHtml(data.special_request) + '</p>' : ''}
        ${data.additional_notes ? '<hr><p><strong>Additional Notes:</strong><br>' + escapeHtml(data.additional_notes) + '</p>' : ''}
        <hr>
        <p><strong>Status:</strong> <span class="label label-info">${escapeHtml(data.status)}</span></p>
        <p><strong>Origin:</strong> ${escapeHtml(data.origin)}</p>
        <p><strong>Created:</strong> ${new Date(data.created_at).toLocaleString()}</p>
    `;

    $('#booking_detail_content').html(html);

    // Cargar detalles de las ofertas seleccionadas
    if (selectedOffers.length > 0) {
        loadSelectedOffersDetails(selectedOffers);
    } else {
        $('#selected_offers_list').html('<p class="text-muted">No services selected</p>');
    }
}

function loadSelectedOffersDetails(offerIds) {
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
                $('#selected_offers_list').html(html);
            } else {
                $('#selected_offers_list').html('<p class="text-danger">Error loading services</p>');
            }
        },
        error: function() {
            $('#selected_offers_list').html('<p class="text-danger">Connection error</p>');
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
    if (!confirm('Are you sure you want to delete this booking request? This action cannot be undone.')) {
        return;
    }

    $.ajax({
        url: 'ajax/booking_requests.php',
        type: 'POST',
        data: { action: 'delete', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Booking request deleted');
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

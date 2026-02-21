var testimonialsTable;

$(document).ready(function(){
    initTable();
    bindEvents();
});

function initTable(){
    testimonialsTable = $('#testimonials_table').DataTable({
        ajax: function(_data, callback){
            var status = $('#testimonial_status_filter').val();
            $.getJSON('ajax/testimonials.php', { action: 'list', status: status }, function(res){
                if (!res || !res.ok) {
                    toastr.error(res && res.message ? res.message : 'Error loading testimonials');
                    callback({ data: [] });
                    return;
                }
                callback({ data: res.data || [] });
            }).fail(function(){
                toastr.error('Error loading testimonials');
                callback({ data: [] });
            });
        },
        columns: [
            { data: 'id' },
            { data: 'client_name', render: safe },
            { data: 'client_location', render: safe },
            { data: 'rating', render: renderRating },
            { data: 'status', render: renderStatus },
            { data: 'comment', render: renderComment },
            { data: 'created_at', render: renderDate },
            { data: null, orderable: false, render: renderActions }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/English.json'
        }
    });
}

function bindEvents(){
    $('#testimonial_status_filter').on('change', function(){
        testimonialsTable.ajax.reload();
    });

    $('#testimonials_table').on('click', '.btn-approve', function(){
        var id = $(this).data('id');
        updateStatus(id, 'approve');
    });

    $('#testimonials_table').on('click', '.btn-reject', function(){
        var id = $(this).data('id');
        updateStatus(id, 'reject');
    });
}

function updateStatus(id, action){
    $.ajax({
        url: 'ajax/testimonials.php',
        type: 'POST',
        dataType: 'json',
        data: { action: action, id: id }
    }).done(function(res){
        if (res && res.ok) {
            toastr.success('Updated');
            testimonialsTable.ajax.reload(null, false);
            return;
        }
        toastr.error(res && res.message ? res.message : 'Error updating');
    }).fail(function(){
        toastr.error('Error updating');
    });
}

function safe(val){
    return val ? $('<div>').text(val).html() : '';
}

function renderRating(val){
    var rating = parseInt(val || 0, 10);
    if (!rating) return '';
    var html = '';
    for (var i = 1; i <= 5; i++) {
        html += '<i class="fas fa-star ' + (i <= rating ? 'text-primary' : 'text-muted') + '"></i>';
    }
    return html;
}

function renderStatus(val){
    var map = {
        pending: '<span class="label label-info">Pending</span>',
        approved: '<span class="label label-success">Approved</span>',
        rejected: '<span class="label label-danger">Rejected</span>',
        archived: '<span class="label label-default">Archived</span>'
    };
    return map[val] || safe(val);
}

function renderComment(val){
    if (!val) return '';
    var text = String(val);
    if (text.length > 140) {
        text = text.slice(0, 140) + '...';
    }
    return safe(text);
}

function renderDate(val){
    if (!val) return '';
    var date = new Date(val);
    if (isNaN(date.getTime())) return safe(val);
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var year = date.getFullYear();
    return day + '/' + month + '/' + year;
}

function renderActions(_val, _type, row){
    if (row.status === 'pending') {
        return '<button class="btn btn-xs btn-success btn-approve" data-id="' + row.id + '"><i class="fa fa-check"></i> Approve</button> '
            + '<button class="btn btn-xs btn-danger btn-reject" data-id="' + row.id + '"><i class="fa fa-times"></i> Reject</button>';
    }
    return '<span class="text-muted">-</span>';
}

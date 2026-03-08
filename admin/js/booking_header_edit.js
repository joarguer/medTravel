var bookingHeaderData = {};

function bookingHeaderToast(type, message, title) {
    if (!window.toastr) {
        return;
    }
    toastr.options = {
        closeButton: true,
        newestOnTop: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: type === 'error' ? '6000' : '2800'
    };
    toastr[type](message, title || '');
}

function renderBookingHeaderPreview(data) {
    var title = $.trim(data.title || '') || 'Online Booking';
    var subtitle = $.trim(data.subtitle || '') || 'Submit your medical travel request and receive a coordinated plan with trusted providers in Colombia.';
    var bgImage = $.trim(data.bg_image || '');

    $('#booking-header-preview-title').text(title);
    $('#booking-header-preview-subtitle').text(subtitle);

    if (bgImage !== '') {
        var imageUrl = '../' + bgImage.replace(/^\/+/, '');
        $('#booking-header-preview').css('background-image', 'linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url(' + imageUrl + ')');
        $('#booking-header-image-preview-img').attr('src', imageUrl);
        $('#booking-header-image-preview').show();
        $('#booking_header_bg_image_display').val(bgImage.split('/').pop());
    } else {
        $('#booking-header-preview').css('background-image', 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)');
        $('#booking-header-image-preview-img').attr('src', '');
        $('#booking-header-image-preview').hide();
        $('#booking_header_bg_image_display').val('');
    }
}

function fillBookingHeaderForm(header) {
    bookingHeaderData = header || {};
    $('#booking_header_id').val(bookingHeaderData.id || '');
    $('#booking_header_title').val(bookingHeaderData.title || '');
    $('#booking_header_subtitle').val(bookingHeaderData.subtitle || '');
    $('#booking_header_bg_image').val(bookingHeaderData.bg_image || '');
    renderBookingHeaderPreview(bookingHeaderData);
}

function loadBookingHeader() {
    $.post('ajax/booking_header_edit.php', { tipo: 'get_header' }, function(res) {
        var response = typeof res === 'string' ? JSON.parse(res) : res;
        if (response.status === 'ok') {
            fillBookingHeaderForm(response.header || {});
        } else {
            bookingHeaderToast('error', response.message || 'No fue posible cargar el header.');
        }
    }).fail(function(xhr) {
        var message = 'Error de conexión al cargar el header.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }
        bookingHeaderToast('error', message);
    });
}

$(document).ready(function() {
    loadBookingHeader();

    $('#booking_header_title, #booking_header_subtitle').on('input', function() {
        renderBookingHeaderPreview({
            title: $('#booking_header_title').val(),
            subtitle: $('#booking_header_subtitle').val(),
            bg_image: $('#booking_header_bg_image').val()
        });
    });

    $('#booking_header_image_file').on('change', function() {
        if (!this.files || !this.files[0]) {
            return;
        }

        var formData = new FormData();
        formData.append('tipo', 'upload_header_image');
        formData.append('image', this.files[0]);
        $('#booking-header-image-uploading').show();

        $.ajax({
            url: 'ajax/booking_header_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                var response = typeof res === 'string' ? JSON.parse(res) : res;
                if (response.status === 'ok') {
                    fillBookingHeaderForm(response.header || {});
                    bookingHeaderToast('success', 'Imagen del header actualizada.', 'Guardado');
                } else {
                    bookingHeaderToast('error', response.message || 'No fue posible subir la imagen.');
                }
            },
            error: function(xhr) {
                var message = 'No fue posible subir la imagen.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                bookingHeaderToast('error', message);
            },
            complete: function() {
                $('#booking-header-image-uploading').hide();
                $('#booking_header_image_file').val('');
            }
        });
    });

    $('#booking-header-form').on('submit', function(e) {
        e.preventDefault();
        $.post('ajax/booking_header_edit.php', {
            tipo: 'save_header',
            title: $('#booking_header_title').val(),
            subtitle: $('#booking_header_subtitle').val(),
            bg_image: $('#booking_header_bg_image').val()
        }, function(res) {
            var response = typeof res === 'string' ? JSON.parse(res) : res;
            if (response.status === 'ok') {
                fillBookingHeaderForm(response.header || {});
                bookingHeaderToast('success', 'Header de booking actualizado.', 'Guardado');
            } else {
                bookingHeaderToast('error', response.message || 'No fue posible guardar el header.');
            }
        }).fail(function(xhr) {
            var message = 'No fue posible guardar el header.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            bookingHeaderToast('error', message);
        });
    });
});

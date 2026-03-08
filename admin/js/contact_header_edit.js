var contactHeaderData = {};

function contactHeaderToast(type, message, title) {
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

function renderContactHeaderPreview(data) {
    var title = $.trim(data.title || '') || 'Contact Us';
    var subtitle = $.trim(data.subtitle || '') || 'Talk to MedTravel about providers, coordination, and booking support for your medical journey.';
    var bgImage = $.trim(data.bg_image || '');

    $('#contact-header-preview-title').text(title);
    $('#contact-header-preview-subtitle').text(subtitle);

    if (bgImage !== '') {
        var imageUrl = '../' + bgImage.replace(/^\/+/, '');
        $('#contact-header-preview').css('background-image', 'linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url(' + imageUrl + ')');
        $('#contact-header-image-preview-img').attr('src', imageUrl);
        $('#contact-header-image-preview').show();
        $('#contact_header_bg_image_display').val(bgImage.split('/').pop());
    } else {
        $('#contact-header-preview').css('background-image', 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)');
        $('#contact-header-image-preview-img').attr('src', '');
        $('#contact-header-image-preview').hide();
        $('#contact_header_bg_image_display').val('');
    }
}

function fillContactHeaderForm(header) {
    contactHeaderData = header || {};
    $('#contact_header_id').val(contactHeaderData.id || '');
    $('#contact_header_title').val(contactHeaderData.title || '');
    $('#contact_header_subtitle').val(contactHeaderData.subtitle || '');
    $('#contact_header_bg_image').val(contactHeaderData.bg_image || '');
    renderContactHeaderPreview(contactHeaderData);
}

function loadContactHeader() {
    $.post('ajax/contact_header_edit.php', { tipo: 'get_header' }, function(res) {
        var response = typeof res === 'string' ? JSON.parse(res) : res;
        if (response.status === 'ok') {
            fillContactHeaderForm(response.header || {});
        } else {
            contactHeaderToast('error', response.message || 'No fue posible cargar el header.');
        }
    }).fail(function(xhr) {
        var message = 'Error de conexión al cargar el header.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }
        contactHeaderToast('error', message);
    });
}

$(document).ready(function() {
    loadContactHeader();

    $('#contact_header_title, #contact_header_subtitle').on('input', function() {
        renderContactHeaderPreview({
            title: $('#contact_header_title').val(),
            subtitle: $('#contact_header_subtitle').val(),
            bg_image: $('#contact_header_bg_image').val()
        });
    });

    $('#contact_header_image_file').on('change', function() {
        if (!this.files || !this.files[0]) {
            return;
        }

        var formData = new FormData();
        formData.append('tipo', 'upload_header_image');
        formData.append('image', this.files[0]);
        $('#contact-header-image-uploading').show();

        $.ajax({
            url: 'ajax/contact_header_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                var response = typeof res === 'string' ? JSON.parse(res) : res;
                if (response.status === 'ok') {
                    fillContactHeaderForm(response.header || {});
                    contactHeaderToast('success', 'Imagen del header actualizada.', 'Guardado');
                } else {
                    contactHeaderToast('error', response.message || 'No fue posible subir la imagen.');
                }
            },
            error: function(xhr) {
                var message = 'No fue posible subir la imagen.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                contactHeaderToast('error', message);
            },
            complete: function() {
                $('#contact-header-image-uploading').hide();
                $('#contact_header_image_file').val('');
            }
        });
    });

    $('#contact-header-form').on('submit', function(e) {
        e.preventDefault();
        $.post('ajax/contact_header_edit.php', {
            tipo: 'save_header',
            title: $('#contact_header_title').val(),
            subtitle: $('#contact_header_subtitle').val(),
            bg_image: $('#contact_header_bg_image').val()
        }, function(res) {
            var response = typeof res === 'string' ? JSON.parse(res) : res;
            if (response.status === 'ok') {
                fillContactHeaderForm(response.header || {});
                contactHeaderToast('success', 'Header de contacto actualizado.', 'Guardado');
            } else {
                contactHeaderToast('error', response.message || 'No fue posible guardar el header.');
            }
        }).fail(function(xhr) {
            var message = 'No fue posible guardar el header.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            contactHeaderToast('error', message);
        });
    });
});

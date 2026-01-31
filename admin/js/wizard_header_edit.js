/**
 * Wizard Header Edit - JavaScript
 * Gestiona la edición del header del wizard de booking
 */

let dataheader = {};

// Cargar header al iniciar la página
$(document).ready(function() {
    open_header();
});

// Cargar información del header
function open_header() {
    $('.btn-header').addClass('active');
    
    $.ajax({
        url: 'ajax/wizard_header_edit.php',
        type: 'POST',
        data: { tipo: 'get_header' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                dataheader = response.data || {};
                render_header_form(response.data);
            } else {
                $('.page-content-col').html('<div class="alert alert-danger">' + (response.message || 'Error al cargar el header. Ejecute el SQL CREATE_booking_wizard_header.sql') + '</div>');
            }
        },
        error: function() {
            $('.page-content-col').html('<div class="alert alert-danger">Error de conexión al cargar el header</div>');
        }
    });
}

// Renderizar formulario del header
function render_header_form(data) {
    var html = `
        <div class="row margin-bottom-40 offers-header" style="background-image: url('https://medtravel.com.co/${data.bg_image}')">
            <div class="col-md-12">
                <h4>${escapeHtml(data.subtitle_1)} / ${escapeHtml(data.subtitle_2)}</h4>
                <h1>${escapeHtml(data.title)}</h1>
            </div>
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-white btn-block" onclick="editHeaderImage()">
                <i class="fa fa-image"></i> Change Header Image
            </button>
        </div>
        <div class="form-group">
            <label>Main Title</label>
            <input type="text" class="form-control" id="title" value="${escapeHtml(data.title)}" onchange="editHeader('title')">
            <small class="text-muted">Example: Booking Wizard</small>
        </div>
        <div class="form-group">
            <label>Breadcrumb Link 1</label>
            <input type="text" class="form-control" id="subtitle_1" value="${escapeHtml(data.subtitle_1)}" onchange="editHeader('subtitle_1')">
            <small class="text-muted">Example: Home</small>
        </div>
        <div class="form-group">
            <label>Breadcrumb Link 2</label>
            <input type="text" class="form-control" id="subtitle_2" value="${escapeHtml(data.subtitle_2)}" onchange="editHeader('subtitle_2')">
            <small class="text-muted">Example: Booking Request</small>
        </div>
    `;

    $('.page-content-col').html(html);
    
    // Aplicar estilos al preview del header
    $('.offers-header').css({
        'background-size': 'cover',
        'background-position': 'center'
    });
}

// Editar un campo del header
function editHeader(field) {
    var value = $('#' + field).val();
    
    $.ajax({
        url: 'ajax/wizard_header_edit.php',
        type: 'POST',
        data: {
            tipo: 'edit_header',
            field: field,
            value: value
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Campo actualizado', 'Éxito');
                // Actualizar preview en vivo
                if (field === 'title') {
                    $('.offers-header h1').text(value);
                } else if (field === 'subtitle_1' || field === 'subtitle_2') {
                    var sub1 = $('#subtitle_1').val();
                    var sub2 = $('#subtitle_2').val();
                    $('.offers-header h4').text(sub1 + ' / ' + sub2);
                }
            } else {
                toastr.error(response.message || 'Error al actualizar', 'Error');
            }
        },
        error: function() {
            toastr.error('Error de conexión', 'Error');
        }
    });
}

// Editar imagen del header
function editHeaderImage() {
    var file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    
    file.onchange = function() {
        if (!file.files || !file.files[0]) {
            return;
        }
        
        var formData = new FormData();
        formData.append('tipo', 'upload_header_image');
        formData.append('image', file.files[0]);
        
        // Mostrar loader
        toastr.info('Subiendo imagen...', 'Procesando');
        
        $.ajax({
            url: 'ajax/wizard_header_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Response:', response);
                
                // Intentar parsear si viene como string
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch(e) {
                        console.error('Error parsing response:', e);
                        toastr.error('Error al procesar la respuesta del servidor');
                        return;
                    }
                }
                
                if (response.success) {
                    toastr.success('Imagen actualizada correctamente', 'Éxito');
                    // Recargar el header para mostrar la nueva imagen
                    setTimeout(function() {
                        open_header();
                    }, 1000);
                } else {
                    toastr.error(response.message || 'Error al subir la imagen', 'Error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', status, error);
                console.error('Response:', xhr.responseText);
                toastr.error('Error al subir la imagen: ' + error, 'Error');
            }
        });
    };
}

// Función auxiliar para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

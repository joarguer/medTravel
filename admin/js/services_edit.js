let dataheader = {};

function refreshServicesData(callback){
    let url = 'ajax/services_edit.php';
    let data = {
        tipo: 'get_header'
    };
    $.post(url, data, function(res){
        try {
            let response = JSON.parse(res);
            
            // Verificar si hay error de tabla no existente
            if (response.error === 'tabla_no_existe') {
                $('.page-content-col').html(`
                    <div class="alert alert-danger">
                        <h4><i class="fa fa-exclamation-triangle"></i> Tabla no encontrada</h4>
                        <p>${response.message}</p>
                        <hr>
                        <p>Por favor ejecute el siguiente script SQL en phpMyAdmin:</p>
                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
CREATE TABLE IF NOT EXISTS services_header (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL DEFAULT 'Our Medical Services',
    subtitle_1 VARCHAR(255) NOT NULL DEFAULT 'MEDICAL SERVICES',
    subtitle_2 TEXT,
    bg_image VARCHAR(500),
    activo ENUM('0','1') DEFAULT '0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO services_header (title, subtitle_1, subtitle_2, activo) 
VALUES ('Our Medical Services', 'MEDICAL SERVICES', 'Discover quality medical services from verified providers', '0');
                        </pre>
                    </div>
                `);
                return;
            }
            
            // Verificar si hay error en la query
            if (response.error === 'query_error') {
                $('.page-content-col').html(`
                    <div class="alert alert-danger">
                        <h4><i class="fa fa-exclamation-triangle"></i> Error de base de datos</h4>
                        <p>${response.message}</p>
                    </div>
                `);
                return;
            }
            
            dataheader = response['header'] || {};
            if(typeof callback === 'function'){
                callback();
            }
        } catch(e) {
            console.error('Error parsing response:', e);
            console.log('Response:', res);
            $('.page-content-col').html('<div class="alert alert-danger">Error al cargar datos: ' + e.message + '<br><pre>' + res + '</pre></div>');
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX Error:', error);
        console.log('Status:', status);
        console.log('Response:', xhr.responseText);
        $('.page-content-col').html('<div class="alert alert-danger">Error de conexión: ' + error + '<br>Status: ' + status + '<br><pre>' + xhr.responseText + '</pre></div>');
    });
}

function renderHeaderView(){
    if(!dataheader || !dataheader.id){
        // Si no hay datos, crear el formulario para insertar
        let body = `
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> No existe configuración. Complete el formulario para crear una.
            </div>
            <div class="form-group">
                <label>Título Principal</label>
                <input type="text" class="form-control" id="new_title" value="Our Medical Services" placeholder="Our Medical Services">
            </div>
            <div class="form-group">
                <label>Subtítulo 1 (Superior)</label>
                <input type="text" class="form-control" id="new_subtitle_1" value="MEDICAL SERVICES" placeholder="MEDICAL SERVICES">
            </div>
            <div class="form-group">
                <label>Subtítulo 2 (Descripción)</label>
                <input type="text" class="form-control" id="new_subtitle_2" value="Discover quality medical services from verified providers" placeholder="Discover quality medical services">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-primary" onclick="createHeader()">
                    <i class="fa fa-save"></i> Crear Configuración
                </button>
            </div>`;
        $('.page-content-col').html(body);
        return;
    }
    
    let id = dataheader.id;
    let title = dataheader.title || 'Our Medical Services';
    let subtitle_1 = dataheader.subtitle_1 || 'MEDICAL SERVICES';
    let subtitle_2 = dataheader.subtitle_2 || 'Discover quality medical services';
    let bg_image = dataheader.bg_image || '';
    
    let backgroundStyle = bg_image ? `background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url(https://medtravel.com.co/${bg_image});` : 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);';
    
    let body = `
        <div class="services-header" id="header_0" style="${backgroundStyle}">
            <div class="col-md-12">
                <h1 id="title_edit">${title}</h1>
                <p id="parrafo_edit"><span>${subtitle_1}</span> / ${subtitle_2}</p>
            </div>
        </div>
        <div class="form-group">
            <label>Título Principal</label>
            <input type="text" onchange="editInputSubmit('title',${id})" class="form-control" id="title" value="${title}" placeholder="Our Medical Services">
        </div>
        <div class="form-group">
            <label>Subtítulo 1 (Superior)</label>
            <input type="text" onchange="editInputSubmit('subtitle_1',${id})" class="form-control" id="subtitle_1" value="${subtitle_1}" placeholder="MEDICAL SERVICES">
        </div>
        <div class="form-group">
            <label>Subtítulo 2 (Descripción)</label>
            <input type="text" onchange="editInputSubmit('subtitle_2',${id})" class="form-control" id="subtitle_2" value="${subtitle_2}" placeholder="Discover quality medical services">
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-white btn-block" onclick="editImg(${id})">
                <i class="fa fa-image"></i> Cambiar imagen de fondo
            </button>
        </div>`;
    
    $('.page-content-col').html(body);
}

$(document).ready(function(){
    refreshServicesData(renderHeaderView);
});

function remove_active(){
    $('.btn-header').removeClass('active');
}

function open_header(id){
    remove_active();
    $('.btn-header').addClass('active');
    renderHeaderView();
}

function createHeader(){
    let title = $('#new_title').val();
    let subtitle_1 = $('#new_subtitle_1').val();
    let subtitle_2 = $('#new_subtitle_2').val();
    
    let url = 'ajax/services_edit.php';
    let data = {
        tipo: 'create_header',
        title: title,
        subtitle_1: subtitle_1,
        subtitle_2: subtitle_2
    };
    
    $.post(url, data, function(res){
        try {
            let response = JSON.parse(res);
            if(response.success){
                notification(response.message, '¡Éxito!', 'success');
                refreshServicesData(renderHeaderView);
            } else {
                notification(response.message, 'Error', 'error');
            }
        } catch(e) {
            notification('Error al procesar respuesta: ' + e.message, 'Error', 'error');
        }
    }).fail(function(){
        notification('Error de conexión al servidor', 'Error', 'error');
    });
}

function editInputSubmit(campo, id){
    let valor = $('#'+campo).val();
    let url = 'ajax/services_edit.php';
    let data = {
        tipo: 'edit_campo',
        campo: campo,
        valor: valor,
        id: id
    };
    $.post(url, data, function(res){
        try {
            let response = JSON.parse(res);
            if(response.success){
                $('#'+campo+'_edit').html(valor);
                notification(response.message, '¡Éxito!', 'success');
                refreshServicesData();
            } else {
                notification(response.message, 'Error', 'error');
            }
        } catch(e) {
            notification('Error al procesar respuesta: ' + e.message, 'Error', 'error');
        }
    }).fail(function(){
        notification('Error de conexión al servidor', 'Error', 'error');
    });
}

function editImg(id){
    let title = $('#title').val() || 'services-header';
    title = title.replace(/\s/g, '_');
    title = title.replace(/[^\w\s]/gi, '');
    title = title.trim();
    
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    
    file.onchange = function(){
        let formData = new FormData();
        formData.append('tipo', 'upload_image');
        formData.append('id', id);
        formData.append('image', file.files[0]);
        formData.append('title', title);
        
        $.ajax({
            url: 'ajax/services_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res){
                try {
                    let response = JSON.parse(res);
                    if(response.success){
                        notification(response.message, '¡Éxito!', 'success');
                        refreshServicesData(renderHeaderView);
                    } else {
                        notification(response.message, 'Error', 'error');
                    }
                } catch(e) {
                    notification('Error al procesar respuesta: ' + e.message, 'Error', 'error');
                }
            },
            error: function(){
                notification('Error al subir la imagen', 'Error', 'error');
            }
        });
    };
}

function notification(text, title, status){
    if(status == "success"){
        toastr.success(text, title);
    } else {
        toastr.error(text, title);
    }
    
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "positionClass": "toast-top-right",
        "onclick": null,
        "showDuration": "1000",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };
}

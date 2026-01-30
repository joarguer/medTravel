let dataheader = {};

$(document).ready(function(){
    open_header();
});

function open_header(){
    $('.btn-header').addClass('active');
    $('.btn-service').removeClass('active');
    
    let url = 'ajax/services_edit.php';
    $.post(url, {tipo: 'get_header'}, function(res){
        let response = JSON.parse(res);
        dataheader = response.header || {};
        
        if(!dataheader.id){
            $('.page-content-col').html('<div class="alert alert-danger">No se encontró configuración. Ejecute el SQL services_coordination_table.sql</div>');
            return;
        }
        
        let body = `
            <div class="row margin-bottom-40 services-header" style="background-image: url('https://medtravel.com.co/${dataheader.header_image}')">
                <div class="col-md-12">
                    <h4>${dataheader.subtitle}</h4>
                    <h1>${dataheader.main_title}</h1>
                </div>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-white btn-block" onclick="editHeaderImage(${dataheader.id})">
                    <i class="fa fa-image"></i> Change Header Image
                </button>
            </div>
            <div class="form-group">
                <label>Section Subtitle</label>
                <input type="text" class="form-control" id="subtitle" value="${dataheader.subtitle}" onchange="editHeader('subtitle')">
            </div>
            <div class="form-group">
                <label>Main Title</label>
                <input type="text" class="form-control" id="main_title" value="${dataheader.main_title}" onchange="editHeader('main_title')">
            </div>
            <div class="form-group">
                <label>Description (shown below services)</label>
                <textarea class="form-control" rows="3" id="description" onchange="editHeader('description')">${dataheader.description}</textarea>
            </div>`;
        $('.page-content-col').html(body);
        
        // Aplicar estilos al preview del header
        $('.services-header').css({
            'background-size': 'cover',
            'background-position': 'center'
        });
        $('.services-header h4').css({
            'font-size': '16px',
            'font-weight': '600',
            'margin-top': '80px',
            'color': '#FFA500'
        });
        $('.services-header h1').css({
            'font-size': '32px',
            'font-weight': '800',
            'margin-top': '10px',
            'text-shadow': '1px 1px 2px rgba(0, 0, 0, 0.3)',
            'margin-bottom': '60px'
        });
    });
}

function editHeader(field){
    let value = $('#' + field).val();
    let url = 'ajax/services_edit.php';
    let data = {
        tipo: 'edit_header',
        id: dataheader.id,
        field: field,
        value: value
    };
    
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status === 'success'){
            toastr.success('Campo actualizado', 'Éxito');
            // Actualizar preview en vivo
            if(field === 'subtitle'){
                $('.services-header h4').text(value);
            } else if(field === 'main_title'){
                $('.services-header h1').text(value);
            }
        } else {
            toastr.error('Error al actualizar', 'Error');
        }
    });
}

function open_service(index, id){
    $('.btn-header').removeClass('active');
    $('.btn-service').removeClass('active');
    $('#btn-service-' + index).addClass('active');
    
    let url = 'ajax/services_edit.php';
    $.post(url, {tipo: 'get_service', id: id}, function(res){
        let service = JSON.parse(res);
        
        let body = `
            <h3>Edit Service: ${service.title}</h3>
            <div class="form-group">
                <label>Icon Preview</label>
                <div style="padding: 20px; background: #f5f5f5; border-radius: 5px; text-align: center; margin-bottom: 15px;">
                    <i id="icon-preview" class="${service.icon_class}" style="font-size: 64px; color: #13357B;"></i>
                </div>
            </div>
            <div class="form-group">
                <label>Icon Class (Font Awesome)</label>
                <input type="text" class="form-control" id="icon_class" value="${service.icon_class}" placeholder="fa fa-heartbeat" onkeyup="updateIconPreview()">
                <small class="text-muted">Examples: fa fa-heartbeat, fa fa-plane, fa fa-hotel, fa fa-car, fa fa-cutlery, fa fa-headphones</small>
            </div>
            <div class="form-group">
                <label>Title</label>
                <input type="text" class="form-control" id="title" value="${service.title}">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" rows="5" id="description">${service.description}</textarea>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-primary" onclick="saveService(${id})">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>`;
        $('.page-content-col').html(body);
    });
}

function updateIconPreview(){
    let iconClass = $('#icon_class').val();
    $('#icon-preview').attr('class', iconClass).css({'font-size': '64px', 'color': '#13357B'});
}

function saveService(id){
    let icon_class = $('#icon_class').val();
    let title = $('#title').val();
    let description = $('#description').val();
    
    let url = 'ajax/services_edit.php';
    let data = {
        tipo: 'edit_service',
        id: id,
        icon_class: icon_class,
        title: title,
        description: description
    };
    
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status === 'success'){
            toastr.success('Service updated successfully', 'Success');
        } else {
            toastr.error('Error updating service', 'Error');
        }
    });
}

function editHeaderImage(id){
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    
    file.onchange = function(){
        let formData = new FormData();
        formData.append('tipo', 'upload_header_image');
        formData.append('id', id);
        formData.append('image', file.files[0]);
        
        $.ajax({
            url: 'ajax/services_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                if(response.status === 'success'){
                    toastr.success('Image uploaded successfully', 'Success');
                    open_header(); // Reload to show new image
                } else {
                    toastr.error(response.message || 'Error uploading image', 'Error');
                }
            },
            error: function(){
                toastr.error('Connection error', 'Error');
            }
        });
    };
}

function uploadHeaderImage(){
    let fileInput = document.getElementById('header_image');
    let file = fileInput.files[0];
    
    if(!file){
        toastr.warning('Please select an image', 'Warning');
        return;
    }
    
    let formData = new FormData();
    formData.append('tipo', 'upload_header_image');
    formData.append('id', dataheader.id);
    formData.append('header_image', file);
    
    $.ajax({
        url: 'ajax/services_edit.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            let response = JSON.parse(res);
            if(response.status === 'success'){
                toastr.success('Image uploaded successfully', 'Success');
                open_header(); // Recargar para mostrar la nueva imagen
            } else {
                toastr.error(response.message || 'Error uploading image', 'Error');
            }
        },
        error: function(){
            toastr.error('Error uploading image', 'Error');
        }
    });
}

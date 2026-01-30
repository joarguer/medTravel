let dataheader = {};

$(document).ready(function(){
    open_header();
});

function open_header(){
    $('.btn-header').addClass('active');
    
    let url = 'ajax/offers_header_edit.php';
    $.post(url, {tipo: 'get_header'}, function(res){
        let response = JSON.parse(res);
        dataheader = response.header || {};
        
        if(!dataheader.id){
            $('.page-content-col').html('<div class="alert alert-danger">No se encontró configuración. Ejecute el SQL offers_header_table.sql</div>');
            return;
        }
        
        let body = `
            <div class="row margin-bottom-40 offers-header" style="background-image: url('https://medtravel.com.co/${dataheader.bg_image}')">
                <div class="col-md-12">
                    <h4>${dataheader.subtitle_1}</h4>
                    <h1>${dataheader.title}</h1>
                    <p>${dataheader.subtitle_2}</p>
                </div>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-white btn-block" onclick="editHeaderImage(${dataheader.id})">
                    <i class="fa fa-image"></i> Change Header Image
                </button>
            </div>
            <div class="form-group">
                <label>Top Subtitle (Small text)</label>
                <input type="text" class="form-control" id="subtitle_1" value="${dataheader.subtitle_1}" onchange="editHeader('subtitle_1')">
                <small class="text-muted">Example: MEDICAL SERVICES</small>
            </div>
            <div class="form-group">
                <label>Main Title</label>
                <input type="text" class="form-control" id="title" value="${dataheader.title}" onchange="editHeader('title')">
                <small class="text-muted">Example: Our Medical Services</small>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" rows="2" id="subtitle_2" onchange="editHeader('subtitle_2')">${dataheader.subtitle_2}</textarea>
                <small class="text-muted">Example: Discover quality medical services from verified providers</small>
            </div>`;
        $('.page-content-col').html(body);
        
        // Aplicar estilos al preview del header
        $('.offers-header').css({
            'background-size': 'cover',
            'background-position': 'center'
        });
    });
}

function editHeader(field){
    let value = $('#' + field).val();
    let url = 'ajax/offers_header_edit.php';
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
            if(field === 'subtitle_1'){
                $('.offers-header h4').text(value);
            } else if(field === 'title'){
                $('.offers-header h1').text(value);
            } else if(field === 'subtitle_2'){
                $('.offers-header p').text(value);
            }
        } else {
            toastr.error('Error al actualizar', 'Error');
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
            url: 'ajax/offers_header_edit.php',
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

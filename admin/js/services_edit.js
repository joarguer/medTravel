let dataheader = {};

function refreshServicesData(callback){
    let url = 'ajax/services_edit.php';
    let data = {
        tipo: 'get_header'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        dataheader = response['header'] || {};
        if(typeof callback === 'function'){
            callback();
        }
    });
}

function renderHeaderView(){
    if(!dataheader || !dataheader.id){
        $('.page-content-col').html('<div class="alert alert-warning">No se encontró configuración. Por favor, ejecute el script SQL primero.</div>');
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
        let response = JSON.parse(res);
        if(response.success){
            $('#'+campo+'_edit').html(valor);
            swal("¡Éxito!", response.message, "success");
            refreshServicesData();
        } else {
            swal("Error", response.message, "error");
        }
    });
}

function editImg(id){
    swal({
        title: "Cambiar imagen de fondo",
        text: "Seleccione una nueva imagen:",
        content: {
            element: "input",
            attributes: {
                type: "file",
                accept: "image/*",
                id: "fileInput"
            }
        },
        buttons: {
            cancel: "Cancelar",
            confirm: {
                text: "Subir",
                closeModal: false
            }
        }
    }).then((result) => {
        if(result){
            let fileInput = document.getElementById('fileInput');
            if(fileInput && fileInput.files.length > 0){
                let formData = new FormData();
                formData.append('tipo', 'upload_image');
                formData.append('id', id);
                formData.append('image', fileInput.files[0]);
                
                $.ajax({
                    url: 'ajax/services_edit.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res){
                        let response = JSON.parse(res);
                        if(response.success){
                            swal("¡Éxito!", response.message, "success");
                            refreshServicesData(renderHeaderView);
                        } else {
                            swal("Error", response.message, "error");
                        }
                    },
                    error: function(){
                        swal("Error", "Error al subir la imagen", "error");
                    }
                });
            } else {
                swal("Error", "No se seleccionó ninguna imagen", "error");
            }
        }
    });
}

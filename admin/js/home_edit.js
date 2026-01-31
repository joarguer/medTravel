let dataCarrucel = [];
const HOME_EDIT_INITIAL_TAB = (typeof homeEditInitialTab !== 'undefined') ? homeEditInitialTab : '';
let bookingData = null;
let bookingPendingOpen = HOME_EDIT_INITIAL_TAB === 'booking';
let bookingFetchCompleted = false;

function escapeHtml(value){
    if(value === undefined || value === null){
        return '';
    }
    return value.toString().replace(/[&<>"'`=\/]/g, function (s) {
        return {
            '&' : '&amp;',
            '<' : '&lt;',
            '>' : '&gt;',
            '"' : '&quot;',
            "'" : '&#39;',
            '/' : '&#47;',
            '`' : '&#96;',
            '=' : '&#61;'
        }[s];
    });
}

function deactivateAllEditorTabs(){
    $('.btn-carrucel, .btn-como-funciona, .btn-service, .btn-booking').removeClass('active');
}
$(document).ready(function(){
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'get_home'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        dataCarrucel = response;
        let body = '';
        let i = 0;
        let id = response[i].id;
        let over_title = response[i].over_title;
        let title = response[i].title;
        let parrafo = response[i].parrafo;
        let btn = response[i].btn;
        let img = response[i].img;
        body += `<div class="row margin-bottom-40 about-header" id="carrucel_${i}">
                    <div class="col-md-12">
                        <h2 id="over_title_${i}">${over_title}</h2>
                        <h1 id="title_${i}">${title}</h1>
                        <p id="parrafo_${i}">${parrafo}</p>
                        <button id="botton-${i}" type="button" class="btn btn-info btn-round">${btn}</button>
                    </div>
                </div>
                <div class="form-group">
                    <input onchange="editInputSubmit('over_title',${i},${id})" type="text" class="form-control add-input" id="over_title_input_${i}" aria-describedby="Over Title" value="${over_title}" placeholder="Over Title">
                </div>
                <div class="form-group">
                    <input onchange="editInputSubmit('title',${i},${id})" type="text" class="form-control add-input" id="title_input_${i}" aria-describedby="Title" value="${title}" placeholder="Title">
                </div>
                <div class="form-group">
                    <input onchange="editInputSubmit('parrafo',${i},${id})" type="text" class="form-control add-input" id="parrafo_input_${i}" aria-describedby="Parrafo" value="${parrafo}" placeholder="Parrafo">
                </div>
                <div class="form-group">
                    <input onchange="editInputSubmit('btn',${i},${id})" type="text" class="form-control add-input" id="btn_input_${i}" aria-describedby="Button" value="${btn}" placeholder="Button">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-white btn-block" onclick="editImg(${i},${id})">Change image</button>
                </div>`;
        $('.page-content-col').html(body);
        $('.about-header').css('background-image', 'url(https://medtravel.com.co/'+img+')');
        $('.about-header').css('background-size', 'cover');
        //background-image opacity 0.5 
        $('.about-header').css('background-color', 'rgba(0, 0, 0, 0.5)');
        $('.about-header h2').css('font-size', '20px'); 
        $('.about-header h2').css('font-weight', '800');
        $('.about-header h2').css('margin-top', '130px');
        $('.about-header h2').css('text-shadow', '1px 1px 0px rgba(0, 0, 0, 0.2)');
        $('.about-header h1').css('margin-top', '-15px')
        $('.about-header p').css('margin-top', '0px');
        $('.about-header p').css('font-size', '18px');
        $('.about-header p').css('font-weight', '400');
        $('.about-header p').css('color', '#fff');
    });
    load_booking();
});

function open_carrucel(i,id){
    deactivateAllEditorTabs();
    $('#btn-select-'+i).addClass('active');
    let body = '';
    let over_title = dataCarrucel[i].over_title;
    let title = dataCarrucel[i].title;
    let parrafo = dataCarrucel[i].parrafo;
    let btn = dataCarrucel[i].btn; 
    let img = dataCarrucel[i].img;
    body += `<div class="row margin-bottom-40 about-header" id="carrucel_${i}">
                <div class="col-md-12">
                    <h2 id="over_title_${i}">${over_title}</h2>
                    <h1 id="title_1">${title}</h1>
                    <p id="parrafo_1">${parrafo}</p>
                    <button id="botton-1" type="button" class="btn btn-info btn-round">${btn}</button>
                </div>
            </div>
            <div class="form-group">
                <input onchange="editInputSubmit('over_title',${i},${id})" type="text" class="form-control" id="over_title_input_${i}" aria-describedby="Over Title" value="${over_title}" placeholder="Over Title">
            </div>
            <div class="form-group">
                <input onchange="editInputSubmit('title',${i},${id})" type="text" class="form-control" id="title_input_${i}" aria-describedby="Title" value="${title}" placeholder="Title">
            </div>
            <div class="form-group">
                <input onchange="editInputSubmit('parrafo',${i},${id})" type="text" class="form-control" id="parrafo_input_${i}" aria-describedby="Parrafo" value="${parrafo}" placeholder="Parrafo">
            </div>
            <div class="form-group">
                <input onchange="editInputSubmit('btn',${i},${id})" type="text" class="form-control" id="btn_input_${i}" aria-describedby="Button" value="${btn}" placeholder="Button">
            </div>
            <div class="form-group">
                <button type="file" class="btn btn-white btn-block" onclick="editImg(${i},${id})">Change image</button>
            </div>`;
    $('.page-content-col').html(body);
    $('.about-header').css('background-image', 'url(https://medtravel.com.co/'+img+')');
    //background-image ancho 800px
    $('.about-header').css('background-size', 'cover');
    $('.about-header h2').css('font-size', '20px'); 
    $('.about-header h2').css('font-weight', '800');
    $('.about-header h2').css('margin-top', '130px');
    $('.about-header h2').css('text-shadow', '1px 1px 0px rgba(0, 0, 0, 0.2)');
    $('.about-header h1').css('margin-top', '-15px');
    $('.about-header p').css('margin-top', '0px');
    $('.about-header p').css('font-size', '18px');
    $('.about-header p').css('font-weight', '400');
    $('.about-header p').css('color', '#fff');
}

function addCarrucel(){
    let body = '';
    body += `<div class="row margin-bottom-40 about-header" id="carrucel_0">
                <div class="col-md-12">
                    <h2 id="over_title_edit">over_title</h2>
                    <h1 id="title_edit">title</h1>
                    <p id="parrafo_edit">parrafo</p>
                    <button id="btn_edit" type="button" class="btn btn-info btn-round">btn</button>
                </div>
            </div>
            <div class="form-group">
                <input type="text" class="form-control add_input" id="over_title" aria-describedby="Over Title" value="" placeholder="Over Title">
            </div>
            <div class="form-group">
                <input type="text" class="form-control add_input" id="title" aria-describedby="Title" value="" placeholder="Title">
            </div>
            <div class="form-group">
                <input type="text" class="form-control add_input" id="parrafo" aria-describedby="Parrafo" value="" placeholder="Parrafo">
            </div>
            <div class="form-group">
                <input type="text" class="form-control add_input" id="btn" aria-describedby="Button" value="" placeholder="Button">
            </div>
            <div class="form-group">
                <button type="file" class="btn btn-white btn-block add_input" onclick="addImg()">Save Image</button>
            </div>
            <script>
            $('.add_input').on('input', function(e) {
                let text_come = e.target.value;
                let input = e.target.id;
                if(input == 'btn'){
                    $('#'+input+'_edit').text(text_come);
                } else{
                    $('#'+input+'_edit').html(text_come);
                }
            });
            </script>`;
    $('.page-content-col').html(body);
    $('.about-header').css('background-size', 'cover');
    $('.about-header').css('background-color', 'rgba(0, 0, 0, 0.5)');
    $('.about-header h2').css('font-size', '20px'); 
    $('.about-header h2').css('font-weight', '800');
    $('.about-header h2').css('margin-top', '130px');
    $('.about-header h2').css('text-shadow', '1px 1px 0px rgba(0, 0, 0, 0.2)');
    $('.about-header h1').css('margin-top', '-15px')
    $('.about-header p').css('margin-top', '0px');
    $('.about-header p').css('font-size', '18px');
    $('.about-header p').css('font-weight', '400');
    $('.about-header p').css('color', '#fff');
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'add_carrucel'
    };
}

function addImg(){
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    file.onchange = function(){
        let form = new FormData();
        form.append('file', file.files[0]);
        form.append('tipo', 'add_img');
        let url = 'ajax/home_edit.php';
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                if(response.status == 'success'){
                    let text = 'Se ha agregado la imagen';
                    let title = 'Imagen';
                    let status = 'success';
                    notification(text, title, status);
                    let img = response.ruta;
                    let id = response.id;
                    $('.about-header').css('background-image', 'url(https://medtravel.com.co/'+img+')');
                    addInput(id);
                }
            }
        });
    }
}

function addInput(id){
    let over_title = $('#over_title').val();
    let title = $('#title').val();
    let parrafo = $('#parrafo').val();
    let btn = $('#btn').val();
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'add_input',
        'over_title': over_title,
        'title': title,
        'parrafo': parrafo,
        'btn': btn,
        'id': id
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha agregado el Over Title';
            let title = 'Over Title';
            let status = 'success';
            notification(text, title, status);
            dataCarrucel.push(response);
            let i = dataCarrucel.length - 1;
            location.reload();
        }
    });
}

function editInputSubmit(input,i,id){
    $('#'+input+'_'+i).attr('onclick', '');
    let text_come = $('#'+input+'_input_'+i).val();
    let url = 'ajax/home_edit.php';
    let data = {
        id: id,
        text_come: text_come,
        input: input,
        tipo: 'edit_input'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado el Over Title';
            let title = 'Over Title';
            let status = 'success';
            notification(text, title, status);
            let text_go = response.text_go;
            $('#'+input+'_'+i).html(text_go);
            $('#'+input+'_'+i).attr('onclick', 'editOverTitle('+i+','+id+')');
            if(input == 'over_title'){
                dataCarrucel[i].over_title = text_go;
            } else if(input == 'title'){
                dataCarrucel[i].title = text_go;
            } else if(input == 'parrafo'){
                dataCarrucel[i].parrafo = text_go;
            } else if(input == 'btn'){
                dataCarrucel[i].btn = text_go;
            }
            open_carrucel(i,id);
        }
    });
} 

function editImg(i,id){
    //get file input
    let title = $('#title_input_'+i).val();
    //busco espacio en blanco y lo reemplazo por guion bajo
    title = title.replace(/\s/g, '_');
    //busco caracteres especiales y los elimino
    title = title.replace(/[^\w\s]/gi, '');
    //elimino los espacios en blanco al inicio y al final
    title = title.trim();
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    file.onchange = function(){
        let form = new FormData();
        form.append('file', file.files[0]);
        form.append('id', id);
        form.append('tipo', 'edit_img');
        form.append('title', title);
        let url = 'ajax/home_edit.php';
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                if(response.status == 'success'){
                    let text = 'Se ha actualizado la imagen';
                    let title = 'Imagen';
                    let status = 'success';
                    notification(text, title, status);
                    let img = response.ruta;
                    dataCarrucel[i].img = img;
                    open_carrucel(i,id);
                }
            }
        });
    }
}

function confirmDeleteCarrucel(i,id){
    if(!confirm('¿Eliminar este carrusel?')){
        return;
    }
    deleteCarrucel(id);
}

function deleteCarrucel(id){
    if(!id){
        toastr.error('No se encontró el registro','Eliminar');
        return;
    }
    $.ajax({
        url: 'ajax/delete_carrucel.php',
        type: 'POST',
        dataType: 'json',
        data: { id: id },
        success: function(response){
            if(response.ok){
                toastr.success('El carrusel fue eliminado','Eliminar');
                location.reload();
            } else {
                toastr.error(response.error || 'No se pudo eliminar el carrusel','Eliminar');
            }
        },
        error: function(){
            toastr.error('Error al comunicarse con el servidor','Eliminar');
        }
    });
}

function notification(text,title,status){
    if(status == "success"){
      toastr.success(text,title)
    } else{
      toastr.error(text,title)
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
    }
}

// ========== CÓMO FUNCIONA ==========
function open_como_funciona(i, id){
    deactivateAllEditorTabs();
    $('#btn-como-'+i).addClass('active');
    
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'get_como_funciona',
        'id': id
    };
    
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        let body = '';
        body += `<div class="row margin-bottom-40">
                    <div class="col-md-12">
                        <h2>Editar Paso ${response.step_number}</h2>
                        <div class="step-preview">
                            <div class="step-icon"><i class="${response.icon_class} fa-3x text-primary"></i></div>
                            <h4>${response.title}</h4>
                            <p>${response.description}</p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Icono (clase de Font Awesome)</label>
                    <input onchange="editComoFunciona('icon_class',${i},${id})" type="text" class="form-control" id="icon_class_input_${i}" value="${response.icon_class}" placeholder="fa fa-search">
                    <small class="form-text text-muted">Ejemplo: fa fa-search, fa fa-calendar, etc.</small>
                </div>
                <div class="form-group">
                    <label>Título</label>
                    <input onchange="editComoFunciona('title',${i},${id})" type="text" class="form-control" id="title_input_${i}" value="${response.title}" placeholder="Título del paso">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea onchange="editComoFunciona('description',${i},${id})" class="form-control" id="description_input_${i}" rows="4" placeholder="Descripción del paso">${response.description}</textarea>
                </div>`;
        $('.page-content-col').html(body);
        $('.step-preview').css('padding', '20px');
        $('.step-preview').css('border', '1px solid #ddd');
        $('.step-preview').css('border-radius', '5px');
        $('.step-preview').css('margin-bottom', '20px');
        $('.step-preview .step-icon').css('margin-bottom', '10px');
    });
}

function editComoFunciona(field, i, id){
    let value = $('#'+field+'_input_'+i).val();
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'edit_como_funciona',
        'id': id,
        'icon_class': $('#icon_class_input_'+i).val(),
        'title': $('#title_input_'+i).val(),
        'description': $('#description_input_'+i).val()
    };
    
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            notification('Se ha actualizado el paso', 'Cómo Funciona', 'success');
            // Actualizar preview
            if(field == 'icon_class'){
                $('.step-icon i').attr('class', value + ' fa-3x text-primary');
            } else if(field == 'title'){
                $('.step-preview h4').text(value);
            } else if(field == 'description'){
                $('.step-preview p').text(value);
            }
        } else {
            notification('Error al actualizar', 'Error', 'error');
        }
    });
}

// ========== BOOKING WIDGET ==========

function load_booking(){
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'get_booking'
    };
    $.post(url, data, function(res){
        bookingFetchCompleted = true;
        bookingData = JSON.parse(res);
        if(HOME_EDIT_INITIAL_TAB === 'booking' || bookingPendingOpen){
            open_booking();
        }
    });
}

function open_booking(){
    deactivateAllEditorTabs();
    $('#btn-booking').addClass('active');
    bookingPendingOpen = false;
    if(!bookingData || !bookingData.id){
        if(!bookingFetchCompleted){
            bookingPendingOpen = true;
        } else {
            $('.page-content-col').html('<div class="alert alert-info">No booking configuration available.</div>');
        }
        return;
    }
    const previewTitle = escapeHtml(bookingData.intro_title || 'Online Booking');
    const previewParagraph = escapeHtml(bookingData.intro_paragraph || '');
    const previewSecondary = escapeHtml(bookingData.secondary_paragraph || '');
    const previewBackground = bookingData.background_img || 'img/tour-booking-bg.jpg';
    const titleValue = escapeHtml(bookingData.intro_title || '');
    const introParagraphValue = escapeHtml(bookingData.intro_paragraph || '');
    const secondaryParagraphValue = escapeHtml(bookingData.secondary_paragraph || '');
    const ctaTextValue = escapeHtml(bookingData.cta_text || '');
    const ctaSubtextValue = escapeHtml(bookingData.cta_subtext || '');
    let body = '';
    body += `<div class="row margin-bottom-40">
                <div class="col-md-12">
                    <h2>Editar Booking</h2>
                </div>
            </div>
            <div class="row margin-bottom-40 about-header" id="booking-preview">
                <div class="col-md-12">
                    <h1>${previewTitle}</h1>
                    <p><span>${previewParagraph}</span> / ${previewSecondary}</p>
                </div>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-white btn-block" onclick="edit_booking_background(${bookingData.id})">Change background image</button>
            </div>
            <div class="form-group">
                <label>Intro Title</label>
                <input onchange="edit_booking('intro_title', this.value)" type="text" class="form-control" id="intro_title_input" value="${titleValue}">
            </div>
            <div class="form-group">
                <label>Intro Paragraph</label>
                <textarea onchange="edit_booking('intro_paragraph', this.value)" class="form-control" id="intro_paragraph_input" rows="3">${introParagraphValue}</textarea>
            </div>
            <div class="form-group">
                <label>Secondary Paragraph</label>
                <textarea onchange="edit_booking('secondary_paragraph', this.value)" class="form-control" id="secondary_paragraph_input" rows="3">${secondaryParagraphValue}</textarea>
            </div>
            <div class="form-group">
                <label>Button Text</label>
                <input onchange="edit_booking('cta_text', this.value)" type="text" class="form-control" id="cta_text_input" value="${ctaTextValue}">
            </div>
            <div class="form-group">
                <label>Button Subtext</label>
                <textarea onchange="edit_booking('cta_subtext', this.value)" class="form-control" id="cta_subtext_input" rows="2">${ctaSubtextValue}</textarea>
            </div>`;
    $('.page-content-col').html(body);
    const bgUrl = 'https://medtravel.com.co/' + previewBackground.replace(/^\/+/, '');
    $('.about-header').css('background-image', 'url(' + bgUrl + ')');
    $('.about-header').css('background-color', 'rgba(0, 0, 0, 0.5)');
    $('.about-header').css('background-size', 'cover');
    $('.about-header').css('background-position', 'center');
    $('.about-header').css('height', '300px');
    $('.about-header').css('font-family', 'Roboto');
    $('.about-header h1').css('font-weight', '800');
    $('.about-header p').css('font-size', '18px');
    $('.about-header p').css('font-weight', '400');
    $('.about-header p').css('color', '#fff');
    $('.about-header p span').css('color', 'orange');
}

function edit_booking(field, value){
    if(!bookingData || !bookingData.id){
        notification('Booking content not loaded', 'Booking', 'error');
        return;
    }
    let allowed = ['intro_title','intro_paragraph','secondary_paragraph','cta_text','cta_subtext'];
    if(allowed.indexOf(field) === -1){
        notification('Invalid field', 'Booking', 'error');
        return;
    }
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'edit_booking',
        'id': bookingData.id,
        'field': field,
        'value': value
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            bookingData[field] = value;
            notification('Booking updated', 'Booking', 'success');
        } else {
            notification('Unable to save booking', 'Booking', 'error');
        }
    });
}

function edit_booking_background(id){
    if(!id){
        notification('Booking ID missing', 'Booking', 'error');
        return;
    }
    let fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.click();
    fileInput.onchange = function(){
        if(!fileInput.files[0]){
            return;
        }
        let form = new FormData();
        form.append('file', fileInput.files[0]);
        form.append('id', id);
        form.append('tipo', 'edit_booking_img');
        let url = 'ajax/home_edit.php';
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                if(response.status == 'success'){
                    bookingData.background_img = response.ruta;
                    notification('Booking background updated', 'Booking', 'success');
                    open_booking();
                } else {
                    notification('Unable to update background', 'Booking', 'error');
                }
            }
        });
    };
}

// ========== SERVICIOS DETALLADOS ==========
function open_service(i, id){
    deactivateAllEditorTabs();
    $('#btn-service-'+i).addClass('active');
    
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'get_services',
        'id': id
    };
    
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        let badge_html = '';
        if(response.badge){
            badge_html = `<span class="badge ${response.badge_class}">${response.badge}</span>`;
        }
        
        let body = '';
        body += `<div class="row margin-bottom-40">
                    <div class="col-md-12">
                        <h2>Editar Servicio</h2>
                        <div class="service-preview">
                            <div class="service-preview-header">
                                ${badge_html}
                                <div class="service-icon"><i class="${response.icon_class} fa-3x text-primary"></i></div>
                            </div>
                            <img src="https://medtravel.com.co/${response.img}" alt="${response.title}" class="img-fluid mb-3" style="max-height: 200px; object-fit: cover; width: 100%;">
                            <h4>${response.title}</h4>
                            <p>${response.description}</p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Icono (clase de Font Awesome)</label>
                    <input onchange="editService('icon_class',${i},${id})" type="text" class="form-control" id="icon_class_input_${i}" value="${response.icon_class}" placeholder="fa fa-hospital">
                    <small class="form-text text-muted">Ejemplo: fa fa-hospital, fa fa-heartbeat, etc.</small>
                </div>
                <div class="form-group">
                    <label>Título</label>
                    <input onchange="editService('title',${i},${id})" type="text" class="form-control" id="title_input_${i}" value="${response.title}" placeholder="Título del servicio">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea onchange="editService('description',${i},${id})" class="form-control" id="description_input_${i}" rows="4" placeholder="Descripción del servicio">${response.description}</textarea>
                </div>
                <div class="form-group">
                    <label>Badge (etiqueta)</label>
                    <input onchange="editService('badge',${i},${id})" type="text" class="form-control" id="badge_input_${i}" value="${response.badge}" placeholder="Nuevo, Popular, etc.">
                </div>
                <div class="form-group">
                    <label>Badge Class (clase CSS)</label>
                    <select onchange="editService('badge_class',${i},${id})" class="form-control" id="badge_class_input_${i}">
                        <option value="bg-primary" ${response.badge_class == 'bg-primary' ? 'selected' : ''}>Azul (Primary)</option>
                        <option value="bg-success" ${response.badge_class == 'bg-success' ? 'selected' : ''}>Verde (Success)</option>
                        <option value="bg-danger" ${response.badge_class == 'bg-danger' ? 'selected' : ''}>Rojo (Danger)</option>
                        <option value="bg-warning" ${response.badge_class == 'bg-warning' ? 'selected' : ''}>Amarillo (Warning)</option>
                        <option value="bg-info" ${response.badge_class == 'bg-info' ? 'selected' : ''}>Cian (Info)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Imagen</label>
                    <button type="button" class="btn btn-white btn-block" onclick="editServiceImg(${i},${id})">Cambiar imagen</button>
                </div>`;
        $('.page-content-col').html(body);
        $('.service-preview').css('padding', '20px');
        $('.service-preview').css('border', '1px solid #ddd');
        $('.service-preview').css('border-radius', '5px');
        $('.service-preview').css('margin-bottom', '20px');
        $('.service-preview-header').css('position', 'relative');
        $('.service-preview-header .badge').css('position', 'absolute');
        $('.service-preview-header .badge').css('top', '10px');
        $('.service-preview-header .badge').css('right', '10px');
        $('.service-preview .service-icon').css('margin-bottom', '10px');
    });
}

function editService(field, i, id){
    let value = $('#'+field+'_input_'+i).val();
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'edit_service',
        'id': id,
        'icon_class': $('#icon_class_input_'+i).val(),
        'title': $('#title_input_'+i).val(),
        'description': $('#description_input_'+i).val(),
        'badge': $('#badge_input_'+i).val(),
        'badge_class': $('#badge_class_input_'+i).val()
    };
    
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            notification('Se ha actualizado el servicio', 'Servicios', 'success');
            // Actualizar preview
            if(field == 'icon_class'){
                $('.service-icon i').attr('class', value + ' fa-3x text-primary');
            } else if(field == 'title'){
                $('.service-preview h4').text(value);
            } else if(field == 'description'){
                $('.service-preview p').text(value);
            } else if(field == 'badge'){
                let badge_class = $('#badge_class_input_'+i).val();
                if(value){
                    $('.service-preview-header .badge').remove();
                    $('.service-preview-header').prepend(`<span class="badge ${badge_class}">${value}</span>`);
                    $('.service-preview-header .badge').css('position', 'absolute');
                    $('.service-preview-header .badge').css('top', '10px');
                    $('.service-preview-header .badge').css('right', '10px');
                } else {
                    $('.service-preview-header .badge').remove();
                }
            } else if(field == 'badge_class'){
                let badge_text = $('#badge_input_'+i).val();
                if(badge_text){
                    $('.service-preview-header .badge').attr('class', 'badge ' + value);
                }
            }
        } else {
            notification('Error al actualizar', 'Error', 'error');
        }
    });
}

function editServiceImg(i, id){
    let title = $('#title_input_'+i).val();
    title = title.replace(/\s/g, '_');
    title = title.replace(/[^\w\s]/gi, '');
    title = title.trim();
    
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    
    file.onchange = function(){
        let form = new FormData();
        form.append('file', file.files[0]);
        form.append('id', id);
        form.append('tipo', 'edit_service_img');
        form.append('title', title);
        let url = 'ajax/home_edit.php';
        
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                if(response.status == 'success'){
                    notification('Se ha actualizado la imagen', 'Imagen', 'success');
                    let img = response.ruta;
                    $('.service-preview img').attr('src', 'https://medtravel.com.co/'+img);
                } else {
                    notification('Error al actualizar la imagen', 'Error', 'error');
                }
            }
        });
    }
}

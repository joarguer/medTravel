let dataheader = Array();
let dataus = Array();
let listArray = Array();
let specialist = Array();
let specialist_list = Array();
let social_media = Array();

function refreshAboutData(callback){
    let url = 'ajax/about_edit.php';
    let data = {
        tipo: 'get_home'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        dataheader = response['header'];
        dataus = response['about_us'];
        specialist = Array.isArray(response['specialist']) ? response['specialist'] : [];
        specialist_list = Array.isArray(response['specialist_list']) ? response['specialist_list'] : [];
        social_media = Array.isArray(response['social_media']) ? response['social_media'] : [];
        if(typeof callback === 'function'){
            callback();
        }
    });
}

function renderHeaderView(){
    if(!dataheader){
        return;
    }
    let body = '';
    let id = dataheader.id;
    let title = dataheader.title;
    let subtitle_1 = dataheader.subtitle_1;
    let subtitle_2 = dataheader.subtitle_2;
    let img = dataheader.img;
    body +=    `<div class="row margin-bottom-40 about-header" id="header_0">
                    <div class="col-md-12">
                        <h1 id="title_edit">${title}</h1>
                        <p id="parrafo_edit"><span>${subtitle_1}</span> / ${subtitle_2}</p>
                    </div>
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('title',${id})" class="form-control add_input" id="title" aria-describedby="Title" value="${title}" placeholder="Título principal (ej. Welcome to MedTravel)">
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('subtitle_1',${id})" class="form-control add_input" id="subtitle_1" aria-describedby="subtitle_1" value="${subtitle_1}" placeholder="Subtítulo superior (ej. Discover curated medical experiences)">
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('subtitle_2',${id})" class="form-control add_input" id="subtitle_2" aria-describedby="subtitle_2" value="${subtitle_2}" placeholder="Subtítulo inferior (ej. Trusted clinics worldwide)">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-white btn-block" onclick="editImg(${id})">Change image</button>
                </div>`;
    $('.page-content-col').html(body);
    $('.about-header').css('background-image', 'url(https://medtravel.com.co/'+img+')');
    $('.about-header').css('background-color', 'rgba(0, 0, 0, 0.5)');
    $('.about-header').css('background-size', 'cover');
    $('.about-header').css('background-position', 'top');
    $('.about-header').css('height', '300px');
    $('.about-header').css('font-family', 'Roboto');
    $('.about-header h1').css('font-weight', '800');
    $('.about-header p').css('font-size', '18px');
    $('.about-header p').css('font-weight', '400');
    $('.about-header p').css('color', '#fff');
    $('.about-header p span').css('color', 'orange');
}

$(document).ready(function(){
    refreshAboutData(renderHeaderView);
});

remove_active = () => {
    $('.btn-header').removeClass('active');
    $('.btn-about').removeClass('active');
    $('.btn-specialist').removeClass('active');
}

//HEADER
function open_header(id){
    remove_active();
    $('.btn-header').addClass('active');
    let body = '';
    let title = dataheader.title;
    let subtitle_1 = dataheader.subtitle_1;
    let subtitle_2 = dataheader.subtitle_2;
    let img = dataheader.img;
    body +=    `<div class="row margin-bottom-40 about-header" id="header_0">
                    <div class="col-md-12">
                        <h1 id="title_edit">${title}</h1>
                        <p id="parrafo_edit"><span>${subtitle_1}</span> / ${subtitle_2}</p>
                    </div>
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('title',${id})" class="form-control edit_header" id="title" aria-describedby="Title" value="${title}" placeholder="Título principal (ej. 'Who We Are')">
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('subtitle_1',${id})" class="form-control edit_header" id="subtitle_1" aria-describedby="subtitle_1" value="${subtitle_1}" placeholder="Subtítulo superior (ej. 'About MedTravel')">
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('subtitle_2',${id})" class="form-control edit_header" id="subtitle_2" aria-describedby="subtitle_2" value="${subtitle_2}" placeholder="Subtítulo inferior (ej. 'Trusted specialists worldwide')">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-white btn-block" onclick="editImg(${id})">Change image</button>
                </div>`;
    $('.page-content-col').html(body);
    $('.about-header').css('background-image', 'url(https://medtravel.com.co/'+img+')');
    $('.about-header').css('background-color', 'rgba(0, 0, 0, 0.5)');
    $('.about-header').css('background-size', 'cover');
    $('.about-header').css('background-position', 'top');
    $('.about-header').css('height', '300px');
    //font-family: 'Roboto', sans-serif;
    $('.about-header').css('font-family', 'Roboto');
    //h1 strong text
    $('.about-header h1').css('font-weight', '800');
    $('.about-header p').css('font-size', '18px');
    $('.about-header p').css('font-weight', '400');
    $('.about-header p').css('color', '#fff');
    $('.about-header p span').css('color', 'orange');
}

function addheader(){
    let body = '';
    body += `<div class="row margin-bottom-40 about-header" id="header_0">
                <div class="col-md-12">
                    <h2 id="over_title_edit"></h2>
                    <h1 id="title_edit">About Us</h1>
                    <p id="parrafo_edit"><span>Home</span> / About</p>
                </div>
            </div>
            <div class="form-group">
                <input type="text" class="form-control edit_header" id="title" aria-describedby="Title" value="${title_1}" placeholder="Título del bloque (ej. 'About MedTravel')">
            </div>
            <div class="form-group">
                <input type="text" class="form-control edit_header" id="subtitle_1" aria-describedby="subtitle_1" value="${subtitle_1}" placeholder="Etiqueta pequeña (ej. 'Warning')">
            </div>
            <div class="form-group">
                <input type="text" class="form-control edit_header" id="subtitle_2" aria-describedby="subtitle_2" value="${subtitle_2}" placeholder="Subtítulo de apoyo (ej. 'Who We Are')">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-white btn-block" onclick="editImg(${id})">Change image</button>
            </div>
            <script>
            $('.edit_header').on('input', function(e) {
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
    let url = 'ajax/about_edit.php';
    let data = {
        'tipo': 'add_header'
    };
}

function editInputSubmit(input,id){
    App.blockUI();
    $('#'+input).attr('onclick', '');
    let text_come = $('#'+input).val();
    let url = 'ajax/about_edit.php';
    let data = {
        id: id,
        text_come: text_come,
        input: input,
        tipo: 'edit_input'
    };
    $.post(url, data)
    .done(function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado el Over Title';
            let title = 'Over Title';
            let status = 'success';
            notification(text, title, status);
            let text_go = response.text_go;
            $('#'+input).html(text_go);
            $('#'+input).attr('onclick', 'editInputSubmit('+id+')');
            if(input == 'title'){
                dataheader.title = text_go;
            } else if(input == `subtitle_1`){
                dataheader.subtitle_1 = text_go;
            } else if(input == `subtitle_2`){
                dataheader.subtitle_2 = text_go;
            }
            open_header(id);
        } else {
            notification('No se guardó el cambio','Encabezado','error');
        }
    })
    .fail(function(){
        notification('Error de conexión al guardar','Encabezado','error');
    })
    .always(function(){
        App.unblockUI();
    });
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
        let url = 'ajax/about_edit.php';
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

function editImg(id){
    //get file input
    let title = $('#title').val();
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
        let url = 'ajax/about_edit.php';
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
                    dataheader.img = img;
                    open_header(id);
                }
            }
        });
    }
}

//ABOUT US
function ensureAboutData(next){
    if(dataus && Object.keys(dataus).length){
        next();
        return;
    }
    refreshAboutData(next);
}

function open_about(id){
    remove_active();
    $('.btn-about').addClass('active');
    ensureAboutData(function(){
        render_about_section(id);
    });
    return;
}

function render_about_section(id){
    let body = '';
    let titulo_small = dataus.titulo_small;
    let titulo_1 = dataus.titulo_1;
    let titulo_2 = dataus.titulo_2;
    let paragrafo = dataus.paragrafo;
    let btn = dataus.btn;
    let list = [];
    try {
        list = dataus.list ? JSON.parse(dataus.list) : [];
    } catch (e) {
        list = [];
    }
    listArray = list.slice();
    let list_html = '';
    let list_edit = '';
    for(let i = 0; i < list.length; i++){
        list_html += `<div class="col-sm-6">
                        <p class="mb-0"><i class="fa fa-arrow-right text-primary me-2"></i>${list[i]}</p>
                    </div>`;
        list_edit += `<div class="form-group">
                        <input onchange="editList(${id},${i})" type="text" class="form-control edit_header" id="list_${i}" aria-describedby="list_${i}" value="${list[i]}" placeholder="Punto de la lista (ej. 'Verified specialists')">
                    </div>`;
    }
    list_edit += `<div class="form-group">
                    <button type="button" class="btn btn-white btn-block" onclick="addList(${id})"><i class="fa fa-plus"></i>Add list</button>
                </div>`;
    let img = dataus.img;
    let bg = dataus.bg;
    
    console.log('About Data:', dataus);
    console.log('IMG from DB:', img);
    console.log('BG from DB:', bg);
    
    const makeUrl = (value, fallback) => {
        if (!value) {
            console.log('No value, using fallback:', fallback);
            return `/${fallback}`;
        }
        if (value.startsWith('http') || value.startsWith('//')) {
            console.log('Absolute URL:', value);
            return value;
        }
        // Las rutas en la DB vienen como "img/about_us/file.jpg?12345"
        // Usamos rutas absolutas desde la raíz del sitio
        let url = value.startsWith('/') ? value : `/${value}`;
        console.log('Constructed URL:', url);
        return url;
    };
    const aboutImgUrl = makeUrl(img, 'img/about-img.jpg');
    const aboutBgUrl = makeUrl(bg, 'img/about-img-bg.png');
    
    console.log('About IMG URL:', aboutImgUrl);
    console.log('About BG URL:', aboutBgUrl);
    
    body +=    `<div class="row">
                <div class="col-lg-6">
                    <h4>Formulario de edición</h4>
                    <div class="form-group">
                        <label>Imagen principal</label>
                        <img src="${aboutImgUrl}" 
                             onerror="console.error('Error loading img:', this.src); this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect width=%22400%22 height=%22300%22 fill=%22%23ddd%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22 font-family=%22Arial%22 font-size=%2220%22%3EImagen no disponible%3C/text%3E%3C/svg%3E'; this.style.border='2px dashed #ccc';" 
                             style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; display: block; background: #f5f5f5;">
                        <small class="text-muted d-block">Ruta: ${img || 'No definida'}</small>
                        <button type="button" class="btn btn-white btn-block" onclick="editImgAbouUs(${id})">Change image</button>
                    </div>
                    <div class="form-group">
                        <label>Imagen de fondo</label>
                        <img src="${aboutBgUrl}" 
                             onerror="console.error('Error loading bg:', this.src); this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect width=%22400%22 height=%22300%22 fill=%22%23ddd%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22 font-family=%22Arial%22 font-size=%2220%22%3EFondo no disponible%3C/text%3E%3C/svg%3E'; this.style.border='2px dashed #ccc';" 
                             style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; display: block; background: #f5f5f5;">
                        <small class="text-muted d-block">Ruta: ${bg || 'No definida'}</small>
                        <button type="button" class="btn btn-white btn-block" onclick="editBgAbouUs(${id})">Change Background</button>
                    </div>
                    <div class="form-group">
                        <label>Etiqueta pequeña</label>
                        <input type="text" onchange="editAboutUs('titulo_small',${id})" class="form-control edit_header" id="titulo_small" aria-describedby="titulo_small" value="${titulo_small}" placeholder="Etiqueta pequeña (ej. 'Warning')">
                    </div>
                    <div class="form-group">
                        <label>Título principal</label>
                        <input type="text" onchange="editAboutUs('titulo_1',${id})" class="form-control edit_header" id="titulo_1" aria-describedby="titulo_1" value="${titulo_1}" placeholder="Título principal (ej. 'Who We Are')">
                    </div>
                    <div class="form-group">
                        <label>Subtítulo</label>
                        <input type="text" onchange="editAboutUs('titulo_2',${id})" class="form-control edit_header" id="titulo_2" aria-describedby="titulo_2" value="${titulo_2}" placeholder="Subtítulo de apoyo (ej. 'MedTravel connects patients with top clinics worldwide')">
                    </div>
                    <div class="form-group">
                        <label>Texto del botón</label>
                        <input type="text" onchange="editAboutUs('btn',${id})" class="form-control edit_header" id="btn" aria-describedby="btn" value="${btn}" placeholder="Texto del botón principal (ej. 'Read More')">
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea data-id="${id}" rows="10" class="wysihtml5 form-control edit_header" id="paragrafo" placeholder="Texto descriptivo (p.e. 2-3 frases sobre MedTravel)">${paragrafo}</textarea>
                    </div>
                    ${list_edit}
                </div>
                <div class="col-lg-6">
                    <h4>Vista previa</h4>
                    <div class="row g-5 align-items-center" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                        <div class="col-12">
                            <div class="h-100" style="border: 20px solid; border-color: transparent #13357B transparent #13357B;">
                                <img src="${aboutImgUrl}" class="img-fluid w-100" style="max-height: 400px; object-fit: cover;" alt="">
                            </div>
                        </div>
                        <div class="col-12" style="background: linear-gradient(rgba(255, 255, 255, .8), rgba(255, 255, 255, .8)), url(${aboutBgUrl}); background-size: cover; padding: 30px; border-radius: 10px;">
                            <h5 class="section-about-title pe-3" style="color: #13357B; font-weight: 600;">${titulo_small}</h5>
                            <h1 class="mb-4" style="font-size: 2rem; font-weight: 700;">${titulo_1} <span class="text-primary">${titulo_2}</span></h1>
                            <div style="margin-bottom: 20px;">${paragrafo}</div>
                            <div class="row gy-2 gx-4 mb-4">
                                ${list_html}
                            </div>
                            <a class="btn btn-primary rounded-pill py-3 px-5 mt-2" href="">${btn}</a>
                        </div>
                    </div>
                </div>
            </div>`;
    
    $('.page-content-col').html(body);
    $('.about-us').css('background-image', 'url(https://medtravel.com.co/'+img+')');
    $('.about-us').css('background-color', 'rgba(0, 0, 0, 0.5)');
    $('.about-us').css('background-size', 'cover');
    $('.about-us').css('height', '300px');
    $('.about-us').css('font-family', 'Roboto');
    $('.about-us h1').css('font-weight', '800');
    $('.about-us p').css('font-size', '18px');
    $('.about-us p').css('font-weight', '400');
    $('.about-us p').css('color', '#fff');
    $('.about-us p span').css('color', 'orange');
    ComponentsEditors.init();
}

function editList(id,i){
    App.blockUI();
    let text_come = $('#list_'+i).val();
    listArray[i] = text_come;
    let url = 'ajax/about_edit.php';
    let data = {
        id: id,
        list: listArray,
        tipo: 'edit_list',
        input: 'list'
    };
    $.post(url, data)
    .done(function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado la lista';
            let title = 'Lista';
            let status = 'success';
            notification(text, title, status);
            dataus.list = response.list;
            open_about(id);
        } else{
            notification('No se ha actualizado la lista','Lista','error');
        }
    })
    .fail(function(){
        notification('Error al guardar la lista','Lista','error');
    })
    .always(function(){
        App.unblockUI();
    });
}

function addList(id){
    let modal_html = `<div class="modal fade" id="modal_list" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="modal-title" id="myModalLabel">About Us List </h4>
                                </div>
                                <div class="modal-body
                                    <form action="#">
                                        <div class="form-body">
                                            <div class="form-group form-md-line-input has-success">
                                                <input type="text" class="form-control" id="text_list">
                                                <label for="text_list">Text List</label>
                                                <span class="help-block">Text list characters</span>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-danger" onclick="addListSave(${id})">Save</button>
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
    $('body').append(modal_html);
    $('#modal_list').modal('show');
}

function addListSave(id){
    let text_list = $('#text_list').val();
    listArray.push(text_list);
    listArray = JSON.stringify(listArray);
    $('#modal_list').modal('hide');
    $('#modal_list').remove();
    $('.modal-backdrop').remove();
    let url = 'ajax/about_edit.php';
    let data = {
        id: id,
        list: listArray,
        tipo: 'add_list'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha agregado la lista';
            let title = 'Lista';
            let status = 'success';
            notification(text, title, status);
            dataus.list = response.list;
            open_about(response.id);
        }
    });
}

function editAboutUs(input,id){
    App.blockUI();
    $('#'+input).attr('onclick', '');
    let text_come = '';
    if(input == 'paragrafo'){
        text_come = $('#'+input).html();
    } else{
        text_come = $('#'+input).val();
    }
    let url = 'ajax/about_edit.php';
    let data = {
        id: id,
        text_come: text_come,
        input: input,
        tipo: 'edit_about_us'
    };
    $.post(url, data)
    .done(function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado el contenido';
            let title = 'About';
            let status = 'success';
            notification(text, title, status);
            let text_go = response.text_go;
            $('#'+input).html(text_go);
            $('#'+input).attr('onclick', 'editAboutUs('+id+')');
            if(input == 'titulo_small'){
                dataus.titulo_small = text_come;
            } else if(input == `titulo_1`){
                dataus.titulo_1 = text_come;
            } else if(input == `titulo_2`){
                dataus.titulo_2 = text_come;
            } else if(input == `paragrafo`){
                dataus.paragrafo = text_come;
            } else if(input == `btn`){
                dataus.btn = text_come;
            }
            open_about(id);
        } else {
            notification('No se guardó el contenido','About','error');
        }
    })
    .fail(function(){
        notification('Error de conexión al guardar About','About','error');
    })
    .always(function(){
        App.unblockUI();
    });
} 

function editImgAbouUs(id){
    title = 'img_about_us';
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    file.onchange = function(){
        let form = new FormData();
        form.append('file', file.files[0]);
        form.append('id', id);
        form.append('tipo', 'edit_img_about_us');
        form.append('title', title);
        let url = 'ajax/about_edit.php';
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                console.log('Response edit_img_about_us:', response);
                if(response.status == 'success'){
                    let text = 'Se ha actualizado la imagen';
                    let title = 'Imagen';
                    let status = 'success';
                    notification(text, title, status);
                    let img = response.ruta;
                    dataus.img = img;
                    if(response.affected_rows !== undefined){
                        console.log('Rows affected:', response.affected_rows);
                    }
                    open_about(id);
                } else {
                    console.error('Error response:', response);
                    notification('Error: ' + (response.error || response.status), 'Imagen', 'error');
                }
            }
        });
    }
}

function editBgAbouUs(id){
    title = 'bg_about_us';
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    file.onchange = function(){
        let form = new FormData();
        form.append('file', file.files[0]);
        form.append('id', id);
        form.append('tipo', 'edit_bg_about_us');
        form.append('title', title);
        let url = 'ajax/about_edit.php';
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                console.log('Response edit_bg_about_us:', response);
                if(response.status == 'success'){
                    let text = 'Se ha actualizado la imagen';
                    let title = 'Imagen';
                    let status = 'success';
                    notification(text, title, status);
                    let bg = response.ruta;
                    dataus.bg = bg;
                    if(response.affected_rows !== undefined){
                        console.log('Rows affected:', response.affected_rows);
                    }
                    open_about(id);
                } else {
                    console.error('Error response:', response);
                    notification('Error: ' + (response.error || response.status), 'Background', 'error');
                }
            }
        });
    }
}

//SPECIALIST - SPECIALIST LIST
function open_specialist(id){
    remove_active();
    $('.btn-specialist').addClass('active');
    let body = '';
    const specialist_list_data = Array.isArray(specialist_list) ? specialist_list : [];
    let specialist_list_edit = '';
    if(!specialist_list_data.length){
        specialist_list_edit = `<div class="col-12">
                                <div class="alert alert-info">
                                    No hay especialistas registrados. Usa el botón para agregar uno nuevo.
                                </div>
                            </div>`;
    } else {
        for(let i = 0; i < specialist_list_data.length; i++){
            let row = specialist_list_data[i];
            let titulo = row.titulo || '';
            let subtitulo = row.subtitulo || '';
            let facebook = row.facebook || '#';
            let twitter = row.twiter || '#';
            let instagram = row.instagram || '#';
            let img = row.img || 'img/guide-1.jpg';
            let rowId = row.id;
            specialist_list_edit += `<div class="col-md-6 col-lg-4">
                                    <div class="guide-item">
                                        <div class="guide-img">
                                            <div class="guide-img-efects">
                                                <img src="../${img}" alt="${titulo}" class="w-100 h-100" style="object-fit: cover;">
                                            </div>
                                            <div class="guide-icon rounded-pill p-2 text-center">
                                                <a class="btn btn-square btn-primary rounded-circle mx-1" target="_blank" href="${facebook}"><i class="fa fa-facebook-f"></i></a>
                                                <a class="btn btn-square btn-primary rounded-circle mx-1" target="_blank" href="${twitter}"><i class="fa fa-twitter"></i></a>
                                                <a class="btn btn-square btn-primary rounded-circle mx-1" target="_blank" href="${instagram}"><i class="fa fa-instagram"></i></a>
                                            </div>
                                        </div>
                                        <div class="guide-title text-center rounded-bottom p-4">
                                            <div class="guide-title-inner">
                                                <h2 class="mt-3"><b>${titulo}</b></h2>
                                                <p class="mb-0">${subtitulo}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-white rounded p-3 shadow-sm mt-3">
                                        <div class="form-group">
                                            <label for="sl_titulo_${rowId}">Nombre</label>
                                            <input type="text" class="form-control" id="sl_titulo_${rowId}" value="${titulo}" placeholder="Nombre del especialista">
                                        </div>
                                        <div class="form-group">
                                            <label for="sl_subtitulo_${rowId}">Cargo o especialidad</label>
                                            <input type="text" class="form-control" id="sl_subtitulo_${rowId}" value="${subtitulo}" placeholder="Ej. Cardiologist">
                                        </div>
                                        <div class="form-group">
                                            <label for="sl_facebook_${rowId}">Facebook</label>
                                            <input type="text" class="form-control" id="sl_facebook_${rowId}" value="${facebook}" placeholder="https://facebook.com/...">
                                        </div>
                                        <div class="form-group">
                                            <label for="sl_instagram_${rowId}">Instagram</label>
                                            <input type="text" class="form-control" id="sl_instagram_${rowId}" value="${instagram}" placeholder="https://instagram.com/...">
                                        </div>
                                        <div class="form-group">
                                            <label for="sl_twiter_${rowId}">Twitter</label>
                                            <input type="text" class="form-control" id="sl_twiter_${rowId}" value="${twitter}" placeholder="https://twitter.com/...">
                                        </div>
                                        <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
                                            <button type="button" class="btn btn-success btn-sm" onclick="saveSpecialistRow(${rowId})">Guardar</button>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="editImgSpecialist(${rowId})">Cambiar imagen</button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeSpecialist(${rowId})">Eliminar</button>
                                        </div>
                                    </div>
                                </div>`;
        }
    }
    body += `<div class="row mb-4 align-items-center">
                <div class="col-md-8">
                    <h5 class="section-about-title text-uppercase mb-1">${dataus.titulo_small || 'Specialists'}</h5>
                    <h2 class="fw-bold mb-0">${dataus.titulo_1 || 'Meet Our Specialists'} <span class="text-primary">${dataus.titulo_2 || ''}</span></h2>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-primary" onclick="addSpecialist()">Agregar especialista</button>
                </div>
            </div>
            <div class="row gy-4">
                ${specialist_list_edit}
            </div>`;
    $('.page-content-col').html(body);
    ComponentsEditors.init();
}

function saveSpecialistRow(id){
    let payload = {
        tipo: 'edit_specialist_list',
        id: id,
        titulo: $('#sl_titulo_'+id).val(),
        subtitulo: $('#sl_subtitulo_'+id).val(),
        facebook: $('#sl_facebook_'+id).val(),
        instagram: $('#sl_instagram_'+id).val(),
        twiter: $('#sl_twiter_'+id).val()
    };
    App.blockUI();
    $.post('ajax/about_edit.php', payload)
    .done(function(res){
        const response = JSON.parse(res);
        if(response.status == 'success'){
            notification('Especialista actualizado','Specialist','success');
            refreshAboutData(() => open_specialist(id));
        } else {
            notification('No se pudo actualizar el especialista','Specialist','error');
        }
    })
    .fail(function(){
        notification('Error de conexión al guardar especialista','Specialist','error');
    })
    .always(function(){
        App.unblockUI();
    });
}

function editImgSpecialist(id){
    let file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.click();
    file.onchange = function(){
        let form = new FormData();
        form.append('file', file.files[0]);
        form.append('id', id);
        form.append('tipo', 'edit_img_specialist');
        let name = $('#sl_titulo_'+id).val();
        form.append('title', name ? name.replace(/\s+/g, '_') : 'specialist');
        let url = 'ajax/about_edit.php';
        App.blockUI();
        $.ajax({
            url: url,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false
        }).done(function(res){
            const response = JSON.parse(res);
            if(response.status == 'success'){
                notification('Imagen del especialista actualizada','Specialist','success');
                refreshAboutData(() => open_specialist(id));
            } else {
                notification('No se pudo actualizar la imagen','Specialist','error');
            }
        }).fail(function(){
            notification('Error de conexión al subir imagen','Specialist','error');
        }).always(function(){
            App.unblockUI();
        });
    }
}

function removeSpecialist(id){
    if(!confirm('¿Eliminar este especialista del listado?')){
        return;
    }
    App.blockUI();
    $.post('ajax/about_edit.php', { tipo: 'remove_specialist', id: id })
    .done(function(res){
        const response = JSON.parse(res);
        if(response.status == 'success'){
            notification('Especialista eliminado','Specialist','success');
            refreshAboutData(() => open_specialist(id));
        } else {
            notification('No se pudo eliminar el especialista','Specialist','error');
        }
    })
    .fail(function(){
        notification('Error de conexión al eliminar','Specialist','error');
    })
    .always(function(){
        App.unblockUI();
    });
}

function addSpecialist(){
    let nombre = prompt('Nombre del especialista', 'Nuevo especialista');
    if(nombre === null){
        return;
    }
    nombre = nombre.trim() || 'Especialista';
    App.blockUI();
    $.post('ajax/about_edit.php', {
        tipo: 'add_specialist',
        titulo: nombre
    })
    .done(function(res){
        const response = JSON.parse(res);
        if(response.status == 'success'){
            notification('Especialista agregado','Specialist','success');
            refreshAboutData(() => open_specialist(response.id));
        } else {
            notification('No se pudo agregar el especialista','Specialist','error');
        }
    })
    .fail(function(){
        notification('Error de conexión al agregar especialista','Specialist','error');
    })
    .always(function(){
        App.unblockUI();
    });
}

//GLOBAL
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

var ComponentsEditors = function () {
    
    var handleWysihtml5 = function () {
        if (!jQuery().wysihtml5) {
            return;
        }

        if ($('.wysihtml5').size() > 0) {
            $('.wysihtml5').wysihtml5({
                "stylesheets": ["../assets/global/plugins/bootstrap-wysihtml5/wysiwyg-color.css"],
                events: {
                    change: function () {
                        let input = 'paragrafo';
                        let id = $('#paragrafo').data('id');
                        App.blockUI();
                        let text_come = $('#paragrafo').val();
                        text_come = text_come.trim();
                        let url = 'ajax/about_edit.php';
                        let data = {
                            id: id,
                            text_come: text_come,
                            input: input,
                            tipo: 'edit_about_us'
                        };
                        $.post(url, data)
                        .done(function(res){
                            let response = JSON.parse(res);
                            if(response.status == 'success'){
                                let text = 'Se ha actualizado el contenido';
                                let title = 'About';
                                let status = 'success';
                                notification(text, title, status);
                                let text_go = response.text_go;
                                $('#'+input).html(text_go);
                                $('#'+input).attr('onclick', 'editAboutUs('+id+')');
                                if(input == 'titulo_small'){
                                    dataus.titulo_small = text_come;
                                } else if(input == `titulo_1`){
                                    dataus.titulo_1 = text_come;
                                } else if(input == `titulo_2`){
                                    dataus.titulo_2 = text_come;
                                } else if(input == `paragrafo`){
                                    dataus.paragrafo = text_come;
                                }
                                open_about(id);
                            } else {
                                notification('No se pudo actualizar el contenido','About','error');
                            }
                        })
                        .fail(function(){
                            notification('Error de conexión al guardar el contenido','About','error');
                        })
                        .always(function(){
                            App.unblockUI();
                        });
                    }
                }
            });
        }
    }

    var handleSummernote = function () {
        $('#summernote_1').summernote({height: 300});
        //API:
        //var sHTML = $('#summernote_1').code(); // get code
        //$('#summernote_1').destroy(); // destroy
    }

    return {
        //main function to initiate the module
        init: function () {
            handleWysihtml5();
            handleSummernote();
        }
    };

}();
/*
function addInput(id){
    let over_title = $('#over_title').val();
    let title = $('#title').val();
    let parrafo = $('#parrafo').val();
    let btn = $('#btn').val();
    let url = 'ajax/about_edit.php';
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
            dataheader.push(response);
            let i = dataheader.length - 1;
            location.reload();
        }
    });
}*/

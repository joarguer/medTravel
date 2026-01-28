let dataheader = Array();
let dataus = Array();
let listArray = Array();
let specialist = Array();
let specialist_list = Array();
let social_media = Array();
$(document).ready(function(){
    let url = 'ajax/about_edit.php';
    let data = {
        'tipo': 'get_home'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        dataheader = response['header'];
        dataus = response['about_us'];
        specialist = response['specialist'];
        specialist_list = response['specialist_list'];
        social_media = response['social_media'];
        let body = '';
        let i = 0;
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
                        <input type="text" onchange="editInputSubmit('title',${id})" class="form-control add_input" id="title" aria-describedby="Title" value="${title}" placeholder="Title">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editInputSubmit('subtitle_1',${id})" class="form-control add_input" id="subtitle_1" aria-describedby="subtitle_1" value="${subtitle_1}" placeholder="subtitle 1">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editInputSubmit('subtitle_2',${id})" class="form-control add_input" id="subtitle_2" aria-describedby="subtitle_2" value="${subtitle_2}" placeholder="subtitle 2">
                    </div>
                    <div class="form-group">
                        <button type="button" onchange="editInputSubmit('img',${id})" class="btn btn-white btn-block" onclick="editImg(${id})">Change image</button>
                    </div>`;
        $('.page-content-col').html(body);
        $('.about-header').css('background-image', 'url(https://medtravel.com.co/'+img+')');
        $('.about-header').css('background-color', 'rgba(0, 0, 0, 0.5)');
        //background-image full width
        $('.about-header').css('background-size', 'cover');
        //el background muestra la parte superior de la imagen
        $('.about-header').css('background-position', 'top');
        //le doy una capa azulada a la imagen
        $('.about-header').css('height', '300px');
        //font-family: 'Roboto', sans-serif;
        $('.about-header').css('font-family', 'Roboto');
        //h1 strong text
        $('.about-header h1').css('font-weight', '800');
        $('.about-header p').css('font-size', '18px');
        $('.about-header p').css('font-weight', '400');
        $('.about-header p').css('color', '#fff');
        $('.about-header p span').css('color', 'orange');
    });
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
                    <input type="text" onchange="editInputSubmit('title',${id})" class="form-control edit_header" id="title" aria-describedby="Title" value="${title}" placeholder="Title">
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('subtitle_1',${id})" class="form-control edit_header" id="subtitle_1" aria-describedby="subtitle_1" value="${subtitle_1}" placeholder="subtitle 1">
                </div>
                <div class="form-group">
                    <input type="text" onchange="editInputSubmit('subtitle_2',${id})" class="form-control edit_header" id="subtitle_2" aria-describedby="subtitle_2" value="${subtitle_2}" placeholder="subtitle 2">
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
                <input type="text" class="form-control edit_header" id="title" aria-describedby="Title" value="${title_1}" placeholder="Title">
            </div>
            <div class="form-group">
                <input type="text" class="form-control edit_header" id="subtitle_1" aria-describedby="subtitle_1" value="${subtitle_1}" placeholder="subtitle 1">
            </div>
            <div class="form-group">
                <input type="text" class="form-control edit_header" id="subtitle_2" aria-describedby="subtitle_2" value="${subtitle_2}" placeholder="subtitle 2">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-white btn-block" onclick="editImg(${id})">Change image</button>
            </div>
            <script>
            $('.edit_header').on('input', function(e) {
                let text_come = e.target.value;
                let input = e.target.id;
                console.log(text_come,input); 
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
    console.log(input,id);
    $('#'+input).attr('onclick', '');
    let text_come = $('#'+input).val();
    console.log(text_come);
    let url = 'ajax/about_edit.php';
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
            App.unblockUI();
        }
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
function open_about(id){
    remove_active();
    $('.btn-about').addClass('active');
    let body = '';
    let titulo_small = dataus.titulo_small;
    let titulo_1 = dataus.titulo_1;
    let titulo_2 = dataus.titulo_2;
    let paragrafo = dataus.paragrafo;
    let btn = dataus.btn;
    let list = Array();
    list = dataus.list;
    list = JSON.parse(list);
    listArray = list;
    console.log('remote: ',list);
    let list_html = '';
    let list_edit = '';
    for(let i = 0; i < list.length; i++){
        list_html += `<div class="col-sm-6">
                        <p class="mb-0"><i class="fa fa-arrow-right text-primary me-2"></i>${list[i]}</p>
                    </div>`;
        list_edit += `<div class="form-group">
                        <input onchange="editList(${id},${i})" type="text" class="form-control edit_header" id="list_${i}" aria-describedby="list_${i}" value="${list[i]}" placeholder="list ${i}">
                    </div>`;
    }
    console.log('local: ',listArray);
    list_edit += `<div class="form-group">
                    <button type="button" class="btn btn-white btn-block" onclick="addList(${id})"><i class="fa fa-plus"></i>Add list</button>
                </div>`;
    let img = dataus.img;
    let bg = dataus.bg;
    body +=    `<div class="col-lg-5 d-flex align-items-center">
                    <div class="form-group">
                        <img src="../${img}" style="width: 100%; overflow: hidden; height: 100%; border-radius: 10px;">
                        <button type="button" class="btn btn-white btn-block" onclick="editImgAbouUs(${id})">Change image</button>
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUs('titulo_small',${id})" class="form-control edit_header" id="titulo_small" aria-describedby="titulo_small" value="${titulo_small}" placeholder="titulo_small">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUs('titulo_1',${id})" class="form-control edit_header" id="titulo_1" aria-describedby="titulo_1" value="${titulo_1}" placeholder="subtitle 1">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUs('titulo_2',${id})" class="form-control edit_header" id="titulo_2" aria-describedby="titulo_2" value="${titulo_2}" placeholder="subtitle 2">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUs('btn',${id})" class="form-control edit_header" id="btn" aria-describedby="btn" value="${btn}" placeholder="subtitle 2">
                    </div>
                    <div class="form-group">
                        <textarea data-id="${id}" rows="10" class="wysihtml5 form-control edit_header" id="paragrafo" placeholder="paragrafo">${paragrafo}</textarea>
                    </div>
                    <div class="form-group">
                    <img src="https://medtravel.com.co/${bg}" style="width: 100%; overflow: hidden; height: 100%; border-radius: 10px;">
                        <button type="button" class="btn btn-white btn-block" onclick="editBgAbouUs(${id})">Change Background</button>
                    </div>
                    ${list_edit}
                </div>
                <div class="col-lg-7" style="background: linear-gradient(rgba(255, 255, 255, .8), rgba(255, 255, 255, .8)), url(../${img});">
                    <h5 class="section-about-title pe-3">${titulo_small}</h5>
                    <h1 class="mb-4">${titulo_1} <span class="text-primary">${titulo_2}</span></h1>
                    ${paragrafo}
                    <div class="row gy-2 gx-4 mb-4">
                        ${list_html}
                    </div>
                    <a class="btn btn-primary rounded-pill py-3 px-5 mt-2" href="">${btn}</a>
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
    console.log(listArray);
    let url = 'ajax/about_edit.php';
    let data = {
        id: id,
        list: listArray,
        tipo: 'edit_list',
        input: 'list'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado la lista';
            let title = 'Lista';
            let status = 'success';
            notification(text, title, status);
            dataus.list = response.list;
            console.log(dataus.list);
            open_about(id);
            App.unblockUI();
        } else{
            let text = 'No se ha actualizado la lista';
            let title = 'Lista';
            let status = 'error';
            notification(text, title, status);
            App.unblockUI();
        }
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
    console.log(listArray);
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
            console.log(dataus.list);
            open_about(response.id);
        }
    });
}

function editAboutUs(input,id){
    console.log(input,id);
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
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado el Over Title';
            let title = 'Over Title';
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
            App.unblockUI();
        }
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
                if(response.status == 'success'){
                    let text = 'Se ha actualizado la imagen';
                    let title = 'Imagen';
                    let status = 'success';
                    notification(text, title, status);
                    let img = response.ruta;
                    dataus.img = img;
                    open_about(id);
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
                if(response.status == 'success'){
                    let text = 'Se ha actualizado la imagen';
                    let title = 'Imagen';
                    let status = 'success';
                    notification(text, title, status);
                    let bg = response.ruta;
                    dataus.bg = bg;
                    open_about(id);
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
    let titulo_small = specialist[0].titulo_small;
    let titulo = specialist[0].titulo_1;
    let facebook = social_media[0].facebook;
    let instagram = social_media[0].instagram;
    let twiter = social_media[0].twiter;
    console.log('remote: ',specialist);
    let specialis_edit = '';
    let specialist_list_edit = '';
    for(let i = 0; i < specialist.length; i++){
        specialis_edit += `<div class="col-sm-6">
                                <p class="mb-0"><i class="fa fa-arrow-right text-primary me-2"></i>${specialist[i]}</p>
                            </div>`;
    }
    console.log('local: ',specialis_edit);
    for(let i = 0; i < specialist_list.length; i++){
        let titulo = specialist_list[i].titulo;
        let subtitulo = specialist_list[i].subtitulo;
        let facebook = specialist_list[i].facebook;
        let twitter = specialist_list[i].twitter;
        let instagram = specialist_list[i].instagram;
        let img = specialist_list[i].img;
        specialist_list_edit += `<div class="col-md-3 col-lg-3">
                                    <div class="guide-item">
                                        <div class="guide-img">
                                            <div class="guide-img-efects">
                                                <img src="../${img}" style="width: 100%; overflow: hidden; height: 100%; border-radius: 10px;">
                                            </div>
                                            <div class="guide-icon rounded-pill p-2 text-center">
                                                <a class="btn btn-square btn-primary rounded-circle mx-1" target="_blanc" href="${facebook}"><i class="fa fa-facebook-f"></i></a>
                                                <a class="btn btn-square btn-primary rounded-circle mx-1" target="_blanc" href="${twiter}"><i class="fa fa-twitter"></i></a>
                                                <a class="btn btn-square btn-primary rounded-circle mx-1" target="_blanc" href="${instagram}"><i class="fa fa-instagram"></i></a>
                                                <button type="button" class="btn btn-square btn-danger rounded-circle mx-1" onclick="editImgAbouUs(${id})"><i class="fa fa-image"></i></button>
                                            </div>
                                        </div>
                                        <div class="guide-title text-center rounded-bottom p-4">
                                            <div class="guide-title-inner">
                                                <h2 class="mt-3"><b>${titulo}</b></h2>
                                                <p class="mb-0">${subtitulo}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
    }
    body +=    `<div class="col-lg-12 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" onchange="editAboutUsList('titulo_small',${id})" class="form-control edit_header" id="titulo_small" aria-describedby="titulo_small" value="${titulo_small}" placeholder="titulo_small">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUsList('titulo',${id})" class="form-control edit_header" id="titulo" aria-describedby="titulo" value="${titulo}" placeholder="subtitle 1">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUsList('facebook',${id})" class="form-control edit_header" id="facebook" aria-describedby="facebook" value="${facebook}" placeholder="subtitle 1">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUsList('instagram',${id})" class="form-control edit_header" id="instagram" aria-describedby="instagram" value="${instagram}" placeholder="subtitle 1">
                    </div>
                    <div class="form-group">
                        <input type="text" onchange="editAboutUsList('twiter',${id})" class="form-control edit_header" id="twiter" aria-describedby="twiter_1" value="${twiter}" placeholder="subtitle 1">
                    </div>
                    ${specialist_list_edit}
                </div>`;
    
    $('.page-content-col').html(body);
    $('.guide-img-efects').css('background-color', 'rgba(0, 0, 0, 0.5)');
    $('.guide-img-efects').css('background-size', 'cover');
    $('.guide-img-efects').css('height', '300px');
    ComponentsEditors.init();
}

function editAboutUsList(input,id){
    console.log(input,id);
    App.blockUI();
    $('#'+input).attr('onclick', '');
    let text_come = $('#'+input).val();
    let url = 'ajax/about_edit.php';
    let data = {
        id: id,
        text_come: text_come,
        input: input,
        tipo: 'edit_about_us_list'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            let text = 'Se ha actualizado el Over Title';
            let title = 'Over Title';
            let status = 'success';
            notification(text, title, status);
            let text_go = response.text_go;
            $('#'+input).html(text_go);
            $('#'+input).attr('onclick', 'editAboutUsList('+id+')');
            if(input == 'titulo_small'){
                specialist.titulo_small = text_come;
            } else if(input == `titulo_1`){
                specialist.titulo_1 = text_come;
            } else if(input == `facebook_1`){
                social_media.facebook = text_come;
            } else if(input == `instagram_1`){
                social_media.instagram = text_come;
            } else if(input == `twiter_1`){
                social_media.twiter = text_come;
            }
            open_specialist(id);
            App.unblockUI();
        }
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
                        console.log(text_come);
                        let url = 'ajax/about_edit.php';
                        let data = {
                            id: id,
                            text_come: text_come,
                            input: input,
                            tipo: 'edit_about_us'
                        };
                        $.post(url, data, function(res){
                            let response = JSON.parse(res);
                            if(response.status == 'success'){
                                let text = 'Se ha actualizado el Over Title';
                                let title = 'Over Title';
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
                                App.unblockUI();
                            }
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
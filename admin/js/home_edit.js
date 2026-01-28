let dataCarrucel = [];
$(document).ready(function(){
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'get_home'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        dataCarrucel = response;
        console.log(response);
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
});

function open_carrucel(i,id){
    console.log(dataCarrucel);
    $('.btn-carrucel').removeClass('active');
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
    console.log(input,i,id);
    $('#'+input+'_'+i).attr('onclick', '');
    let text_come = $('#'+input+'_input_'+i).val();
    console.log(text_come);
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
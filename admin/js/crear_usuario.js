$(document).ready(function(){
    //removemos data-toggle de los tabs
    $('#tab_href_1_2').removeAttr('data-toggle').removeAttr('href');
    $('#tab_href_1_3').removeAttr('data-toggle').removeAttr('href');
    $('#tab_href_1_4').removeAttr('data-toggle').removeAttr('href');
    $('.switch-radio1').bootstrapSwitch('readonly', true);
    let url = "ajax/crear_usuario.php";
    $.post(url, { tipo: 'listar_empresas' }, function (respuesta) {
        respuesta = JSON.parse(respuesta);
        console.log(respuesta.status);
        if(respuesta.status == true){
            let empresas = respuesta.empresas;
            console.log(empresas);
            let options = '<option value="">Seleccione</option>';
            empresas.forEach(empresa => {
                options += `<option value="${empresa.id}">${empresa.rasocial}</option>`;
            });
            $('#empresa').html(options);
            $('#empresa').select2();
        }
    });
    
    // Detectar cambio en los radio buttons de rol
    $('input[name="radio1"]').on('change', function() {
        let rol = $(this).val();
        // Si es rol 4 (Proveedor), mostrar dropdown de providers
        if (rol === '4') {
            $('#div-provider').show();
            $('#provider_id').attr('required', true);
        } else {
            $('#div-provider').hide();
            $('#provider_id').attr('required', false);
            $('#provider_id').val('');
        }
    });
    
    // Verificar rol inicial al cargar (si está marcado Proveedor)
    if ($('#option4').is(':checked')) {
        $('#div-provider').show();
        $('#provider_id').attr('required', true);
    }
});

$('#btn-crea-usuario').click(function(e){
    e.preventDefault();
    if( $('#nombre').val() == "" || $('#apellido').val() == "" || $('#email').val() == "" || $('#telefono').val() == "" || $('#direccion').val() == "" || $('#ciudad').val() == "" || $('#pass').val() == "" || $('#pass2').val() == "" ){
        let text = "Todos los campos son obligatorios";
        let title = "Creación Usuario";
        let status = "error";
        notification(text,title,status);
        App.unblockUI();
        return;
    }
    let creaEmpresa = $('#creaEmpresa').val();
    if(creaEmpresa == 1){
        if( $('#rasocial').val() == "" || $('#nit_e').val() == "" || $('#telefono_e').val() == "" || $('#celular_e').val() == "" || $('#direccion_e').val() == "" || $('#ciudad_e').val() == "" || $('#email_e').val() == "" || $('#url_e').val() == "" ){
            let text = "Todos los campos son obligatorios";
            let title = "Creación Usuario";
            let status = "error";
            notification(text,title,status);
            App.unblockUI();
            return;
        }
    }
    //obtenemos los valores del formulario serialize
    var datos = $("#form-crear-usuario").serialize();
    let rasocial = $('#empresa').find('option:selected').text();
    //agregamos parametros
    datos += "&tipo=crear_usuario";
    datos += "&rasocial=" + rasocial;
    //enviamos los datos por post
    let url = "ajax/crear_usuario.php";
    $.post(url, datos, function(respuesta){
        respuesta = JSON.parse(respuesta);
        if( respuesta.status == true){
            $('#tab_href_1_2').attr('href', '#tab_1_2').tab('show').attr('data-toggle', 'tab');
            //borramos el formulario form-crear-usuario
            $('#tab_href_1_1').removeAttr('href').removeAttr('data-toggle');
            //agregamos el id del usuario al input id_usuario
            $('#id_usuario').val(respuesta.id);
            let usuario = $('#email').val();
            //agregamos el nombr de usuario al input usuario
            $('#usuario').val(usuario);
            let nombre_usuario = $('#nombre').val() + " " + $('#apellido').val();
            $('.profile-usertitle-name').html(nombre_usuario);
            let cargo = $('#cargo').val();
            $('.profile-usertitle-job').html(cargo);
            $('.switch-radio1').bootstrapSwitch('readonly', false);
            let text = "El usuario se ha creado correctamente";
            let title = "Creación Usuario";
            let status = "success";
            notification(text,title,status);
            console.log(respuesta);
        }else{
            let text = "El usuario no se ha creado correctamente";
            let title = "Creación Usuario";
            let status = "error";
            notification(text,title,status);
        }
        App.unblockUI();
    });
});

$('#nit_e').on("change",()=>{
    let nit = $('#nit_e').val();
    const url = 'ajax/validaciones.php';
    $.post(url, {nit: nit, tipo: 'nit'}, function(data){
        data = JSON.parse(data);
        console.log(data);
        if(data.validacion == true){
            $('#error-nit_e').html('<span class="help-block text-danger">El NIT ya se encuentra registrado</span>');
            $('#btn-crea-usuario').attr('disabled', true);
        } else{
            $('#error-nit_e').html('');
            $('#btn-crea-usuario').attr('disabled', false);
        }
    });
});

$('.switch-radio1').on('switchChange.bootstrapSwitch', function(event, state) {
    let id_usuario = $('#id_usuario').val(); 
    let usuario = $('#usuario').val();
    let rol = event.target.value; 
    var archivoValidacion = "ajax/crear_usuario.php";
    $.post(archivoValidacion, { usuario: usuario, id_usuario: id_usuario, rol: rol, tipo: 'rol' }, function (respuesta) {
        respuesta = JSON.parse(respuesta);
        if (respuesta.status == true) {
                let text = "El rol se ha actualizado correctamente";
                let title = "Actualización Rol";
                let status = "success";
                notification(text,title,status);
        } else {
                let text = "El rol no se ha actualizado correctamente";
                let title = "Actualización Rol";
                let status = "error";
                notification(text,title,status);
        }
        App.unblockUI();
        return;
    }); 

});

$('#img-avatar').on("change",()=>{
    let files = $('#img-avatar')[0].files[0];
    if(files === undefined){
        return;
    }
    let reader = new FileReader();
    reader.onload = function(e) {
        $('#avatar').attr('src', e.target.result);
    }
    reader.readAsDataURL(files);
});

function crearAvatar(){
    App.blockUI();
    //validamos que los campos no esten vacios
    if( $('#img-avatar').val() == "" ){
        let text = "Todos los campos son obligatorios";
        let title = "Creación Avatar";
        let status = "error";
        notification(text,title,status);
        App.unblockUI();
        return;
    }
    let id_usuario = $('#id_usuario').val();
    var formData = new FormData();
    var files = $('#img-avatar')[0].files[0];
    if(files === undefined){
        return;
    }
    formData.append('file',files);
    formData.append('id_usuario',id_usuario);
    formData.append('tipo','crear_avatar');
    $.ajax({
            url: 'ajax/crear_usuario.php',
            type: 'post',
            data: formData,
            contentType: false,
            processData: false,
            success: function(respuesta) {
                respuesta = JSON.parse(respuesta);
                if (respuesta.status == true) {
                    $("#avatar").attr('src',respuesta.ruta);
                    //$("#imgAvatar").attr('src',respuesta.ruta);
                    $('#tab_href_1_3').attr('href', '#tab_1_3').tab('show').attr('data-toggle', 'tab');
                    //borramos el formulario form-crear-usuario
                    $('#tab_href_1_2').removeAttr('href').removeAttr('data-toggle');
                    let username = $('#usuario').val();
                    $('#username').val(username);
                    let text = "La imagen se ha actualizado correctamente";
                    let title = "Actualización Imagen";
                    let status = "success";
                    notification(text,title,status);
                } else  if (respuesta.status == false) {
                    let text = "La imagen no se ha actualizado correctamente";
                    let title = "Actualización Imagen";
                    let status = "error";
                    notification(text,title,status);
                } else{
                    let text = "La imagen no se ha actualizado correctamente";
                    let title = "Actualización Imagen";
                    let status = "error";
                    notification(text,title,status);
                }
                App.unblockUI();
            }
    });
    return false;
}

$('#password_2').keyup(function(){
    App.blockUI();
    var pass1 = $("#password_1").val();
    var pass2 = $("#password_2").val();
    if(pass1 === pass2){
        $("#comparaTexto").html('<span class="font-green-jungle">correcto!</span>');
        $("#btnSubmitPass").attr("disabled", false);
        $("#btnSubmitPass").attr("onClick", 'changePass()');
    } else{
        $("#comparaTexto").html('<span class="font-red-thunderbird">incorrecto!</span>');
        $("#btnSubmitPass").attr("disabled", true);
        $("#btnSubmitPass").attr("onClick", '');
    }
    App.unblockUI();
});

let password = '';
function changePass() {
    App.blockUI();
    let pass1 = $("#password_1").val();
    password  = pass1;
    let id_usuario = $('#id_usuario').val();
    let archivoValidacion = "ajax/crear_usuario.php";
    $.post( archivoValidacion, { pass1: pass1, tipo: 'crear_password', id_usuario: id_usuario }, function (respuesta) {
        respuesta = JSON.parse(respuesta);
        if(respuesta.status == true){
            let text = "El password se han actualizado correctamente";
            let title = "Actualización Password";
            let status = "success";
            notification(text,title,status);  
            restartForm();
        } else {
            let text = "El password no se han actualizado correctamente";
            let title = "Actualización Password";
            let status = "error";
            notification(text,title,status);
        }
        App.unblockUI();
	}).fail(function () {
		alert('error');
	});
}

function restartForm(){
    $('#tab_href_1_1').attr('href', '#tab_1_1').tab('show').attr('data-toggle', 'tab');
    $('#tab_href_1_2').removeAttr('data-toggle').removeAttr('href');
    $('#tab_href_1_3').removeAttr('data-toggle').removeAttr('href');
    $('#tab_href_1_4').removeAttr('data-toggle').removeAttr('href');
    $('.switch-radio1').bootstrapSwitch('readonly', true);
    let text = "El usuario se ha creado correctamente";
    let title = "Creación Usuario";
    let status = "success";
    notification(text,title,status);
    let id_usuario  = $('#id_usuario').val();
    let email       = $('#usuario').val();
    let asunto      = "Creación Cuenta Administrativa";
    
    let mensaje     = `<tbody>
                            <tr>
                                <td class="esd-block-text es-p10t es-p5b" bgcolor="transparent" align="left">
                                    <h3 style="color: #2980d9;">Hola ${nombre},</h3>
                                </td>
                            </tr>
                            <tr>
                                <td class="esd-block-text es-p10t" bgcolor="transparent" align="left">
                                    <p style="text-align: justify;">Hemos creado una cuenta a tu nombre, para ingresar utiliza las siguientes credenciales:</p>
                                    <ul>
                                        <li>Usuario: ${email}</li>
                                        <li>Contraseña: ${password}'</li>
                                    </ul>
                                    <p>Para ingresar a la plataforma da click en el siguiente botón:</p>
                                    <p><a href="https://ejemagicoadmin.com" class="es-button" target="_blank" style="border-style: solid; border-color: #2980d9; border-width: 10px 20px 10px 20px; background: #2980d9; border-radius: 0px; font-size: 18px; font-family: arial, helvetica, sans-serif; font-weight: normal; font-style: normal; line-height: 120%; color: #ffffff; text-decoration: none; width: auto; display: inline-block;">Ingresar</a></p>
                                    <p>No olvides cambiar tu contraseña al ingresar por primera vez.</p>
                                    <br><br><br>
                                </td>
                            </tr>
                        </tbody>`;
    let sBCC        = "joarguer@gmail.com";
    let addCC       = "";
    enviarCorreo(id_usuario,email,asunto,mensaje,sBCC,addCC,password);
    $("#form-crear-usuario").trigger("reset");
    $("#form-avatar-usuario").trigger("reset");
    $("#form-password-usuario").trigger("reset");
}

//$email,$asunto,$mensaje,$nombre,$sBCC,$addCC
function enviarCorreo(id_usuario,email,asunto,mensaje,sBCC,addCC,password){
    let archivoValidacion = "ajax/enviaMail.php";
    $.post( archivoValidacion, { id_usuario: id_usuario, email: email, asunto: asunto, mensaje: mensaje, sBCC: sBCC, addCC: addCC, tipo: 'crea_usuario', password: password }, function (respuesta) {
        respuesta = JSON.parse(respuesta);
        if(respuesta.status == true){
            let text = "El correo se ha enviado correctamente";
            let title = "Envío Correo";
            let status = "success";
            notification(text,title,status);
        } else {
            let text = "El correo no se ha enviado correctamente";
            let title = "Envío Correo";
            let status = "error";
            notification(text,title,status);
        }
        App.unblockUI();
    }).fail(function () {
        alert('error');
    });
}

///////////////////////// NOTIFICACIONES /////////////////////////
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
function validaUsuario(){    
    App.blockUI();
    var usrlogin_actual = $('#usrlogin').val();  
    if(usrlogin_actual == ''){
        $("#usuarioTexto").html('<span class="font-red-thunderbird">escibe un nombre de usuario!</span>');
        App.unblockUI();
        return;
    }
    var archivoValidacion = "ajax/mis_datos.php";
    $.post( archivoValidacion, { usrlogin: usrlogin_actual, usuario: 1 }, function (respuesta) {
        if(respuesta == 0){
            $("#estadoUsuario").val(1);
            $("#usuarioTexto").html('<span class="font-green-jungle">valido!</span>');
        } else{
            $("#estadoUsuario").val(0);
            $("#usuarioTexto").html('<span class="font-red-thunderbird">ya existe!</span>');
        }
        App.unblockUI();
	}).fail(function () {
		alert('error');
	});
}

function actualizarDatos(e){
    console.log(e);
    App.blockUI();
    let valor = e.value;
    let campo = e.id;
    var archivoValidacion = "ajax/mis_datos.php";
    $.post(archivoValidacion, { valor, campo, tipo: 'editar_usuario' }, function(respuesta){
        respuesta = JSON.parse(respuesta);
        if(respuesta.status == true){
            toastr.success("Los datos se han actualizado correctamente", "Actualización Usuario")
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
        } else{
            toastr.error("Los datos no se han actualizado correctamente", "Actualización Usuario")

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
        App.unblockUI();
    }); 
}

function subirImg(){
    App.blockUI();
    var formData = new FormData();
    var files = $('#img-avatar')[0].files[0];
    let id = $('#id_usuario').val();
    formData.append('id',id);
    if(files === undefined){
        return;
    }
    formData.append('file',files);
    $.ajax({
            url: 'ajax/uploadImg.php',
            type: 'post',
            data: formData,
            contentType: false,
            processData: false,
            success: function(respuesta) {
                console.log(respuesta);
                if (respuesta != 'null') {
                    $("#avatar").attr('src',respuesta);
                    $("#imgAvatar").attr('src',respuesta);
                    $("#foto_perfil").attr('src',respuesta);
                    $('.img-responsive').attr('src',respuesta);
                    $('#avatar_header').attr('src',respuesta);
                    toastr.success("La imagen se han actualizado correctamente", "Actualización Imagen")

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
                } else {
                    toastr.error("La imagen no se han actualizado correctamente", "Actualización Imagen")
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
                App.unblockUI();
            }
    });
    return false;
}

$('#password_actual').on('change', function(){
    App.blockUI();
    let password_actual = $('#password_actual').val();  
    let archivoValidacion = "ajax/mis_datos.php";
    $.post( archivoValidacion, { password_actual: password_actual, tipo: 'valida_pass' }, function (res) {
        let respuesta = JSON.parse(res);
        if(respuesta.status != 0){
            $("#passTexto").html('<span class="font-green-jungle">correcto!</span>');
            $("#usuario_edit").attr("disabled", false);
            $('#password').attr('disabled', false);
            $('#rpassword').attr('disabled', false);
        } else{
            $("#passTexto").html('<span class="font-red-thunderbird">incorrecto!</span>');
            $("#usuario_edit").attr("disabled", true);
            $('#password').attr('disabled', true);
            $('#rpassword').attr('disabled', true);
        }
        App.unblockUI();
	});
});

function comparaPass(){
    App.blockUI();
    var pass1 = $("#password").val();
    var pass2 = $("#rpassword").val();
    if(pass1 === pass2){
        $("#comparaTexto").html('<span class="font-green-jungle">correcto!</span>');
        $("#cambiar_password").attr("disabled", false);
        $("#cambiar_password").attr("onClick", 'changePass()');
    } else{
        $("#comparaTexto").html('<span class="font-red-thunderbird">incorrecto!</span>');
        $("#cambiar_password").attr("disabled", true);
        $("#cambiar_password").attr("onClick", '');
    }
    App.unblockUI();
}

function changePass(){
    console.log('changePass');
    App.blockUI();
    var pass1 = $("#password").val();
    var archivoValidacion = "ajax/mis_datos.php";
    let usuario = $('#usuario_edit').val();
    $.post( archivoValidacion, { usuario: usuario, pass1: pass1, tipo: 'cambia_pass' }, function (respuesta) {
        respuesta = JSON.parse(respuesta);
        if(respuesta.status == true){
            toastr.success("El password se a actualizado correctamente", "Actualización Password")

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
        } else {
            toastr.error("El password no se han actualizado correctamente", "Actualización Password")
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
        App.unblockUI();
	}).fail(function () {
		alert('error');
	});
}            
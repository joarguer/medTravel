$(document).ready(function(){
    //removemos data-toggle de los tabs
    $('#tab_href_1_2').removeAttr('data-toggle').removeAttr('href');
    $('#tab_href_1_3').removeAttr('data-toggle').removeAttr('href');
    $('#tab_href_1_4').removeAttr('data-toggle').removeAttr('href');
    if ($('.switch-radio1').length) $('.switch-radio1').bootstrapSwitch('readonly', true);
    let url = "ajax/crear_usuario.php";
    $.post(url, { tipo: 'listar_empresas' }, function (respuesta) {
        respuesta = JSON.parse(respuesta);
        if(respuesta.status == true){
            let empresas = respuesta.empresas;
            let options = '<option value="">Seleccione</option>';
            empresas.forEach(empresa => {
                options += `<option value="${empresa.id}">${empresa.rasocial}</option>`;
            });
            $('#empresa').html(options);
            if (window.CREAR_USUARIO_CTX && window.CREAR_USUARIO_CTX.isAdmin) {
                $('#empresa').select2();
            }
        }
    });

    function getActorScopeCopy(){
        let ctx = window.CREAR_USUARIO_CTX || {};
        if (ctx.isAdmin) {
            return {
                sidebar: 'Operas como administración central. Aquí puedes crear cuentas manuales globales o cuentas de dominio, según el rol seleccionado.',
                title: ctx.scopeTitle || 'Administración central',
                text: ctx.scopeText || 'Puedes crear cuentas manuales adicionales dentro del sistema según el rol y el alcance permitido.'
            };
        }
        if (ctx.serviceProviderId) {
            return {
                sidebar: 'Operas dentro de tu proveedor complementario. Toda cuenta creada aquí quedará subordinada a ese proveedor complementario.',
                title: ctx.scopeTitle || 'Scope actual: proveedor complementario',
                text: ctx.scopeText || 'Tu sesión está limitada al dominio complementario. Este flujo crea cuentas adicionales de ese scope.'
            };
        }
        if (ctx.providerId) {
            return {
                sidebar: 'Operas dentro de tu prestador médico. Toda cuenta creada aquí quedará subordinada a ese prestador médico.',
                title: ctx.scopeTitle || 'Scope actual: prestador médico',
                text: ctx.scopeText || 'Tu sesión está limitada al dominio médico. Este flujo crea cuentas adicionales de ese scope.'
            };
        }
        return {
            sidebar: 'Este flujo crea cuentas manuales adicionales dentro del sistema.',
            title: ctx.scopeTitle || 'Alta manual de cuentas',
            text: ctx.scopeText || 'El rol seleccionado define el alcance final de la cuenta.'
        };
    }

    function buildRoleSummary(){
        let ctx = window.CREAR_USUARIO_CTX || {};
        let roleMap = window.ROLES_HELP || {};
        let rol = String($('#user_role').val() || '');
        let roleHelp = roleMap[rol] || null;
        let providerRole = String(ctx.roleProvider || 4);
        let providerAdminRole = String(ctx.roleProviderAdmin || 12);
        let complementaryRole = String(ctx.roleComplementary || 13);
        let actorCopy = getActorScopeCopy();
        let summary = actorCopy.text;

        if (rol === providerRole || rol === providerAdminRole) {
            summary = 'Vas a crear una cuenta adicional del dominio médico. Debe quedar asociada a un prestador médico y no reemplaza el owner/admin inicial del onboarding canónico.';
        } else if (rol === complementaryRole) {
            summary = 'Vas a crear una cuenta adicional del dominio complementario. Debe quedar asociada a un proveedor complementario activo dentro del scope actual.';
        } else if (ctx.isAdmin) {
            summary = 'Vas a crear una cuenta manual global o interna. Revisa el rol seleccionado para confirmar el alcance operativo que tendrá en el sistema.';
        }

        if (roleHelp && roleHelp.menu_summary) {
            summary += ' Acceso principal esperado: ' + roleHelp.menu_summary;
        }

        return summary;
    }

    function applyScopeCopy(){
        let actorCopy = getActorScopeCopy();
        $('#manual-account-sidebar-scope').text(actorCopy.sidebar);
        $('#manual-account-sidebar-job').text('Alta manual de cuenta adicional');
        $('#current-scope-title').text(actorCopy.title);
        $('#current-scope-text').text(actorCopy.text);
        $('#wizard-role-summary').html('<strong>Tipo de cuenta y scope:</strong> ' + buildRoleSummary());
    }
    
    // Detectar cambio en el select de rol (reemplaza radios)
    function applyRoleVisibility(){
        let ctx = window.CREAR_USUARIO_CTX || {};
        let rol = $('#user_role').val();
        let providerRole = String(ctx.roleProvider || 4);
        let providerAdminRole = String(ctx.roleProviderAdmin || 12);
        let complementaryRole = String(ctx.roleComplementary || 13);
        let isProvider = (rol === providerRole || rol === providerAdminRole);
        let isComplementary = rol === complementaryRole;
        if (isProvider) {
            $('#div-provider').show();
            $('#provider_id').attr('required', true);
            $('#div-service-provider').hide();
            $('#service_provider_id').attr('required', false).val('');
            $('#div-empresa').hide();
        } else if (isComplementary) {
            $('#div-provider').hide();
            $('#provider_id').attr('required', false).val('');
            $('#div-service-provider').show();
            $('#service_provider_id').attr('required', true);
            $('#div-empresa').hide();
        } else {
            $('#div-provider').hide();
            $('#provider_id').attr('required', false).val('');
            $('#div-service-provider').hide();
            $('#service_provider_id').attr('required', false).val('');
            $('#div-empresa').show();
        }
        if (!ctx.isAdmin && ctx.providerId) {
            // For provider self-management, lock to their provider
            if ($('#user_role').val() !== providerAdminRole && $('#user_role').val() !== providerRole) {
                $('#user_role').val(providerRole);
            }
            $('#div-provider').show();
            $('#provider_id').val(ctx.providerId);
            $('#provider_id').attr('required', true).prop('disabled', true);
            $('#div-service-provider').hide();
            $('#service_provider_id').attr('required', false).val('');
            $('#div-empresa').hide();
        } else if (!ctx.isAdmin && ctx.serviceProviderId) {
            // Complementary provider scope
            $('#user_role').val(complementaryRole);
            $('#div-provider').hide();
            $('#provider_id').attr('required', false).val('');
            $('#div-service-provider').show();
            $('#service_provider_id').val(ctx.serviceProviderId);
            $('#service_provider_id').attr('required', true).prop('disabled', true);
            $('#div-empresa').hide();
        }
    }
    function applyRoleHelp(){
        let roleMap = window.ROLES_HELP || {};
        let ctx = window.CREAR_USUARIO_CTX || {};
        let currentRole = String($('#user_role').val() || '');
        let roleHelp = roleMap[currentRole] || null;
        let defaultHelp = 'Selecciona un rol para ver qué scope/empresa requiere.';
        let helpText = roleHelp && roleHelp.hint ? roleHelp.hint : defaultHelp;
        $('#role-scope-help').text(helpText);
        if (ctx.isAdmin) {
            $('#role-actor-help').text('Como administración central, puedes cambiar el rol para definir el alcance de la cuenta manual que se creará.');
        } else if (ctx.serviceProviderId) {
            $('#role-actor-help').text('Tu sesión fija el alta al proveedor complementario actual. El rol visible es informativo dentro de ese scope.');
        } else if (ctx.providerId) {
            $('#role-actor-help').text('Tu sesión fija el alta al prestador médico actual. El rol visible solo aplica dentro de ese scope.');
        } else {
            $('#role-actor-help').text('El rol seleccionado define el alcance general de esta cuenta nueva.');
        }
    }
    $('#user_role').on('change', function(){
        applyRoleVisibility();
        applyRoleHelp();
        applyScopeCopy();
    });
    applyRoleVisibility();
    applyRoleHelp();
    applyScopeCopy();
});

function validateCreateForm(){
    let ctx = window.CREAR_USUARIO_CTX || {};
    let rolVal = $('#user_role').val();
    let providerRole = String(ctx.roleProvider || 4);
    let providerAdminRole = String(ctx.roleProviderAdmin || 12);
    let complementaryRole = String(ctx.roleComplementary || 13);
    let isMedicalRole = (rolVal === providerRole || rolVal === providerAdminRole);
    let isComplementaryRole = (rolVal === complementaryRole);

    if ($('#nombre').val() === '' || $('#apellido').val() === '' || $('#email').val() === '' || $('#telefono').val() === '' || $('#direccion').val() === '' || $('#ciudad').val() === '') {
        return 'Completa los campos obligatorios para crear la cuenta manual.';
    }
    if (isMedicalRole && !ctx.providerId && $('#provider_id').val() === '') {
        return 'Selecciona el prestador médico al que quedará asociada la cuenta.';
    }
    if (isComplementaryRole && !ctx.serviceProviderId && $('#service_provider_id').val() === '') {
        return 'Selecciona el proveedor complementario al que quedará asociada la cuenta.';
    }
    return '';
}

$('#btn-crea-usuario').click(function(e){
    e.preventDefault();
    let validationError = validateCreateForm();
    if (validationError !== '') {
        let text = validationError;
        let title = 'Alta manual de cuenta';
        let status = "error";
        notification(text,title,status);
        App.unblockUI();
        return;
    }
    //obtenemos los valores del formulario serialize
    var datos = $("#form-crear-usuario").serialize();
    let rolVal = $('#user_role').val();
    let ctx = window.CREAR_USUARIO_CTX || {};
    let providerRole = String(ctx.roleProvider || 4);
    let providerAdminRole = String(ctx.roleProviderAdmin || 12);
    let complementaryRole = String(ctx.roleComplementary || 13);
    let isMedicalRole = (rolVal === providerRole || rolVal === providerAdminRole);
    let rasocial = '';
    if (isMedicalRole) {
        rasocial = $('#provider_id').find('option:selected').text();
    } else if (rolVal === complementaryRole) {
        rasocial = $('#service_provider_id').find('option:selected').text();
    } else {
        rasocial = $('#empresa').find('option:selected').text();
    }
    // limpiar empresa si rol proveedor
    if (isMedicalRole || rolVal === complementaryRole) {
        datos = datos.replace(/(^|&)empresa=[^&]*/,'$1empresa=');
    }
    // asegurar provider_id vacío cuando no es proveedor
    if (!isMedicalRole) {
        datos = datos.replace(/(^|&)provider_id=[^&]*/,'$1provider_id=');
    }
    // asegurar service_provider_id vacío cuando no es rol complementario
    if (rolVal !== complementaryRole) {
        datos = datos.replace(/(^|&)service_provider_id=[^&]*/,'$1service_provider_id=');
    }
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
            $('.profile-usertitle-job').html(cargo !== '' ? cargo : 'Cuenta manual creada');
            $('.switch-radio1').bootstrapSwitch('readonly', false);
            $('#wizard-role-summary').html('<strong>Siguiente paso:</strong> la cuenta ya fue creada. Ahora puedes cargar un avatar opcional y definir la contraseña inicial para entregar el acceso.');
            let text = 'La cuenta manual se creó correctamente. Continúa con el avatar opcional o define la contraseña inicial.';
            let title = 'Alta manual de cuenta';
            let status = "success";
            notification(text,title,status);
            console.log(respuesta);
        }else{
            let text = 'No fue posible crear la cuenta manual con los datos enviados.';
            let title = 'Alta manual de cuenta';
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
            let text = 'El rol de la cuenta creada se actualizó correctamente.';
            let title = 'Actualización de rol';
                let status = "success";
                notification(text,title,status);
        } else {
            let text = 'No fue posible actualizar el rol de la cuenta creada.';
            let title = 'Actualización de rol';
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
        let text = 'Selecciona una imagen si deseas asignar un avatar a esta cuenta.';
        let title = 'Avatar de la cuenta';
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
                    let text = 'El avatar de la cuenta se actualizó correctamente.';
                    let title = 'Avatar de la cuenta';
                    let status = "success";
                    notification(text,title,status);
                } else  if (respuesta.status == false) {
                    let text = 'No fue posible guardar el avatar de la cuenta.';
                    let title = 'Avatar de la cuenta';
                    let status = "error";
                    notification(text,title,status);
                } else{
                    let text = 'No fue posible guardar el avatar de la cuenta.';
                    let title = 'Avatar de la cuenta';
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
        $("#comparaTexto").html('<span class="font-green-jungle">Las contraseñas coinciden.</span>');
        $("#btnSubmitPass").attr("disabled", false);
        $("#btnSubmitPass").attr("onClick", 'changePass()');
    } else{
        $("#comparaTexto").html('<span class="font-red-thunderbird">Las contraseñas no coinciden.</span>');
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
            let text = 'La contraseña inicial se guardó correctamente.';
            let title = 'Contraseña inicial';
            let status = "success";
            notification(text,title,status);  
            restartForm();
        } else {
            let text = 'No fue posible guardar la contraseña inicial de la cuenta.';
            let title = 'Contraseña inicial';
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
    $('#wizard-role-summary').html('<strong>Flujo completado:</strong> la cuenta manual ya fue creada y configurada. Puedes iniciar otra alta manual desde este mismo formulario.');
    let text = 'La cuenta manual quedó lista correctamente.';
    let title = 'Alta manual de cuenta';
    let status = "success";
    notification(text,title,status);
    let id_usuario  = $('#id_usuario').val();
    let email       = $('#usuario').val();
    let nombreUsuario = ($('#nombre').val() + ' ' + $('#apellido').val()).trim();
    let asunto      = 'Nueva cuenta MedTravel';
    enviarCorreo({
        id_usuario: id_usuario,
        to: email,
        name: nombreUsuario,
        username: email,
        subject: asunto,
        temp_password: password
    });
    $("#form-crear-usuario").trigger("reset");
    $("#form-avatar-usuario").trigger("reset");
    $("#form-password-usuario").trigger("reset");
}

function enviarCorreo(payload){
    let archivoValidacion = "/admin/ajax/enviaMail.php";
    $.ajax({
        url: archivoValidacion,
        type: 'POST',
        data: payload,
        dataType: 'json'
    }).done(function (respuesta) {
        if(respuesta && (respuesta.ok === true || respuesta.status === true)){
            notification('Cuenta creada, correo enviado', 'Envío de correo', 'success');
        } else {
            notification('Cuenta creada, correo pendiente', 'Envío de correo', 'error');
        }
    }).fail(function () {
        notification('Cuenta creada, correo pendiente', 'Envío de correo', 'error');
    }).always(function(){
        App.unblockUI();
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

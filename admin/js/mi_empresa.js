$(document).ready(function() {
    
    // Manejar envío del formulario
    $('#form-empresa').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $('#btn-guardar');
        var btnText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
        
        var formData = {
            tipo: 'actualizar_empresa',
            name: $('#name').val(),
            description: $('#description').val(),
            city: $('#city').val(),
            address: $('#address').val(),
            phone: $('#phone').val(),
            email: $('#email').val(),
            website: $('#website').val()
        };
        
        $.ajax({
            url: 'ajax/mi_empresa.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.ok) {
                    toastr.success(response.message || 'Cambios guardados correctamente', 'Éxito');
                } else {
                    toastr.error(response.error || 'Error al guardar los cambios', 'Error');
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Error de conexión: ' + error, 'Error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(btnText);
            }
        });
    });
    
    // Manejar cambio de logo
    $('#logo').on('change', function() {
        var file = this.files[0];
        
        if (!file) {
            return;
        }
        
        // Validar tamaño
        if (file.size > 2 * 1024 * 1024) {
            toastr.error('El archivo excede el tamaño máximo de 2MB', 'Error');
            $(this).val('');
            return;
        }
        
        // Validar tipo
        var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            toastr.error('Formato no permitido. Use JPG, PNG o WEBP', 'Error');
            $(this).val('');
            return;
        }
        
        // Subir archivo
        uploadLogo(file);
    });
    
    function uploadLogo(file) {
        var formData = new FormData();
        formData.append('tipo', 'upload_logo');
        formData.append('logo', file);
        
        toastr.info('Subiendo logo...', 'Procesando');
        
        $.ajax({
            url: 'ajax/mi_empresa.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.ok) {
                    toastr.success(response.message || 'Logo actualizado correctamente', 'Éxito');
                    
                    // Actualizar preview
                    if (response.url) {
                        $('#logo-preview').attr('src', response.url + '?t=' + new Date().getTime());
                    }
                } else {
                    toastr.error(response.error || 'Error al subir el logo', 'Error');
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Error de conexión: ' + error, 'Error');
            }
        });
    }
    
    // Validación en tiempo real
    $('#email').on('blur', function() {
        var email = $(this).val();
        if (email && !isValidEmail(email)) {
            toastr.warning('El formato del email no es válido', 'Validación');
        }
    });
    
    $('#website').on('blur', function() {
        var url = $(this).val();
        if (url && !isValidURL(url)) {
            toastr.warning('El formato de la URL no es válido', 'Validación');
        }
    });
    
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function isValidURL(url) {
        var re = /^https?:\/\/.+/i;
        return re.test(url);
    }
});

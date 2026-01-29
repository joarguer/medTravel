$(document).ready(function() {
    // Guardar configuración del header
    $('#form_services_header').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'ajax/services_edit.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#mensaje_services').html(
                        '<div class="alert alert-success alert-dismissible">' +
                        '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                        '<i class="fa fa-check"></i> ' + response.message +
                        '</div>'
                    );
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#mensaje_services').html(
                        '<div class="alert alert-danger alert-dismissible">' +
                        '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                        '<i class="fa fa-exclamation-triangle"></i> ' + response.message +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#mensaje_services').html(
                    '<div class="alert alert-danger alert-dismissible">' +
                    '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                    '<i class="fa fa-exclamation-triangle"></i> Error de conexión al servidor' +
                    '</div>'
                );
            }
        });
    });
});

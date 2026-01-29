// Variables globales
var tablaClientes;
var isEditMode = false;

// Inicializar cuando el documento esté listo
$(document).ready(function() {
    initDataTable();
    initToastr();
});

// Inicializar toastr
function initToastr() {
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
    };
}

// Inicializar DataTable
function initDataTable() {
    tablaClientes = $('#tabla_clientes').DataTable({
        "processing": true,
        "ajax": {
            "url": "ajax/clientes.php",
            "type": "POST",
            "data": { tipo: 'get' },
            "dataSrc": function(json) {
                if (json.success) {
                    return json.data;
                } else {
                    toastr.error(json.message || 'Error al cargar clientes');
                    return [];
                }
            }
        },
        "columns": [
            { "data": "id" },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return row.nombre + ' ' + row.apellido;
                }
            },
            { "data": "email" },
            { 
                "data": "telefono",
                "render": function(data) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            { 
                "data": "pais",
                "render": function(data) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            { 
                "data": null,
                "render": function(data, type, row) {
                    var location = [];
                    if (row.estado) location.push(row.estado);
                    if (row.ciudad) location.push(row.ciudad);
                    return location.length > 0 ? location.join(', ') : '<span class="text-muted">N/A</span>';
                }
            },
            { 
                "data": "status",
                "render": function(data) {
                    return getStatusBadge(data);
                }
            },
            { 
                "data": "origen_contacto",
                "render": function(data) {
                    return getOrigenBadge(data);
                }
            },
            { 
                "data": "created_at",
                "render": function(data) {
                    return data ? formatDate(data) : '';
                }
            },
            { 
                "data": null,
                "orderable": false,
                "render": function(data, type, row) {
                    return `
                        <button class="btn btn-xs btn-info" onclick="viewCliente(${row.id})" title="Ver">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="btn btn-xs btn-primary" onclick="editCliente(${row.id})" title="Editar">
                            <i class="fa fa-edit"></i>
                        </button>
                        <button class="btn btn-xs btn-danger" onclick="deleteCliente(${row.id}, '${row.nombre} ${row.apellido}')" title="Eliminar">
                            <i class="fa fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
        },
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true
    });
}

// Obtener badge de status
function getStatusBadge(status) {
    var badges = {
        'lead': '<span class="label label-info">Lead</span>',
        'cotizado': '<span class="label label-warning">Cotizado</span>',
        'confirmado': '<span class="label label-primary">Confirmado</span>',
        'en_viaje': '<span class="label label-success">En Viaje</span>',
        'post_tratamiento': '<span class="label label-default">Post Tratamiento</span>',
        'finalizado': '<span class="label label-success">Finalizado</span>',
        'inactivo': '<span class="label label-danger">Inactivo</span>'
    };
    return badges[status] || '<span class="label label-default">' + status + '</span>';
}

// Obtener badge de origen
function getOrigenBadge(origen) {
    var badges = {
        'web': '<span class="label label-info"><i class="fa fa-globe"></i> Web</span>',
        'whatsapp': '<span class="label label-success"><i class="fa fa-whatsapp"></i> WhatsApp</span>',
        'telefono': '<span class="label label-primary"><i class="fa fa-phone"></i> Teléfono</span>',
        'email': '<span class="label label-warning"><i class="fa fa-envelope"></i> Email</span>',
        'referido': '<span class="label label-info"><i class="fa fa-user"></i> Referido</span>',
        'redes_sociales': '<span class="label label-primary"><i class="fa fa-share-alt"></i> RRSS</span>',
        'otro': '<span class="label label-default">Otro</span>'
    };
    return badges[origen] || '<span class="label label-default">' + origen + '</span>';
}

// Formatear fecha
function formatDate(dateString) {
    if (!dateString) return '';
    var date = new Date(dateString);
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var year = date.getFullYear();
    return day + '/' + month + '/' + year;
}

// Abrir modal para crear cliente
function openCreateModal() {
    isEditMode = false;
    $('#modalClienteTitle').text('Nuevo Cliente');
    $('#formCliente')[0].reset();
    $('#cliente_id').val('');
    
    // Valores por defecto
    $('#pais').val('USA');
    $('#idioma_preferido').val('en');
    $('#status').val('lead');
    $('#origen_contacto').val('web');
    $('#tipo_documento').val('passport');
    
    $('#modalCliente').modal('show');
}

// Ver cliente (solo lectura)
function viewCliente(id) {
    $.ajax({
        url: 'ajax/clientes.php',
        type: 'POST',
        data: { tipo: 'get_one', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var cliente = response.data;
                
                // Mostrar información en modo lectura
                var html = `
                    <div class="row">
                        <div class="col-md-12">
                            <h4>Información Personal</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="200">Nombre Completo:</th>
                                    <td>${cliente.nombre} ${cliente.apellido}</td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>${cliente.email}</td>
                                </tr>
                                <tr>
                                    <th>Teléfono:</th>
                                    <td>${cliente.telefono || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>WhatsApp:</th>
                                    <td>${cliente.whatsapp || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Fecha de Nacimiento:</th>
                                    <td>${cliente.fecha_nacimiento ? formatDate(cliente.fecha_nacimiento) : 'N/A'}</td>
                                </tr>
                            </table>
                            
                            <h4>Ubicación</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="200">País:</th>
                                    <td>${cliente.pais || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Estado/Ciudad:</th>
                                    <td>${cliente.estado || ''} ${cliente.ciudad || ''}</td>
                                </tr>
                                <tr>
                                    <th>Dirección:</th>
                                    <td>${cliente.direccion || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Código Postal:</th>
                                    <td>${cliente.codigo_postal || 'N/A'}</td>
                                </tr>
                            </table>
                            
                            <h4>Información del Cliente</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="200">Status:</th>
                                    <td>${getStatusBadge(cliente.status)}</td>
                                </tr>
                                <tr>
                                    <th>Origen de Contacto:</th>
                                    <td>${getOrigenBadge(cliente.origen_contacto)}</td>
                                </tr>
                                <tr>
                                    <th>Documento:</th>
                                    <td>${cliente.tipo_documento || 'N/A'} - ${cliente.numero_pasaporte || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Idioma:</th>
                                    <td>${cliente.idioma_preferido || 'N/A'}</td>
                                </tr>
                            </table>
                            
                            ${cliente.contacto_emergencia_nombre ? `
                            <h4>Contacto de Emergencia</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="200">Nombre:</th>
                                    <td>${cliente.contacto_emergencia_nombre}</td>
                                </tr>
                                <tr>
                                    <th>Teléfono:</th>
                                    <td>${cliente.contacto_emergencia_telefono || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Relación:</th>
                                    <td>${cliente.contacto_emergencia_relacion || 'N/A'}</td>
                                </tr>
                            </table>
                            ` : ''}
                            
                            ${(cliente.condiciones_medicas || cliente.alergias || cliente.medicamentos_actuales) ? `
                            <h4>Información Médica</h4>
                            <table class="table table-bordered">
                                ${cliente.condiciones_medicas ? `<tr><th width="200">Condiciones:</th><td>${cliente.condiciones_medicas}</td></tr>` : ''}
                                ${cliente.alergias ? `<tr><th>Alergias:</th><td>${cliente.alergias}</td></tr>` : ''}
                                ${cliente.medicamentos_actuales ? `<tr><th>Medicamentos:</th><td>${cliente.medicamentos_actuales}</td></tr>` : ''}
                            </table>
                            ` : ''}
                            
                            ${cliente.notas ? `
                            <h4>Notas Internas</h4>
                            <div class="alert alert-info">${cliente.notas}</div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                // Crear modal temporal para ver
                var viewModal = `
                    <div class="modal fade" id="modalViewCliente" tabindex="-1" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                    <h4 class="modal-title">Información del Cliente</h4>
                                </div>
                                <div class="modal-body">${html}</div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                                    <button type="button" class="btn btn-primary" onclick="$('#modalViewCliente').modal('hide'); editCliente(${id});">Editar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remover modal anterior si existe
                $('#modalViewCliente').remove();
                $('body').append(viewModal);
                $('#modalViewCliente').modal('show');
                
            } else {
                toastr.error(response.message || 'Error al cargar el cliente');
            }
        },
        error: function() {
            toastr.error('Error de conexión al cargar el cliente');
        }
    });
}

// Editar cliente
function editCliente(id) {
    isEditMode = true;
    $('#modalClienteTitle').text('Editar Cliente');
    
    $.ajax({
        url: 'ajax/clientes.php',
        type: 'POST',
        data: { tipo: 'get_one', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var cliente = response.data;
                
                // Llenar formulario
                $('#cliente_id').val(cliente.id);
                $('#nombre').val(cliente.nombre);
                $('#apellido').val(cliente.apellido);
                $('#email').val(cliente.email);
                $('#fecha_nacimiento').val(cliente.fecha_nacimiento);
                $('#telefono').val(cliente.telefono);
                $('#whatsapp').val(cliente.whatsapp);
                $('#pais').val(cliente.pais);
                $('#estado').val(cliente.estado);
                $('#ciudad').val(cliente.ciudad);
                $('#direccion').val(cliente.direccion);
                $('#codigo_postal').val(cliente.codigo_postal);
                $('#tipo_documento').val(cliente.tipo_documento);
                $('#numero_pasaporte').val(cliente.numero_pasaporte);
                $('#idioma_preferido').val(cliente.idioma_preferido);
                $('#status').val(cliente.status);
                $('#origen_contacto').val(cliente.origen_contacto);
                $('#contacto_emergencia_nombre').val(cliente.contacto_emergencia_nombre);
                $('#contacto_emergencia_telefono').val(cliente.contacto_emergencia_telefono);
                $('#contacto_emergencia_relacion').val(cliente.contacto_emergencia_relacion);
                $('#condiciones_medicas').val(cliente.condiciones_medicas);
                $('#alergias').val(cliente.alergias);
                $('#medicamentos_actuales').val(cliente.medicamentos_actuales);
                $('#notas').val(cliente.notas);
                
                // Marketing / UTM
                $('#utm_source').val(cliente.utm_source);
                $('#utm_medium').val(cliente.utm_medium);
                $('#utm_campaign').val(cliente.utm_campaign);
                $('#utm_content').val(cliente.utm_content);
                $('#utm_term').val(cliente.utm_term);
                $('#referred_by').val(cliente.referred_by);
                
                $('#modalCliente').modal('show');
            } else {
                toastr.error(response.message || 'Error al cargar el cliente');
            }
        },
        error: function() {
            toastr.error('Error de conexión al cargar el cliente');
        }
    });
}

// Guardar cliente (crear o actualizar)
function saveCliente() {
    // Validar campos requeridos
    if (!$('#nombre').val() || !$('#apellido').val() || !$('#email').val()) {
        toastr.warning('Por favor complete los campos requeridos');
        return;
    }
    
    // Validar email
    var email = $('#email').val();
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        toastr.warning('Por favor ingrese un email válido');
        return;
    }
    
    var formData = $('#formCliente').serialize();
    var tipo = isEditMode ? 'update' : 'create';
    formData += '&tipo=' + tipo;
    
    $.ajax({
        url: 'ajax/clientes.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('.modal-footer button').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#modalCliente').modal('hide');
                tablaClientes.ajax.reload();
            } else {
                toastr.error(response.message || 'Error al guardar el cliente');
            }
        },
        error: function() {
            toastr.error('Error de conexión al guardar el cliente');
        },
        complete: function() {
            $('.modal-footer button').prop('disabled', false);
        }
    });
}

// Eliminar cliente
function deleteCliente(id, nombre) {
    if (confirm('¿Está seguro de eliminar al cliente ' + nombre + '?\n\nEsta acción no se puede deshacer.')) {
        $.ajax({
            url: 'ajax/clientes.php',
            type: 'POST',
            data: { tipo: 'delete', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    tablaClientes.ajax.reload();
                } else {
                    toastr.error(response.message || 'Error al eliminar el cliente');
                }
            },
            error: function() {
                toastr.error('Error de conexión al eliminar el cliente');
            }
        });
    }
}

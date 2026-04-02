<?php
include __DIR__ . '/include/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - My Profile</title>
    <?php echo $global_first_style; ?>
    <?php echo $theme_global_style; ?>
    <?php echo $theme_layout_style; ?>
    <style>
        .mis-datos-form .form-section { margin-top: 16px; margin-bottom: 10px; font-weight: 700; }
        .mis-datos-form .required-mark { color: #e7505a; }
    </style>
</head>
<body class="page-header-fixed page-sidebar-closed-hide-logo page-md">
<div class="wrapper">
    <header class="page-header">
        <nav class="navbar mega-menu" role="navigation">
            <div class="container-fluid">
                <?php echo $top_header; ?>
                <?php echo $top_header_2; ?>
            </div>
        </nav>
    </header>

    <div class="container-fluid">
        <div class="page-content">
            <div class="breadcrumbs">
                <h1>My Profile</h1>
                <ol class="breadcrumb">
                    <li><a href="/client/index.php">Home</a></li>
                    <li class="active">My Profile</li>
                </ol>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="portlet light bordered">
                        <div class="portlet-title">
                            <div class="caption">
                                <i class="icon-user font-blue"></i>
                                <span class="caption-subject font-blue bold uppercase">Complete Your Profile</span>
                            </div>
                        </div>
                        <div class="portlet-body">
                            <form id="misDatosForm" class="mis-datos-form">
                                <input type="hidden" id="cliente_id" name="cliente_id" value="0">

                                <h4 class="form-section">Personal Information</h4>
                                <div class="row">
                                    <div class="col-md-6" data-field-wrap="nombre">
                                        <div class="form-group">
                                            <label>First Name <span class="required-mark" data-required-mark="nombre" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="nombre" name="nombre">
                                        </div>
                                    </div>
                                    <div class="col-md-6" data-field-wrap="apellido">
                                        <div class="form-group">
                                            <label>Last Name <span class="required-mark" data-required-mark="apellido" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="apellido" name="apellido">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6" data-field-wrap="email">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" id="email" name="email" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6" data-field-wrap="fecha_nacimiento">
                                        <div class="form-group">
                                            <label>Date of Birth <span class="required-mark" data-required-mark="fecha_nacimiento" style="display:none;">*</span></label>
                                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6" data-field-wrap="telefono">
                                        <div class="form-group">
                                            <label>Phone <span class="required-mark" data-required-mark="telefono" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="telefono" name="telefono">
                                        </div>
                                    </div>
                                    <div class="col-md-6" data-field-wrap="whatsapp">
                                        <div class="form-group">
                                            <label>WhatsApp <span class="required-mark" data-required-mark="whatsapp" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="whatsapp" name="whatsapp">
                                        </div>
                                    </div>
                                </div>

                                <h4 class="form-section">Location</h4>
                                <div class="row">
                                    <div class="col-md-4" data-field-wrap="pais">
                                        <div class="form-group">
                                            <label>Country <span class="required-mark" data-required-mark="pais" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="pais" name="pais">
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="estado">
                                        <div class="form-group">
                                            <label>State/Province <span class="required-mark" data-required-mark="estado" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="estado" name="estado">
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="ciudad">
                                        <div class="form-group">
                                            <label>City <span class="required-mark" data-required-mark="ciudad" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="ciudad" name="ciudad">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-8" data-field-wrap="direccion">
                                        <div class="form-group">
                                            <label>Address <span class="required-mark" data-required-mark="direccion" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="direccion" name="direccion">
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="codigo_postal">
                                        <div class="form-group">
                                            <label>ZIP Code <span class="required-mark" data-required-mark="codigo_postal" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="codigo_postal" name="codigo_postal">
                                        </div>
                                    </div>
                                </div>

                                <h4 class="form-section">Documentation &amp; Language</h4>
                                <div class="row">
                                    <div class="col-md-4" data-field-wrap="tipo_documento">
                                        <div class="form-group">
                                            <label>Document Type <span class="required-mark" data-required-mark="tipo_documento" style="display:none;">*</span></label>
                                            <select class="form-control" id="tipo_documento" name="tipo_documento"></select>
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="numero_pasaporte">
                                        <div class="form-group">
                                            <label>Document Number <span class="required-mark" data-required-mark="numero_pasaporte" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="numero_pasaporte" name="numero_pasaporte">
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="idioma_preferido">
                                        <div class="form-group">
                                            <label>Preferred Language <span class="required-mark" data-required-mark="idioma_preferido" style="display:none;">*</span></label>
                                            <select class="form-control" id="idioma_preferido" name="idioma_preferido"></select>
                                        </div>
                                    </div>
                                </div>

                                <h4 class="form-section">Emergency Contact</h4>
                                <div class="row">
                                    <div class="col-md-4" data-field-wrap="contacto_emergencia_nombre">
                                        <div class="form-group">
                                            <label>Name <span class="required-mark" data-required-mark="contacto_emergencia_nombre" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="contacto_emergencia_nombre" name="contacto_emergencia_nombre">
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="contacto_emergencia_telefono">
                                        <div class="form-group">
                                            <label>Phone <span class="required-mark" data-required-mark="contacto_emergencia_telefono" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="contacto_emergencia_telefono" name="contacto_emergencia_telefono">
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="contacto_emergencia_relacion">
                                        <div class="form-group">
                                            <label>Relationship <span class="required-mark" data-required-mark="contacto_emergencia_relacion" style="display:none;">*</span></label>
                                            <input type="text" class="form-control" id="contacto_emergencia_relacion" name="contacto_emergencia_relacion">
                                        </div>
                                    </div>
                                </div>

                                <h4 class="form-section">Basic Medical Information</h4>
                                <div class="row">
                                    <div class="col-md-4" data-field-wrap="condiciones_medicas">
                                        <div class="form-group">
                                            <label>Medical Conditions <span class="required-mark" data-required-mark="condiciones_medicas" style="display:none;">*</span></label>
                                            <textarea class="form-control" id="condiciones_medicas" name="condiciones_medicas" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="alergias">
                                        <div class="form-group">
                                            <label>Allergies <span class="required-mark" data-required-mark="alergias" style="display:none;">*</span></label>
                                            <textarea class="form-control" id="alergias" name="alergias" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4" data-field-wrap="medicamentos_actuales">
                                        <div class="form-group">
                                            <label>Current Medications <span class="required-mark" data-required-mark="medicamentos_actuales" style="display:none;">*</span></label>
                                            <textarea class="form-control" id="medicamentos_actuales" name="medicamentos_actuales" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" id="btnGuardarMisDatos">
                                        <i class="fa fa-save"></i> Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo $footer; ?>
    </div>
</div>

<?php echo $theme_layout_script; ?>
<script src="/client/js/notifications.js" type="text/javascript"></script>
<script>
(function(){
    var endpoint = '/client/ajax/save_mis_datos.php';
    var editableFields = [];
    var requiredFields = [];
    var fieldMeta = {};

    function showError(message) {
        if (window.toastr) {
            toastr.error(message);
        } else {
            alert(message);
        }
    }

    function showSuccess(message) {
        if (window.toastr) {
            toastr.success(message);
        } else {
            alert(message);
        }
    }

    function hideAllEditableWrappers() {
        $('[data-field-wrap]').hide();
    }

    function fillSelectOptions(field, meta, selectedValue) {
        var $select = $('#' + field);
        if (!$select.length) {
            return;
        }
        if ($select.prop('tagName').toLowerCase() !== 'select') {
            return;
        }

        $select.empty();
        var options = (meta && meta.enum_options) ? meta.enum_options : [];
        if (!options.length) {
            $select.append('<option value="">Select...</option>');
        } else {
            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                $select.append('<option value="' + opt + '">' + opt + '</option>');
            }
        }
        if (selectedValue !== undefined && selectedValue !== null) {
            $select.val(String(selectedValue));
        }
    }

    function applySchemaUI() {
        hideAllEditableWrappers();

        for (var i = 0; i < editableFields.length; i++) {
            var field = editableFields[i];
            $('[data-field-wrap="' + field + '"]').show();
            var required = requiredFields.indexOf(field) !== -1;
            $('[data-required-mark="' + field + '"]').toggle(required);
            $('#' + field).prop('required', required);
        }
    }

    function populateData(data) {
        $('#cliente_id').val(data.id || 0);
        $('#email').val(data.email || '');

        for (var i = 0; i < editableFields.length; i++) {
            var field = editableFields[i];
            var value = data[field] !== undefined && data[field] !== null ? data[field] : '';
            if ($('#' + field).prop('tagName') && $('#' + field).prop('tagName').toLowerCase() === 'select') {
                fillSelectOptions(field, fieldMeta[field] || {}, value);
            } else {
                $('#' + field).val(value);
            }
        }
    }

    function loadProfile() {
        $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            data: { tipo: 'get_profile' }
        }).done(function(resp){
            if (!resp || !resp.ok) {
                showError((resp && resp.message) ? resp.message : 'Could not load your profile.');
                return;
            }

            editableFields = resp.editable_fields || [];
            requiredFields = resp.required_fields || [];
            fieldMeta = resp.field_meta || {};

            applySchemaUI();
            populateData(resp.data || {});

            if (resp.created_profile) {
                showSuccess('Your patient profile was created. Please complete the missing fields and save.');
            }
        }).fail(function(xhr){
            showError('Error loading profile (' + xhr.status + ').');
        });
    }

    function saveProfile() {
        var data = { tipo: 'save_profile' };

        for (var i = 0; i < editableFields.length; i++) {
            var field = editableFields[i];
            data[field] = $('#' + field).val();
        }

        var missing = [];
        for (var j = 0; j < requiredFields.length; j++) {
            var reqField = requiredFields[j];
            var value = (data[reqField] || '').toString().trim();
            if (value === '') {
                missing.push(reqField);
            }
        }
        if (missing.length) {
            showError('Please fill in all required fields before saving.');
            return;
        }

        $('#btnGuardarMisDatos').prop('disabled', true);
        $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            data: data
        }).done(function(resp){
            if (!resp || !resp.ok) {
                showError((resp && resp.message) ? resp.message : 'Could not save your data.');
                return;
            }
            showSuccess('Data saved successfully.');
            if (resp.data) {
                populateData(resp.data);
            }
        }).fail(function(xhr){
            showError('Error saving profile (' + xhr.status + ').');
        }).always(function(){
            $('#btnGuardarMisDatos').prop('disabled', false);
        });
    }

    $('#misDatosForm').on('submit', function(e){
        e.preventDefault();
        saveProfile();
    });

    loadProfile();
})();
</script>
</body>
</html>

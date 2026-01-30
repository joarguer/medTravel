# Implementación Admin Home Edit - Nuevas Secciones

## Resumen
Se necesita agregar en el panel admin/home_edit.php la capacidad de editar:
1. Sección "Cómo Funciona" (4 pasos)
2. Sección "Servicios Detallados" (6 tarjetas)

## Cambios Completados

### 1. Base de Datos ✓
- Tabla `home_como_funciona` creada
- Tabla `home_services` creada
- Datos iniciales insertados

### 2. Frontend (index.php) ✓
- Sección "Cómo Funciona" lee de `home_como_funciona`
- Sección "Servicios Detallados" lee de `home_services`

## Próximos Pasos para Admin

### admin/home_edit.php
Necesita agregar dos nuevas secciones en el sidebar (después de "Carrucel"):

```html
<!-- Después del sidebar de Carrucel, agregar: -->

<h3>Cómo Funciona</h3>
<div class="como-funciona-sidebar">
    <ul class="nav navbar-nav margin-bottom-35 como-funciona-list">
        <?php 
            $busca_como = mysqli_query($conexion,"SELECT * FROM home_como_funciona WHERE activo = '0' ORDER BY step_number ASC");
            $n = 0;
            while($fil = mysqli_fetch_array($busca_como)){ 
                $id = $fil['id'];
        ?>
        <li class="btn-como-funciona <?php echo ($n == 0) ? 'active' : '';?> como-funciona-item" id="btn-como-<?php echo $n;?>">
            <div class="como-funciona-item__content">
                <a class="como-funciona-link" onclick="open_como_funciona(<?php echo $n;?>,<?php echo $id;?>)">
                    <span class="como-funciona-link__icon"><i class="<?php echo $fil['icon_class'];?>"></i></span>
                    <span><?php echo $fil['step_number'];?>. <?php echo $fil['title'];?></span>
                </a>
            </div>
        </li>
        <?php $n++; } ?>
    </ul>
</div>

<h3>Servicios Detallados</h3>
<div class="services-sidebar">
    <ul class="nav navbar-nav margin-bottom-35 services-list">
        <?php 
            $busca_services = mysqli_query($conexion,"SELECT * FROM home_services WHERE activo = '0' ORDER BY orden ASC");
            $m = 0;
            while($fil = mysqli_fetch_array($busca_services)){ 
                $id = $fil['id'];
        ?>
        <li class="btn-service <?php echo ($m == 0) ? 'active' : '';?> service-item" id="btn-service-<?php echo $m;?>">
            <div class="service-item__content">
                <a class="service-link" onclick="open_service(<?php echo $m;?>,<?php echo $id;?>)">
                    <span class="service-link__icon"><i class="<?php echo $fil['icon_class'];?>"></i></span>
                    <span><?php echo $fil['title'];?></span>
                </a>
            </div>
        </li>
        <?php $m++; } ?>
    </ul>
</div>
```

### admin/ajax/home_edit.php
Agregar estos casos al switch:

```php
// Después de los casos existentes, agregar:

if($tipo == 'get_como_funciona'){
    $busco = mysqli_query($conexion,"SELECT * FROM home_como_funciona WHERE activo = '0' ORDER BY step_number ASC");
    while($rst = mysqli_fetch_array($busco)){
        $resultados[] = $rst;
    }
}

if($tipo == 'edit_como_funciona'){
    $id = $_REQUEST["id"];
    $field = $_REQUEST["field"];
    $value = $_REQUEST["value"];
    $busca = mysqli_query($conexion,"UPDATE home_como_funciona SET $field = '$value' WHERE id = '$id'");
    if($busca){
        $resultados['status'] = 'success';
    } else {
        $resultados['status'] = 'error';
    }
}

if($tipo == 'get_services'){
    $busco = mysqli_query($conexion,"SELECT * FROM home_services WHERE activo = '0' ORDER BY orden ASC");
    while($rst = mysqli_fetch_array($busco)){
        $resultados[] = $rst;
    }
}

if($tipo == 'edit_service'){
    $id = $_REQUEST["id"];
    $field = $_REQUEST["field"];
    $value = $_REQUEST["value"];
    $busca = mysqli_query($conexion,"UPDATE home_services SET $field = '$value' WHERE id = '$id'");
    if($busca){
        $resultados['status'] = 'success';
    } else {
        $resultados['status'] = 'error';
    }
}

if($tipo == 'edit_service_img'){
    $id = $_REQUEST["id"];
    $title = $_REQUEST["title"];
    $ruta = "../../img/services/".$id."_".$title."_".$_FILES['file']['name'];
    if (($_FILES["file"]["type"] == "image/pjpeg") || ($_FILES["file"]["type"] == "image/jpeg") || ($_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/gif")) {
        $busco = mysqli_query($conexion,"SELECT img FROM home_services WHERE id = '$id'");
        if(mysqli_num_rows($busco) > 0){
            $archivo_ = mysqli_fetch_array($busco);
            $archivo =  "../../".$archivo_['img'];
            $archivo = explode("?",$archivo);
            $archivo = $archivo[0];
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $ruta)) {
            $ruta   = "img/services/".$id."_".$title."_".$_FILES['file']['name']."?".rand();
            $busca  = mysqli_query($conexion,"UPDATE home_services SET img = '$ruta' WHERE id = '$id'");
            $resultados['status'] = 'success';
            $resultados['ruta'] = $ruta;
        } else {
            $resultados['status'] = 'error1';
        }
    } else {
        $resultados['status'] = 'error2';
    }
}
```

### admin/js/home_edit.js
Agregar funciones para editar las nuevas secciones (al final del archivo):

```javascript
// Funciones para "Cómo Funciona"
let dataComoFunciona = [];

function loadComoFunciona(){
    $.post('ajax/home_edit.php', {tipo: 'get_como_funciona'}, function(res){
        dataComoFunciona = JSON.parse(res);
    });
}

function open_como_funciona(i, id){
    $('.btn-como-funciona').removeClass('active');
    $('#btn-como-'+i).addClass('active');
    
    let data = dataComoFunciona[i];
    let body = `
        <h2>Paso ${data.step_number}: ${data.title}</h2>
        <div class="form-group">
            <label>Número de Paso</label>
            <input type="number" class="form-control" value="${data.step_number}" 
                   onchange="editComoFunciona(${id}, 'step_number', this.value)">
        </div>
        <div class="form-group">
            <label>Clase del Icono (ej: fa fa-comments)</label>
            <input type="text" class="form-control" value="${data.icon_class}" 
                   onchange="editComoFunciona(${id}, 'icon_class', this.value)">
        </div>
        <div class="form-group">
            <label>Título</label>
            <input type="text" class="form-control" value="${data.title}" 
                   onchange="editComoFunciona(${id}, 'title', this.value)">
        </div>
        <div class="form-group">
            <label>Descripción</label>
            <textarea class="form-control" rows="3" 
                      onchange="editComoFunciona(${id}, 'description', this.value)">${data.description}</textarea>
        </div>
    `;
    $('.page-content-col').html(body);
}

function editComoFunciona(id, field, value){
    $.post('ajax/home_edit.php', {
        tipo: 'edit_como_funciona',
        id: id,
        field: field,
        value: value
    }, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            toastr.success('Actualizado correctamente');
            loadComoFunciona();
        } else {
            toastr.error('Error al actualizar');
        }
    });
}

// Funciones para "Servicios Detallados"
let dataServices = [];

function loadServices(){
    $.post('ajax/home_edit.php', {tipo: 'get_services'}, function(res){
        dataServices = JSON.parse(res);
    });
}

function open_service(i, id){
    $('.btn-service').removeClass('active');
    $('#btn-service-'+i).addClass('active');
    
    let data = dataServices[i];
    let body = `
        <h2>${data.title}</h2>
        <div class="form-group">
            <label>Orden</label>
            <input type="number" class="form-control" value="${data.orden}" 
                   onchange="editService(${id}, 'orden', this.value)">
        </div>
        <div class="form-group">
            <label>Clase del Icono (ej: fas fa-heartbeat)</label>
            <input type="text" class="form-control" value="${data.icon_class}" 
                   onchange="editService(${id}, 'icon_class', this.value)">
        </div>
        <div class="form-group">
            <label>Título</label>
            <input type="text" class="form-control" value="${data.title}" 
                   onchange="editService(${id}, 'title', this.value)">
        </div>
        <div class="form-group">
            <label>Descripción</label>
            <textarea class="form-control" rows="3" 
                      onchange="editService(${id}, 'description', this.value)">${data.description}</textarea>
        </div>
        <div class="form-group">
            <label>Badge (opcional)</label>
            <input type="text" class="form-control" value="${data.badge || ''}" 
                   onchange="editService(${id}, 'badge', this.value)">
        </div>
        <div class="form-group">
            <label>Clase del Badge (ej: bg-success)</label>
            <input type="text" class="form-control" value="${data.badge_class || ''}" 
                   onchange="editService(${id}, 'badge_class', this.value)">
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-white btn-block" onclick="editServiceImg(${id})">Cambiar Imagen</button>
        </div>
    `;
    $('.page-content-col').html(body);
}

function editService(id, field, value){
    $.post('ajax/home_edit.php', {
        tipo: 'edit_service',
        id: id,
        field: field,
        value: value
    }, function(res){
        let response = JSON.parse(res);
        if(response.status == 'success'){
            toastr.success('Actualizado correctamente');
            loadServices();
        } else {
            toastr.error('Error al actualizar');
        }
    });
}

function editServiceImg(id){
    // Similar a editImg() del carrusel
    let input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(){
        let file = this.files[0];
        let formData = new FormData();
        formData.append('file', file);
        formData.append('tipo', 'edit_service_img');
        formData.append('id', id);
        formData.append('title', 'service');
        
        $.ajax({
            url: 'ajax/home_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res){
                let response = JSON.parse(res);
                if(response.status == 'success'){
                    toastr.success('Imagen actualizada');
                    loadServices();
                } else {
                    toastr.error('Error al subir imagen');
                }
            }
        });
    };
    input.click();
}

// Llamar al cargar la página
$(document).ready(function(){
    loadComoFunciona();
    loadServices();
});
```

## Notas Importantes

1. Crear directorio `img/services/` para almacenar las imágenes de los servicios
2. Los estilos CSS ya existen en el home_edit.php actual, solo aplicarlos a las nuevas secciones con clases similares
3. El sistema de edición es similar al del carrucel, pero adaptado a estas nuevas estructuras

## Testing

1. Verificar que se cargan correctamente las secciones en el sidebar
2. Probar edición de textos
3. Probar subida de imágenes
4. Verificar que los cambios se reflejan en index.php

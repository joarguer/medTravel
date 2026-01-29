<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"] == ""){
    header("Location: include/salir.php?error=1");
    exit();
}

// Función helper para verificar si es admin (consistente con include.php)
function es_usuario_admin() {
    $es_admin = false;
    if (isset($_SESSION['ppal']) && intval($_SESSION['ppal']) === 1) {
        $es_admin = true;
    }
    if (isset($_SESSION['rol'])) {
        $rolval = (string) $_SESSION['rol'];
        if (intval($rolval) === 1) {
            $es_admin = true;
        } elseif (stripos($rolval, 'admin') !== false || stripos($rolval, 'administrador') !== false) {
            $es_admin = true;
        }
    }
    return $es_admin;
}

// Función helper para verificar si tiene rol 2 o superior
function tiene_rol_minimo_2() {
    if (es_usuario_admin()) {
        return true;
    }
    if (isset($_SESSION['rol'])) {
        $rolval = intval($_SESSION['rol']);
        if ($rolval === 2) {
            return true;
        }
    }
    return false;
}

// Mejor comparación basada en el nombre del script para evitar rutas absolutas inesperadas
$current = basename($_SERVER['PHP_SELF']);
$admin_only = array(
    'crear_usuario.php','create_bd.php',
    'home_edit.php','about_edit.php','services_edit.php','offer_detail_edit.php','blog_edit.php',
    'service_categories.php','service_catalog.php','providers.php','clientes.php','provider_verification.php'
);
$role2_allowed = array(
    'tipo_vehiculos.php','add_info_turistico.php','edit_info_turistico.php',
    'tipo_traslado.php','traslados.php','traslados_editar.php','add_programa.php',
    'add_excursion.php','edit_excursion.php','add_atractivos.php','edit_atractivos.php',
    'excursiones.php','edit_excursiones.php','add_extension.php','edit_extension.php',
    'add_atractivos_ext.php','edit_atractivos_ext.php','rangos.php','rango_traslados.php',
    'otros_ajustes.php','cantidad_pax.php','base.php','cotizaciones.php','cotizacion.php',
    'servicios_aprobados.php','orden_servicio.php','orden_servicio_detalle.php'
);

if (in_array($current, $admin_only) && !es_usuario_admin()) {
    header("Location: include/salir.php?error=1");
    exit();
}
if (in_array($current, $role2_allowed) && !tiene_rol_minimo_2()) {
    header("Location: include/salir.php?error=1");
    exit();
}
?>

<?php
return [
    // Clave que ConectarBot enviará en el header `X-ConectarBot-Key`. Cambiar en producción.
    'API_KEY' => getenv('CONECTARBOT_API_KEY') ?: 'CHANGE_ME',

    // Límite simple por IP: requests permitidas por minuto (usar null para desactivar).
    'RATE_LIMIT_PER_MIN' => 60,

    // Nombre de la fuente que se incluye en la meta-respuesta.
    'SOURCE' => 'medtravel',

    // Dominio público usado para convertir rutas relativas (imágenes, logos, fotos)
    // en URLs absolutas consumibles por ConectarBot. Sin trailing slash.
    'PUBLIC_BASE_URL' => getenv('CONECTARBOT_PUBLIC_BASE_URL') ?: 'https://medtravel.com.co',
];

<?php
return [
    // Clave que ConectarBot enviará en el header `X-ConectarBot-Key`. Cambiar en producción.
    'API_KEY' => getenv('CONECTARBOT_API_KEY') ?: 'CHANGE_ME',

    // Límite simple por IP: requests permitidas por minuto (usar null para desactivar).
    'RATE_LIMIT_PER_MIN' => 60,

    // Nombre de la fuente que se incluye en la meta-respuesta.
    'SOURCE' => 'medtravel',
];

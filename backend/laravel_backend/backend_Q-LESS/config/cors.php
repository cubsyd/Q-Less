<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Define qué rutas estarán sujetas a CORS. Normalmente se incluyen las
    | rutas de la API y las cookies de Sanctum.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Métodos HTTP permitidos. Puedes poner '*' para permitir todos,
    | o especificar ['GET', 'POST', 'PUT', 'DELETE'].
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Define qué dominios pueden hacer peticiones. '*' permite todos.
    | En producción conviene restringir a tu frontend (ej: http://localhost:3000).
    |
    */

    'allowed_origins' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Puedes usar patrones de regex para permitir orígenes dinámicos.
    |
    */

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Headers permitidos en las solicitudes. '*' permite todos.
    |
    */

    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers que estarán disponibles para el navegador.
    |
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | Tiempo en segundos que el navegador puede cachear la respuesta de preflight.
    |
    */

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Si tu API necesita enviar cookies o credenciales, ponlo en true.
    |
    */

    'supports_credentials' => false,

];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'api_gp' => [
        'url' => env('API_GP_URL'),
        'timeout' => env('API_GP_TIMEOUT', 15),
        'retry_attempts' => (int) env('LOCAL_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'company' => env('ID_COMPANIA'),
        'endpoints' => [
           
        ],
    ],
    'api_sales' => [
        'url' => env('API_SALES_URL'),
        'timeout' => env('API_GP_TIMEOUT', 150),
        'retry_attempts' => (int) env('LOCAL_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'endpoints' => [
            'obtener_clave_acceso' => 'api/obtener-clave-acceso',
            'anular_comprobante_gp' => 'api/anularfactura',
            'actualizar_estado_factura' => 'api/actualizar-estado-factura',
            'obtener_datos_factura' => 'api/obtener-datos-factura',

            //validar si tiene pago o no la factura para anular
            'validar_pago_factura' => 'api/validar-pago-factura',

        ],
    ],
    'api_seedbilling' => [
        'url' => env('API_SEED_BILLING_URL'),
        'timeout' => env('API_SEED_BILLING_TIMEOUT', 150),
        'token' => env('API_SEED_BILLING_TOKEN'),
        'idSuscriptor' => env('API_SEED_BILLING_ID_SUSCRIPTOR'),
        'retry_delay' => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'endpoints' => [
            'anular_comprobante' => 'api/anulacionComprobante',
        ]
    ]

];

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
            'validar_usuario'                       => 'api/validar-usuario',
            'consultar_transferencia_cabecera'      => 'api/consultar-cabecera-transferencia',
            'consultar_transferencia_detalle'       => 'api/consultar-detalle-transferencia',
            'consultar_sucursales_cliente'          => 'api/consultar-sucursales-clientes',
            'consultar_coordenadas'                 => 'api/consultar-listado-coordenadas',
            'consultar_stock_bodega'                => 'api/consultar-stock-bodega',
            'consultar_articulo_bodega_compartimento' => 'api/consultar-stock-articulo-bodega-compartimento',
        ],
    ],
    'api_seedbilling' => [
        'url' => env('API_SEED_BILLING_URL'),
        'timeout' => env('API_SEED_BILLING_TIMEOUT', 15),
        'token' => env('API_SEED_BILLING_TOKEN'),
        'idSuscriptor' => env('API_SEED_BILLING_ID_SUSCRIPTOR'),
        'retry_delay' => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'endpoints' => [
            'anular_comprobante' => 'api/anulacionComprobante',
        ]
    ]

];

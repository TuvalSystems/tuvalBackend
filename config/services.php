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
        'domain'    => env('MAILGUN_DOMAIN'),
        'secret'    => env('MAILGUN_SECRET'),
        'endpoint'  => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token'     => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'       => env('AWS_ACCESS_KEY_ID'),
        'secret'    => env('AWS_SECRET_ACCESS_KEY'),
        'region'    => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'api_gp' => [
        'url'           => env('API_GP_URL'),
        'timeout'       => env('API_GP_TIMEOUT', default: 30),
        'retry_attempts' => (int) env('LOCAL_API_RETRY_ATTEMPTS', 3),
        'retry_delay'   => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'company'       => env('ID_COMPANIA'),
        'endpoints'     => [],
    ],
    'api_sales' => [
        'url'       => env('API_SALES_URL'),
        'timeout'   => env('API_GP_TIMEOUT',  30),
        'retry_attempts' => (int) env('LOCAL_API_RETRY_ATTEMPTS', 3),
        'retry_delay'   => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'endpoints'     => [],
    ],
    'api_seedbilling' => [
        'url_cancel'    => env('API_SEED_BILLING_CANCEL_URL'),
        'url_consult'   => env('API_SEED_BILLING_CONSULT_URL'),
        'timeout'       => env('API_SEED_BILLING_TIMEOUT', 150),
        'token'         => env('API_SEED_BILLING_TOKEN'),
        'idSuscriptor'  => env('API_SEED_BILLING_ID_SUSCRIPTOR'),
        'retry_delay'   => (int) env('LOCAL_API_RETRY_DELAY', 100),
        'endpoints'     => []
    ],
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v25.0'),
        'order_ready_type' => env('WHATSAPP_ORDER_READY_TYPE', 'template'),
        'order_ready_template' => env('WHATSAPP_ORDER_READY_TEMPLATE', 'pedido_listo_retiro'),
        'order_ready_language' => env('WHATSAPP_ORDER_READY_LANGUAGE', 'es'),
        'order_ready_image_url' => env('WHATSAPP_ORDER_READY_IMAGE_URL', 'https://www.w3.org/People/mimasa/test/imgformat/img/w3c_home.jpg'),
        'order_ready_pdf_url' => env('WHATSAPP_ORDER_READY_PDF_URL', 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf'),
    ],

];

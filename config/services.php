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
            // 'validar_usuario' => 'api/validar-usuario',
            // 'consultar_transferencia_cabecera' => 'api/consultar-cabecera-transferencia',
            // 'consultar_transferencia_detalle' => 'api/consultar-detalle-transferencia',
            // 'consultar_sucursales_cliente' => 'api/consultar-sucursales-clientes',
            // 'consultar_coordenadas' => 'api/consultar-listado-coordenadas',
            // 'consultar_lista_precio_articulo' => 'api/consultar-lista-precio-articulo',

            // 'consultar_stock_bodega' => 'api/consultar-stock-bodega',
            // 'consultar_stock_bodega_async' => 'api/consultar-stock-bodega-lotes',
            // 'consultar_articulo_bodega_compartimento' => 'api/consultar-stock-articulo-bodega-compartimento',
            // 'transferencia_compartimento' => 'api/transferencia-compartimento',
            // 'consultar_item_compartimento' => 'api/devoluciones/consulta-item-compartimento-bin',

            //devoluciones - recibir devolucion
            // 'generar_nota_credito' => 'api/devoluciones/generar-nota-credito',
            // 'respuesta_nota_credito' => 'api/devoluciones/respuesta-nota-credito',
            // 'generar_devolucion_venta' => 'api/devoluciones/generar-devolucion-venta',
            // 'consultar_grupo_economico_cliente' => 'api/devoluciones/consultar-grupo-economico-cliente',

            //cobros
            // 'consultar_cupo_disponible_cliente' => 'api/cobros/consultar-cupo-disponible-cliente',
            // 'consulta_documentos_vencidos_cliente' => 'api/cobros/consultar-documentos-vencidos-cliente',
            // 'consulta_documentos_sin_soporte_cliente' => 'api/cobros/consultar-documentos-sin-soporte-cliente',
            // 'consulta_lista_documentos_vencidos_cliente' => 'api/cobros/consultar-lista-documentos-vencidos-cliente',
            // 'consulta_lista_documentos_sin_soporte_cliente' => 'api/cobros/consultar-lista-documentos-sin-soporte-cliente',


            //transacciones
            // 'validar_recibo_item_l' => 'api/transacciones/validar-generacion-documento-recibo-compra-l',
            // 'eliminar_recibo_item_l' => 'api/transacciones/eliminar-documento-recibo-compra-l',
            // 'guardar_recibo_item_l' => 'api/transacciones/guardar-documento-recibo-compra-l',
            // 'generar_recibo_item_l' => 'api/transacciones/generar-documento-recibo-compra-l',
            // 'respuesta_recibo_item_l' => 'api/transacciones/respuesta-documento-recibo-compra-l',

            //transacciones - facturacion
            // 'guarda_documento_factura_vta' => 'api/transacciones/guardar-documento-factura-vta',
            // 'respuesta_documento_factura_vta' => 'api/transacciones/respuesta-documento-factura-vta',
            // 'eliminar_documento_factura_vta' => 'api/transacciones/eliminar-documento-factura-vta',



            //cotizaciones
            'guardar_cotizacion_recordatorio' => 'api/cotizaciones/guardar-cotizacion-recordatorio',
            'lista_items_alternativos' => 'api/cotizaciones/lista-items-alternativos',




            //cliente
            // 'obtener_tipo_vendedor' => 'api/cliente/obtener-tipo-vendedor',
            // 'obtener_segmento_cliente' => 'api/cliente/obtener-segmento-cliente',
            // 'obtener_tipo_vendedor_detalle' => 'api/cliente/obtener-tipo-venededor-detalle',

            //guias de remision -general
            'guia.remision.crear' => 'api/inventario/crear-guia-remision',
            'guia.remision.existe' => 'api/inventario/validar-existencia-guia-remision',
            // guia de remision - despacho
            'guia.trfv.movimientos.generar' => 'api/inventario/guardar-generar-movimiento-transferencia-venta',
            'guia.reposicion.movimiento.generar' => 'api/inventario/guardar-generar-movimiento-reposicion',
            'guia.despacho.movimiento.generar' => 'api/inventario/guardar-generar-movimiento-despacho',
            'guia.reserva.movimiento.generar' => 'api/inventario/reservas/guardar-generar-movimiento/transferencia',
            'guia.obtener.clave_acceso' => 'api/inventario/guia-remision-obtener-claveacceso',

            //reservas
            'obtener_notificaciones_local' => 'api/reservas/obtener-notificaciones-local',

            //apis para validar existencia de guias de remision
            'guia.despacho.check.ajuste' => 'api/inventario/validar-existencia-guia-despacho',
            'guia.transferencia.check.ajuste' => 'api/inventario/validar-existencia-guia-transferencia',
            'guia.reposicion.check.ajuste' => 'api/inventario/validar-existencia-guia-reposicion',
            'guia.reserva.check.ajuste' => 'api/inventario/reservas/validar-existencia-guia',

            //api para valdiar existencia de guia de remision de reserva - recibidas
            'guia.reserva.recibido.check.ajuste' => 'api/inventario/reservas/validar-existencia-ajuste-recibir',

            //recibir guias de remision
            'guia.reserva.recibir.items' => 'api/inventario/reservas/guardar-generar-movimiento/recibir',
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

<?php

namespace App\Services\Api\Endpoints;

class ApiSalesEndpoints
{

    // ---
    // ⬅️ 1. Endpoints de anulacion de facturas
    // ---

    public const RETURN_CLAVE_ACCESO = 'api/facturacion/obtener-clave-acceso';
    public const RETURN_ANULAR_COMPROBANTE_GP = 'api/facturacion/anularfactura';
    public const RETURN_ACTUALIZAR_ESTADO_FACTURA = 'api/facturacion/actualizar-estado-factura';
    public const RETURN_OBTENER_DATOS_FACTURA = 'api/facturacion/obtener-datos-factura';
    public const RETURN_VALIDAR_PAGO_FACTURA = 'api/facturacion/validar-pago-factura';

}

<?php

namespace App\Services\Integraciones\Api\Endpoints;

class ApiSalesEndpoints
{

    // ---
    // ⬅️ 1. Endpoints de anulacion de facturas
    // ---

    public const RETURN_CLAVE_ACCESO = 'api/anulacion/obtener-clave-acceso';
    public const RETURN_ANULAR_COMPROBANTE_GP = 'api/anulacion/anularfactura';
    public const RETURN_ACTUALIZAR_ESTADO_FACTURA = 'api/anulacion/actualizar-estado-factura';
    public const RETURN_OBTENER_DATOS_FACTURA = 'api/anulacion/obtener-datos-factura';
    public const RETURN_VALIDAR_PAGO_FACTURA = 'api/anulacion/validar-pago-factura';



    /** nuevos proceso de anulaciones */


    public const ANULACION_CLAVE_ACCESO = 'api/anulacion/clave-acceso';
    public const ANULACION_PROCESO_GP = 'api/anulacion/proceso-anulacion';
    public const ANULACION_ACTUALIZAR_FACTURA = 'api/anulacion/actualizar-factura';
    public const ANULACION_INFORMACION_FACTURA = 'api/anulacion/informacion-factura';
    public const ANULACION_VALIDAR_PAGO = 'api/anulacion/validar-pago';


}

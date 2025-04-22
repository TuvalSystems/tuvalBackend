<?php

namespace App\Services\Devoluciones;

use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\WebserviceController;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Traits\Devoluciones\DevulucionesCriteriosTrait;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class   DevolucionService implements DevolucionServiceInterface
{
    use DevulucionesCriteriosTrait, JsonResponseTrait;

    protected WebserviceController $webservice;
    protected PedidosController $pedidosController;

    public function __construct(WebserviceController $webservice, PedidosController $pedidosController)
    {
        $this->webservice = $webservice;
        $this->pedidosController = $pedidosController;
    }

    public function processDevolucion($devolucion)
    {

        try {
            DB::beginTransaction();
            $criterio = $this->getCriterioDevolucion($devolucion);
            if (!$criterio) {
                DB::rollBack();
                return $this->buildErrorResponse('Criterio de devolución no encontrado', 404);
            }

            if (!empty($criterio['metodo']) && method_exists($this, $criterio['metodo'])) {
                $this->{$criterio['metodo']}($devolucion);
            }

            if ($criterio['generarnotadecredito'] === true) {
                $ntCredito =  $this->generarNotaDeCredito($devolucion);
                if (!$ntCredito->isSuccessful()) {
                    DB::rollBack();
                    return $ntCredito;
                }

                if ($criterio['volverafacturar'] === true) {
                    $this->pedidosController->volverafacturar($devolucion);
                }
                if ($criterio['gpOrdenDespacho'] === true) {
                    $this->updateStatusOrdenDespacho($devolucion->pedido_id);
                }
                if ($criterio['generadevolucionventa'] === true) {
                    $this->generarDevolucionVenta($devolucion);
                }
            }
            DB::commit();
            return $this->buildSuccessResponse('Devolución procesada correctamente',  200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error al procesar devolución ID {$devolucion->id}: {$e->getMessage()}");

            return $this->buildErrorResponse(
                'Error inesperado al procesar devolución: ' . $e->getMessage(),
                500
            );
        }
    }


    public function getCriterioDevolucion($devolucion)
    {
        foreach ($this->listReturnCriteria() as $criterio) {
            if (
                $criterio['estado'] == $devolucion->estado &&
                $this->coincidirTipoDevolucion($criterio['tipo_devolucion'], $devolucion->tipo_devolucion)
                && $criterio['despachado'] == $devolucion->despachado
                && $criterio['volver_a_facturar'] == $devolucion->volver_a_facturar
                && $criterio['devolucion_bodega'] == $devolucion->devolucion_bodega
            ) {
                return $criterio;
            }
        }
        return null;
    }


    public function coincidirTipoDevolucion($criterioTipo, $tipoDevolucion)
    {
        if ($criterioTipo === null) {
            return in_array($tipoDevolucion, ['parcial', 'total']);
        }
        return $criterioTipo === $tipoDevolucion;
    }


    public function procesardevoluciontotalsinrf($devolucion)
    {
        $this->actualizarDetalleDevolucion($devolucion, 'seleccionado');
    }

    public function procesardevolucionparcialconrf($devolucion)
    {
        $this->actualizarDetalleDevolucion($devolucion, 'disponible');
    }

    public function actualizarDetalleDevolucion($devolucion, String $origen)
    {
        if (!in_array($origen, ['seleccionado', 'disponible'])) {
            Log::warning("Intento de uso de campo inválido: $origen");
            return;
        }

        $afectados = DB::table('gp_devoluciones_detalles')
            ->where('devolucion_id', $devolucion->id)
            ->update([
                'devuelto' => DB::raw("`$origen`")
            ]);
        Log::info("Se actualizaron {$afectados} registros en 'devuelto' usando columna '$origen' para devolución ID {$devolucion->id}");
        return $afectados;
    }



    public function generarNotaDeCredito($devolucion)
    {
        $pedido = GpPedido::find($devolucion->pedido_id);
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $docId = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $pedido->PTO_VTA_ID)
            ->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'NC%')->first();
        $cliente = GpCliente::find($devolucion->cliente_id);
        $sucursal = GpClienteSucursal::find($devolucion->sucursal_facturacion_id);

        $subtotal = 0;
        $iva = 0;
        $linea = 0;
        $detalleDevolucion = [];

        foreach ($detalles as $key => $detalle) {
            if ($detalle->devuelto ==  0) continue;

            $linea++;
            $cantidad = $detalle->devuelto;
            $valorUnitario = $detalle->ART_VALOR;
            $descuento =  $detalle->ART_DESCUENTO;
            $totalLinea = round(($valorUnitario - $descuento) * $cantidad, 2);

            $detalleDevolucion[] = [
                'NC_VTA_DET_LINEA' => $linea,
                'NC_VTA_DET_DOCU_ORIGEN' => $devolucion->devolucion,
                'NC_VTA_DET_ID_TIPO' => '4',
                'NC_VTA_DET_ARTICULO' => $detalle->ART_CODIGO,
                'NC_VTA_DET_DESC_ARTICULO' => $detalle->ART_DESCRIPCION,
                'NC_VTA_DET_UNIDAD_MEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                'NC_VTA_DET_BODEGA' =>  $pedido->BOD_BODEGA,
                'NC_VTA_DET_CANTIDAD' => $cantidad,
                'NC_VTA_DET_COSTO_UNITARIO' => $valorUnitario,
                'NC_VTA_DET_PRECIO_UNITARIO' => $valorUnitario,
                'NC_VTA_DET_DESCUENTO' => $descuento,
                'NC_VTA_DET_IMPUESTO_IVA' => $detalle->ART_IVA,
                'NC_VTA_DET_TOTAL' => $totalLinea
            ];
            $subtotal +=  $totalLinea;
            $iva += $detalle->ART_IVA;
        }
        $total = $subtotal + $iva;

        // return $detalles;
        $cabecera = [[
            'NC_VTA_DOCU_ORIGEN' => $devolucion->devolucion,
            'NC_VTA_ID_TIPO' => '4',
            'NC_VTA_PUNTO_VENTA' => $pedido->PTO_VTA_ID,
            'NC_VTA_DOC_ID' => $docId->PTO_VTA_ID_FACTURA_NC,
            'NC_VTA_ID_CLIENTE' => $cliente->CLI_RUC,
            'NC_VTA_ID_VENDEDOR' => $pedido->VEND_ID,
            'NC_VTA_ID_DIRECCION' => $sucursal->CLI_SUC_ID_DIRECCION,
            'NC_VTA_COND_PAGO' => $pedido->COND_ID,
            'NC_VTA_NOTA_DOCU' => $devolucion->factura,
            'NC_VTA_FECHA' => now()->format('Y-m-d'),
            'NC_VTA_BODEGA' => $pedido->BOD_BODEGA,
            'NC_VTA_SUBTOTAL' => $subtotal,
            'NC_VTA_DESCUENTO_COMERCIAL' => 0,
            'NC_VTA_IMPUESTO' => $iva,
            'NC_VTA_TOTAL' =>  $total,
        ]];

        $parameters = [
            'Cabecera' => $cabecera,
            'Detalle' => $detalleDevolucion,
        ];
          //aqui, migrar el api de msw a java con respuestas JSON
        $response = $this->webservice->transaccional('MST_NC_VTA', $parameters);
        if (!isset($response['MST_NC_VTAResult'])) {
            return $this->buildErrorResponse($response['MST_NC_VTAResult'], 500);
        }
        $consultaParams = [
            'documento_origen' => $devolucion->devolucion,
            'tipo_documento' => 4,
        ];

        $respuesta =  $this->webservice->transaccional('MST_Respuesta_Nota_Credito_Vta', $consultaParams);
        $respuestaNC = $respuesta['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0] ?? [];
        return $respuesta;
        if (!empty($respuestaNC['DOC_GENERADO'])) {
            $devolucion->DOC_GENERADO = $respuestaNC['DOC_GENERADO'];
            $devolucion->estado = 'procesado';
            $devolucion->fecha_NC = now()->format('Y-m-d');

            $this->bajarDevolucionAPedido($devolucion, $detalles, $pedido);
            $resultado = $this->buildSuccessResponse('Nota de crédito generada correctamente', 200);
        } else {
            $devolucion->NC_VTA_MENSAJE = ($respuestaNC['NC_VTA_MENSAJE'] ?? '') . ' - ' . ($respuestaNC['NC_VTA_CODIGO'] ?? '');
            $resultado = $this->buildErrorResponse('Error al generar la nota de crédito: ' . $devolucion->NC_VTA_MENSAJE, 500);
        }

        $devolucion->save();
        $this->totalDevoluciones($devolucion, $detalles);

        return $resultado;
    }


    public function bajarDevolucionAPedido($devolucion, $detalles, $pedido)
    {
        if ($devolucion->rebajado) {
            return;
        }
        $pedidoId = $pedido->id;
        $bodega = $pedido->BOD_BODEGA;
        $actualizarStock = !$devolucion->despachado;
        foreach ($detalles as $key => $value) {
            $articulo = GpPedidoDetalle::find($value->detalle_id);

            if (!$articulo || $articulo->pedido_id !== $pedidoId) {
                continue;
            }
            $articulo->devuelto += $value->devuelto;
            $articulo->save();
            if ($actualizarStock) {
                $stock = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                    ->where('STO_ART_BODEGA', $bodega)
                    ->first();

                if ($stock) {
                    $stock->reservado -= $value->devuelto;
                    $stock->save();
                }
            }
        }
        $devolucion->rebajado = true;
        $devolucion->save();
        return $devolucion;
    }

    public function totalDevoluciones($devolucion, $detalles)
    {
        $subtotal = 0.0;
        $descuento = 0.0;
        $subtotalMenosDescuento = 0.0;
        $ivaTotal = 0.0;

        $tipoImpuesto = [];
        $sumaImpuesto = [];
        foreach ($detalles  as $key => $detalle) {
            if ($detalle->devuelto == 0) continue;

            $cantidad = (float) $detalle->devuelto;
            $valorUnitario = (float) $detalle->ART_VALOR;
            $descuentoUnitario = (float) $detalle->ART_DESCUENTO;
            $porcentajeImpuesto = (float) $detalle->ART_IMP_VTA;

            $valorTotal = round($valorUnitario * $cantidad, 2);
            $descuentoTotal = round($descuentoUnitario * $cantidad, 2);
            $valorNeto = round(($valorUnitario - $descuentoTotal) * $cantidad,     2);
            $iva = round($valorNeto * ($porcentajeImpuesto / 100), 2);

            $tipoImpuesto[$porcentajeImpuesto] = ($tipoImpuesto[$porcentajeImpuesto] ?? 0) + $valorTotal;
            $sumaImpuesto[$porcentajeImpuesto] = ($sumaImpuesto[$porcentajeImpuesto] ?? 0) + $iva;

            $descuento += $descuentoTotal;
            $subtotalMenosDescuento += $valorNeto;
            $ivaTotal += $iva;
        }

        foreach ($tipoImpuesto as $valor) {
            $subtotal += $valor;
        }

        $devolucion->subtotal = round($subtotal, 2);
        $devolucion->descuento = round($descuento, 2);
        $devolucion->subtotalmenosdescuento = round($subtotalMenosDescuento, 2);
        $devolucion->iva = round($ivaTotal, 2);
        $devolucion->total = round($subtotalMenosDescuento + $ivaTotal, 2);
        $devolucion->save();
    }


    public function updateStatusOrdenDespacho($pedidoId)
    {
        GpOrdenDespacho::update([
            'estado' => 'cancelada',
        ]);
    }

    public function generarDevolucionVenta($devolucion)
    {
        $userId = Auth::user()->id ?? 'adminWeb';

        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $pedido = GpPedido::find($devolucion->pedido_id);

        $itemsDevolucion = [];
        foreach ($detalles as $detalle) {
            if ($detalle->ART_TIPO_COMPRA == 'SERV') {
                continue;
            }

            $devuelto = $devolucion->despachado && $devolucion->volver_a_facturar ? $detalle->recibido : $detalle->devuelto;
            if (!$devuelto) {
                continue;
            }

            $compartimiento = $detalle->ART_TIPO_COMPRA === 'L' ? $pedido->pedido : $detalle->compartimiento;
            $itemsDevolucion[] = [
                'Item' => $detalle->ART_CODIGO,
                'Cantidad' => $devuelto,
                'Compartimento' => $compartimiento
            ];
        }
        if (!empty($itemsDevolucion)) {
            $parms = [
                'IDCompania' => env('ID_COMPANIA'),
                'Documento' => $devolucion->devolucion,
                'PedidoOrigen' => $pedido->pedido,
                'Bodega' => $pedido->BOD_BODEGA,
                'Observacion' => $devolucion->DOC_GENERADO,
                'Usuario' => $userId,
                'Items_Devolucion' => $itemsDevolucion,
            ];
            $respuesta =  $this->webservice->inventario('GeneraDevolucionVenta', $parms);
            $retorno = $respuesta['GeneraDevolucionVentaResult']['Retorno'][0] ?? [];
            if (!empty($retorno['Codigo']) && $retorno['Codigo'] == 0) {
                $devolucion->devolucion_bodega = true;
            } else {
                $devolucion->AJ_VTA_MENSAJE = $retorno['Mensaje'] ?? 'Error desconocido';
            }
        } else {
            $devolucion->devolucion_bodega = true;
        }

        $devolucion->save();
    }
}

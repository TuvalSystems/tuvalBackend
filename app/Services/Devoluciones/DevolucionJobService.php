<?php

namespace App\Services\Devoluciones;

use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\WebserviceController;
use App\Models\devoluciones\GpDevolucionErroresJob;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Services\Api\ApiGpService;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevolucionJobService
{
    use DevolucionesCriteriosTrait, JsonResponseTrait;
    protected ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService;

    public function __construct(ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService)
    {
        $this->apiGpReturnsIntegrationService = $apiGpReturnsIntegrationService;
    }

    public function listarTodasLasDevolucionesAplicables()
    {
        $criterios = $this->listReturnCriteria();
        $devoluciones = collect();

        foreach ($criterios as $criterio) {
            $items = $this->listaDevolucionPorCriterio($criterio);
            $devoluciones = $devoluciones->merge($items);
        }

        return $devoluciones->unique('id'); // Evitar devoluciones repetidas si caen en más de un criterio
    }

    public function listaDevolucionPorCriterio($criterio)
    {
        $query = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_devoluciones.pedido_id')
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->where('gp_devoluciones.estado', $criterio['estado'])
            ->where('gp_devoluciones.tipo_devolucion', $criterio['tipo_devolucion'])
            ->where('gp_devoluciones.volver_a_facturar', $criterio['volver_a_facturar']);

        if (!is_null($criterio['despachado'])) {
            $query->where(function ($q) use ($criterio) {
                if ($criterio['despachado'] == 0) {
                    $q->where('gp_devoluciones.despachado', 0)
                        ->orWhereNull('gp_devoluciones.despachado');
                } elseif ($criterio['despachado'] == 1) {
                    $q->where('gp_devoluciones.despachado', 1);
                }
            });
        }

        //validacion de estado solicitado
        if ($criterio['estado'] == 'solicitado' && $criterio['tipo_devolucion'] == 'total') {
            $query->join('gp_devoluciones_detalles as dvd', 'dvd.devolucion_id', '=', 'gp_devoluciones.id')
                ->where('dvd.ART_TIPO_COMPRA', 'L')
                // esto me valida que solo procese las devoluciones que tengan devoluciones anteriores en estado parcial
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('gp_devoluciones as dp')
                        ->where('dp.pedido_id', '=', DB::raw('gp_devoluciones.pedido_id'))
                        ->where('dp.tipo_devolucion', 'parcial');
                });
        }

        // validacion de estado recibido
        if ($criterio['estado'] == 'recibido') {
            $query->join('gp_devoluciones_detalles as dvd', 'dvd.devolucion_id', '=', 'gp_devoluciones.id')
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        // ✅ Caso 1: Despachado Parcial = 1
                        $subQ->where('dvd.despachado_parcial',  1)
                            ->orWhereNotNull('dvd.compartimiento')
                            ->where('dvd.recibido', '>', 0)
                            ->where('dvd.devuelto', '>', 0);
                    });
                });
        }

        //validacione en estado aprobado
        if ($criterio['estado'] === 'aprobado') {
            $query->join('gp_devoluciones_detalles as dvd', 'dvd.devolucion_id', '=', 'gp_devoluciones.id')
                ->where(function ($q) {
                    $q->where('dvd.devuelto', '>', 0)
                        ->orWhereNull('dvd.devuelto');
                })
                ->where('dvd.recibido', '>', 0)
                ->whereNotNull('dvd.compartimiento');
        }

        if (array_key_exists('devolucion_bodega', $criterio)) {
            $query->when(is_null($criterio['devolucion_bodega']), function ($q) {
                $q->whereNull('gp_devoluciones.devolucion_bodega');
            }, function ($q) use ($criterio) {
                $q->where('gp_devoluciones.devolucion_bodega', $criterio['devolucion_bodega']);
            });
        }
        return $query->get();
    }


    public function procesarDevolucionJob($devolucion)
    {
        $criterios = $this->listReturnCriteria();
        $apiGp = app(ApiGpService::class);
        $order = app(OrderServiceInterface::class);
        $pedidosController = new PedidosController($apiGp, $order);

        try {

            $criterioAplicable = $this->encontrarCriterioAplicable($devolucion, $criterios);


            if ($criterioAplicable) {
                $this->ejecutarProcesosDevolucion($devolucion, $criterioAplicable, $pedidosController);
            } else {
                GpDevolucionErroresJob::create([
                    'devolucion_id' => $devolucion->id,
                    'criterio_id' => $criterioAplicable['id'] ?? null,
                    'proceso' => 'criterio',
                    'mensaje' => 'No se encontró un criterio aplicable para la devolución.',
                ]);
            }
        } catch (\Throwable $e) {
            GpDevolucionErroresJob::create([
                'devolucion_id' => $devolucion->id,
                'criterio_id' => $criterioAplicable['id'] ?? null,
                'proceso' => 'procesamiento',
                'mensaje' => $e->getMessage(),
            ]);
            Log::error('Error procesando devolución ID: ' . $devolucion->id . ' - ' . $e->getMessage());
        }
    }


    private function encontrarCriterioAplicable($devolucion, $criterios)
    {
        foreach ($criterios as $criterio) {
            if (
                $devolucion->estado == $criterio['estado'] &&
                $devolucion->volver_a_facturar == $criterio['volver_a_facturar'] &&
                $devolucion->tipo_devolucion == $criterio['tipo_devolucion'] &&
                ($criterio['despachado'] === null || $devolucion->despachado == $criterio['despachado']) &&
                ($criterio['devolucion_bodega'] === null ||  $devolucion->devolucion_bodega == $criterio['devolucion_bodega'])
            ) {
                return $criterio;
            }
        }

        return null; // Si no encuentra un criterio que cumpla
    }


    private function ejecutarProcesosDevolucion($devolucion, $criterio, PedidosController $pedidosController)
    {
        // Procesar método si está definido
        if (!empty($criterio['metodo']) && method_exists($this, $criterio['metodo'])) {
            $this->{$criterio['metodo']}($devolucion);
        }

        // Generar Devolución de Venta si corresponde
        if (!empty($criterio['generadevolucionventa']) && $criterio['generadevolucionventa'] === true) {
            $this->generarDevolucionVenta($devolucion);
        }

        // Generar Nota de Crédito si corresponde
        if ($criterio['generarnotadecredito'] === true) {
            $ntCredito =  $this->generarNotaDeCredito($devolucion);
            $respArray = $ntCredito->getData(true);
            if ($respArray['success'] === false) {
                throw new \Exception('Error generando nota de crédito: ' . ($respArray['error'] ?? 'Error desconocido'));
            }
        }

        // Volver a Facturar si corresponde
        if (!empty($criterio['volverafacturar']) && $criterio['volverafacturar'] === true) {
            $pedidosController->volverafacturar($devolucion);
        }



        // Cancelar Orden de Despacho si corresponde
        if (!empty($criterio['gpOrdenDespacho']) && $criterio['gpOrdenDespacho'] === true) {
            $this->updateStatusOrdenDespacho($devolucion->pedido_id);
        }
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
            'cabecera' => $cabecera,
            'detalle' => $detalleDevolucion,
        ];
        $responseNtc = $this->apiGpReturnsIntegrationService->generateCreditNote($parameters);
        if ($responseNtc['success'] === false) {
            return $this->buildErrorResponse($responseNtc['message'], 404);
        }

        $consultaParams = [
            'document' => $devolucion->devolucion,
            'tipo' => 4,
        ];
        $responseCreditNote = $this->apiGpReturnsIntegrationService->responseCreditNote($consultaParams);

        if (!empty(trim($responseCreditNote['respuesta'][0]['documentoGenerado']))) {
            $devolucion->DOC_GENERADO = $responseCreditNote['respuesta'][0]['documentoGenerado'];
            $devolucion->estado = 'procesado';
            $devolucion->fecha_NC = now()->format('Y-m-d');
            $this->bajarDevolucionAPedido($devolucion, $detalles, $pedido);
            $resultado = $this->buildSuccessResponse('Nota de crédito generada correctamente', 200);
        } else {
            $devolucion->NC_VTA_MENSAJE = ($responseCreditNote['respuesta'][0]['mensaje'] ?? '') . ' - ' . ($responseCreditNote['respuesta'][0]['codigo'] ?? '');
            $resultado = $this->buildErrorResponse('Error al generar la nota de crédito: ' . $devolucion->NC_VTA_MENSAJE, 500);
        }

        $devolucion->save();
        $this->totalDevoluciones($devolucion, $detalles);
        return $resultado ?? $this->buildErrorResponse('Error ha ocurrido mientras se generaba la nota de credito', 500);
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
        GpOrdenDespacho::where('pedido_id', $pedidoId)
            ->update(['estado' => 'cancelada']);
    }

    public function generarDevolucionVenta($devolucion)
    {
        $userId = 'adminWeb';

        // Obtener el pedido y los detalles
        $pedido = GpPedido::find($devolucion->pedido_id);
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)
            ->where('ART_TIPO_COMPRA', '!=', 'SERV') // Filtramos los servicios directamente en la consulta
            ->get();

        // 🛠️ Preparación de los items para la devolución
        $itemsDevolucion = $detalles->map(function ($detalle) use ($devolucion, $pedido) {
            $devuelto = ($devolucion->despachado && $devolucion->volver_a_facturar)
                ? $detalle->recibido
                : $detalle->devuelto;

            if (!$devuelto) {
                return null; // Si no hay devuelto, se ignora el item
            }

            $compartimiento = $detalle->ART_TIPO_COMPRA === 'L' ? $pedido->pedido : $detalle->compartimiento;

            return [
                'Item' => $detalle->ART_CODIGO,
                'Cantidad' => $devuelto,
                'Compartimento' => $compartimiento
            ];
        })->filter()->values()->toArray(); // Removemos los nulos y reindexamos el array

        // 🚫 Early return si no hay items
        if (empty($itemsDevolucion)) {
            $devolucion->devolucion_bodega = true;
            $devolucion->save();
            return;
        }

        // 🔄 Parámetros para la integración
        $parms = [
            'IDCompania' => config('services.api_gp.company'),
            'Documento' => $devolucion->devolucion,
            'PedidoOrigen' => $pedido->pedido,
            'Bodega' => $pedido->BOD_BODEGA,
            'Observacion' => $devolucion->DOC_GENERADO,
            'Usuario' => $userId  ?? 'adminWeb',
            'Items_Devolucion' => $itemsDevolucion,
        ];

        // 🚀 Llamada al servicio
        $res1 = $this->apiGpReturnsIntegrationService->generateSalesReturn($parms);

        // ✅ Validación de la respuesta
        $codigo = Arr::get($res1, '0.codigo', 99); // Si no existe 'codigo', se asume error 99
        $success = Arr::get($res1, '0.success', false);
        $mensaje = Arr::get($res1, '0.mensaje', 'Error desconocido');

        if (!$success) {
            Log::error("Error en procesar la devolución: Código $devolucion->id - $devolucion->devolucion - $codigo - $mensaje - " . json_encode($res1));
            $devolucion->AJ_VTA_MENSAJE = "Error en la devolucion: $codigo - $mensaje";
            $devolucion->devolucion_bodega = false;
            $devolucion->save();
            throw new \Exception('Error en la al realizar el ajuste: ' . ($codigo ?? 'Código desconocido') . ' - ' . ($mensaje ?? 'Error desconocido'));
        }
        Log::info("El ajuste de la devolucion se ha procesado correctamente: Código $devolucion->id - $devolucion->devolucion - $codigo - $mensaje - " . json_encode($res1));
        // 🟢 Actualización correcta
        $devolucion->devolucion_bodega = true;
        $devolucion->save();
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
            ->where('despachado_parcial', 0)
            ->update([
                'devuelto' => DB::raw("`$origen`")
            ]);
        Log::info("Se actualizaron {$afectados} registros en 'devuelto' usando columna '$origen' para devolución ID {$devolucion->id}");
        return $afectados;
    }
}

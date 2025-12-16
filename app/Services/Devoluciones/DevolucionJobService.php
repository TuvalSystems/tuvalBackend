<?php

namespace App\Services\Devoluciones;

use App\Http\Controllers\ventas\PedidosController;
use App\Models\devoluciones\GpDevolucionDocument;
use App\Models\devoluciones\GpDevolucionErroresJob;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpIngreso;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Services\Api\ApiGpService;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevolucionJobService
{
    use DevolucionesCriteriosTrait, JsonResponseTrait;
    protected ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService;
    protected $devolucionBuilder;
    public function __construct(ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService, DevolucionParamsBuilderInterface $devolucionBuilder)
    {
        $this->apiGpReturnsIntegrationService = $apiGpReturnsIntegrationService;
        $this->devolucionBuilder = $devolucionBuilder;
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
        // Trabaja con un Scope para aplicar los criterios de búsqueda
        return GpDevoluciones::conCriterios($criterio)->get();

    }


    public function procesarDevolucionJob($devolucion)
    {
        $criterios = $this->listReturnCriteria();
        $apiGp = app(ApiGpService::class);
        $order = app(OrderServiceInterface::class);
        $aproval = app(ApprovalServiceInterface::class);
        $invoice = app(InvoiceServiceInterface::class);
        $function = app(FunctionsServiceInterface::class);
        $pedidosController = new PedidosController($apiGp, $order, $aproval, $invoice, $function);

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
            Log::channel('devolucion')->error('Error procesando devolución ID: ' . $devolucion->id . ' - ' . $e->getMessage());
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
                ($criterio['devolucion_bodega'] === null || $devolucion->devolucion_bodega == $criterio['devolucion_bodega'])
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
            $ntCredito = $this->generarNotaDeCredito($devolucion);
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
            if ($detalle->devuelto == 0)
                continue;

            $linea++;
            $cantidad = $detalle->devuelto;
            $valorUnitario = $detalle->ART_VALOR;
            $descuento = $detalle->ART_DESCUENTO;
            $totalLinea = round(($valorUnitario - $descuento) * $cantidad, 2);

            $detalleDevolucion[] = [
                'NC_VTA_DET_LINEA' => $linea,
                'NC_VTA_DET_DOCU_ORIGEN' => $devolucion->devolucion,
                'NC_VTA_DET_ID_TIPO' => '4',
                'NC_VTA_DET_ARTICULO' => $detalle->ART_CODIGO,
                'NC_VTA_DET_DESC_ARTICULO' => $detalle->ART_DESCRIPCION,
                'NC_VTA_DET_UNIDAD_MEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                'NC_VTA_DET_BODEGA' => $pedido->BOD_BODEGA,
                'NC_VTA_DET_CANTIDAD' => $cantidad,
                'NC_VTA_DET_COSTO_UNITARIO' => $valorUnitario,
                'NC_VTA_DET_PRECIO_UNITARIO' => $valorUnitario,
                'NC_VTA_DET_DESCUENTO' => $descuento,
                'NC_VTA_DET_IMPUESTO_IVA' => $detalle->ART_IVA,
                'NC_VTA_DET_TOTAL' => $totalLinea
            ];
            $subtotal += $totalLinea;
            $iva += $detalle->ART_IVA;
        }
        $total = $subtotal + $iva;

        // return $detalles;
        $cabecera = [
            [
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
                'NC_VTA_TOTAL' => $total,
            ]
        ];

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
        $totalDiscount = 0.0;
        $netSubtotal = 0.0;
        $totalIva = 0.0;
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', $detalles->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');

        foreach ($detalles as $key => $detalle) {
            if ($detalle->devuelto == 0)
                continue;

            $cantidad = (float) $detalle->devuelto;
            $valorUnitario = (float) $detalle->ART_VALOR;
            $descuentoUnitario = (float) $detalle->ART_DESCUENTO;
            $impuestoPorcentaje = (float) $articulosImpuesto[$detalle->ART_CODIGO] ?? 0;



            $lineNet = round(($valorUnitario - $descuentoUnitario) * $cantidad, 2);
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineaSubtotal = $lineNet + $lineaDiscount;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);

            $subtotal += $lineaSubtotal;
            $totalDiscount += $lineaDiscount;
            $netSubtotal += $lineNet;
            $totalIva += $lineaIva;
        }

        $devolucion->subtotal = round($subtotal, 2);
        $devolucion->descuento = round($totalDiscount, 2);
        $devolucion->subtotalmenosdescuento = round($netSubtotal, 2);
        $devolucion->iva = round($totalIva, 2);
        $devolucion->total = round($netSubtotal + $totalIva, 2);
        $devolucion->save();
    }


    public function updateStatusOrdenDespacho($pedidoId)
    {
        GpOrdenDespacho::where('pedido_id', $pedidoId)
            ->update(['estado' => 'cancelada']);
    }

    public function generarDevolucionVenta($devolucion)
    {
        $userIngreso = $this->getUserIngreso($devolucion);
        $userId = filled($userIngreso) ? trim($userIngreso) : 'adminWeb';

        // Obtener el pedido y los detalles
        $pedido = GpPedido::find($devolucion->pedido_id);
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)
            ->where('ART_TIPO_COMPRA', '!=', 'SERV') // Filtramos los servicios directamente en la consulta
            ->where('despachado_parcial', 1)
            ->where(function ($query) {
                $query->where('devuelto', '>', 0)
                    ->whereNotNull('devuelto')
                    ->where('recibido', '>', 0)
                    ->whereNotNull('recibido');
            })
            ->get();

        // 🛠️ Preparación de los items para la devolución
        $itemsDevolucion = $detalles->map(function ($detalle) use ($devolucion, $pedido) {
            // Obtenemos el valor devuelto del detalle
            $devuelto = ($devolucion->despachado && $devolucion->volver_a_facturar) ? $detalle->recibido : $detalle->devuelto;
            if (!$devuelto) {
                return null; // Si no hay devuelto, se ignora el item
            }

            $compartimiento = $detalle->ART_TIPO_COMPRA === 'L' ? $pedido->pedido : $detalle->compartimiento;

            return [
                'item' => $detalle->ART_CODIGO,
                'cantidad' => $devuelto,
                'compartimiento' => $compartimiento
            ];
        })->filter()->values()->toArray(); // Removemos los nulos y reindexamos el array

        // 🚫 Early return si no hay items
        if (empty($itemsDevolucion)) {
            $devolucion->devolucion_bodega = true;
            $devolucion->save();
            return;
        }

        // 🔄 Parámetros para la integración
        $params = $this->devolucionBuilder->builderParamsDevolutionAdjustment($devolucion, $pedido, $userId, $itemsDevolucion);

        // 🚀 Llamada al servicio
        $res1 = $this->apiGpReturnsIntegrationService->generateSalesReturn($params);
        if ($res1['success'] === false) {
            Log::error("Error al realizar el Ajuste de la devolucion ", [
                'res1' => $res1
            ]);
            $devolucion->AJ_VTA_MENSAJE = $res1['message'];
            $devolucion->devolucion_bodega = false;
            $devolucion->save();
            throw new \Exception('Ajuste : mensaje -  ' . ($res1['message'] ?? 'Error desconocido'));
        }
        Log::channel('devolucion')->info("Se realizo el Ajuste de la devolucion {$devolucion->id} con el documento {$res1['data']['docGp']}");
        $this->createDocumentDevolution($devolucion, $res1['data'], $userId);
        // 🟢 Actualización correcta
        $devolucion->devolucion_bodega = true;
        $devolucion->save();
    }

    public function getUserIngreso(GpDevoluciones $devolucion): ?string
    {
        return GpIngreso::where('tipo', 'devolucion')->where('documento', $devolucion->devolucion)->value('USERID');
    }

    public function createDocumentDevolution($devolucion, $responseApi, $userId)
    {
        $devDoc = new GpDevolucionDocument();
        $devDoc->devolucion_id = $devolucion->id;
        $devDoc->devolucion = $devolucion->devolucion;
        $devDoc->id_movimiento = $responseApi['idMovimiento'];
        $devDoc->documento_gp = $responseApi['docGp'];
        $devDoc->save();
    }


    public function procesardevoluciontotalsinrf($devolucion)
    {
        $origen = 'seleccionado'; // Por defecto, usamos 'seleccionado'
        $afectados = DB::table('gp_devoluciones_detalles')
            ->where('devolucion_id', $devolucion->id)
            // ->where('despachado_parcial', 0)
            ->update([
                'devuelto' => DB::raw("`$origen`")
            ]);
        Log::channel('devolucion')->info("Se actualizaron {$afectados} registros en 'devuelto' usando columna '$origen' para devolución ID {$devolucion->id}");
        return $afectados;
    }

    public function procesardevolucionparcialconrf($devolucion)
    {
        $origen = 'disponible'; // Por defecto, usamos 'seleccionado'
        $afectados = DB::table('gp_devoluciones_detalles')
            ->where('devolucion_id', $devolucion->id)
            //  ->where('despachado_parcial', 0)
            ->update([
                'devuelto' => DB::raw("`$origen`")
            ]);
        Log::channel('devolucion')->info("Se actualizaron {$afectados} registros en 'devuelto' usando columna '$origen' para devolución ID {$devolucion->id}");
        return $afectados;
    }

    public function procesarDevolucionesRecibirParcialesSinRf($devolucion)
    {
        $origen = 'seleccionado'; // Por defecto, usamos 'seleccionado'
        $afectados = DB::table('gp_devoluciones_detalles')
            ->where('devolucion_id', $devolucion->id)
            ->where('despachado_parcial', 0)
            ->update([
                'devuelto' => DB::raw("`$origen`")
            ]);
        Log::channel('devolucion')->info("Se actualizaron {$afectados} registros en 'devuelto' usando columna '$origen' para devolución ID {$devolucion->id} en devoluciones estado recibido");
        return $afectados;
    }



}

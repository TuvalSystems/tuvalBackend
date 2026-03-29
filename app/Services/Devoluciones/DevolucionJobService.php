<?php

namespace App\Services\Devoluciones;

use App\Enums\devoluciones\DevolutionStatus;
use App\Models\GpPedido;
use App\Models\GpIngreso;
use App\Models\GpCliente;
use App\Models\GpArticulo;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Traits\JsonResponseTrait;
use App\Models\GpClienteSucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\GpPedidoDetalleStock;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\devoluciones\GpDevolucionDocument;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpDevolucionErroresJob;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Devoluciones\Contracts\ReInvoiceServiceInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use Carbon\Carbon;
use Exception;

class DevolucionJobService
{
    use DevolucionesCriteriosTrait, JsonResponseTrait;

    public function __construct(
        protected readonly ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService,
        protected readonly DevolucionParamsBuilderInterface $devolucionBuilder,
        protected readonly ReInvoiceServiceInterface $reInvoice
    ) {}

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


    public function procesarDevolucionJob(GpDevoluciones $devolucion)
    {
        $criterios = $this->listReturnCriteria();

        try {
            $criterioAplicable = $this->encontrarCriterioAplicable($devolucion, $criterios);
            if ($criterioAplicable) {
                $this->ejecutarProcesosDevolucion($devolucion, $criterioAplicable);
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


    private function encontrarCriterioAplicable(GpDevoluciones $devolucion, $criterios)
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


    private function ejecutarProcesosDevolucion(GpDevoluciones $devolucion, $criterio)
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
            $this->generarNotaDeCredito($devolucion);
        }

        // Volver a Facturar si corresponde
        if (!empty($criterio['volverafacturar']) && $criterio['volverafacturar'] === true) {
            $this->reInvoice->execute($devolucion);
        }



        // Cancelar Orden de Despacho si corresponde
        if (!empty($criterio['gpOrdenDespacho']) && $criterio['gpOrdenDespacho'] === true) {
            $this->updateStatusOrdenDespacho($devolucion->pedido_id);
        }
    }



    public function generarNotaDeCredito(GpDevoluciones $devolucion)
    {
        $pedido             = GpPedido::find($devolucion->pedido_id);
        $detalles           = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $docId              = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $pedido->PTO_VTA_ID)->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'NC%')->first();
        $cliente            = GpCliente::find($devolucion->cliente_id);
        $sucursal           = GpClienteSucursal::find($devolucion->sucursal_facturacion_id);
        $articulosImpuesto  = GpArticulo::whereIn('ART_CODIGO', $detalles->pluck('ART_CODIGO'))
            ->pluck('ART_IMP_VTA', 'ART_CODIGO');
        $subtotal = 0.0;
        $iva = 0.0;
        $detalleDevolucion = [];

        foreach ($detalles as $key => $detalle) {
            if ($detalle->devuelto == 0) {
                continue;
            }

            $valorUnitario = $detalle->ART_VALOR;
            $cantidad = $detalle->devuelto;
            $descuento = $detalle->ART_DESCUENTO;
            $porcentajeIva = (float) ($articulosImpuesto[$detalle->ART_CODIGO] ?? 0);


            $lineNet = round((($valorUnitario - $descuento) * $cantidad) + 0.000001, 2);
            $ivaLineaRaw = $lineNet * ($porcentajeIva / 100);
            $ivaLineaParaApi = round($ivaLineaRaw, 2);

            $detalleDevolucion[] = [
                'NC_VTA_DET_LINEA' => $key + 1,
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
                'NC_VTA_DET_IMPUESTO_IVA' => $ivaLineaParaApi,
                'NC_VTA_DET_TOTAL' => $lineNet
            ];
            $subtotal += $lineNet;
            $iva += $ivaLineaRaw;
        }
        $totalIvaFinal = round($iva, 2);
        $total = round($subtotal + $totalIvaFinal, 2);

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
                'NC_VTA_SUBTOTAL' => round($subtotal, 2),
                'NC_VTA_DESCUENTO_COMERCIAL' => 0,
                'NC_VTA_IMPUESTO' => $totalIvaFinal,
                'NC_VTA_TOTAL' => $total,
            ]
        ];

        $parameters = [
            'cabecera' => $cabecera,
            'detalle' => $detalleDevolucion,
        ];
        $responseNtc = $this->apiGpReturnsIntegrationService->generateCreditNote($parameters);
        if ($responseNtc['success'] === false) {
            throw new Exception($responseNtc['message']);
        }

        $consultaParams = [
            'document' => $devolucion->devolucion,
            'tipo' => 4,
        ];
        $respNc             = $this->getApiResponseNoteCredit($consultaParams);
        $successNc          = data_get($respNc, 'success', false);
        $docGenerado        = data_get($respNc, 'data.docGenerado', null);
        $responseCreditNote = data_get($respNc, 'data', []);
        $fechaNc            = data_get($responseCreditNote, 'fecha', now()->format('Y-m-d'));
        if (!$successNc || empty($docGenerado)) {
            $msgError       = data_get($respNc, 'message', 'Error desconocido al consultar la nota de crédito.');
            $devolucion->NC_VTA_MENSAJE = $msgError;
            $devolucion->save();
            throw new Exception($msgError);
        }
        $devolucion->NC_VTA_MENSAJE = 'Nota de crédito generada correctamente.';
        $devolucion->fecha_NC       = Carbon::parse($fechaNc)->format('Y-m-d');
        $devolucion->estado         = DevolutionStatus::PROCESADO;
        $devolucion->DOC_GENERADO   = $docGenerado;
        $devolucion->save();
        $this->bajarDevolucionAPedido($devolucion, $detalles, $pedido);
        $this->totalDevoluciones($devolucion, $detalles);
    }


    public function getApiResponseNoteCredit($params)
    {
        return $this->apiGpReturnsIntegrationService->retrieveCreditNoteResponse($params);
    }


    public function bajarDevolucionAPedido(GpDevoluciones $devolucion, $detalles, GpPedido $pedido)
    {
        if ($devolucion->rebajado) {
            return;
        }
        $bodega             = $pedido->BOD_BODEGA;
        $detalleIds         = collect($detalles)->pluck('detalle_id')->all();
        $articulos          = GpPedidoDetalle::whereIn('id', $detalleIds)->where('pedido_id', $pedido->id)->get()->keyBy('id');
        foreach ($detalles as $value) {
            $art = $articulos->get($value->detalle_id);
            if (!$art) {
                continue;
            }
            $art->devuelto += $value->devuelto;
            $art->save();
            if (!$value->despachado_parcial) {
                $stock = GpPedidoDetalleStock::where('detalle_id', $art->id)
                    ->where('STO_ART_BODEGA', $bodega)
                    ->first();

                if ($stock && $stock->reservado > 0) {
                    $stock->reservado = max(0, $stock->reservado - $value->devuelto);
                    $stock->save();
                }
            }
        }
        $devolucion->rebajado = true;
        $devolucion->save();
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



            $lineNet = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);
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

    public function generarDevolucionVenta(GpDevoluciones $devolucion)
    {
        $userIngreso = $this->getUserIngreso($devolucion);
        $userId = filled($userIngreso) ? trim($userIngreso) : 'adminWeb';

        $pedido = GpPedido::find($devolucion->pedido_id);
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)
            ->articulosFisicos()
            ->validosParaNotaCredito()
            ->get();

        $itemsDevolucion = $detalles->map(function ($detalle) use ($devolucion, $pedido) {
            $cantidad = ($devolucion->despachado && $devolucion->volver_a_facturar) ? $detalle->recibido : $detalle->devuelto;
            if (!$cantidad)
                return null;

            $compartimiento = $detalle->ART_TIPO_COMPRA === 'L' ? $pedido->pedido : $detalle->compartimiento;
            return [
                'item' => $detalle->ART_CODIGO,
                'cantidad' => $cantidad,
                'compartimiento' => $compartimiento
            ];
        })->filter()->values()->toArray();


        if (empty($itemsDevolucion)) {
            $devolucion->devolucion_bodega = true;
            $devolucion->save();
            Log::channel('devolucion')->info("Devolución {$devolucion->id}: Sin items físicos para ajuste de stock.");
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
            throw new Exception('Ajuste : mensaje -  ' . ($res1['message'] ?? 'Error desconocido'));
        }
        Log::channel('devolucion')->info("Se realizo el Ajuste de la devolucion {$devolucion->id} con el documento {$res1['data']['docGp']}");
        $this->createDocumentDevolution($devolucion, $res1['data']);
        // 🟢 Actualización correcta
        $devolucion->devolucion_bodega = true;
        $devolucion->save();
    }

    public function getUserIngreso(GpDevoluciones $devolucion): ?string
    {
        return GpIngreso::where('tipo', 'devolucion')->where('documento', $devolucion->devolucion)->value('USERID');
    }

    public function createDocumentDevolution($devolucion, $responseApi)
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

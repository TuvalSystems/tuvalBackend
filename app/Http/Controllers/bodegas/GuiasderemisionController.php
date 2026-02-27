<?php

namespace App\Http\Controllers\bodegas;

use RuntimeException;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpUsuario;
use App\Models\GpPuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\GpOrdenDespacho;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GpPedidoDetalle;
use App\Enums\Ventas\OrderStatus;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\GpPedidoDetalleStock;
use App\Models\despachos\GpGuiaRemision;
use App\Models\traslados\GpTrasladosVentas;
use App\Notifications\User\UserNotification;
use App\Http\Controllers\WebserviceController;
use App\Models\reservas\GpGuiaRemisionReserva;
use Illuminate\Validation\ValidationException;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteReplenishmentTransferServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteSalesTransferServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;

class GuiasderemisionController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly DeliveryNoteServiceInterface $deliveryNoteService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly ReservationDeliveryNoteServiceInterface $reservedDeliveryNoteService,
        protected readonly DeliveryNoteDispatchServiceInterface $dispatch,
        protected readonly EntryServiceInterface $entryService,
        protected readonly DeliveryNoteSalesTransferServiceInterface $salesTransfer,
        protected readonly DeliveryNoteReplenishmentTransferServiceInterface $replenishment
    ) {}

    public function consultarGuiaGeneradaV1(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $response = $this->deliveryNoteService->consultExistenceGeneratedGuides($data);
            if ($response !== '') {
                return $this->errorResponse($response, [], 409);
            }
            return $this->buildSuccessResponse('Validación exitosa. No existen conflictos con otras guías.', 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function checkguiaremision(): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->deliveryNoteService->mapAssigmentNumberGuideRemision();
            DB::commit();
            return $this->buildSuccessResponse('Guias de remision actualizadas', 200);
        } catch (\Throwable $e) {
            Log::error('Error en la funcion de checkGuiaRemision', [
                'error' => $e->getMessage(),
            ]);
            DB::rollBack();
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimirguia($id): Response
    {

        $guia = GpGuiaRemision::where('id', $id)->first();

        if ($guia->tipo == 'despacho') {
            $pedido = GpPedido::where('pedido', $guia->pedido)->first();
            $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->first();

            if (!$orden) {
                $pedido = GpPedido::where('cotizacion_id', $pedido->cotizacion_id)->first();
                $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->first();
            }

            $guia->orden = $orden->orden;
            $guia->factura = $pedido->factura;
            $guia->orden_de_compra = $pedido->orden_de_compra;
        }


        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();
        $peso = 0;
        foreach ($detalle as $key => $det) {
            if ($guia->tipo == 'despacho') {
                $articulo = GpPedidoDetalle::where('id', $det->detalle_id)->first();
            } elseif ($guia->tipo == 'reposicion') {
                $articulo = GpTrasladosReposicion::where('id', $det->detalle_id)->first();
            } elseif ($guia->tipo == 'transferencia') {
                $articulo = GpTrasladosVentas::where('id', $det->detalle_id)->first();
            }
            if ($articulo) {
                if ($articulo->ART_TIPO_COMPRA == 'SERV') {
                    unset($detalle[$key]);
                }
                $peso += (float) number_format($articulo->ART_PESO * $det->GUI_REM_CANTIDAD, 2, '.', '');
            }
            $detTemp = GpPedidoDetalle::where('id', $det->detalle_id)->value('CHECK_ALTERNATIVO');

            $det['CHECK_ALTERNATIVO'] = $detTemp ?? 0;
        }

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $guia->GUI_REM_ESTABLECIMIENTO)->first()->toarray();

        $data = array();



        $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)->get()->toArray();

        $arr_trans = array();
        foreach ($transferencias as $trans) {
            $arr_trans[$trans['pedido']] = $trans['pedido'];
        }

        $data['peso'] = number_format($peso, 2, '.', '');
        $data['guia'] = $guia;
        $data['detalle'] = $detalle;
        $data['transferencias'] = $arr_trans;
        $data['PTO_VTA_DESCRIPCION'] = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $data['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
        //return view('guiaRemisionPDF', $data);
        $pdf = PDF::loadView('guiaRemisionPDF', $data);
        return $pdf->stream('guia_remision.pdf');
    }

    public function imprimirordenalmacenamiento(Request $request): Response
    {
        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');
        //demonios endemoniados
        $data = $request->all();
        $pdf = PDF::loadView('ordenAlmacenamientoPDF', $data);
        return $pdf->stream('orden_almacenamiento_' . $data['GUI_REM_GUIA_REMISION'] . '.pdf');
    }


    public function imprimirordenalmacenamientodevolucion(Request $request): Response
    {
        $data = $request->all();
        $pdf = PDF::loadView('ordenAlmacenamientoDevolucionPDF', $data);
        return $pdf->stream('orden_almacenamiento_' . $data['devolucion']['devolucion'] . '.pdf');
    }

    public function eliminarguia($id): Response|Application|ResponseFactory
    {
        $guia = GpGuiaRemision::where('id', $id)->first();

        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();

        if ($guia->tipo == 'transferencia') {
            foreach ($detalle as $item) {
                $traslado = GpTrasladosVentas::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        if ($guia->tipo == 'reposicion') {
            foreach ($detalle as $item) {
                $traslado = GpTrasladosReposicion::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        if ($guia->tipo == 'despacho') {
            foreach ($detalle as $item) {
                $traslado = GpPedidoDetalle::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        $guia->estado = 'eliminada';
        $guia->save();

        return response([
            'success' => true,
            'message' => 'Guía de remisión eliminada',
        ]);
    }


    public function despacharguia(Request $request, $id)
    {
        $user = $request->user();
        try {
            // return DB::transaction(function () use ($user, $id) {
            $guia = GpGuiaRemision::whereKey($id)->lockForUpdate()->firstOrFail();

            // Re-chequea después del lock (otro proceso pudo enviarla)
            if ($guia->estado === 'enviada') {
                return $this->buildSuccessResponse('Guía de remisión despachada por otro usuario', 200);
            }
            $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();
            $response = null;

            switch ($guia->tipo) {
                case 'transferencia':
                    $response = $this->deliveryNoteService->dispatchGuideTransferShipment($guia, $detalle, $user);
                    break;
                case 'despacho':
                    $response = $this->deliveryNoteService->dispatchGuideDispatchShipment($guia, $detalle, $user);

                    break;
                case 'reposicion':
                    $response = $this->deliveryNoteService->dispatchGuideReplacementShipment($guia, $detalle, $user);
                    break;
                default:
                    throw new \Exception("Tipo de guía no reconocido: {$guia->tipo}");
            }
            return $response;
            // });
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error en despachar guia de remision (despacharguia)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'guia_id' => $id
            ]);

            return $this->buildErrorResponse(
                $e->getMessage(),
                500
            );
        }
    }



    public function recibirguia(Request $request)
    {
        $data = $request->all();
        $user = $request->user();
        if ($data['tipo'] == 'transferencia') {
            return $this->recibirguiatransferencia($data, $user);
        }
        if ($data['tipo'] == 'reposicion') {
            return $this->recibirguiareposicion($data,  $user);
        }
        return;
    }

    public function recibirguiareposicion($data, GpUsuario $user): Response|Application|ResponseFactory
    {
        // ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');

        $documentos = array();

        foreach ($data['detalle'] as $item) {
            if ($item['recibido'] > 0) {
                $traslado = GpTrasladosReposicion::where('id', $item['detalle_id'])
                    ->first();
                $item['traslado'] = $traslado;

                $transferencia = GpGuiaRemisionTransferencia::where('pedido', $traslado->Documento)
                    ->where('guia_remision_id', $data['id'])
                    ->first();

                $documentos[$traslado->Documento]['Documento'] = $traslado->Documento;
                $documentos[$traslado->Documento]['transferencia'] = $transferencia->codigo;
                $documentos[$traslado->Documento]['items'][] = $item;
            }
        }

        $documentos = array_values($documentos);
        $webservices = new WebserviceController();

        foreach ($documentos as $transferencia) {
            $item_arr = array();
            foreach ($transferencia['items'] as $key => $item) {
                if ($item['recibido'] > 0) {
                    $item_arr[$key]['Item'] = $item['GUI_REM_CODI_INTERNO'];
                    $item_arr[$key]['Compartimento'] = $item['compartimiento'];
                    $item_arr[$key]['Cantidad'] = $item['recibido'];
                }
            }
            if (count($item_arr)) {
                $item_arr = array_values($item_arr);

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Documento' => $transferencia['Documento'],
                    'NumeroTransferOri' => $transferencia['transferencia'],
                    'BodegaOrigen' => $data['bodega_origen'],
                    'BodegaDestino' => $data['bodega_destino'],
                    'Observacion' => $data['GUI_REM_OBSERVACION'],
                    'Usuario' => $user->USERID,
                    'Items' => $item_arr
                );

                $respuesta = $webservices->inventario('GeneraRecepcionTransferenciaReposicon', $parameters);
                if (isset($respuesta['GeneraRecepcionTransferenciaReposiconResult']['Retorno'][0]['Codigo']) && $respuesta['GeneraRecepcionTransferenciaReposiconResult']['Retorno'][0]['Codigo'] == '0') {

                    foreach ($transferencia['items'] as $item) {
                        if ($item['recibido'] > 0) {
                            $recibido = GpGuiaRemisionDetalle::where('id', $item['id'])->first();
                            $recibido->recibido += $item['recibido'];
                            $recibido->save();
                        }
                    }
                } else {
                    return response([
                        'success' => false,
                        'message' => $respuesta['GeneraRecepcionTransferenciaReposiconResult']['Retorno'][0]['Mensaje'],
                    ]);
                }
            }
        }

        $guia = GpGuiaRemision::where('id', $data['id'])->first();
        $listo = $this->checkguiacompleta($guia);

        // $ingresos = new IngresosController();
        // $ingresos->ingreso('traslado_reposicion', $data, $data['detalle']);
        $this->entryService->registerEntity('traslado_reposicion', $data, $data['detalle'], $user);


        if ($listo) {
            $guia->estado = 'recibida';
            $guia->save();

            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida completamente',
            ]);
        } else {
            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida parcialmente',
            ]);
        }
    }

    public function checkguiacompleta($guia): bool
    {
        $listo = true;
        $recibidos = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();

        foreach ($recibidos as $recibido) {
            if ($recibido->recibido < $recibido->GUI_REM_CANTIDAD) {
                $listo = false;
            }
        }

        return $listo;
    }

    public function recibirguiatransferencia($data, GpUsuario $user): Response|Application|ResponseFactory
    {

        $documentos = array();
        foreach ($data['detalle'] as $item) {
            if ($item['recibido'] > 0) {
                $traslado = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
                    ->where('gp_traslados_ventas.id', $item['detalle_id'])
                    ->select('gp_traslados_ventas.*', 'gp_pedidos.pedido')
                    ->first();
                $item['traslado'] = $traslado;

                $transferencia = GpGuiaRemisionTransferencia::where('pedido', $traslado->pedido)
                    ->where('guia_remision_id', $data['id'])
                    ->first();

                $documentos[$traslado->pedido]['pedido'] = $traslado->pedido;
                $documentos[$traslado->pedido]['transferencia'] = '';
                if (isset($transferencia->codigo))
                    $documentos[$traslado->pedido]['transferencia'] = $transferencia->codigo;
                $documentos[$traslado->pedido]['items'][] = $item;
            }
        }

        $documentos = array_values($documentos);

        //        echo '<pre>';
        //        print_r($documentos);
        //        die();

        $webservices = new WebserviceController();

        foreach ($documentos as $transferencia) {
            $item_arr = array();
            foreach ($transferencia['items'] as $key => $item) {



                if ($item['recibido'] > 0) {
                    $item_arr[$key]['Item'] = $item['GUI_REM_CODI_INTERNO'];
                    $item_arr[$key]['Compartimento'] = '';
                    $item_arr[$key]['Cantidad'] = $item['recibido'];
                }
            }

            if (count($item_arr)) {
                $item_arr = array_values($item_arr);

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Pedido' => $transferencia['pedido'],
                    'NumeroTransferOri' => $transferencia['transferencia'],
                    'BodegaOrigen' => $data['bodega_origen'],
                    'BodegaDestino' => $data['bodega_destino'],
                    'Observacion' => $data['GUI_REM_OBSERVACION'],
                    'Usuario' => $user->USERID,
                    'Items' => $item_arr
                );


                $respuesta = $webservices->inventario('GeneraRecepcionTransferenciaVenta', $parameters);



                if (
                    isset($respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Codigo'])
                    && ($respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Codigo'] == '0'
                        // || $respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Codigo'] == '1377'
                    )
                ) {
                    foreach ($transferencia['items'] as $item) {

                        if ($item['recibido'] > 0) {
                            $stock = GpPedidoDetalleStock::where('detalle_id', $item['traslado']->detalle_id)
                                ->where('STO_ART_BODEGA', $item['traslado']->bodega_origen)
                                ->first();
                            $stock->seleccionado = $stock->seleccionado - $item['recibido'];
                            $stock->save();

                            $stock2 = GpPedidoDetalleStock::where('detalle_id', $item['traslado']->detalle_id)
                                ->where('STO_ART_BODEGA', $item['traslado']->bodega_destino)
                                ->first();
                            $stock2->seleccionado = $stock2->seleccionado + $item['recibido'];
                            $stock2->reservado = $stock2->reservado + $item['recibido'];
                            $stock2->save();

                            $recibido = GpGuiaRemisionDetalle::where('id', $item['id'])->first();
                            $recibido->recibido += $item['recibido'];
                            $recibido->save();
                        }
                    }
                } else {
                    return response([
                        'success' => false,
                        'message' => $respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Mensaje'],
                    ]);
                }
            }
        }

        $guia = GpGuiaRemision::where('id', $data['id'])->first();
        $listo = $this->checkguiacompleta($guia);

        // $ingresos = new IngresosController();
        // $ingresos->ingreso('traslado_venta', $data, $data['detalle']);
        $this->entryService->registerEntity('traslado_venta', $data, $data['detalle'], $user);

        if ($listo) {
            $guia = GpGuiaRemision::where('id', $data['id'])->first();
            $guia->estado = 'recibida';
            $guia->save();

            foreach ($documentos as $transferencia) {
                $pedido = GpPedido::where('pedido', $transferencia['pedido'])
                    ->where('estado', 'traslado')
                    ->first();
                if ($pedido) {

                    $cliente = GpCliente::where('id', $pedido->cliente_id)->first();
                    if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
                        $pedido->factura_posterior_despacho = 1;
                        if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                            $pedido->factura_a_demanda = 1;
                        } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                            $pedido->factura_siguiente_mes = 1;
                        }
                        $pedido->save();
                    }
                    $creatorUser = GpUsuario::where('id', $pedido->user_id)->first();
                    $this->invoiceService->checkSendInvoice($pedido, true);

                    $creatorUser->notify(new UserNotification('Articulo/s recibidos', 'Articulo/s del pedido ' . $pedido->pedido . ' han sido recibidos COMPLETAMENTE en la bodega ' . $pedido->BOD_BODEGA));
                }
                //aqui la notifiacion

            }

            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida completamente',
            ]);
        } else {
            foreach ($documentos as $transferencia) {
                $pedido = GpPedido::where('pedido', $transferencia['pedido'])
                    ->where('estado', 'traslado')
                    ->first();

                if ($pedido) {
                    $this->invoiceService->checkTrasladado($pedido);

                    if ($pedido->estado == OrderStatus::ESPERA) {
                        $creatorUser = GpUsuario::where('id', $pedido->user_id)->first();
                        $this->invoiceService->checkSendInvoice($pedido, true);
                        $creatorUser->notify(new UserNotification('Articulo/s recibidos', 'Artículo/s del pedido ' . $pedido->pedido . ' han sido recibidos PARCIALMENTE en la bodega ' . $pedido->BOD_BODEGA));
                    }
                }
            }

            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida parcialmente',
            ]);
        }
    }



    public function consultarDetalleGuiaPorId(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            return $this->successResponse('Detalle de guia obtenido', $this->deliveryNoteService->getDeliveryNoteDetailByType($data['guia']), 200);
        } catch (\Throwable $e) {
            Log::error('Error al consultar detalle de guia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine()
            ]);
            return $this->buildErrorResponse('Error al consultar detalle de guia', 500);
        }
    }


    public function consultarDetalleGuiaReservaPorId(Request $request): JsonResponse
    {
        try {
            $id = $request->get('id');
            return $this->successResponse('Detalle de guia obtenido', $this->reservedDeliveryNoteService->getDetailReservationDeliveryNoteById($id), 200);
        } catch (\Throwable $e) {
            Log::error('Error al consultar detalle de guia reserva', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine()
            ]);
            return $this->buildErrorResponse('Error al consultar detalle de guia reserva', 500);
        }
    }


    public function verificarYAsignarAjusteGuiaDespacho($id): JsonResponse
    {
        try {
            $guia = $this->deliveryNoteService->reassignDispatchGuideAdjustment($id);
            $msg = "Ajuste Reasignado para la guia {$guia->id} del pedido {$guia->pedido}";
            return $this->buildSuccessResponse($msg, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }



    public function creacionAjusteGpGuiaRemision($id): JsonResponse
    {
        try {
            $guia = $this->deliveryNoteService->createAdjustmentDeliveryNote($id);
            return $this->buildSuccessResponse('Ajuste de guia creado exitosamente', 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }



    public function obtenerInfoGUiaRemisionAjustePedido($id): JsonResponse
    {
        try {
            $data = $this->deliveryNoteService->getDeliveryNoteAdjustmentInfoByDeliveryNoteId($id);
            return $this->successResponse('Información obtenida', $data, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function procesarPedidoDespachoPorGuia($id): JsonResponse
    {
        try {
            $pedido = $this->deliveryNoteService->processAjusteWevOrderGUideRemision($id);
            return $this->successResponse('Información obtenida', $pedido, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function listaGuiaDespachoConAjuste(Request $request): JsonResponse
    {
        try {
            $fechaInicio = $request->get('fechaInicio');
            $fechaFin = $request->get('fechaFin');
            $pedido = $this->deliveryNoteService->getDeliveryNoteDispatchWithAjustment($fechaInicio, $fechaFin);
            return $this->successResponse('Información obtenida', $pedido, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function listaGuiaTransferenciaReposicionConAjuste(Request $request): JsonResponse
    {
        try {
            $fechaInicio = $request->get('fechaInicio');
            $fechaFin = $request->get('fechaFin');
            $estado = $request->get('estado');
            $pedido = $this->deliveryNoteService->getDeliveryNoteTransferAndRepositionAjustment($fechaInicio, $fechaFin, $estado);
            return $this->successResponse('Información obtenida', $pedido, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    /** proceso para reajuste de guia de transferencias  */
    public function procesarReajusteGuiaTransferencia(Request $request): JsonResponse
    {
        try {
            $id = $request->get('guiaId');
            $pedido = $request->get('pedido');
            $guia = $this->deliveryNoteService->processTransferGuideAdjustment($id, $pedido);
            return $this->successResponse('Reajuste procesado exitosamente para la guia de transferencia', $guia, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    /** proceso para reajuste de guia de reposiciones */

    public function procesarReajusteGuiaReposicion(Request $request): JsonResponse
    {
        try {
            $id = $request->get('guiaId');
            $documento = $request->get('documento');
            $guia = $this->deliveryNoteService->processRepositionGuideAdjustment($id, $documento);
            return $this->successResponse('Reajuste procesado exitosamente para la guia de reposición', $guia, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    /***
     *
     *
     *
     *
     * proceso de generacion de guia de remision de reservas
     *
     *
     *
     *
     * */


    public function crearGuiaRemisionReserva(Request $request): JsonResponse
    {
        $payload = $request->all();
        try {
            $response = $this->reservedDeliveryNoteService->createDeliveryNoteFromReservation($payload, $request->user());
            return $this->successResponse('Guia de reserva creada exitosamente', $response, 200);
        } catch (RuntimeException $r) {
            return $this->buildErrorResponse($r->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion generarGuiaReserva -GuiasderemisionController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function enviarGuiaRemisionReservaAGp($id): JsonResponse
    {
        try {
            $guia = GpGuiaRemisionReserva::where('id', $id)->first();
            $response = $this->reservedDeliveryNoteService->sendReservationDeliveryNoteToGp($guia);
            return $this->successResponse('Guia de reserva creada exitosamente', $response, 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion enviarGuiaRemisionReservaAGp -GuiasderemisionController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function generarPdfGuiaRemisionReserva(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:gp_guia_remision_reservas,id',
            ]);
            $data = $this->reservedDeliveryNoteService->generatePdfReservedDeliveryNote($validated);
            return $this->buildPdfResponse($data);
        } catch (ValidationException $ve) {
            return $this->validationErrorResponse($ve->errors(), 'validation error', 422);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion generarPdfGuiaRemisionReserva -GuiasderemisionController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function despacharGuiaRemisionReserva(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:gp_guia_remision_reservas,id',
            ]);
            $response = $this->reservedDeliveryNoteService->dispatchGuideReservation($validated, $request->user());
            return $this->successResponse('Guia de reserva Despachada', $response, 200);
        } catch (RuntimeException $r) {
            return $this->buildErrorResponse($r->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion enviarGuiaRemisionReservaAGp -GuiasderemisionController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    /**
     *
     *  PROCESO DE CREACION DE GUIA DE DESPACHO
     */

    public function crearGuiaRemisionDespacho(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $response   = $this->dispatch->createDeliveryNoteFromDispatch($payload, $request->user());
            return $this->successResponse('Guia de Despacho creada exitosamente', $response, 200);
        }, 'despachos', 'crearGuiaRemisionDespacho -GuiasderemisionController');
    }


    /***
     * PROCESO DE CREACION DE GUIA DE VENTA
     */

    public function crearGuiaRemisionVenta(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->salesTransfer->createDeliveryNoteSalesTransfer($payload, $user);
            return $this->successResponse('Guia de Transferencia de Venta creada exitosamente', $response, 200);
        }, 'guia_ventas', 'crearGuiaRemisionVenta -GuiasderemisionController');
    }

    /** generar (recibir guias de transferencia de venta) ingreso de guia de transferencia de venta */
    public function recibirGuiaRemisionVenta(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->salesTransfer->receiveDeliveryNoteSalesTransfer($payload, $user);
            return $this->buildSuccessResponse($response, 200);
        }, 'guia_ventas', 'recibirGuiaRemisionVenta -GuiasderemisionController');
    }

    /**
     * PROCESO DE CREACION DE GUIA DE REPOSICION
     */

    public function crearGuiaRemisionReposicion(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->replenishment->createDeliveryNoteReplenishmentTransfer($payload, $user);
            return $this->successResponse('Guia de Reposicion creada exitosamente', $response, 200);
        }, 'guia_ventas', 'crearGuiaRemisionReposicion -GuiasderemisionController');
    }

    public function recibirGuiaRemisionReposicion(Request $request)
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->replenishment->receiveDeliveryNoteReplenishmentTransfer($payload, $user);
            return $this->successResponse('Guia de Reposicion recibida exitosamente', $response, 200);
            // return $this->buildSuccessResponse($response, 200);
        }, 'guia_ventas', 'recibirGuiaRemisionReposicion');
    }
}

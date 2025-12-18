<?php

namespace App\Services\Devoluciones;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\GpVendedor;
use App\Services\Api\ApiSalesService;
use Illuminate\Support\Arr;
use App\Models\GpPedidoDetalle;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\devoluciones\GpDevolucionDocument;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\ApiGpService;
use App\Services\Api\ApiSeedBellingService;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevolucionService implements DevolucionServiceInterface
{
    use DevolucionesCriteriosTrait,
        JsonResponseTrait;

    protected $apiGpReturnsIntegrationService;
    protected $apiSales;
    protected $apiSeedBelling;
    protected $approvalService;
    protected $devolucionRepository;
    protected $orderRepository;
    protected $devolucionBuilder;
    protected $apiGpService;
    protected $dispatchService;

    public function __construct(

        ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService,
        ApiSalesService $apiSales,
        ApiSeedBellingService $apiSeedBelling,
        ApprovalServiceInterface $approvalService,
        DevolucionRepositoryInterface $devolucionRepository,
        OrderRepositoryInterface $orderRepository,
        DevolucionParamsBuilderInterface $devolucionBuilder,
        ApiGpService $apiGpService,
        DispatchServiceInterface $dispatchServiceInterface
    ) {
        $this->apiGpReturnsIntegrationService = $apiGpReturnsIntegrationService;
        $this->apiSales = $apiSales;
        $this->apiSeedBelling = $apiSeedBelling;
        $this->approvalService = $approvalService;
        $this->devolucionRepository = $devolucionRepository;
        $this->orderRepository = $orderRepository;
        $this->devolucionBuilder = $devolucionBuilder;
        $this->apiGpService = $apiGpService;
        $this->dispatchService = $dispatchServiceInterface;
    }


    /**
     * LISTADO DE DEVOLUCIONES Y PEDIDOS PARA VISUALIAR EN LA PANTALLA DE SOLICITUD DE DEVOLUCIONES
     *
     * PENDIENTE DE MEJORAR
     */

    public function getAllOrdersWithDevoluciones($cienteId, $puntVentaId)
    {
        $pedidos = GpPedido::where('gp_pedidos.PTO_VTA_ID', $puntVentaId)
            ->where('cliente_id', $cienteId)
            ->whereIn('estado', ['facturado', 'anulada'])
            ->orderBy('gp_pedidos.created_at', 'desc')->limit(10000)->get();
    }



    /**
     * ✅ Proceso de enviar devolución
     *
     * @param array $data
     * @return GpDevoluciones
     */

    public function handleDevolutionTransaction($data)
    {
        $devolucion = Arr::get($data, 'devolucion');
        $pedidoDev = Arr::get($data, 'pedido');
        $detallePedido = Arr::get($data, 'detallepedido');

        $orderId = Arr::get($data, 'pedido.id');
        $order = GpPedido::find($orderId);
        if (!$order) {
            abort(404, 'Pedido no encontrado');
        }

        if ($order->despacho_en_proceso && !$order->despachado) {
            abort(400, 'El pedido esta en proceso de despacho, no se puede enviar la devolucion');
        }

        $detailInfo = [
            'devolucion' => $devolucion,
            'pedido' => $pedidoDev,
            'detalle' => [],
        ];

        foreach ($detallePedido as $det) {
            $isRefacturacion = $devolucion['volver_a_facturar'];
            $isSelected = ($det['seleccionado'] ?? 0) > 0;
            if ($isRefacturacion || $isSelected) {
                $detailInfo['detalle'][] = $det;
            }
        }

        $itemsDespachados = array_filter($detallePedido, function ($detalle) {
            return ($detalle['despacho'] ?? false) === true && ($detalle['despachado'] ?? 0) > 0;
        });

        $detailInfo['pedido']['despachado'] = count($itemsDespachados) > 0 ? 1 : 0;


        $newDev = DB::transaction(function () use ($detailInfo) {
            if (count($detailInfo['detalle']) > 0) {
                return $this->executeReturn($detailInfo);
            }
            throw new Exception("No hay ítems válidos para generar la devolución.");
        });

        try {
            $this->dispatchService->cancelDispatchIfEmptyPicking($orderId);
            $this->processInvoiceAnulation($data, $newDev);
        } catch (Exception $e) {
            Log::error("Error crítico: Devolución {$newDev->id} creada, pero falló la anulación: " . $e->getMessage());
            $newDev->warning_message = "Devolución generada {$newDev->devolucion}, pero no fue anulada por el siguiente motivo: " . $e->getMessage();
        }

        return $newDev;
    }





    public function executeReturn($data): GpDevoluciones
    {
        $user = Auth::user();
        $gpUser = GpUsuario::where('id', $user->id)->first();
        $requiredApproval = $this->approvalService->checkoutApprovalDevoluciones($data);
        $status = $requiredApproval === 'yes' ? 'espera' : 'solicitado';
        $calculatedData = [
            'status' => $requiredApproval === 'yes' ? 'espera' : 'solicitado',
            'requiredApproval' => $requiredApproval,
            'USERID' => $gpUser->USERID,
            'userId' => $gpUser->id,
            'canNullify' => $this->getCheckoutPaymentInvoiceGp(Arr::get($data, 'pedido', []), Arr::get($data, 'devolucion', []), $status),
            'anulacionHabilitada' => $this->canBeAnulledByLimitDate(Arr::get($data, 'pedido', [])),
            'isDispatched' => $this->hasDispatchedOrderDetails(Arr::get($data, 'pedido', []), Arr::get($data, 'detalle', []), Arr::get($data, 'devolucion', [])),
        ];
        $devolucion = $this->devolucionBuilder->formPayloadDevolutionHeader($data, $calculatedData);
        $devolucion->save();
        $this->storeReturnDetail($devolucion->id, Arr::get($data, 'pedido', []), Arr::get($data, 'detalle', []));
        return $devolucion;
    }

    public function processInvoiceAnulation($data, $newDev)
    {
        $isCleanOrder = $this->devolucionRepository->isOrderNotDispatchedOrProcessing($data['pedido']);
        $hasOtherReturns = $this->devolucionRepository->hasOtherActiveReturns($data['pedido'], $newDev);
        $validateItemsL = $this->devolucionRepository->validateItemLDetailInvoice($newDev);
        $hasPartialDispatch = $this->devolucionRepository->hasPartiallyDispatchedItems($newDev);

        $shouldAnulated = (
            $isCleanOrder &&
            ($data['devolucion']['tipo_devolucion'] ?? '') === "total" &&
            !$hasOtherReturns &&
            !$validateItemsL &&
            !$hasPartialDispatch &&
            ($data['devolucion']['volver_a_facturar'] ?? false) === false &&
            ($newDev->permite_anulacion === true || $newDev->anulacion_habilitada === true)
        );

        if ($shouldAnulated) {
            $anulacionResponse = $this->anularDevolucion($data['pedido'], $newDev);
            $respData = $anulacionResponse ? $anulacionResponse->getData(true) : null;
            if (!$respData || ($respData['success'] ?? false) === false) {
                $msg = $respData['error'] ?? 'Ocurrió un error al anular la factura';
                throw new Exception($msg);
            }
        }
    }


    /**
     * PROCESO DE ANULACION DE DEVOLUCION
     */

    public function anularDevolucion($pedido, $devolucion)
    {
        $pedido = GpPedido::find($pedido['id']);
        if ($this->isInvoiceAlreadyVoided($pedido, $devolucion)) {
            // return $this->buildErrorResponse('La factura ya fue anulada', 400);
            abort(400, 'La factura ya fue anulada');
        }
        return $this->processCancellation($pedido, $devolucion);
    }

    public function isInvoiceAlreadyVoided($pedido, $devolucion)
    {
        return $this->devolucionRepository->isInvoiceAlreadyVoided($pedido, $devolucion);
    }
    public function validateInvoiceNotDispatchOrInProcess($pedido)
    {
        return $this->devolucionRepository->isOrderNotDispatchedOrProcessing($pedido);
    }

    public function countsReturnsByOrders($pedido, $devolucion)
    {

        return $this->devolucionRepository->hasOtherActiveReturns($pedido, $devolucion);
    }

    public function validateItemLDetailInvoice($devolucion)
    {
        return $this->devolucionRepository->validateItemLDetailInvoice($devolucion, );
    }

    public function validateItemPartialDispatch($devolucion)
    {
        return $this->devolucionRepository->hasPartiallyDispatchedItems($devolucion);
    }

    public function canBeAnulledByLimitDate($pedido)
    {
        $fechaFactura = $pedido['fecha_factura'] ?? null;
        $ptoVtaId = $pedido['PTO_VTA_ID'] ?? null;

        if (!$fechaFactura || !$ptoVtaId) {
            return false;
        }

        $ptoVta = GpPuntoVenta::where('PTO_VTA_ID', $ptoVtaId)->first();
        if (!$ptoVta || !$ptoVta->PTO_VTA_DIAS_MAXIMO_PARA_ANULACION) {
            return false;
        }
        $diaLimite = $ptoVta->PTO_VTA_DIAS_MAXIMO_PARA_ANULACION;

        $fechaLimite = Carbon::parse($fechaFactura)
            ->addMonth()
            ->startOfMonth()
            ->day($diaLimite);
        return now()->lte($fechaLimite);
    }

    public function processCancellation($order, $devolucion)
    {
        // VALIDACION DE CLAVE DE ACCESO
        $accessKeyResponse = $this->apiGetDocumentAccessKey($order);
        $this->saveAccessKeyInfo($order, $accessKeyResponse, $devolucion);
        if (!$this->isValidAccessKey($accessKeyResponse)) {
            $devolucion->update([
                'estado' => 'no_procesado',
                'motivo_no_anulada' => $accessKeyResponse['message']
            ]);
            // throw new \Exception('La devolución es válida para anulación, pero ' . ' ' . $accessKeyResponse['message'] ?? 'Error al obtener la clave de acceso');
            abort(400, 'La devolución es válida para anulación, pero ' . ' ' . $accessKeyResponse['message'] ?? 'Error al obtener la clave de acceso');
        }

        //VALIDACION DE ANULACION DE FACTURA GP
        $cancelResponse = $this->apiInvalidateGpVoucher($order);
        if ($cancelResponse['success'] === false) {
            $this->saveBaseCancellationInfo($order, $cancelResponse, $devolucion);

            // throw new \Exception('La devolución es válida para anulación, pero ' . $cancelResponse['message'] ?? 'Error al anular la factura');
            abort(400, 'La devolución es válida para anulación, pero ' . $cancelResponse['message'] ?? 'Error al anular la factura');
        }
        $this->saveFailedCancellation($order, $cancelResponse, $devolucion);
        $this->sendSeedBilling($order, $accessKeyResponse['claveAcceso'], $devolucion);
        return $this->buildSuccessResponse('Factura anulada correctamente', 200);
    }

    public function saveBaseCancellationInfo($order, $cancelResponse, $devolucion)
    {

        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id],
            [
                'devolucion_id' => $devolucion['id'],
                'pedido' => $order->pedido,
                'factura' => $order->factura,
                'msj_anulacion_gp' => $cancelResponse['message'],
                'diario_response_gp' => $cancelResponse['diario'] ?? null,
                'estado' => 'pendiente',
            ]
        );
    }

    public function saveAccessKeyInfo($order, $accessKeyResponse, $devolucion)
    {

        $data = [
            'resp_document' => $accessKeyResponse['message'] ?? null,
            'pedido' => $order->pedido,
            'estado' => 'pendiente',
        ];

        // si existe la clave de acceso
        if (!empty($accessKeyResponse['claveAcceso'])) {
            $data['claveacceso'] = $accessKeyResponse['claveAcceso'];
            $data['documento_id'] = $accessKeyResponse['id'];
            $data['pedido'] = $order->pedido;
            $data['estado'] = 'pendiente';
        }

        // si existe el mensaje de error y no existe la clave de acceso


        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id, 'factura' => $order->factura, 'devolucion_id' => $devolucion['id']],
            $data
        );
    }

    private function isValidAccessKey($accessKeyResponse): bool
    {
        return !isset($accessKeyResponse['success'])
            && !empty($accessKeyResponse['claveAcceso']);
    }
    private function sendSeedBilling($order, string $accessKey, $devolucion): void
    {
        $response = $this->apiResponseSeedBelling($accessKey);

        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id, 'factura' => $order->factura, 'devolucion_id' => $devolucion['id']],
            [
                'resp_seedbilling' => $response['respuesta'] ?? null,
                'estado' => 'enviada',
            ]
        );

        $this->apiUpdateTbhFacAnuAutorize($order->factura); //me actualiza el estado de la factura anulada
        $this->updateStatusDevolutions($order, $devolucion); //me actualiza el estado de la devolucion
        $this->updateStatusOrders($order); //me actualiza el estado del pedido
        $this->updatedStatusOrderDispatch($order); //me actualiza el estado del despacho
        $this->updateDevolucionWithSelectedQuantity($devolucion); //me actualiza el devuelto con el seleccionado
        $this->updateDetailOrderStock($order, $devolucion); //me actualiza el detalle pedido stock de cada item
    }

    private function saveFailedCancellation($order, array $cancelResponse, $devolucion): void
    {
        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id],
            [
                'devolucion_id' => $devolucion['id'],
                'pedido' => $order->pedido,
                'factura' => $order->factura,
                'msj_anulacion_gp' => $cancelResponse['message'],
                'diario_response_gp' => $cancelResponse['diario'] ?? null,
                'estado' => 'pendiente',
            ]
        );
    }

    public function updateStatusDevolutions($order, $devolucion)
    {
        GpDevoluciones::where('pedido_id', $order->id)
            ->where('id', $devolucion->id ?? $devolucion['id'])
            ->update(['estado' => 'anulada']);
    }

    public function updateStatusOrders($order)
    {
        GpPedido::where('id', $order->id)
            ->update(['estado' => 'anulada']);
    }
    public function updatedStatusOrderDispatch($order)
    {
        GpOrdenDespacho::where('pedido_id', $order->id)
            ->update(['estado' => 'cancelada']);
    }


    public function apiInvalidateGpVoucher($pedido)
    {
        return $this->apiSales->invalidateGpVoucher(array(
            'numeroDocumento' => $pedido->factura,
            'cliente' => $pedido->CLI_RUC
        ));
    }

    public function apiGetDocumentAccessKey($pedido)
    {
        return $this->apiSales->getDocumentAccesKey(array(
            'numdoc' => $pedido->factura,
        ));
    }

    public function apiResponseSeedBelling($accesCode)
    {

        return $this->apiSeedBelling->anularComprobante(array(
            'claveAcceso' => $accesCode,
        ));
    }

    public function apiUpdateTbhFacAnuAutorize($factura)
    {
        $this->apiSales->updatedStatusDocumentGp(array(
            'numdoc' => $factura,
        ));
    }

    public function updateDetailOrderStock($order, $devolucion)
    {
        if ($devolucion['rebajado'] || $devolucion->rebajado) {
            return;
        }
        $pedidoId = $order->id;
        $bodega = $order->BOD_BODEGA;
        $actualizarStock = !$devolucion->despachado;
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
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
                    $stock->reservado = max(0, $stock->reservado - $value->devuelto);
                    $stock->save();
                }
            }
        }
        $devolucion->rebajado = true;
        $devolucion->save();
    }

    public function updateDevolucionWithSelectedQuantity($devolucion)
    {
        $origen = 'seleccionado'; // Por defecto, usamos 'seleccionado'
        $afectados = DB::table('gp_devoluciones_detalles')
            ->where('devolucion_id', $devolucion->id)
            // ->where('despachado_parcial', 0)
            ->update([
                'devuelto' => DB::raw("`$origen`")
            ]);
        Log::channel('devolucion')->info("Se actualizaron {$afectados} registros en 'devuelto' usando columna '$origen' para devolución ID {$devolucion->id} en devoluciones estado anulada");
        return $afectados;
    }




    // esta función verifica si el pedido tiene guías de remisión asociadas o no
    // y con eso determinamos si la devlucion pasa de despachado a no despachado

    public function hasDispatchedOrderDetails($pedido, $detallepedido, $devolucion): bool
    {
        // Verifica si la devolucion es de volver a facturar
        if ($devolucion['volver_a_facturar'] === true) {
            $pedidoIds = GpPedido::where('cotizacion_id', $pedido['cotizacion_id'])
                ->where('estado', '!=', 'anulada')
                ->orderByDesc('id')
                ->first();
            if (!$pedidoIds) {
                return false;
            }
            $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
            return GpPedidoDetalle::where('pedido_id', $pedidoIds->id)
                ->whereIn('ART_CODIGO', $codigos)
                ->where('trasladado', '>', 0)
                ->exists();
        }

        // verifica si el pedido tiene factura origen para determinar si tiene o no despacho
        $pedidoConsulta = GpPedido::find($pedido['id']);
        $tieneFacturaOrigen = !empty($pedidoConsulta->factura_origen);
        if ($tieneFacturaOrigen) {
            //validador para casos donde el pedido tiene despachado null se verifica en caso de que tenga factura origen y si haya items despachados parciales
            if ($pedidoConsulta->despachado === false || $pedidoConsulta->despachado === null) {
                $pedido_origen = GpPedido::where('factura', $pedido['factura_origen'])->orderByDesc('id')->first();
                if (is_null(optional($pedido_origen)->despachado) || $pedido_origen->despachado === false) {
                    return $this->hasDetailDispatched($detallepedido);
                }
            }

            //esta condicional valida que pedido tenga factura origen y despachado
            if ($pedidoConsulta->despachado === true || $pedidoConsulta->despachado === 1) {
                $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
                return GpPedidoDetalle::where('pedido_id', $pedidoConsulta->id)
                    ->whereIn('ART_CODIGO', $codigos)
                    ->where('trasladado', '>', 0)
                    ->exists();
            }
        }

        // Verifica si alguno de los detalles del pedido tiene despacho
        return $this->hasDetailDispatched($detallepedido);
    }

    public function hasDetailDispatched($detallepedido): bool
    {
        return collect($detallepedido)
            ->contains(function ($item) {
                return !empty($item['despachado'])
                    && (float) $item['despachado'] > 0
                    && isset($item['despacho'])
                    && $item['despacho'] === true;
            });
    }

    public function getCheckoutPaymentInvoiceGp($pedido, $devolucion, $status): bool
    {
        $isTotalSolicitada = $devolucion['tipo_devolucion'] === 'total' && $status === 'solicitado';
        if (!$isTotalSolicitada) {
            return false;
        }
        $response = $this->apiSales->getCheckoutPaymentInvoiceGp(
            array(
                'numeroDocumento' => $pedido['factura'],
                'cliente' => $pedido['CLI_RUC']
            )
        );

        if (isset($response['success']) && $response['success'] === false) {
            return true;
        }
        if (isset($response['documento']) && $response['documento'] === 'PAGOS') {
            return false;
        }
        return true;
    }

    public function storeReturnDetail($returnId, $pedido, $details): void
    {
        foreach ($details as $item) {
            $dispatched = $this->isDevolucionItemDispatchedInGuia($pedido, $item);
            $detail = $this->devolucionBuilder->formPayloadDevolutionDetail($returnId, $item, $dispatched);
            $detail->save();
        }
    }

    public function isDevolucionItemDispatchedInGuia($pedido, $item): bool
    {
        //verifica si el pedido tiene factura origen
        $pedidoConsulta = GpPedido::find($pedido['id']);
        $tienefacturaOrigen = !empty($pedidoConsulta->factura_origen);

        if ($tienefacturaOrigen) {
            //verifica que el pedido tenga factura origen y que no este despachado
            if ($pedidoConsulta->despachado === false || $pedidoConsulta->despachado === null) {
                $existItemGuia = GpGuiaRemisionDetalle::where('detalle_id', $item['id'])->exists();
                if ($existItemGuia) {
                    return isset($item['despachado']) && (float) $item['despachado'] > 0 && $item['despacho'] === true;
                }
                return $this->hasItemTrasladadoPedidoRelacionados($pedidoConsulta->factura_origen, $item['ART_CODIGO']);
            }
            //verifica que el pedido tenga factura origen y que este despachado
            if ($pedidoConsulta->despachado === true || $pedidoConsulta->despachado === 1) {
                return $this->hasItemTrasladadoPedidoRelacionados($pedidoConsulta->factura_origen, $item['ART_CODIGO']);
            }
        }

        //retorna en el proceso normal
        return isset($item['despachado']) && (float) $item['despachado'] > 0 && $item['despacho'] === true;
    }

    public function hasItemTrasladadoPedidoRelacionados($facturaOrigen, $artCodigo): bool
    {
        $pedidoOrigenIds = GpPedido::where('factura', $facturaOrigen)->pluck('id');
        $cotizacionesOrigen = GpPedido::whereIn('id', $pedidoOrigenIds)->pluck('cotizacion_id');
        $pedidosRelacionados = GpPedido::whereIn('cotizacion_id', $cotizacionesOrigen)->pluck('id');

        return GpPedidoDetalle::whereIn('pedido_id', $pedidosRelacionados)
            ->where('ART_CODIGO', $artCodigo)
            ->where('trasladado', '>', 0)
            ->exists();
    }





    /**
     * ✅ Obtiene los detalles de una devolución específica.
     *
     * @param array $devolucion
     *
     */

    public function getReturnsDetailDevolucion($devolucion)
    {
        $pedido = $this->devolucionRepository->getOrderbyId($devolucion['pedido_id']);
        if ($devolucion['estado'] == 'aprobado' && $devolucion['aprobado'] == 'yes') {
            $detalle = $this->devolucionRepository->getDetailDevolucion($devolucion['id'], 1);
        } else {
            $detalle = $this->devolucionRepository->getDetailDevolucion($devolucion['id']);
        }

        //test
        $items = collect($detalle)
            ->filter(fn($item) => $item['seleccionado'] > 0 || $item['devuelto'] > 0)
            ->pluck('ART_CODIGO')
            ->toArray();


        // $respuesta = $webservices->inventario('ConsultaItemCompartimento', $parameters);
        $responseCompartimentos = $this->apiGpReturnsIntegrationService->consultMultipleItemsBin($items, $pedido['BOD_BODEGA']);
        $detalle = collect($detalle)->map(function ($item) use ($responseCompartimentos) {
            $compartimento = collect($responseCompartimentos)
                ->first(function ($response) use ($item) {
                    // 🔍 Verificar dentro de 'data' si existe el item
                    return $response['data']['Item'] === $item['ART_CODIGO'];
                });

            $item['compartimientos'] = $compartimento['lista'] ?? [];
            $item['compartimiento'] = $compartimento['data']['bin'] ?? null;

            return $item;
        })->toArray();

        $segmento = $this->getSegmentoCliente($pedido['CLI_RUC']);
        return response([
            'success' => true,
            'detalle' => $detalle,
            'segmento' => $segmento
        ]);
    }

    public function getSegmentoCliente($ruc)
    {
        $response = $this->apiGpService->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }




    /**
     * Metodo para obtener el detalle del pedido devuelto y que se solicitara devolucion.
     * Se migro la funcion de SP de MSW MST_Cliente_Grupo_Economico a Java
     */
    public function getDetailOrderReturnById($order)
    {
        $orderId = Arr::get($order, 'id');
        $clientId = Arr::get($order, 'cliente_id');

        if ($this->devolucionRepository->getReturnsAnulated($orderId)) {
            abort(409, 'Esta factura se encuentra anulada en el SRI');
        }

        if ($this->devolucionRepository->getReturnsInProcess($orderId)) {
            abort(409, 'Esta factura ya tiene una devolución en proceso');
        }

        $pedido = $this->devolucionRepository->getOrderbyId($orderId);

        if ($pedido->despacho_en_proceso && !$pedido->despachado) {
            abort(409, 'El pedido está siendo despachado');
        }

        $detalles = $this->orderRepository->getAllDetailsOrderById($orderId);
        $motivos = $this->devolucionRepository->getMotivosDevolucion();
        $cliente = $this->devolucionRepository->getClientsByClientId($clientId)[0] ?? null;


        $grupoEconomico = $this->apiGpReturnsIntegrationService->consultGroupEconomicByClient([
            'ruc' => $cliente->CLI_RUC ?? null,
        ]);

        if (!$grupoEconomico['success']) {
            abort(400, $grupoEconomico['message'] ?? 'Error al obtener el grupo económico del cliente');
        }

        $clientesArr = collect($grupoEconomico['CGE_RUC'] ?? [])->map(function ($ruc) {
            $cliente = $this->devolucionRepository->getClientsByClientRuc($ruc);
            if ($cliente) {
                $cliente->sucursales = $this->devolucionRepository->getClientsSucursByClientRuc($cliente->CLI_RUC);
            }
            return $cliente;
        })->filter()->values()->all();

        $detailFormat = $this->getDetailFormatPedido($detalles, $pedido);
        $devolucionAnterior = $this->devolucionRepository->getReturnsHistoryByOrderId($orderId);

        return [
            'detalle'               => $detailFormat,
            'motivos'               => $motivos,
            'clientes'              => $clientesArr,
            'devolucion_anterior'   => $devolucionAnterior,
            'items_devueltos'       => $this->validarItemNoTengaDespachoParcial($detalles)
        ];
    }

    public function getDetailFormatPedido($detalles, $pedido)
    {
        $result = [];
        $yaFueRefacturado = GpDevoluciones::where('pedido_id', $pedido->id)
            ->where('volver_a_facturar', true)->where('estado', '!=', 'rechazado')->exists();
        if ($yaFueRefacturado) {
            return [];
        }


        $esRefacturacion = !empty($pedido->factura_origen);
        $pedidoOrigen = $esRefacturacion ? GpPedido::find($pedido->factura_origen)->orderBy('id', 'desc')->first() : null;
        $condicionalDespachoOrigen = $pedidoOrigen ? ($pedidoOrigen->despachado != null) : false;

        foreach ($detalles as $key => $dt) {
            $common = clone $dt;
            $common->reservado = $dt->reservado ?? 0;
            $common->devuelto = $dt->devuelto ?? 0;
            $common->seleccionado = 0;

            if ($esRefacturacion) {
                // para pedidoes de refacturacion despachados totalmente
                if ($condicionalDespachoOrigen === true && ($pedidoOrigen->despachado === true || $pedidoOrigen->despachado === 1)) {
                    $devueltoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 1);
                    $itemDespachado = clone $common;
                    $itemDespachado->despachado = $common->ART_CANTIDAD;
                    $itemDespachado->despacho = true;
                    $itemDespachado->devuelto = (float) $devueltoDespachado === (float) $common->devuelto ? $common->devuelto : $devueltoDespachado;
                    $itemDespachado->disponible = max($common->ART_CANTIDAD - $itemDespachado->devuelto, 0);

                    if ($itemDespachado->disponible > 0) {
                        $result[] = $itemDespachado;
                    }
                }

                //para pedidos de refacturacion no despachados totalmente
                if ($condicionalDespachoOrigen === false && ($pedidoOrigen->despachado === false || $pedidoOrigen->despachado === null)) {
                    $common->despachado = $this->devolucionRepository->getSumGuidesRemisionByOrderDetailId($dt->id) ?? 0;
                    $devueltoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 1);
                    $devueltoNoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 0);

                    $totalCantidad = (float) $common->ART_CANTIDAD;
                    $totalDespachado = (float) $common->despachado;
                    $totalNoDespachados = max($totalCantidad - $totalDespachado, 0);
                    if ($devueltoDespachado >= 0) {
                        $itemDespachado = clone $common;
                        $itemDespachado->despacho = true;
                        $itemDespachado->devuelto = $devueltoDespachado;
                        $itemDespachado->disponible = max($totalDespachado - $devueltoDespachado, 0);
                        if ($itemDespachado->disponible > 0) {
                            $result[] = $itemDespachado;
                        }
                    }

                    // Bloque para los items no despachados
                    if ($devueltoNoDespachado >= 0) {
                        $itemNoDespachado = clone $common;
                        $itemNoDespachado->despacho = false;
                        $itemNoDespachado->devuelto = $devueltoNoDespachado;
                        $itemNoDespachado->disponible = max($totalNoDespachados - $devueltoNoDespachado, 0);
                        if ($itemNoDespachado->disponible > 0) {
                            $result[] = $itemNoDespachado;
                        }
                    }
                }
            } else {
                $common->despachado = $this->devolucionRepository->getSumGuidesRemisionByOrderDetailId($dt->id) ?? 0;
                $devueltoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 1);
                $devueltoNoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 0);

                $totalCantidad = (float) $common->ART_CANTIDAD;
                $totalDespachado = (float) $common->despachado;
                $totalNoDespachados = max($totalCantidad - $totalDespachado, 0);
                if ($devueltoDespachado >= 0) {
                    $itemDespachado = clone $common;
                    $itemDespachado->despacho = true;
                    $itemDespachado->devuelto = $devueltoDespachado;
                    $itemDespachado->disponible = max($totalDespachado - $devueltoDespachado, 0);
                    if ($itemDespachado->disponible > 0) {
                        $result[] = $itemDespachado;
                    }
                }

                if ($devueltoNoDespachado >= 0) {
                    $itemNoDespachado = clone $common;
                    $itemNoDespachado->despacho = false;
                    $itemNoDespachado->devuelto = $devueltoNoDespachado;
                    $itemNoDespachado->disponible = max($totalNoDespachados - $devueltoNoDespachado, 0);
                    if ($itemNoDespachado->disponible > 0) {
                        $result[] = $itemNoDespachado;
                    }
                }
            }
        }
        return collect($result)->sortBy('ART_CODIGO')->values()->all();
    }


    public function validarItemNoTengaDespachoParcial($detalles)
    {
        return collect($detalles)->contains(function ($item) {
            $cantidad = (float) ($item['ART_CANTIDAD'] ?? 0);
            $trasladado = (float) ($item['trasladado'] ?? 0);

            return $trasladado > 0 && $trasladado < $cantidad;
        });
    }


    /** function imprimirdevolucion -controlador
     * Prints the details of a return based on the provided return ID.
     *
     * @param int $devolucion_id The ID of the return to be printed.
     * @return array
     */

    public function printReturn($devolucion_id)
    {
        $data = [];
        $devolucion = $this->devolucionRepository->getReturnByIdToArray($devolucion_id);
        $pedido = $this->devolucionRepository->getOrderByIdWithUsers($devolucion['pedido_id']);
        $detalleDev = $this->devolucionRepository->getDetailDevolucionById($devolucion_id);
        $puntoVenta = $this->devolucionRepository->getByPtoVtaIdReturns($pedido['PTO_VTA_ID']);
        $data['devolucion'] = $devolucion;
        $data['pedido'] = $pedido;

        $cliente = $this->devolucionRepository->getClientsByClientIdToArray($pedido['cliente_id']);
        $email = explode(';', str_replace(',', ';', $cliente['CLI_CORREO_ELECT_SRI']));
        $cliente['CLI_CORREO_ELECT_SRI'] = $email[0];
        $data['cliente'] = $cliente;

        $sucursal = $this->devolucionRepository->getSucursalByInvoiceToArray($pedido['sucursal_facturacion_id']);
        $data['sucursal'] = $sucursal;

        $data['subtotal'] = 0;
        $data['descuento'] = 0;
        $data['subtotalmenosdescuento'] = 0;
        $data['iva'] = 0;

        $detalleGrouped = collect($detalleDev)->groupBy(function ($det) {
            return $det['detalle_id'] . '_' . $det['ART_CODIGO'];
        })->map(function ($group) {
            $base = $group->first();
            $base['seleccionado'] = $group->sum('seleccionado');
            $base['devuelto'] = $group->sum('devuelto');
            $base['recibido'] = $group->sum('recibido');
            return $base;
        })->sortBy('detalle_id')->values();

        foreach ($detalleGrouped as $art) {
            $data['tipoimpuesto'][$art['ART_IMP_VTA']] = 0;
            $data['sumaimpuesto'][$art['ART_IMP_VTA']] = 0;
        }
        foreach ($detalleGrouped as $key => $art) {

            if ($art['devuelto'] == 0) {
                unset($detalleGrouped[$key]);
                continue;
            }

            $data['tipoimpuesto'][$art['ART_IMP_VTA']] += (float) number_format($art['ART_VALOR'] * $art['devuelto'], 2, '.', '');
            $data['sumaimpuesto'][$art['ART_IMP_VTA']] += (float) number_format(($art['ART_IVA'] / $art['ART_CANTIDAD']) * $art['devuelto'], 2, '.', '');


            $data['descuento'] += (float) number_format($art['ART_DESCUENTO'] * $art['devuelto'], 2, '.', '');
            $data['subtotalmenosdescuento'] += (float) number_format(($art['ART_VALOR'] - $art['ART_DESCUENTO']) * $art['devuelto'], 2, '.', '');
            $data['iva'] += (float) number_format(((($art['ART_VALOR'] - $art['ART_DESCUENTO']) * $art['devuelto']) * $art['ART_IMP_VTA'] / 100), 2, '.', '');
        }

        foreach ($data['tipoimpuesto'] as $linea) {
            $data['subtotal'] += $linea;
        }

        $data['subtotalmenosdescuento'] = (float) number_format($data['subtotalmenosdescuento'], 2, '.', '');
        $data['descuento'] = (float) number_format($data['descuento'], 2, '.', '');
        $data['iva'] = (float) number_format($data['iva'], 2, '.', '');
        $data['total'] = number_format($data['subtotalmenosdescuento'] + $data['iva'], 2, '.', '');


        $data['detalle'] = $detalleGrouped;
        $data['PTO_VTA_DESCRIPCION'] = $puntoVenta->PTO_VTA_DESCRIPCION;
        $data['direccion'] = $puntoVenta->PTO_VTA_DIRECCION_1 . ' ' . $puntoVenta->PTO_VTA_DIRECCION_2;
        $data['telefono'] = $puntoVenta->PTO_VTA_FONO_1;
        $data['vendedor'] = GpVendedor::where('VEND_ID', $sucursal['CLI_SUC_VENDEDOR'])->first()->toarray();

        return $data;
    }



    /**
     * FUNCIONES PARA LA GENERACION DE NOTA DE CREDITO Y AJUSTE EN CASO DE QUE SE HAYA
     * GENERADO ALGUN ERROR POR AB MOTIVO
     */

    public function generarNotaCreditoDevolucionesFallidas($id)
    {

        try {
            $devolucion = GpDevoluciones::find($id);
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
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
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
        foreach ($detalles as $key => $detalle) {
            if ($detalle->devuelto == 0)
                continue;

            $cantidad = (float) $detalle->devuelto;
            $valorUnitario = (float) $detalle->ART_VALOR;
            $descuentoUnitario = (float) $detalle->ART_DESCUENTO;
            $porcentajeImpuesto = (float) $detalle->ART_IMP_VTA;

            $valorTotal = round($valorUnitario * $cantidad, 2);
            $descuentoTotal = round($descuentoUnitario * $cantidad, 2);
            $valorNeto = round(($valorUnitario - $descuentoTotal) * $cantidad, 2);
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


    public function generarAjusteDevolucionesVentaFallidas($id)
    {
        try {
            $userId = 'adminWeb';
            $devolucion = GpDevoluciones::find($id);
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
            $this->createDocumentDevolution($devolucion, $res1['data'], $userId);
            // 🟢 Actualización correcta
            $devolucion->devolucion_bodega = true;
            $devolucion->save();

            return $this->buildSuccessResponse('Ajuste realizado correctamente', 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function createDocumentDevolution($devolucion, $responseApi, $userId)
    {
        $devDoc = new GpDevolucionDocument();
        $devDoc->devolucion_id = $devolucion->id;
        $devDoc->devolucion = $devolucion->devolucion;
        $devDoc->id_movimiento = $responseApi['idMovimiento'];
        $devDoc->documento_gp = $responseApi['docGp'];
        // $devDoc->user_id = $userId;
        $devDoc->save();
    }


    public function responseCreditNoteDevolution($devolucionId)
    {
        try {
            DB::beginTransaction();
            $devolucion = GpDevoluciones::find($devolucionId);
            $pedido = GpPedido::find($devolucion->pedido_id);
            $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
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
            DB::commit();
            return $resultado;
        } catch (\Throwable $e) {
            Log::error('Error al generar la nota de crédito: ' . $e->getMessage());
            return $this->buildErrorResponse('Error ha ocurrido mientras se generaba la nota de credito de la devolucion' . $e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Services\Devoluciones;

use App\Models\GpPedido;
use App\Models\GpVendedor;
use App\Services\Api\ApiSalesService;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use Illuminate\Support\Arr;
use App\Models\GpPedidoDetalle;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Http\Controllers\WebserviceController;
use App\Http\Controllers\ventas\PedidosController;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\ApiSeedBellingService;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;


class DevolucionService implements DevolucionServiceInterface
{
    use DevolucionesCriteriosTrait,
        JsonResponseTrait;

    protected PedidosController $pedidosController;
    protected ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService;
    protected ApiSalesService $apiSales;
    protected ApiSeedBellingService $apiSeedBelling;
    protected ApprovalServiceInterface $approvalService;
    protected DevolucionRepositoryInterface $devolucionRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected NotificationServiceInterface $notificationService;

    public function __construct(

        PedidosController $pedidosController,
        ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService,
        ApiSalesService $apiSales,
        ApiSeedBellingService $apiSeedBelling,
        ApprovalServiceInterface $approvalService,
        DevolucionRepositoryInterface $devolucionRepository,
        OrderRepositoryInterface $orderRepository,
    ) {

        $this->pedidosController = $pedidosController;
        $this->apiGpReturnsIntegrationService = $apiGpReturnsIntegrationService;
        $this->apiSales = $apiSales;
        $this->apiSeedBelling = $apiSeedBelling;
        $this->approvalService = $approvalService;
        $this->devolucionRepository = $devolucionRepository;
        $this->orderRepository = $orderRepository;
    }


    //solicitudes de devoluciones

    public function anularDevolucion($pedido, $devolucion)
    {
        $pedido = GpPedido::find($pedido['id']);
        if ($this->isInvoiceAlreadyVoided($pedido, $devolucion)) {
            return $this->buildErrorResponse('La factura ya fue anulada', 400);
        }
        return $this->processCancellation($pedido, $devolucion);
    }

    public function isInvoiceAlreadyVoided($pedido, $devolucion)
    {
        return $this->devolucionRepository->isInvoiceAlreadyVoided($pedido, $devolucion);
    }
    public function validateInvoiceAnulation($pedido)
    {
        return $this->devolucionRepository->validateInvoiceAnulation($pedido);
    }

    public function countsReturnsByOrders($pedido, $devolucion)
    {

        return $this->devolucionRepository->countsReturnsByOrders($pedido, $devolucion);
    }

    public function validateItemLDetailInvoice($devolucion)
    {
        return $this->devolucionRepository->validateItemLDetailInvoice($devolucion, );
    }

    public function validateItemPartialDispatch($devolucion)
    {
        return $this->devolucionRepository->validateItemsPartialDispatch($devolucion);
    }

    public function processCancellation($order, $devolucion)
    {
        // VALIDACION DE CLAVE DE ACCESO
        $accessKeyResponse = $this->apiGetDocumentAccessKey($order);
        $this->saveAccessKeyInfo($order, $accessKeyResponse, $devolucion);
        if (!$this->isValidAccessKey($accessKeyResponse)) {
            throw new \Exception('La devolución es válida para anulación, pero ' . ' ' . $accessKeyResponse['message'] ?? 'Error al obtener la clave de acceso');
            // return $this->buildErrorResponse($accessKeyResponse['message'] ?? 'Error al obtener la clave de acceso', 400);
        }

        //VALIDACION DE ANULACION DE FACTURA GP
        $cancelResponse = $this->apiInvalidateGpVoucher($order);
        if ($cancelResponse['success'] === false) {
            $this->saveBaseCancellationInfo($order, $cancelResponse, $devolucion);

            throw new \Exception('La devolución es válida para anulación, pero ' . $cancelResponse['message'] ?? 'Error al anular la factura');
            // return $this->buildErrorResponse($cancelResponse['message'] ?? 'Error al anular la factura', 400);
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

        $this->apiUpdateTbhFacAnuAutorize($order->factura);
        $this->updateStatusDevolutions($order, $devolucion);
        $this->updateStatusOrders($order);
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


    /**
     * ✅ Ejecuta la devolución de un pedido.
     *
     * @param array $data
     * @return GpDevoluciones
     */

    public function executeReturn($data): GpDevoluciones
    {
        $user = Auth::user();
        $requiredApproval = $this->approvalService->checkoutApprovalDevoluciones($data);

        $status = $requiredApproval === 'yes' ? 'espera' : 'solicitado';

        $lastReturn = GpDevoluciones::latest('id')->first();
        $nextId = $lastReturn?->id + 1 ?? 1;


        $return = new GpDevoluciones();
        $return->devolucion = $this->generateReturnCode($nextId);
        $return->estado = $status;
        $return->pedido_id = Arr::get($data, 'pedido.id');
        $return->cliente_id = Arr::get($data, 'pedido.cliente_id');
        $return->pedido = Arr::get($data, 'pedido.pedido');
        $return->factura = Arr::get($data, 'pedido.factura');
        $return->volver_a_facturar = Arr::get($data, 'devolucion.volver_a_facturar');
        $return->tipo_devolucion = Arr::get($data, 'devolucion.tipo_devolucion');
        $return->despachado = $this->hasDispatchedOrderDetails(Arr::get($data, 'pedido', []), Arr::get($data, 'detallepedido', []), Arr::get($data, 'devolucion', []));
        $return->cliente_volver_a_facturar_id = Arr::get($data, 'devolucion.cliente_volver_a_facturar_id');
        $return->user_id = $user->id;
        $return->USERID = $user->USERID;
        $return->sucursal_id = Arr::get($data, 'devolucion.sucursal_id');
        $return->sucursal_facturacion_id = Arr::get($data, 'devolucion.sucursal_facturacion_id');
        $return->motivo = Arr::get($data, 'devolucion.motivo');
        $return->orden_de_compra = Arr::get($data, 'pedido.orden_de_compra');
        $return->requiere_aprobacion = $requiredApproval;
        $return->aprobado = 'no';
        $return->impuesto = Arr::get($data, 'pedido.impuesto');
        $return->observacion = Arr::get($data, 'devolucion.observacion');
        $return->save();

        $this->storeReturnDetail($return->id, Arr::get($data, 'pedido', []), Arr::get($data, 'detallepedido', []));
        return $return;
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
        if (!empty($pedidoConsulta['factura_origen'])) {
            $pedido_origen = GpPedido::where('factura', $pedido['factura_origen'])->orderByDesc('id')->first();
            return (bool) optional($pedido_origen)->despachado;
        }

        // Verifica si alguno de los detalles del pedido tiene despacho
        $hasDetailDispatched = collect($detallepedido)
            ->contains(function ($item) {
                return !empty($item['despachado'])
                    && (float) $item['despachado'] > 0
                    && isset($item['despacho'])
                    && $item['despacho'] === true;
            });
        if ($hasDetailDispatched) {
            return true;
        }
        return false;
    }


    public function generateReturnCode(int $id): string
    {
        return 'DEV' . str_pad($id, 8, '0', STR_PAD_LEFT);
    }

    public function storeReturnDetail($returnId, $pedido, $details): void
    {
        foreach ($details as $item) {
            $detail = new GpDevolucionesDetalle();
            $detail->devolucion_id = $returnId;
            $detail->detalle_id = Arr::get($item, 'id');
            $detail->ART_CODIGO = Arr::get($item, 'ART_CODIGO');
            $detail->ART_DESCRIPCION = Arr::get($item, 'ART_DESCRIPCION');
            $detail->ART_DESCRIPCION_ORIGINAL = Arr::get($item, 'ART_DESCRIPCION_ORIGINAL');
            $detail->ART_CODIGO_TRES_LETRAS = Arr::get($item, 'ART_CODIGO_TRES_LETRAS');
            $detail->ART_DECIMAL_CANTIDAD = (int) Arr::get($item, 'ART_DECIMAL_CANTIDAD');
            $detail->ART_PLAN_UMEDIDA = Arr::get($item, 'ART_PLAN_UMEDIDA');
            $detail->ART_MARCA = Arr::get($item, 'ART_MARCA');
            $detail->ART_MATERIAL = Arr::get($item, 'ART_MATERIAL');
            $detail->ART_PESO = Arr::get($item, 'ART_PESO');
            $detail->ART_TIPO_COMPRA = Arr::get($item, 'ART_TIPO_COMPRA');
            $detail->ART_VALOR = (float) Arr::get($item, 'ART_VALOR');
            $detail->ART_IVA = (float) Arr::get($item, 'ART_IVA');
            $detail->ART_VALOR_ORIGINAL = (float) Arr::get($item, 'ART_VALOR_ORIGINAL');
            $detail->ART_DESCUENTO = (float) Arr::get($item, 'ART_DESCUENTO');
            $detail->ART_CANTIDAD = (float) Arr::get($item, 'ART_CANTIDAD');
            $detail->ART_STOCK = (float) Arr::get($item, 'ART_STOCK');
            $detail->ART_IMP_VTA = (float) Arr::get($item, 'ART_IMP_VTA');
            $detail->ART_COSTO_PROMEDIO = (float) Arr::get($item, 'ART_COSTO_PROMEDIO');
            $detail->descuento = (float) Arr::get($item, 'descuento');
            $detail->descuento_original = (float) Arr::get($item, 'descuento_original');
            $detail->modificador = (float) Arr::get($item, 'modificador');
            $detail->seleccionado = (float) Arr::get($item, 'seleccionado');
            $detail->disponible = (float) Arr::get($item, 'disponible');
            $detail->despachado_parcial = $this->isDevolucionItemDispatchedInGuia($pedido, $item);
            $detail->save();
        }
    }

    public function isDevolucionItemDispatchedInGuia($pedido, $item): bool
    {
        //verifica si el pedido tiene factura origen
        $pedidoConsulta = GpPedido::find($pedido['id']);
        if (!empty($pedidoConsulta['factura_origen'])) {
            $pedidoOrigenIds = GpPedido::where('factura', $pedido['factura_origen'])->pluck('id');
            $cotizacionesOrigen = GpPedido::whereIn('id', $pedidoOrigenIds)->pluck('cotizacion_id');
            $pedidosRelacionados = GpPedido::whereIn('cotizacion_id', $cotizacionesOrigen)->pluck('id');

            return GpPedidoDetalle::whereIn('pedido_id', $pedidosRelacionados)
                ->where('ART_CODIGO', $item['ART_CODIGO'])
                ->where('trasladado', '>', 0) // comparar por código del artículo
                ->exists();

        }
        return isset($item['despachado']) && (float) $item['despachado'] > 0 && $item['despacho'] === true;
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


        return response([
            'success' => true,
            'detalle' => $detalle,
        ]);
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
            return $this->buildErrorResponse('Esta factura se encuentra anulada en el SRI', 409);
        }

        if ($this->devolucionRepository->getReturnsInProcess($orderId)) {
            return $this->buildErrorResponse('Esta factura ya tiene una devolución en proceso', 409);
        }

        $pedido = $this->devolucionRepository->getOrderbyId($orderId);

        if ($pedido->despacho_en_proceso && !$pedido->despachado) {
            return $this->buildErrorResponse('El pedido está siendo despachado', 409);
        }

        $detalles = $this->orderRepository->getAllDetailsOrderById($orderId);
        $motivos = $this->devolucionRepository->getMotivosDevolucion();
        $cliente = $this->devolucionRepository->getClientsByClientId($clientId)[0] ?? null;


        $grupoEconomico = $this->apiGpReturnsIntegrationService->consultGroupEconomicByClient([
            'ruc' => $cliente->CLI_RUC ?? null,
        ]);

        if (!$grupoEconomico['success']) {
            $this->buildErrorResponse($grupoEconomico['message'] ?? 'Error al obtener el grupo económico del cliente', 400);
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

        return response([
            'success' => true,
            'detalle' => $detailFormat,
            'motivos' => $motivos,
            'clientes' => $clientesArr,
            'devolucion_anterior' => $devolucionAnterior,
        ]);
    }


    public function getDetailFormatPedido($detalles, $pedido)
    {
        $pedidoOrigen = ($pedido->factura_origen && $pedido->despachada)
            ? $this->devolucionRepository->getOrderOriginByInvoice($pedido->factura_origen)
            : null;
        $result = [];

        foreach ($detalles as $dt) {
            $common = clone $dt;
            $common->reservado = $dt->reservado ?? 0;
            $common->devuelto = $det->devuelto ?? 0;
            $common->seleccionado = 0;
            $common->despachado = $this->devolucionRepository->getSumGuidesRemisionByOrderDetailId($dt->id) ?? 0;


            $devueltoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 1);
            $devueltoNoDespachado = $this->devolucionRepository->sumReturnedQtyByDetailId($dt->id, 0);

            $totalCantidad = (float) $common->ART_CANTIDAD;
            $totalDespachado = (float) $common->despachado;
            $totalNoDespachados = max($totalCantidad - $totalDespachado, 0);

            // Lógica cuando hay items despachado
            if ($totalDespachado > 0) {
                $itemDespachado = clone $common;
                $itemDespachado->despacho = true;
                $itemDespachado->devuelto = $devueltoDespachado;
                $itemDespachado->disponible = max($totalDespachado - $devueltoDespachado, 0);
                if ($itemDespachado->disponible > 0) {
                    $result[] = $itemDespachado;
                }
            }

            if ($totalNoDespachados > 0) {
                $itemNoDespachado = clone $common;
                $itemNoDespachado->despacho = false;
                $itemNoDespachado->devuelto = $devueltoNoDespachado;
                $itemNoDespachado->disponible = max($totalNoDespachados - $devueltoNoDespachado, 0);

                if ($pedidoOrigen && !$pedidoOrigen->despachada) {
                    $itemNoDespachado->disponible = 0;
                }

                if ($itemNoDespachado->disponible > 0) {
                    $result[] = $itemNoDespachado;
                }
            }
        }
        return collect($result)->sortBy('ART_CODIGO')->values()->all();
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

}

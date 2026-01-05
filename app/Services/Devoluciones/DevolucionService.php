<?php

namespace App\Services\Devoluciones;

use Exception;
use Carbon\Carbon;
use App\Models\GpPedido;
use App\Models\GpUsuario;
use App\Models\GpCliente;
use App\Models\GpVendedor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use App\Models\GpPedidoDetalle;
use App\Models\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use App\Models\GpClienteSucursal;
use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\GpPedidoDetalleStock;
use Illuminate\Support\Facades\Auth;
use App\Services\Api\ApiSalesService;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\devoluciones\GpDevolucionDocument;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpArticulo;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Services\Devoluciones\Contracts\ReturnCancellationServiceInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class DevolucionService implements DevolucionServiceInterface
{
    use DevolucionesCriteriosTrait,
        JsonResponseTrait;



    public function __construct(
        protected readonly ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService,
        protected readonly ApiSalesService $apiSales,
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly DevolucionRepositoryInterface $devolucionRepository,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly DevolucionParamsBuilderInterface $devolucionBuilder,
        protected readonly ApiGpService $apiGpService,
        protected readonly DispatchServiceInterface $dispatchService,
        protected readonly ReturnCancellationServiceInterface $anulationService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly EntryServiceInterface $entryService
    ) {
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
            Log::error("Error De proceso processInvoiceAnulation o cancelDispatchIfEmptyPicking: Devolución {$newDev->id} creada, pero falló la anulación: " . $e->getMessage());
            if ($newDev->estado !== 'no_procesado') {
                $newDev->update([
                    'estado' => 'no_procesado',
                    'motivo_no_anulada' => "Fallo en proceso: " . $e->getMessage()
                ]);
            }
            throw $e;
        }
        return $newDev->refresh();
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
            ($newDev->permite_anulacion === true && $newDev->anulacion_habilitada === true)
        );

        if ($shouldAnulated) {
            $this->anulationService->execute($data['pedido'], $newDev);
        }
    }


    /**
     * validaciones para el proceso de anulacion
     * */

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
        $response = $this->apiSales->validateGpInvoicePayments(
            array(
                'numdoc' => $pedido['factura'],
                'cliente' => $pedido['CLI_RUC']
            )
        );
        $succes = data_get($response, 'success', false);
        return (bool) $succes;

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

        // $respuesta = $webservices->inventario('ConsultaItemCompartimento', $parameters);
        $detalleEnriquecido = collect($detalle)->map(function ($item) use ($pedido) {
            $resp = $this->stockWarehouseService->fetchStockWarehouseCompartmentDevolution([
                'item' => $item['ART_CODIGO'],
                'bodega' => $pedido->BOD_BODEGA
            ]);
            $bin = data_get($resp, 'detalle.compartimento', null);
            $lista = data_get($resp, 'lista', []);
            $item['compartimientos'] = $lista;
            $item['compartimiento'] = $bin;
            return $item;
        })->toArray();

        $segmento = $this->getSegmentoCliente($pedido['CLI_RUC']);
        return response([
            'success' => true,
            'detalle' => $detalleEnriquecido,
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
            'detalle' => $detailFormat,
            'motivos' => $motivos,
            'clientes' => $clientesArr,
            'devolucion_anterior' => $devolucionAnterior,
            'items_devueltos' => $this->validarItemNoTengaDespachoParcial($detalles)
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
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', collect($detalleDev)->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');
        $puntoVenta = $this->devolucionRepository->getByPtoVtaIdReturns($pedido['PTO_VTA_ID']);
        $data['devolucion'] = $devolucion;
        $data['pedido'] = $pedido;

        $cliente = $this->devolucionRepository->getClientsByClientIdToArray($pedido['cliente_id']);
        $email = explode(';', str_replace(',', ';', $cliente['CLI_CORREO_ELECT_SRI']));
        $cliente['CLI_CORREO_ELECT_SRI'] = $email[0];
        $data['cliente'] = $cliente;

        $sucursal = $this->devolucionRepository->getSucursalByInvoiceToArray($pedido['sucursal_facturacion_id']);
        $data['sucursal'] = $sucursal;

        $data['subtotal'] = 0.00;
        $data['descuento'] = 0.00;
        $data['subtotalmenosdescuento'] = 0.00;
        $data['iva'] = 0.00;

        $detalleGrouped = collect($detalleDev)->groupBy(function ($det) {
            return $det['detalle_id'] . '_' . $det['ART_CODIGO'];
        })->map(function ($group) {
            $base = $group->first();
            $base['seleccionado'] = $group->sum('seleccionado');
            $base['devuelto'] = $group->sum('devuelto');
            $base['recibido'] = $group->sum('recibido');
            return $base;
        })->sortBy('detalle_id')->values();



        foreach ($detalleGrouped as $key => $art) {
            $valorUnitario = (float) $art['ART_VALOR'];
            $cantidad = (float) $art['devuelto'];
            $descuentoUnitario = (float) $art['ART_DESCUENTO'];
            $impuestoPorcentaje = (float) $articulosImpuesto[$art['ART_CODIGO']] ?? 0;
            $tipoImpuesto = $art['ART_IMP_VTA'];

            if ($cantidad == 0) {
                unset($detalleGrouped[$key]);
                continue;
            }


            $lineNet = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineaSubtotal = $lineNet + $lineaDiscount;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);

            if (!isset($data['tipoimpuesto'][$tipoImpuesto])) {
                $data['tipoimpuesto'][$tipoImpuesto] = 0;
                $data['sumaimpuesto'][$tipoImpuesto] = 0;
            }


            $data['tipoimpuesto'][$art['ART_IMP_VTA']] += $lineaSubtotal;
            $data['sumaimpuesto'][$art['ART_IMP_VTA']] += $lineaIva;

            $data['subtotal'] += $lineaSubtotal;
            $data['descuento'] += $lineaDiscount;
            $data['subtotalmenosdescuento'] += $lineNet;
            $data['iva'] += $lineaIva;
        }

        $data['subtotalmenosdescuento'] = (float) round($data['subtotalmenosdescuento'], 2);
        $data['descuento'] = (float) round($data['descuento'], 2);
        $data['iva'] = (float) round($data['iva'], 2);
        $totalMatematico = $data['subtotalmenosdescuento'] + $data['iva'];
        $data['total'] = number_format($totalMatematico, 2, '.', '');


        $data['detalle'] = $detalleGrouped;
        $data['PTO_VTA_DESCRIPCION'] = $puntoVenta->PTO_VTA_DESCRIPCION;
        $data['direccion'] = $puntoVenta->PTO_VTA_DIRECCION_1 . ' ' . $puntoVenta->PTO_VTA_DIRECCION_2;
        $data['telefono'] = $puntoVenta->PTO_VTA_FONO_1;
        $data['vendedor'] = GpVendedor::where('VEND_ID', $sucursal['CLI_SUC_VENDEDOR'])->first()->toarray();

        return $data;
    }

    /** para facturas antes del 2026 */
    public function printReturnOld($devolucion_id)
    {
        $data = [];
        $devolucion = $this->devolucionRepository->getReturnByIdToArray($devolucion_id);
        $pedido = $this->devolucionRepository->getOrderByIdWithUsers($devolucion['pedido_id']);
        $detalleDev = $this->devolucionRepository->getDetailDevolucionById($devolucion_id);
        $puntoVenta = $this->devolucionRepository->getByPtoVtaIdReturns($pedido['PTO_VTA_ID']);
        $data['devolucion'] = $devolucion;
        $data['pedido'] = $pedido;
        $typeDev = $devolucion['tipo_devolucion'] == 'total' ? 'total' : 'parcial';

        $cliente = $this->devolucionRepository->getClientsByClientIdToArray($pedido['cliente_id']);
        $email = explode(';', str_replace(',', ';', $cliente['CLI_CORREO_ELECT_SRI']));
        $cliente['CLI_CORREO_ELECT_SRI'] = $email[0];
        $data['cliente'] = $cliente;

        $sucursal = $this->devolucionRepository->getSucursalByInvoiceToArray($pedido['sucursal_facturacion_id']);
        $data['sucursal'] = $sucursal;

        $data['subtotal'] = 0.00;
        $data['descuento'] = 0.00;
        $data['subtotalmenosdescuento'] = 0.00;
        $data['iva'] = 0.00;

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
            $data['tipoimpuesto'][$art['ART_IMP_VTA']] = 0.00;
            $data['sumaimpuesto'][$art['ART_IMP_VTA']] = 0.00;
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
        $data['total'] = $typeDev == 'total' ? $pedido['total'] : number_format($data['subtotalmenosdescuento'] + $data['iva'], 2, '.', '');


        $data['detalle'] = $detalleGrouped;
        $data['PTO_VTA_DESCRIPCION'] = $puntoVenta->PTO_VTA_DESCRIPCION;
        $data['direccion'] = $puntoVenta->PTO_VTA_DIRECCION_1 . ' ' . $puntoVenta->PTO_VTA_DIRECCION_2;
        $data['telefono'] = $puntoVenta->PTO_VTA_FONO_1;
        $data['vendedor'] = GpVendedor::where('VEND_ID', $sucursal['CLI_SUC_VENDEDOR'])->first()->toarray();

        return $data;
    }



    /**  proceso de listar devoluciones por recibir
     *
     *
     */

    public function fetchReturnsToReceived($perPage = 10, $search, $sortBy = 'gd.created_at', $sortOrder = 'desc', $ptoVtaId): LengthAwarePaginator
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVtaId)->value('PTO_VTA_BOD_BODEGA');
        $allowedSortFields = [
            'gd.created_at',
            'gd.devolucion',
            'gd.pedido',
            'gd.factura',
            'gd.DOC_GENERADO',
        ];

        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'gd.created_at';
        }

        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $query = GpDevoluciones::query()
            ->from('gp_devoluciones as gd')
            ->select([
                'gd.id',
                'gd.devolucion',
                'gp.CLI_RUC',
                'gp.CLI_RAZON_SOCIAL',
                'gd.USERID',
                'gp.PTO_VTA_DESCRIPCION',
                'gd.created_at',
                'gd.volver_a_facturar'
            ])
            ->join('gp_pedidos as gp', 'gd.pedido_id', '=', 'gp.id')
            ->join('gp_punto_ventas as gpv', 'gp.PTO_VTA_ID', '=', 'gpv.PTO_VTA_ID')
            ->whereIn('gd.estado', ['aprobado', 'solicitado'])
            ->where('gd.despachado', '=', 1)
            ->where('gp.BOD_BODEGA', '=', $bodega)
            ->where(function ($p) {
                $p->where('gd.tipo_devolucion', '!=', 'total')
                    ->orWhere('gd.volver_a_facturar', '=', false);
            });


        if ($search) {
            $query->where(function ($q) use ($search) {
                $columns = [
                    'gd.devolucion',
                    'gd.pedido',
                    'gd.factura',
                    'gd.DOC_GENERADO',
                    'gp.CLI_RAZON_SOCIAL',
                    'gp.CLI_RUC',
                ];
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $query->orderBy($sortBy, $sortOrder);
        return $query->paginate($perPage);
    }




    public function getReturnDetailToReceiveById(int $id)
    {
        $devolucion = GpDevoluciones::find($id);
        $pedido = $this->devolucionRepository->getOrderbyId($devolucion->pedido_id);
        $segmento = $this->getSegmentoCliente($pedido->CLI_RUC);
        $detalle = $this->devolucionRepository->getDetailDevolucion($devolucion->id, 1);
        $devolucion->orderDespacho = $this->devolucionRepository->getDispatchOrder($devolucion->pedido_id);
        $detalleEnriquecido = collect($detalle)->map(function ($item) use ($pedido) {
            $resp = $this->stockWarehouseService->fetchStockWarehouseCompartmentDevolution([
                'item' => $item['ART_CODIGO'],
                'bodega' => $pedido->BOD_BODEGA
            ]);
            $bin = data_get($resp, 'detalle.compartimento', null);
            $lista = data_get($resp, 'lista', []);
            $item['compartimientos'] = $lista;
            $item['compartimiento'] = $bin;
            return $item;
        });

        return [
            'devolucion' => $devolucion,
            'detalle' => $detalleEnriquecido,
            'segmento' => $segmento,
            'pedido' => $pedido
        ];
    }



    public function generatePdfOrderStorageDevolution(array $data)
    {
        $header = data_get($data, 'header');
        $detalle = data_get($data, 'details');

        $viewData = [
            'header' => $header,
            'details' => $detalle,
        ];

        $html = view('pdf.orden-almacenamiento-devolucion', $viewData)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();
        $filename = 'orden-almacenamiento-' . $header['devolucion'] . '.pdf';

        return [
            'binary' => $binary,
            'filename' => $filename,
            'content_type' => 'application/pdf',
        ];
    }


    public function receivedDevolutions(array $header, array $detail)
    {
        $auth = Auth::user();
        $gpuser = GpUsuario::find($auth->id);

        return DB::transaction(function () use ($gpuser, $header, $detail) {
            $dev = GpDevoluciones::lockForUpdate()->find($header['id']);
            if (!$dev) {
                abort(409, 'devolución no encontrada');
            }
            if (in_array($dev->estado, ['recibido', 'procesado'])) {
                abort(409, 'Esta devolución ya fue recibida en otra instancia');
            }
            foreach ($detail as $dt) {
                $itmd = GpDevolucionesDetalle::lockForUpdate()->find($dt['id']);

                if ($itmd) {
                    $newDev = data_get($dt, 'new_devuelto', 0.00);
                    $itmd->devuelto += $newDev;
                    $itmd->recibido += $newDev;
                    $itmd->compartimiento = $dt['compartimiento'];
                    $itmd->USERID = $gpuser->USERID;
                    $itmd->save();
                }
            }
            $this->createRegisterHistoryReceivedDevolution($dev, $gpuser, $detail);
            $dev->update([
                'estado' => 'recibido',
            ]);
            return $dev;
        });
    }

    public function createRegisterHistoryReceivedDevolution(GpDevoluciones $devolucion, GpUsuario $gpuser, array $detail)
    {
        $header = $this->devolucionBuilder->createRegisterHistortyHeaderDev($devolucion, $gpuser);
        $header->save();
        foreach ($detail as $dt) {
            $newDt = $this->devolucionBuilder->createRegisterHistoryDetailDev($dt, $header);
            $newDt->save();
        }
    }


    public function dennyDevolucion(int $id)
    {
        $auth = Auth::user();
        $gpuser = GpUsuario::find($auth->id);
        return DB::transaction(function () use ($id, $gpuser) {
            $devolucion = GpDevoluciones::lockForUpdate()->findOrFail($id);
            $devolucion->update([
                'estado' => 'rechazado',
                'aprobado' => 'no',
                'aprobador_id' => $gpuser->id
            ]);

            return $devolucion->refresh();
        });
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
                throw new Exception($responseNtc['message']);
            }

            $consultaParams = [
                'document' => $devolucion->devolucion,
                'tipo' => 4,
            ];
            $respNc = $this->getApiResponseNoteCredit($consultaParams);
            $successNc = data_get($respNc, 'success', false);
            $docGenerado = data_get($respNc, 'data.docGenerado', null);
            $responseCreditNote = data_get($respNc, 'data', []);
            $fechaNc = data_get($responseCreditNote, 'fecha', now()->format('Y-m-d'));
            if (!$successNc || empty($docGenerado)) {
                $msgError = data_get($respNc, 'message', 'Error desconocido al consultar la nota de crédito.');
                $devolucion->NC_VTA_MENSAJE = $msgError;
                $devolucion->save();
                throw new Exception($msgError);
            }
            $devolucion->NC_VTA_MENSAJE = 'Nota de crédito generada correctamente.';
            $devolucion->fecha_NC = Carbon::parse($fechaNc)->format('Y-m-d');
            $devolucion->estado = 'procesado';
            $devolucion->DOC_GENERADO = $docGenerado;
            $devolucion->save();
            $this->bajarDevolucionAPedido($devolucion, $detalles, $pedido);
            $this->totalDevoluciones($devolucion, $detalles);
            return $devolucion;
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
    public function getApiResponseNoteCredit($params)
    {
        return $this->apiGpReturnsIntegrationService->retrieveCreditNoteResponse($params);

    }

    public function bajarDevolucionAPedido(GpDevoluciones $devolucion, $detalles, GpPedido $order)
    {
        if ($devolucion->rebajado) {
            return;
        }
        $pedidoId = $order->id;
        $bodega = $order->BOD_BODEGA;
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



            // $lineNet = round(($valorUnitario - $descuentoUnitario) * $cantidad, 2);
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
                throw new Exception('Ajuste : mensaje -  ' . ($res1['message'] ?? 'Error desconocido'));
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
        $devolucion = GpDevoluciones::find($devolucionId);
        $pedido = GpPedido::find($devolucion->pedido_id);
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
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
            $msgError = data_get($respNc, 'message', 'Error desconocido al consultar la nota de crédito.');
            $devolucion->NC_VTA_MENSAJE = $msgError;
            $devolucion->save();
            throw new Exception($msgError);
        }
        $devolucion->NC_VTA_MENSAJE = 'Nota de crédito generada correctamente.';
        $devolucion->fecha_NC = Carbon::parse($fechaNc)->format('Y-m-d');
        $devolucion->estado = 'procesado';
        $devolucion->DOC_GENERADO = $docGenerado;
        $devolucion->save();
        $this->bajarDevolucionAPedido($devolucion, $detalles, $pedido);
        $this->totalDevoluciones($devolucion, $detalles);
        return $devolucion->refresh();
    }
}

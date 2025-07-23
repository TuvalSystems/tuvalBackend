<?php

namespace App\Services\Aprobaciones;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpDatosItemsL;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpPuntoVenta;
use App\Services\Api\MswService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use App\Traits\JsonResponseTrait;
use DateTime;
use App\Models\GpPedido;
use Illuminate\Support\Arr;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRegistroCopia;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Api\ApiGpService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalService implements ApprovalServiceInterface
{
    use JsonResponseTrait;
    protected $functionService;
    protected $notificationService;
    protected $invoiceService;
    protected $mswService;
    protected $apiGpService;
    protected $stockWarehouseService;
    public function __construct(
        FunctionsServiceInterface $functionService,
        NotificationServiceInterface $notificationService,
        InvoiceServiceInterface $invoiceService,
        MswService $mswService,
        ApiGpService $apiGpService,
        StockWarehouseServiceInterface $stockWarehouseServiceInterface
    ) {
        $this->functionService = $functionService;
        $this->notificationService = $notificationService;
        $this->invoiceService = $invoiceService;
        $this->mswService = $mswService;
        $this->apiGpService = $apiGpService;
        $this->stockWarehouseService = $stockWarehouseServiceInterface;
    }

    // aprobacion comercial tanto de pedido como de cotizaciones

    public function checkoutApprovalComercial($data, $order = false)
    {
        // TODO: Implement checkoutApprovalComercial() method.
        $return = ['requiere' => 'no', 'motivo' => null];

        $nivelFormaPagoCliente = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL'])->orderBy('DESC_FORMA_PAGO_CODIGO')->first();
        if (!$nivelFormaPagoCliente) {
            return [
                'requiere' => 'no',
                'motivo' => "Forma de pago no encontrada en la base de datos."
            ];
        }
        $nivelCliente = $nivelFormaPagoCliente->nivel;


        $nivelFormaPagoCotizacion = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO'])->orderBy('DESC_FORMA_PAGO_CODIGO')->first();
        if (!$nivelFormaPagoCotizacion) {
            return [
                'requiere' => 'no',
                'motivo' => "Forma de pago de cotizacion no encontrada en la base de datos."
            ];
        }
        $nivelCotizacion = $nivelFormaPagoCotizacion->nivel;


        $porcentajeMargen = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', 'VTA-BNS-L')->get()->first();
        if (!$porcentajeMargen) {
            return [
                'requiere' => 'yes',
                'motivo' => "Porcentaje de margen no encontrado en la base de datos."
            ];
        }


        if (!empty($data['cotizacion_id']) && isset($data['cotizacion']) && $data['cotizacion_id'] != '') {
            $cotizacion = GpCotizacion::where('id', $data['cotizacion_id'])->first();
            if (!$cotizacion) {
                return [
                    'requiere' => 'no',
                    'motivo' => "Cotizacion no encontrada en la base de datos."
                ];
            }



            $nivelFormPagoCotizacionAnterior = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion->DESC_FORMA_PAGO_CODIGO)
                ->orderBy('DESC_FORMA_PAGO_CODIGO')->first();
            $nivelAnterior = $nivelFormPagoCotizacionAnterior && isset($nivelFormPagoCotizacionAnterior->nivel) ? $nivelFormPagoCotizacionAnterior->nivel : 0;

            // si la forma de pago de la cotizacion es mayor que la del cliente
            if ($nivelCotizacion > $nivelAnterior && $nivelCotizacion > $nivelCliente) {
                $cotizacion->aprobado_comercial = 'no';
                $cotizacion->save();
                return [
                    'requiere' => 'yes',
                    'motivo' => "Forma de pago de cotizacion mayor que la del cliente modificando cotizacion: " . $nivelCotizacion . ' - ' . $nivelCliente
                ];
            }

            //detalle de la cotizacion
            foreach ($data['cotizacion'] as $detalle) {
                if (!empty($detalle['detalle_id'])) {
                    $detOriginal = GpCotizacionDetalle::find($detalle['detalle_id']);
                    if (!$detOriginal)
                        continue;

                    //VALIDACIONES PARA EL PEDIDO
                    if ($order) {
                        //validaciones del descuento en caso de que sea pedido
                        if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $detOriginal->descuento) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => "Descuento del detalle diferente al descuento original -" . $detalle['detalle_id']
                            ];
                        }

                        //validaciones para el valor en caso de que sea pedido
                        if ($detalle['ART_VALOR'] < $detOriginal->ART_VALOR) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => "Valor menor que el detalle Original -" . $detalle['detalle_id'] . ' - ' . $detalle['ART_VALOR']
                            ];
                        }
                    }

                    //VALIDACIONES POR EL TIO DE ARTICULO
                    if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                        foreach ($detalle['proveedores'] as $proveedor) {
                            $proveedorOriginal = GpCotizacionDetalleProveedores::where('detalle_id', $detOriginal->id)->first();
                            if ($proveedor['MARGEN'] < $proveedorOriginal->MARGEN) {
                                return [
                                    'requiere' => 'yes',
                                    'motivo' => "Margen del producto L es menor al margen aprobado en la cotización."
                                ];
                            }
                        }
                    } else {
                        if ($detalle['ART_DESCRIPCION'] != $detOriginal->ART_DESCRIPCION) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => 'Descripcion del detalle diferente a la original -' . $detalle['detalle_id']
                            ];
                        }

                        if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] > $detOriginal->descuento) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => "Descuento del detalle diferente al descuento original - {$detalle['detalle_id']}."

                            ];
                        }
                    }
                }
            }


            //chequeo del total corrregido
            if (
                $cotizacion->requiere_aprobacion_comercial == 'yes' &&
                $cotizacion->aprobado_comercial == 'yes' &&
                abs($data['total'] - $cotizacion->total) <= 0.10
            ) {
                return [
                    'requiere' => 'no',
                    'motivo' => "Total dentro del rango  permitido"
                ];
            }
        }
        if ($order) {
            $cotizacion = GpCotizacion::find($data['cotizacion_id']);
            if ($cotizacion && $cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial != 'yes') {
                return [
                    'requiere' => 'yes',
                    'motivo' => "Requiere aprobación comercial. El pedido generado desde 'Volver a pedir' no cuenta con la aprobación de la cotización {$cotizacion->cotizacion}."

                ];
            }
        }
        // validacion para "volver a pedir"
        if (!$order) {
            $registroVolverAPedir = GpRegistroCopia::where('id', $data['registro_copia'])->where('tipo', 'volver_a_pedir')->first();
            if ($registroVolverAPedir) {
                $desde = $registroVolverAPedir->pedido_origen ? 'pedido' : 'cotizacion';
                $origen = $registroVolverAPedir->pedido_origen
                    ? 'PED' . str_pad($registroVolverAPedir->pedido_origen, 8, "0", STR_PAD_LEFT)
                    : 'COT' . str_pad($registroVolverAPedir->cotizacion_origen, 8, "0", STR_PAD_LEFT);
                $fechaOrigen = $registroVolverAPedir->pedido_origen
                    ? optional(GpPedido::find($registroVolverAPedir->pedido_origen))->created_at
                    : optional(GpCotizacion::find($registroVolverAPedir->cotizacion_origen))->created_at;

                $origenEntidad = $registroVolverAPedir->pedido_origen
                    ? GpPedido::find($registroVolverAPedir->pedido_origen)
                    : GpCotizacion::find($registroVolverAPedir->cotizacion_origen);

                // para validar si el origen esta en espera y no ha sido aprobado
                if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial == 'yes' && $origenEntidad->aprobado_comercial != 'yes') {
                    return [
                        'requiere' => 'yes',
                        'motivo' => "Cotización desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $origen, fecha: " . substr($fechaOrigen, 0, 10)
                    ];
                }

                // Motivo normal por ser copia
                return [
                    'requiere' => 'yes',
                    'motivo' => "Cotización desde 'Volver a Pedir' ($desde original: $origen, fecha: " . substr($fechaOrigen, 0, 10) . ")"
                ];
            }
        }

        //validar nivel de forma de pago Mayor que del cliente

        if ($nivelCotizacion > $nivelCliente) {
            return [
                'requiere' => 'yes',
                'motivo' => "Forma de pago de cotizacion mayor que la del cliente: ' . $nivelCotizacion . ' > ' . $nivelCliente"
            ];
        }

        // Porcentaje de descuento general diferente
        if ($data['porcentage_descuento'] != $data['porcentage_descuentooriginal']) {
            return [
                'requiere' => 'yes',
                'motivo' => "Porcentaje de descuento general diferente a la original"
            ];
        }

        // validacion de cada detallle
        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        foreach ($data['cotizacion'] as $detalle) {
            //validacin de descuento por detalle
            if ($detalle['descuento'] != $detalle['descuento_original']) {
                return [
                    'requiere' => 'yes',
                    'motivo' => "Porcentaje de descuento general diferenye a la original"
                ];
            }

            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                foreach ($detalle['proveedores'] as $proveedor) {
                    $margen = number_format(($proveedor['COSTO_UNITARIO'] /
                        (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100), 4, '.', '');

                    if ($margen > $detalle['ART_VALOR']) {
                        return [
                            'requiere' => 'yes',
                            'motivo' => "Margen del producto L es mayor al valor del producto"
                        ];
                    }
                }
            } else {
                if ($detalle['ART_VALOR'] < $detalle['ART_VALOR_ORIGINAL']) {
                    return [
                        'requiere' => 'yes',
                        'motivo' => "El valor del producto es meor que el valor original"
                    ];
                }

                if ($detalle['ART_VALOR'] > ($detalle['ART_VALOR_ORIGINAL'] * (1 + ($ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO) / 100))) {
                    return [
                        'requiere' => 'yes',
                        'motivo' => "El valor del producto es mayor que el valor original y supera el margen permitido - {$porcentajeMargen->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO}"
                    ];
                }
                if ($detalle['ART_TIPO_COMPRA']  != 'L') {
                    if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL']) {
                        return [
                            'requiere' => 'yes',
                            'motivo' => "La descripcion del producto es diferente a la original"
                        ];
                    }
                }
            }
        }
        return $return;
    }
    /*** aprovacion crediciticia *** */

    public function checkoutApprovalCredit($data)
    {
        if ($data['COND_ID'] == 'CONTADO') {
            return 'no';
        }

        $params = [
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => Arr::get($data, 'CLI_RUC'),
        ];
        $cupoDisponible = $this->mswService->cobranzas('ConsultaCupoDisponibleCliente', $params);
        if ($data['total'] >  $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_DISPONIBLE_GP']) {
            return 'Cupo';
        }

        $documentosVencidos = $this->mswService->cobranzas('ConsultaDocumentosVencidosCliente', $params);
        if ($documentosVencidos['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'][0]['DV_SALDO_PENDIENTE'] > 0) {
            return 'Documentos Vencidos';
        }

        $documentosSinSoporte = $this->mswService->cobranzas('ConsultaDocumentosSinSoporteCliente', $params);
        if ($documentosSinSoporte['ConsultaDocumentosSinSoporteClienteResult']['DOCUMENTOS_SIN_SOPORTE'][0]['DS_SALDO_PENDIENTE'] > 0) {
            return 'Saldo Pendiente';
        }

        return 'no';
    }

    public function checkoutApprovalDevoluciones($data)
    {

        $tipoDevolucion = Arr::get($data, 'devolucion.tipo_devolucion');
        $volverAFacturar = Arr::get($data, 'devolucion.volver_a_facturar', false);
        $detallePedido = Arr::get($data, 'detallepedido', []);
        $fechaFactura = Arr::get($data, 'pedido.fecha_factura', null);
        $daysDifference = $this->daysDifference($fechaFactura);
        $hasTypeLItems = collect($detallePedido)->contains(
            fn($detalle) => strtolower(Arr::get($detalle, 'ART_TIPO_COMPRA', '')) === 'l'
        );

        // Verifica si el pedido tiene detalles de orden despachados
        // Esto es necesario para determinar si se requiere despacho
        // en caso de que la devolucion sea parcial o total
        // y no se vuelva a facturar
        $requiresDispatch = $this->hasDispatchedOrderDetails(Arr::get($data, 'pedido', []), $detallePedido, $volverAFacturar);


        if ($requiresDispatch) {
            return 'yes';
        }

        if ($tipoDevolucion === 'parcial') {
            if ($volverAFacturar) {
                return 'yes';
            }
            if ($hasTypeLItems) {
                return 'yes';
            }
            return $daysDifference <= 7 ? 'no' : 'yes';
        }

        if ($tipoDevolucion === 'total') {
            if ($volverAFacturar) {
                return 'yes';
            }
            if ($hasTypeLItems) {
                return 'yes';
            }
            return $daysDifference <= 7 ? 'no' : 'yes';
        }

        return 'yes';
    }


    public function daysDifference($fecha)
    {
        if (!$fecha)
            return 0;

        $origin = new DateTime($fecha);
        $target = new DateTime('now');
        return $origin->diff($target)->days;
    }


    public function hasDispatchedOrderDetails($pedido, $detallepedido, $volverAFacturar): bool
    {
        if ($volverAFacturar === true) {
            $lastOrder = GpPedido::where('cotizacion_id', $pedido['cotizacion_id'])
                ->where('estado', '!=', 'anulada')
                ->orderByDesc('id')
                ->first();
            if (!$lastOrder) {
                return false;
            }
            $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
            return GpPedidoDetalle::where('pedido_id', $lastOrder->id)
                ->whereIn('ART_CODIGO', $codigos)
                ->where('trasladado', '>', 0)
                ->exists();
        }

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




    /**
     *  FUNCIONES PARA LA APROBACIÓN CREDITICIA DE PEDIDOS
     */

    public function getOrderApprovalData($data)
    {
        try {
            $detallePedido = GpPedidoDetalle::where('pedido_id', $data['id'])->get()->toArray();
            $alert = $this->functionService->consultStockDetail($data, $detallePedido, $data['id']);
            if ($alert) {
                return response()->json(['success' => false, 'message' => "Pedido {$data['pedido']} rechazado por falta de Stock :" . $alert], 200);
            }
            return $this->buildSuccessResponse("Pedido {$data['pedido']} aprobado", 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 402);
        }
    }



    public function approveCreditOrder($data)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $pedido = GpPedido::where('id', $data['id'])->first();
            if (!$pedido) {
                DB::rollBack();
                return $this->buildErrorResponse("Pedido no encontrado", 404);
            }
            $pedido->aprobado_crediticio = 'yes';
            $pedido->aprobador_crediticio_id = $user->id;
            $pedido->save();

            $this->invoiceService->checkSendInvoice($pedido);
            $this->updateOrderStockOnCreditOrCommercialApproval($pedido, 'crediticia');
            DB::commit();
            return $this->buildSuccessResponse("Pedido {$data['pedido']} aprobado", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al aprobar el pedido {$data['pedido']} - function approveCreditOrder: " . $e);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function denyCreditApproval($data)
    {
        $user = Auth::user();
        $pedido = GpPedido::where('id', $data['id'])->whereNotIn('estado', ['eliminado', 'anulada'])->first();
        if (!$pedido) {
            return $this->buildErrorResponse("Pedido no encontrado", 404);
        }
        $pedido->estado = 'rechazado';
        $pedido->aprobado_crediticio = 'yes';
        $pedido->aprobador_crediticio_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->cleanDetailOrderStock($pedido);
        return $this->buildSuccessResponse("Pedido {$data['pedido']} rechazado", 200);
    }


    public function rejectCreditOrderForInsufficientStock($data)
    {
        $user = Auth::user();
        $pedido = GpPedido::where('id', $data['id'])->whereNotIn('estado', ['eliminado', 'anulada'])->first();
        $pedido->estado = 'rechazado';
        $pedido->aprobado_crediticio = 'yes';
        $pedido->aprobador_crediticio_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->cleanDetailOrderStock($pedido);
        $this->notificationService->sendNotification($pedido->user_id, 'Pedido rechazado por falta de stock', $data['alert']);
        return $this->buildErrorResponse($data['alert'], 422);
    }

    public function cleanDetailOrderStock($pedido)
    {
        $bodega = $pedido->BOD_BODEGA;
        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->pluck('id')->toArray();
        GpPedidoDetalleStock::whereIn('detalle_id', $detalles)
            ->where('STO_ART_BODEGA', $bodega)->update(['reservado' => 0]);
    }



    /**  FUNCIONES DE APROBACIÓN COMERCIAL DE PEDIDOS Y COTIZACIONES  */

    public function approveComercialOrder($data)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $pedido = GpPedido::where('id', $data['id'])->first();
            if (!$pedido) {
                DB::rollBack();
                return $this->buildErrorResponse("Pedido no encontrado", 404);
            }
            $pedido->aprobado_comercial = 'yes';
            $pedido->aprobador_comercial_id = $user->id;
            $pedido->save();

            $this->invoiceService->checkSendInvoice($pedido);
            $this->updateOrderStockOnCreditOrCommercialApproval($pedido, 'comercial');
            DB::commit();
            return $this->buildSuccessResponse("Pedido {$data['pedido']} aprobado", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al aprobar el pedido {$data['pedido']} - function approveCreditOrder: " . $e);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function denyApproveComercialOrderForInsufficientStock($data)
    {
        $user = Auth::user();
        $pedido = GpPedido::where('id', $data['id'])->first();
        if (!$pedido) {
            return $this->buildErrorResponse("Pedido no encontrado", 404);
        }
        $pedido->estado = 'rechazado';
        $pedido->aprobado_comercial = 'yes';
        $pedido->aprobador_comercial_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->cleanDetailOrderStock($pedido);
        $this->notificationService->sendNotification($pedido->user_id, 'Pedido rechazado por falta de stock', $data['alert']);
        return $this->buildSuccessResponse("Pedido {$data['pedido']} rechazado", 200);
    }


    public function denyApproveComercialOrder($data)
    {
        $user = Auth::user();
        $pedido = GpPedido::where('id', $data['id'])->first();
        if (!$pedido) {
            return $this->buildErrorResponse("Pedido no encontrado", 404);
        }
        $pedido->estado = 'rechazado';
        $pedido->aprobado_comercial = 'yes';
        $pedido->aprobador_comercial_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->cleanDetailOrderStock($pedido);
        return $this->buildSuccessResponse("Pedido {$data['pedido']} rechazado", 200);
    }


    public function updateOrderStockOnCreditOrCommercialApproval($order, $typeApproval)
    {
        $requiresComercialApproval = $order->requiere_aprobacion_comercial === 'yes';
        $requiresCreditApproval = $order->requiere_aprobacion_crediticia === 'yes';
        $isComercialApproved = $order->aprobado_comercial === 'yes';
        $isCreditApproved = $order->aprobado_crediticio === 'yes';

        // solo requiere aprobacion comercial
        if ($requiresComercialApproval  && !$requiresCreditApproval) {
            if ($isComercialApproved  && !$isCreditApproved && $typeApproval == 'comercial') {
                $this->updateStockOnOrderApproval($order);
            }
        }

        // solo requiere aprobacion crediticia
        if (!$requiresComercialApproval && $requiresCreditApproval) {
            if (!$isComercialApproved && $isCreditApproved && $typeApproval == 'crediticia') {
                $this->updateStockOnOrderApproval($order);
            }
        }
        // requiere ambas aprobaciones
        if ($requiresComercialApproval && $requiresCreditApproval) {
            if ($isComercialApproved && $isCreditApproved && $typeApproval == 'crediticia') {
                $this->updateStockOnOrderApproval($order);
            }
        }
    }


    public function updateStockOnOrderApproval($order)
    {
        $detalles = GpPedidoDetalle::with('pedidoDetalleStocks')
            ->where('pedido_id', $order->id)
            ->where('ART_TIPO_COMPRA', '!=', 'L')->get();
        if ($detalles->isEmpty()) {
            return;
        }
        $flatDetails  = $this->stockFormatterForUpdateApproval($detalles);
        $parmas = $this->stockFormatterParametersUpdateApproval($flatDetails);
        $stocksResult =  $this->getStockWarehouseAsyncAndFormantter($parmas);
        $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);

        $this->updateOrderDetailStockAndGetStockDisponible($detalles,  $newFormatStock, $order->id);
    }


    public function stockFormatterForUpdateApproval($detalles)
    {
        return $detalles->pluck('pedidoDetalleStocks')
            ->flatten()
            ->map(function ($stock) use ($detalles) {
                $detalle = $detalles->firstWhere('id', $stock->detalle_id);
                return [
                    'stock_id'                 => $stock->id,
                    'pedido_id'                => $stock->pedido_id,
                    'detalle_id'               => $stock->detalle_id,
                    'STO_ART_ARTICULO'         => $detalle->ART_CODIGO,
                    'STO_ART_BODEGA'           => $stock->STO_ART_BODEGA,
                    'STO_ART_STOCK_DISPONIBLE' => $stock->STO_ART_STOCK_DISPONIBLE,
                    'seleccionado'             => $stock->seleccionado
                ];
            })->toArray();
    }

    public function stockFormatterParametersUpdateApproval($newDetails)
    {
        return collect($newDetails)
            ->map(fn($d) => ['articulo' => $d['STO_ART_ARTICULO'], 'bodega' => $d['STO_ART_BODEGA']])
            ->unique()
            ->values()
            ->toArray();
    }

    public function getStockWarehouseAsyncAndFormantter($parmas)
    {
        $stocksResult =  $this->apiGpService->getStockWarehouseAsync($parmas);
        return $this->formatResultStocksApproval($stocksResult);
    }

    public function formatResultStocksApproval($stockResults)
    {
        if (!is_array($stockResults)) {
            return [];
        }
        return collect($stockResults)
            ->filter(fn($item) => is_array($item))
            ->map(fn($item) => [
                'STO_ART_ARTICULO' => $item['ARTICULO'] ?? '',
                'STO_ART_BODEGA' => $item['BODEGA'] ?? '',
                'STO_ART_STOCK_DISPONIBLE' => number_format((float) $item['STOCK_DISPONIBLE'], 2, '.', '') ?? 0,
            ])
            ->values()
            ->toArray();
    }

    public function formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult)
    {
        $stockMap = collect($stocksResult)->keyBy(function ($stock) {
            return $stock['STO_ART_ARTICULO'] . '|' . $stock['STO_ART_BODEGA'];
        });
        return collect($flatDetails)->map(function ($detail) use ($stockMap) {
            $key = $detail['STO_ART_ARTICULO'] . '|' . $detail['STO_ART_BODEGA'];
            $stockDisponible = $stockMap->get($key)['STO_ART_STOCK_DISPONIBLE'] ?? 0;
            return  [
                'stock_id' => $detail['stock_id'],
                'pedido_id' => $detail['pedido_id'],
                'detalle_id' => $detail['detalle_id'],
                'STO_ART_ARTICULO' => $detail['STO_ART_ARTICULO'],
                'STO_ART_BODEGA' => $detail['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => $stockDisponible,
                'seleccionado' => $detail['seleccionado']
            ];
        });
    }




    public function updateOrderDetailStockAndGetStockDisponible($detalles, $newFormatStock, $orderId)
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockWarehouseService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, $orderId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }
        if ($allFormattedStock->isEmpty()) {
            return;
        }
        foreach ($allFormattedStock as $stock) {
            if (isset($stock['stock_id']) && ($st = GpPedidoDetalleStock::find($stock['stock_id']))) {
                $st->STO_ART_STOCK_DISPONIBLE = $stock['STO_ART_STOCK_DISPONIBLE'];
                $st->reservado = $stock['seleccionado'];
                $st->save();
            }
        }
    }
}

<?php

namespace App\Services\Devoluciones;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Services\Api\ApiSalesService;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use Illuminate\Support\Arr;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\GpPedidoDetalleStock;
use Illuminate\Support\Facades\Auth;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Http\Controllers\WebserviceController;
use App\Http\Controllers\ventas\PedidosController;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Services\Api\ApiSeedBellingService;
use App\Traits\Devoluciones\DevolucionesCriteriosTrait;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;

class   DevolucionService implements DevolucionServiceInterface
{
    use DevolucionesCriteriosTrait,
        JsonResponseTrait;
    protected WebserviceController $webservice;
    protected PedidosController $pedidosController;
    protected ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService;
    protected ApiSalesService $apiSales;
    protected ApiSeedBellingService $apiSeedBelling;
    protected ApprovalServiceInterface $approvalService;
    protected DevolucionRepositoryInterface $devolucionRepository;
    protected NotificationServiceInterface $notificationService;

    public function __construct(
        WebserviceController $webservice,
        PedidosController $pedidosController,
        ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService,
        ApiSalesService $apiSales,
        ApiSeedBellingService $apiSeedBelling,
        ApprovalServiceInterface $approvalService,
        DevolucionRepositoryInterface $devolucionRepository,
    ) {
        $this->webservice = $webservice;
        $this->pedidosController = $pedidosController;
        $this->apiGpReturnsIntegrationService = $apiGpReturnsIntegrationService;
        $this->apiSales = $apiSales;
        $this->apiSeedBelling = $apiSeedBelling;
        $this->approvalService = $approvalService;
        $this->devolucionRepository = $devolucionRepository;
    }

    public function processDevolucion($devolucion)
    {

        // $criterio = $this->getCriterioDevolucion($devolucion);
        // if (!$criterio) {
        //     return $this->buildErrorResponse('Criterio de devolución no encontrado', 404);
        // }
        // if ($criterio['generadevolucionventa'] === true) {
        //       $this->generarDevolucionVenta($devolucion);
        // }


        try {
            $criterio = $this->getCriterioDevolucion($devolucion);
            if (!$criterio) {
                return $this->buildErrorResponse('Criterio de devolución no encontrado', 404);
            }

            if (!empty($criterio['metodo']) && method_exists($this, $criterio['metodo'])) {
                $this->{$criterio['metodo']}($devolucion);
            }

            if ($criterio['generarnotadecredito'] === true) {
                $ntCredito =  $this->generarNotaDeCredito($devolucion);
                if (!$ntCredito->isSuccessful()) {
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

            return $this->buildSuccessResponse('Devolución procesada correctamente',  200);
        } catch (\Throwable $e) {
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
        GpOrdenDespacho::where('pedido_id', $pedidoId) // Filtrar por el ID del pedido
            ->update(['estado' => 'cancelada']);
    }



    public function generarDevolucionVenta($devolucion)
    {
        $userId = Auth::user()->id ?? 'adminWeb';

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
            'IDCompania' => env('ID_COMPANIA'),
            'Documento' => $devolucion->devolucion,
            'PedidoOrigen' => $pedido->pedido,
            'Bodega' => $pedido->BOD_BODEGA,
            'Observacion' => $devolucion->DOC_GENERADO,
            'Usuario' => $userId,
            'Items_Devolucion' => $itemsDevolucion,
        ];

        // 🚀 Llamada al servicio
        $res1 = $this->apiGpReturnsIntegrationService->generateSalesReturn($parms);

        // ✅ Validación de la respuesta
        $codigo = Arr::get($res1, '0.codigo', 99); // Si no existe 'codigo', se asume error 99
        $success = Arr::get($res1, '0.success', false);
        $mensaje = Arr::get($res1, '0.mensaje', 'Error desconocido');

        if (!$success) {
            Log::error("Error en procesar la devolución: Código $codigo - $mensaje - " . json_encode($res1));
            $devolucion->AJ_VTA_MENSAJE = "Error en la devolucion: $codigo - $mensaje";
            $devolucion->devolucion_bodega = false;
            $devolucion->save();
            throw new \Exception('Error en la devolución: ' . ($codigo ?? 'Código desconocido') . ' - ' . ($mensaje ?? 'Error desconocido'));
        }

        // 🟢 Actualización correcta
        $devolucion->devolucion_bodega = true;
        $devolucion->save();
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
        return $this->devolucionRepository->validateItemLDetailInvoice($devolucion,);
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
        $cancelResponse  = $this->apiInvalidateGpVoucher($order);
        if ($cancelResponse['success'] === false) {
            $this->saveBaseCancellationInfo($order, $cancelResponse,  $devolucion);

            throw new \Exception('La devolución es válida para anulación, pero ' . $cancelResponse['message'] ?? 'Error al anular la factura');
            // return $this->buildErrorResponse($cancelResponse['message'] ?? 'Error al anular la factura', 400);
        }
        $this->saveFailedCancellation($order, $cancelResponse, $devolucion);
        $this->sendSeedBilling($order, $accessKeyResponse['claveAcceso'], $devolucion);
        return $this->buildSuccessResponse('Factura anulada correctamente', 200);
    }

    public  function saveBaseCancellationInfo($order, $cancelResponse, $devolucion)
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
                'estado' =>  'enviada',
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

        return  $this->apiSeedBelling->anularComprobante(array(
            'claveAcceso' => $accesCode,
        ));
    }

    public function apiUpdateTbhFacAnuAutorize($factura)
    {
        $this->apiSales->updatedStatusDocumentGp(array(
            'numdoc' => $factura,
        ));
    }


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
        $return->despachado = Arr::get($data, 'pedido.despachado');
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

        $this->storeReturnDetail($return->id, Arr::get($data, 'detallepedido', []));
        return $return;
    }

    public function generateReturnCode(int $id): string
    {
        return 'DEV' . str_pad($id, 8, '0', STR_PAD_LEFT);
    }

    public function storeReturnDetail($returnId, $details): void
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
            $detail->despachado_parcial = (float) Arr::get($item, 'despacho');
            $detail->save();
        }
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
}

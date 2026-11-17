<?php

namespace App\Http\Controllers\Ventas\Cotizaciones;

use App\Services\Ventas\Compartido\Contracts\IdempotentSaveServiceInterface;
use App\Http\Requests\Venta\Cotizaciones\GuardarCotizacionProspectoRequest;
use App\Http\Controllers\Controller;
use App\Models\Integraciones\Sincronizacion\GpCronLog;
use App\Models\Ventas\Clientes\GpProspecto;
use App\Traits\JsonResponseTrait;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\CondicionesPago\GpDescuentoFormaPago;
use Illuminate\Http\Request;
use App\Services\Integraciones\Soap\LegacyGpSoapService;
use App\Http\Requests\Venta\Cotizaciones\ValidateStoreQuotationRequest;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Comercial\CommercialVisibilityService;
use App\Services\Ventas\Clientes\Contracts\ClientQueryServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationCreatorServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\ProspectQuoteServiceInterface;
use App\Services\Ventas\Pedidos\Contracts\InvoiceHistoryServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Response;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;

class CotizacionesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    private const LOG_CHANNEL = 'cotizacion';

    public function __construct(
        protected readonly IdempotentSaveServiceInterface $idempotentSave,
        protected readonly QuotationServiceInterface $quotationService,
        protected readonly ClientQueryServiceInterface $clientService,
        protected readonly QuotationCreatorServiceInterface $quotationCreator,
        protected readonly CommercialVisibilityService $commercial,
        protected readonly InvoiceHistoryServiceInterface $invoiceHistory,
        protected readonly ProspectQuoteServiceInterface $prospectService
    ) {}




    public function store(ValidateStoreQuotationRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $user                   = $request->user();
            $imagenesExistentes     = $request->input('imagenes_existentes', []);
            $imagenesNuevas         = $request->file('imagenes_nuevas', []);
            $pdfsNuevos             = $request->file('pdfs_nuevos', []);
            $pdfsExistentes         = $request->input('pdfs_existentes', []);
            $cotizacion = $this->quotationCreator->createOrUpdate(
                $this->idempotentSave->requestData($request),
                $user,
                $imagenesExistentes,
                $imagenesNuevas,
                $pdfsExistentes,
                $pdfsNuevos
            );
            $msg = "Cotización {$cotizacion->cotizacion} guardada correctamente";
            return $this->buildSuccessResponse($msg, 200);
        }, self::LOG_CHANNEL, 'CotizacionesController@store');
    }


    public function guardarCotizacionProspecto(GuardarCotizacionProspectoRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $cotizacion = $this->prospectService->create($request->validated(), $request->user());
            return $this->successResponse(
                "La cotización #{$cotizacion->cotizacion} ha sido guardada",
                ['id' => $cotizacion->id, 'cotizacion' => $cotizacion->cotizacion],
                200
            );
        }, self::LOG_CHANNEL, 'guardarCotizacionProspecto');
    }

    public function vercotizaciones(Request $request): Response|Application|ResponseFactory
    {
        $user = $request->user() ?? GpUsuario::find(137);
        $data = $request->validate([
            'puntodeventa_id' => 'required',
            'estado' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'cotizacion_filtro' => ''
        ]);

        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', data_get($data, 'puntodeventa_id'))->first();

        $cotizaciones = GpCotizacion::with('reserva')->where('gp_cotizacions.PTO_VTA_ID', '=', $data['puntodeventa_id'])
            ->join('gp_descuento_forma_pagos', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions.DESC_FORMA_PAGO_CODIGO')
            ->select('gp_cotizacions.*', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_TIPO_CREDITO');

        $this->commercial->applyLimitsVisibiliteList($cotizaciones, $user, $puntoVenta->id, 'cotizaciones', 'gp_cotizacions.user_id');

        if ($data['estado'] == 'pendientes') {
            $cotizaciones = $cotizaciones
                ->where(function ($q) {
                    $q->where('gp_cotizacions.estado', '!=', 'procesado');
                    $q->where('gp_cotizacions.estado', '!=', 'caducado');
                    $q->where('gp_cotizacions.estado', '!=', 'rechazado');
                })
                ->where(function ($q) {
                    $q->whereNull('prospecto_id');
                    $q->orWhere(function ($q) {
                        $q->whereNotNull('prospecto_id');
                        $q->whereNotNull('cliente_id');
                    });
                });
        } else if ($data['estado'] == 'caducado') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.estado', 'caducado')
                ->whereNull('prospecto_id');
        } else if ($data['estado'] == 'prospectos') {
            $cotizaciones = $cotizaciones
                ->where(function ($q) {
                    $q->whereNotNull('prospecto_id');
                    $q->whereNull('cliente_id');
                })
                ->where('gp_cotizacions.estado', '!=', 'procesado');
        } else if ($data['estado'] == 'procesadas') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.estado', 'procesado');
        } else if ($data['estado'] == 'rechazadas') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.estado', 'rechazado');
        }
        if (isset($data['fecha_desde']) && $data['fecha_desde'] != '' && isset($data['fecha_hasta']) && $data['fecha_hasta'] != '') {
            $cotizaciones = $cotizaciones->whereDate('gp_cotizacions.created_at', '>=', $data['fecha_desde'] . ' 00:00:00');
            $cotizaciones = $cotizaciones->whereDate('gp_cotizacions.created_at', '<=', $data['fecha_hasta'] . ' 23:59:59');
        }
        if (isset($data['cotizacion_filtro']) && $data['cotizacion_filtro'] != '') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.cotizacion', 'like', '%' . $data['cotizacion_filtro'] . '%');
        }
        $cotizaciones = $cotizaciones->orderBy('created_at', 'desc')->limit(1500)->get();



        foreach ($cotizaciones as $cotizacion) {

            $cotizacion->direccion_entrega = GpClienteSucursal::where('id', $cotizacion->sucursal_id)->first();
            $cotizacion->recordatorioventa = GpPedido::where('cotizacion_id', $cotizacion->id)->whereNotNull('factura')->exists() ? 1 : 0;
            $cotizacion->recuperar = 'yes';
            $cotizacion->imprimir = 'yes';
            $cotizacion->facturar = 'no';
            if ($cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial == 'no') {
                $cotizacion->imprimir = 'no';
            } elseif ($cotizacion->requiere_aprobacion_crediticia == 'yes' && $cotizacion->aprobado_crediticio == 'no') {
                $cotizacion->imprimir = 'no';
                $cotizacion->recuperar = 'no';
            }
            if ($cotizacion->impreso == 'yes') {
                $cotizacion->recuperar = 'yes';
                $cotizacion->facturar = 'yes';
            }
            if ($cotizacion->estado == 'rechazado') {
                $cotizacion->imprimir = 'no';
                $cotizacion->recuperar = 'no';
            }
        }
        return response([
            'success' => true,
            'cotizaciones' => $cotizaciones,
        ]);
    }


    public function detallecotizacion(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->all();
            $quote = $this->quotationService->detailQuotation($data, $request->user());
            return $this->successResponse('Información Obtenida Correctamente', $quote, 200);
        }, self::LOG_CHANNEL, 'detallecotizacion');
    }

    /** proceso de recuperar cotizacion optimizado para el nuevo front  */
    public function recuperarCotizacionV2(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $request->validate([
                'id' => 'required|exists:gp_cotizacions,id',
            ]);
            $cotizacion = $this->quotationService->restoreQuotationV2($request->input('id'));
            return $this->successResponse('Información Obtenida Correctamente', $cotizacion, 200);
        }, self::LOG_CHANNEL, 'recuperarCotizacionV2');
    }


    public function volverAPedirCotizacionV2(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $request->validate([
                'id' => 'required|exists:gp_cotizacions,id',
            ]);
            $user = $request->user();
            $info = $this->quotationService->loadQuotationForCopy($request->input('id'), $user);
            return $this->successResponse('Información Obtenida Correctamente', $info, 200);
        }, self::LOG_CHANNEL, 'volverAPedirCotizacionV2');
    }


    /** ELIMINA LAS COTIZACIONES EXPIRADAS SEGÚN LOS DIAS POR PUNTO DE VENTA*/
    public function checkvalidezcotizaciones()
    {
        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');
        $puntosdeventa = GpPuntoVenta::get();
        foreach ($puntosdeventa as $puntodeventa) {
            $cotizaciones = GpCotizacion::where('PTO_VTA_ID', $puntodeventa->PTO_VTA_ID)
                ->where('estado', '!=', 'caducado')
                ->where('estado', '!=', 'procesado')
                ->orderBy('created_at')
                ->get();

            if (count($cotizaciones)) {
                foreach ($cotizaciones as $cotizacion) {
                    $validez_real = $puntodeventa->PTO_VTA_DIA_VALIDEZ_COTIZACION;
                    for ($i = 1; $i <= $validez_real; $i++) {
                        $expira = date('Y-m-d', strtotime($i . ' day', strtotime($cotizacion->created_at)));
                        $parameters = array(
                            'IDCompania' => env('ID_COMPANIA'),
                            'Fecha' => $expira,
                        );
                        $webservices = new LegacyGpSoapService;
                        $diaHabil = $webservices->cobranzas('ConsultaDiaFeriado', $parameters);
                        if ($diaHabil['ConsultaDiaFeriadoResult']['Retorno']['0']['Codigo'] == 0) {
                            $validez_real++;
                        }
                    }
                    $expira = date('Y-m-d', strtotime($validez_real . ' day', strtotime($cotizacion->created_at)));

                    if ($expira < date('Y-m-d')) {
                        $cotizacion->estado = 'caducado';
                        $cotizacion->save();
                    }
                }
            }
        }
        $cronLog = new GpCronLog();
        $cronLog->metodo = 'checkvalidezcotizaciones';
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();
    }

    public function imprimircotizacion(int $id)
    {
        try {
            $cotizacion = $this->quotationService->printQuotationPdf($id, false);
            $pdf = Pdf::loadView('cotizacionPDF', $cotizacion);
            return $pdf->stream('cotizacion-' . $cotizacion['cotizacion'] . '.pdf');
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimircotizacionimagenes(int $id)
    {
        try {
            $cotizacion = $this->quotationService->printQuotationPdf($id, true);
            $pdf = Pdf::loadView('cotizacionImagenesPDF', $cotizacion);
            return $pdf->stream('cotizacion-' . $cotizacion['cotizacion'] . '.pdf');
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimirCotizacionProspecto(int $id): Response|string
    {
        $cotizacionModel = GpCotizacion::where('id', $id)->first();
        $cotizacion = $cotizacionModel->toarray();

        $cotizacionModel->impreso = 'yes';
        if (is_null($cotizacionModel->fecha_impreso)) {
            $cotizacionModel->fecha_impreso = date('Y-m-d H:i:s');
        }
        $cotizacionModel->save();

        $detalle = GpCotizacionDetalle::where('cotizacion_id', $cotizacion['id'])->get()->toarray();
        $peso = 0;
        foreach ($detalle as &$art) {
            $peso += $art['ART_PESO'] * $art['ART_CANTIDAD'];
        }
        $cotizacion['peso'] = $peso;
        $cotizacion['detalle'] = $detalle;
        $formadepago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion['DESC_FORMA_PAGO_CODIGO'])->first()->toarray();
        $cotizacion['formadepago'] = $formadepago['DESC_FORMA_PAGO_TIPO_CREDITO'];

        $cliente = GpProspecto::where('id', $cotizacion['prospecto_id'])->first()->toarray();

        $cotizacion['cliente'] = $cliente;
        //
        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $cotizacion['PTO_VTA_ID'])->first()->toarray();

        if ($puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] == 0) {
            $cotizacion['validez'] = '0 días';
        } else {

            $cotizacion['validez'] = $puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] . ' días hábiles';
        }

        $cotizacion['expira'] = date('Y-m-d', strtotime($puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] . ' day', strtotime($cotizacion['updated_at'])));

        $cotizacion['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];

        $data = $cotizacion;
        //return view('cotizacionPDF', $data);
        $pdf = Pdf::loadView('cotizacionProspectoPDF', $data);
        return $pdf->stream('cotizacion-' . $cotizacion['cotizacion'] . '.pdf');
    }

    public function guardarordendecompra(Request $request): Response|Application|ResponseFactory
    {
        $data = $request->all();

        $cotizacion = GpCotizacion::where('id', $data['id'])->get()->first();
        $cotizacion->orden_de_compra = $data['orden_de_compra'];
        $cotizacion->nota_interna = $data['nota_interna'];
        $cotizacion->save();

        return response([
            'success' => true,
            'message' => 'Orden de compra guardada'
        ]);
    }

    public function guardarmensaje(Request $request): Response|Application|ResponseFactory
    {
        $data = $request->all();

        $cotizacion = GpCotizacion::where('id', $data['id'])->get()->first();
        $cotizacion->orden_de_compra = $data['orden_de_compra'];
        $cotizacion->aprobado_comercial_mensaje = $data['aprobado_comercial_mensaje'];
        $cotizacion->save();

        return response([
            'success' => true,
            'message' => 'Todo guardado'
        ]);
    }


    public function ListaMotivosNoVenta(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $lista = $this->quotationService->getNonSalesReasons();
            return $this->successResponse('Lista Obtenida', $lista);
        }, self::LOG_CHANNEL, 'getlistNonSaleReasons');
    }


    public function registrarOActualizarRecordatorioNoVenta(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->all();
            $this->quotationService->updateNonSaleAndReminderData($data, $request->user());
            return $this->buildSuccessResponse('Recordatorio guardado correctamente');
        }, self::LOG_CHANNEL, 'updateNonSaleAndReminderData');
    }


    /**
     * Summary of sendAllQuotesRecorderSQLServer
     *  Se enviar todas las cotizaciones pendientes de recordatorio de venta a SQL Server que tengan un motivo asignado
     * @return void
     */
    public function sendAllQuotesRecorderSQLServer()
    {
        $cotizaciones = GpCotizacion::with(['usuario', 'usuariorecordatorio', 'motivosNoVenta'])->whereNotNull('cotizacion_venta_motivo_id')
            ->get();
        foreach ($cotizaciones as $cotizacion) {
            $this->quotationService->sendStoreReminderGp($cotizacion);
        }
    }

    /** lista de items alterantivos traidos del apigp -cotizaciones sql server */
    public function getItemsAlternatives(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $item = data_get($request, 'item', '');
            $lista = $this->quotationService->getItemsAlternatives($item);
            return $this->successResponse('Datos Obtenidos Correctamente', $lista, 200);
        }, self::LOG_CHANNEL, 'getItemsAlternatives');
    }

    /**
     * API PARA LISTAR LAS NOVEDADES DE LAS COTIZACIONES
     *
     */
    public function consultQuotationsNews(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $resumen = $this->quotationService->consultQuotationsNews($request->all());
            return $this->successResponse('Datos Obtenidos Correctamente', $resumen, 200);
        }, self::LOG_CHANNEL, 'consultQuotationsNews');
    }

    /***  para obtener el stock del articulo en tiempo real por item y no por cotización  */
    public function obtenerStockItemPorBodega(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'articulo'  => 'required',
                'bodega'    => 'required',
            ]);
            $articulo       = $request->input('articulo', null);
            $bodega         = $request->input('bodega', null);
            $cotizacionId   = $request->input('cotizacion_id', null);
            $response       = $this->quotationService->getStockItemsForWarehouse($articulo, $bodega, $cotizacionId);
            return $this->successResponse('Stock obtenido correctamente', $response, 200);
        }, self::LOG_CHANNEL, 'obtenerStockItemPorBodega');
    }

    public function obtenerStockItemsLote(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'articulos'     => 'required|array',
                'bodega'        => 'required|string',
                'tipo'          => 'nullable|string',
                'cotizacion_id' => 'nullable|integer',
            ]);

            $items          = data_get($validated, 'articulos');
            $bodega         = data_get($validated, 'bodega');
            $cotizacionId   = data_get($validated, 'cotizacion_id', null);
            $cotizacionId = $cotizacionId !== null ? (int) $cotizacionId : null;
            $response = $this->quotationService->getStockItemsForWarehouseV2($items, $bodega, $cotizacionId);
            return $this->successResponse('Lote obtenido correctamente', $response, 200);
        }, self::LOG_CHANNEL, 'obtenerStockItemsLote');
    }

    public function obteneMargenItemlL(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'articulo' => 'required',
            ]);
            $datosL = $this->quotationService->getMarginItem($request->articulo);
            return $this->successResponse('Información obtenida correctamente', $datosL, 200);
        }, self::LOG_CHANNEL, 'obteneMargenItemlL');
    }



    /** seccion de cliente  */


    public function validarSegmentoCliente(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $ruc = $request->input('ruc', null);
            $hasSegment = $this->quotationService->checkClientWithSegmentExist($ruc);
            if ($hasSegment['success'] === true) {
                return $this->buildExistResponse(true, 'Segmento encontrado', 200);
            }
            return $this->buildExistResponse(false, 'Segmento no encontrado', 202);
        }, self::LOG_CHANNEL, 'validarSegmentoCliente');
    }

    public function actualizarSegmentoCliente(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $ruc            = $request->input('ruc', null);
            $segmento       = $request->input('segmento', null);
            $subsegmento    = $request->input('subsegmento', null);
            $this->quotationService->updateClientWithSegment($ruc, $segmento, $subsegmento);
            return $this->buildSuccessResponse('Segmento del cliente actualizado correctamente', 200);
        }, self::LOG_CHANNEL, 'actualizarSegmentoCliente');
    }

    public function obtenerListaPrecioDefectoPuntoVenta(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $pvCodigo = $request->input('pvCodigo', null);
            $response = $this->quotationService->getDefaultPriceListByPos($pvCodigo);
            return $this->successResponse('Información Obtenida Correctamente', $response);
        }, self::LOG_CHANNEL, 'obtenerListaPrecioDefectoPuntoVenta');
    }


    public function obtenerListaProyectosVenta(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->quotationService->getistProyectSellectToGp();
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, self::LOG_CHANNEL, 'obtenerListaProyectosVenta');
    }


    public function obtenerFacturasHistoricasCotizacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'cliente_id'    => 'required|exists:gp_clientes,id',
                'filters'       => 'required|array',
                'pvId'          => 'required|integer',
                'pvCode'        => 'required|string',

            ]);
            $filters    = data_get($validate, 'filters', []);
            $clientId   = data_get($validate, 'cliente_id');
            $pvId       = data_get($validate, 'pvId');
            $pvCode     = data_get($validate,  'pvCode');
            $user = $request->user() ?? GpUsuario::find(179);
            $data = $this->invoiceHistory->fetchInvoiceHistory($user, $clientId, $pvId, $pvCode, $filters);
            return $this->successResponse('Lista Obtenida Correctamente', $data);
        }, self::LOG_CHANNEL, 'obtenerFacturasHistoricasCotizacion');
    }




    /** prospecto  */
    public function recuperarProspecto(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $documento = $request->input('documento');
            $info = $this->prospectService->restoreProspectQuote($documento);
            return $this->successResponse('Información Obtenida Correctamente', $info);
        }, self::LOG_CHANNEL, 'recuperarProspecto');
    }
}

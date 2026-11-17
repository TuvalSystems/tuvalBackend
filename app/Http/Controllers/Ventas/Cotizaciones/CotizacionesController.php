<?php

namespace App\Http\Controllers\Ventas\Cotizaciones;

use App\Services\Ventas\Compartido\Contracts\IdempotentSaveServiceInterface;
use App\Http\Requests\Venta\Cotizaciones\GuardarCotizacionProspectoRequest;
use App\Http\Requests\Venta\Cotizaciones\ListarCotizacionesRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Cotizaciones\GuardarObservacionesCotizacionRequest;
use App\Http\Requests\Venta\Cotizaciones\GuardarMensajeCotizacionRequest;
use App\Models\Integraciones\Sincronizacion\GpCronLog;
use App\Traits\JsonResponseTrait;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use Illuminate\Http\Request;
use App\Services\Integraciones\Soap\LegacyGpSoapService;
use App\Http\Requests\Venta\Cotizaciones\ValidateStoreQuotationRequest;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Clientes\Contracts\ClientQueryServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationCreatorServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationServiceInterface;
use App\Services\Ventas\Cotizaciones\QuotationQueryService;
use App\Services\Ventas\Cotizaciones\QuotationDocumentService;
use App\Services\Ventas\Cotizaciones\Contracts\ProspectQuoteServiceInterface;
use App\Services\Ventas\Pedidos\Contracts\InvoiceHistoryServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CotizacionesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    private const LOG_CHANNEL = 'cotizacion';

    public function __construct(
        protected readonly IdempotentSaveServiceInterface $idempotentSave,
        protected readonly QuotationServiceInterface $quotationService,
        protected readonly ClientQueryServiceInterface $clientService,
        protected readonly QuotationCreatorServiceInterface $quotationCreator,
        protected readonly InvoiceHistoryServiceInterface $invoiceHistory,
        protected readonly QuotationQueryService $quotationQuery,
        protected readonly QuotationDocumentService $quotationDocuments,
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

    public function vercotizaciones(ListarCotizacionesRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            return $this->successResponse(
                'Cotizaciones obtenidas correctamente',
                $this->quotationQuery->paginate($request->validated(), $request->user() ?? GpUsuario::findOrFail(137)),
                200
            );
        }, self::LOG_CHANNEL, 'vercotizaciones');
    }


    public function detallecotizacion(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->validate(['id' => 'required|integer|min:1']);
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

    public function imprimircotizacion(int $id): Response|JsonResponse
    {
        return $this->handleDocumentAction(function () use ($id): Response {
            return $this->buildFileResponse($this->quotationDocuments->printQuotation($id));
        }, self::LOG_CHANNEL, 'imprimircotizacion');
    }

    public function imprimircotizacionimagenes(int $id): Response|JsonResponse
    {
        return $this->handleDocumentAction(function () use ($id): Response {
            return $this->buildFileResponse($this->quotationDocuments->printQuotation($id, true));
        }, self::LOG_CHANNEL, 'imprimircotizacionimagenes');
    }

    public function imprimirCotizacionProspecto(int $id): Response|JsonResponse
    {
        return $this->handleDocumentAction(function () use ($id): Response {
            return $this->buildFileResponse($this->quotationDocuments->printProspectQuotation($id));
        }, self::LOG_CHANNEL, 'imprimirCotizacionProspecto');
    }

    public function guardarordendecompra(GuardarObservacionesCotizacionRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $this->quotationService->updateAnnotations($request->integer('id'), $request->safe()->except('id'));
            return $this->buildSuccessResponse('Orden de compra guardada');
        }, self::LOG_CHANNEL, 'guardarordendecompra');
    }

    public function guardarmensaje(GuardarMensajeCotizacionRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $this->quotationService->updateAnnotations($request->integer('id'), $request->safe()->except('id'));
            return $this->buildSuccessResponse('Todo guardado');
        }, self::LOG_CHANNEL, 'guardarmensaje');
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

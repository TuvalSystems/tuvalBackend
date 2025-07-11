<?php

namespace App\Services\Venta;

use App\Models\GpBodega;
use App\Models\GpCliente;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpUsuario;
use App\Models\GpTempCart;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpDatosItemsL;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Models\GpDescuentoFormaPago;
use Illuminate\Support\Facades\Auth;
use App\Models\GpCotizacionVentaMotivo;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\WebserviceController;
use App\Models\GpArticulo;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class QuotationService implements QuotationServiceInterface
{
    use JsonResponseTrait;
    protected $quotationRepository;
    protected $apiGpService;
    protected $apiBillingService;
    protected $functionService;
    protected $stockWarehouseService;
    public function __construct(
        QuotationRepositoryInterface $quotationRepository,
        ApiGpService $apiGpService,
        ApiBillingService $apiBillingService,
        FunctionsServiceInterface $functionService,
        StockWarehouseServiceInterface $stockWarehouseService
    ) {
        $this->quotationRepository = $quotationRepository;
        $this->apiGpService = $apiGpService;
        $this->apiBillingService = $apiBillingService;
        $this->functionService = $functionService;
        $this->stockWarehouseService = $stockWarehouseService;
    }


    /**
     * OBTENER MOTIVOS DE NO VENTA
     * @return Collection
     */
    public function getNonSalesReasons(): Collection
    {
        return GpCotizacionVentaMotivo::orderBy('id')->get();
    }

    /**
     * ENVIAR LAS COTIZACIONES QUE TENGAN RECORDATORIO A DYNAMIC GP
     * @param GpCotizacion $cotizacion
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */

    public function updateNonSaleAndReminderData($data)
    {
        $user = Auth::user();
        $cotizacion = GpCotizacion::where('id', $data['cotizacion_id'])->update([
            'fecha_recordatorio' => $data['fecha_recordatorio'],
            'cotizacion_venta_motivo_id' => $data['motivo_id'],
            'observacion' => $data['observacion'],
            'recordar' => $data['recordar'],
            'user_ing_recordatorio' => $user->USERID,

        ]);
        $sendQuote = GpCotizacion::with(['usuario', 'usuariorecordatorio', 'motivosNoVenta'])->find($data['cotizacion_id']);
        $this->sendStoreReminderGp($sendQuote);
        return null;
    }


    public function sendStoreReminderGp($cotizacion)
    {
        $parmas = [
            'txCot' => $cotizacion->cotizacion,
            'txUsr' => $cotizacion->usuario->USERID,
            'txCliRuc' => $cotizacion->CLI_RUC,
            'txCliRazSoc' => $cotizacion->CLI_RAZON_SOCIAL,
            'txCotCliSri' => $cotizacion->CLI_CORREO_ELECT_SRI,
            'txEst' => $cotizacion->estado,
            'txBod' => $cotizacion->BOD_BODEGA,
            'txPtoVta' => $cotizacion->PTO_VTA_ID,
            'txPtoVtaDesc' => $cotizacion->PTO_VTA_DESCRIPCION,
            'txCotReqapr' => $cotizacion->requiere_aprobacion_comercial,
            'txCotAprCom' => $cotizacion->aprobado_comercial,
            'txCotMot' => $cotizacion->motivosNoVenta->descripcion,
            'coCotRec' => $cotizacion->recordar,
            'feCotRec' => $cotizacion->fecha_recordatorio,
            'txObsMot' => $cotizacion->observacion,
            'feIng' => date('Y-m-d'),
            'txUsrIng' => $cotizacion->user_ing_recordatorio,
            'feUltmod' => date('Y-m-d'),
            'txUsrMod' => $cotizacion->user_ing_recordatorio
        ];
        $this->apiGpService->storeQuoteReminderGp($parmas);
    }



    /**
     * RECUPERAR COTIZACIONES CON RECORDATORIO PARA HOY PARA PROCESO JOB ENVIO DE NOTIFICACION Y CORREO ELECTRONICO
     * @return Collection
     */

    public function getQuotationsWithReminderToday()
    {
        return $this->quotationRepository->getScheduledReminders();
    }



    /** PROCESO DE RECUPERAR COTIZACION  --- REFACTORIZACIÓN */

    public function restoreQuotation($data, $user)
    {
        $clientes = $this->functionService->getClient($data);
        $bodegas = $this->functionService->getBodegas($data, 1);
        $detalles = $this->getQuotationDetailWithSuppliers($data['id']);
        $arrArticulos = $detalles->pluck('ART_CODIGO')->map(fn($artCode) => ['articulo' => $artCode]);
        $seleccionStockPorArticulo = $this->getSelectedStockByArticle($data['id'], $detalles);
        $stockGeneral =  $this->getStockGeneralRestoreQuotation($arrArticulos, $bodegas);  //obtiene los items formateados
        // $globalStock = $this->getGlobalStockGroupByWarehouse($stockGeneral);  // agrupo por articulo y por bodega
        $this->mergeSelectedStock($stockGeneral, $seleccionStockPorArticulo);
        $availableArticulos = [];
        foreach ($detalles as $item) {
            $artFormat = $this->stockWarehouseService->newFormatStock($stockGeneral, $item->toArray()); // me lo devuelve en array
            if (!$artFormat['disponible']) {
                $clase = GpArticulo::where('ART_CODIGO', $item->ART_CODIGO)->first();
                $item->ART_CODIGO = $this->functionService->findAvailableArticleCodeL($availableArticulos, $clase->ART_CLASE_ARTICULO);
                $availableArticulos[] = $item->ART_CODIGO;
            }
            $item->stock = $artFormat['stock'];
            $item->ART_STOCK = $artFormat['ART_STOCK'];
        }
        $this->functionService->recalculateIva($detalles);
        $this->functionService->setCartFormQuotation($detalles, $data, $user);
        return response([
            'success' => true,
            'cliente' => $clientes,
            'currentSucursal' => $data['sucursal_id'],
            'cotizacion' => $data['id'],
            'test' => 'recuperarcotizacion',
        ]);
    }

    public function getQuotationDetailWithSuppliers($quotationId)
    {
        $details = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $details->each(function ($item) use ($quotationId) {
            $item->proveedores = GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)
                ->where('detalle_id', $item->id)->get()->toArray();
        });
        return $details;
    }
    public function getSelectedStockByArticle($quotationId, $quotationDetails)
    {
        $selectedStock = [];
        foreach ($quotationDetails as $item) {
            $stock = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->where('detalle_id', $item->id)->get()->toArray();
            foreach ($stock as $st) {
                $st['ART_CODIGO'] = $item->ART_CODIGO;
                $selectedStock[] = $st;
            }
        }
        return $selectedStock;
    }


    public function getGlobalStockGroupByWarehouse($globalStock)
    {
        return collect($globalStock)->groupBy('STO_ART_ARTICULO')
            ->map(fn($items) => $items->keyBy('STO_ART_BODEGA'))->toArray();
    }

    public function getStockGeneralRestoreQuotation($articulos, $bodegas)
    {
        $formatStock = [];
        foreach ($articulos as $value) {
            $stockRequests = array_map(fn($whr) => ['articulo' => $value['articulo'], 'bodega' => $whr,], $bodegas);
            $stockResults = array_map(fn($params) => $this->apiGpService->getStockWarehouse($params), $stockRequests);
            $formatted = $this->formatResultStocksQuotation($stockResults);
            $formatStock = array_merge($formatStock, $formatted);
        }

        return $formatStock;
    }


    public function formatResultStocksQuotation($stockResults)
    {
        if (empty($stockResults)) {
            return [];
        }
        $formattedStocks = [];
        foreach ($stockResults as $value) {
            if (!is_array($value)) {
                continue;
            }
            $formattedStocks = array_merge(
                $formattedStocks,
                array_map(
                    fn($item) => [
                        'STO_ART_ARTICULO' => $item['ARTICULO'] ?? '',
                        'STO_ART_BODEGA' => $item['BODEGA'] ?? '',
                        'STO_ART_STOCK_DISPONIBLE' => number_format((float) $item['STOCK_DISPONIBLE'], 2, '.', '') ?? 0,
                    ],
                    array_values($value)
                )
            );
        }
        return $formattedStocks;
    }


    public function mergeSelectedStock(&$stockGeneral, $seleccionStockPorArticulo)
    {
        $seleccionIndex = [];
        foreach ($seleccionStockPorArticulo as $seleccion) {
            $key = $seleccion['ART_CODIGO'] . '|' . $seleccion['STO_ART_BODEGA'];
            $seleccionIndex[$key] = $seleccion['seleccionado'];
        }

        // Aplicar en $stockGeneral
        foreach ($stockGeneral as &$stock) {
            $key = $stock['STO_ART_ARTICULO'] . '|' . $stock['STO_ART_BODEGA'];
            $stock['seleccionado'] = $seleccionIndex[$key] ?? 0;
        }
    }

    public function restoreDescriptionItemQuotation($articulo, $bodega)
    {
        $params = ['articulo' => $articulo, 'bodega' => $bodega];
        $articulo = $this->apiGpService->getStockWarehouse($params);

        if (empty($articulo) || !is_array($articulo) || !isset($articulo[0])) {
            return null;
        }
        $art = $articulo[0] ?? null;
        return $art['DESCRIPCION_ARTICULO'] ?? null;
    }



    /**
     * LISTA DE ALTERNATIVAS
     */

    public function getItemsAlternatives($item)
    {
        return $this->apiGpService->getListQuotationItemsAlternatives(array('item' => $item));
    }



    /**
     *  ACTUALIZACION DE DATOS DE COTIZACIÓN - GPTEMPCART
     */

    public function updateCheckAlternativeItem($id, $checkAlternative)
    {

        GpTempCart::where('id', $id)
            ->update(['CHECK_ALTERNATIVO' => $checkAlternative]);

        return $this->buildSuccessResponse(
            'Check alternativo actualizado correctamente',
            200
        );
    }


    public function syncAndGetCheckAlternativo($data, $articulo)
    {
        if (empty($data['test'])) {
            return 0;
        }

        $item = GpTempCart::find($articulo->id);
        if (!$item) {
            return 0;
        }

        $checkAlternativo = 0;

        switch ($data['test']) {
            case 'recuperarcotizacion':
                $checkAlternativo = $this->handleRecuperarCotizacion($item, $articulo);
                break;
            case 'volver_a_pedir':
                $checkAlternativo = $this->handleVolverAPedir($data, $item, $articulo);
                break;
        }

        return $checkAlternativo ?: 0;
    }

    public function handleRecuperarCotizacion($item, $articulo)
    {
        if (!$item->detalle_id) {
            return 0;
        }

        $detalle = GpCotizacionDetalle::find($articulo->detalle_id);
        if (!$detalle) {
            return 0;
        }

        return $this->updateCheckAlternativeItemType($item, $detalle->CHECK_ALTERNATIVO);
    }

    public function handleVolverAPedir($data, $item, $articulo)
    {

        if (empty($data['registro_copia'])) {
            return 0;
        }

        $registroCopia = GpRegistroCopia::find($data['registro_copia']);
        if (!$registroCopia) {
            return 0;
        }

        if ($registroCopia->cotizacion_origen) {
            return $this->processCotizacionOrigen($registroCopia, $item, $articulo);
        }

        if ($registroCopia->pedido_origen) {
            return $this->processPedidoOrigen($registroCopia, $item, $articulo);
        }

        return 0;
    }

    public function processCotizacionOrigen($registroCopia, $item, $articulo)
    {
        $cotizacion = GpCotizacion::find($registroCopia->cotizacion_origen);
        if (!$cotizacion) {
            return 0;
        }
        $detalle = GpCotizacionDetalle::where('id', $articulo->detalle_id)->where('cotizacion_id', $cotizacion->id)->first();
        if (!$detalle) {
            return 0;
        }
        return $this->updateCheckAlternativeItemType($item, $detalle->CHECK_ALTERNATIVO);
    }

    public function processPedidoOrigen($registroCopia, $item, $articulo)
    {
        $pedido = GpPedido::find($registroCopia->pedido_origen);
        if (!$pedido) {
            return 0;
        }

        $detalle = GpPedidoDetalle::where('ART_CODIGO', $articulo->ART_CODIGO)->where('orden', $articulo->orden)->where('pedido_id', $pedido->id)->first();
        if (!$detalle) {
            return 0;
        }

        return $this->updateCheckAlternativeItemType($item, $detalle->CHECK_ALTERNATIVO);
    }

    public function updateCheckAlternativeItemType($item, $checkAlternative)
    {
        $item->CHECK_ALTERNATIVO = $checkAlternative;
        $item->save();
        return $checkAlternative;
    }



    /**
     * IMPRIMIR COTIZCIONES EN PDF
     */

    public function printQuotationPdf($id)
    {
        $cotizacion = GpCotizacion::where('id', $id)->first()->toArray();

        if (!$cotizacion) {
            return $this->buildErrorResponse(
                'No se encontró la cotización',
                404
            );
        }
        if ($cotizacion['requiere_aprobacion_comercial'] == 'yes' && $cotizacion['aprobado_comercial'] == 'no') {
            return $this->buildErrorResponse(
                'La cotización requiere aprobación comercial y aún no ha sido aprobada',
                403
            );
        }

        if ($cotizacion['requiere_aprobacion_crediticia'] == 'yes' && $cotizacion['aprobado_crediticio'] == 'no') {
            return $this->buildErrorResponse(
                'La cotización requiere aprobación crediticia y aún no ha sido aprobada',
                403
            );
        }
        if ($cotizacion['estado'] == 'caducado') {
            return $this->buildErrorResponse(
                'La cotización ha caducado y no puede ser impresa',
                403
            );
        }

        $this->updatePrintsQuotation($id);
        $detalle = GpCotizacionDetalle::where('cotizacion_id', $id)
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $peso = 0;
        $tipoImpuesto = [];
        $sumaImpuesto = [];

        foreach ($detalle as $item) {
            $clave = $item['ART_IMP_VTA'];
            $subtotal = round($item['ART_VALOR'] * $item['ART_CANTIDAD'], 2);
            $iva = round($item['ART_IVA'], 2);

            $peso += $item['ART_PESO'] * $item['ART_CANTIDAD'];

            $tipoImpuesto[$clave] = ($tipoImpuesto[$clave] ?? 0) + $subtotal;
            $sumaImpuesto[$clave] = ($sumaImpuesto[$clave] ?? 0) + $iva;
        }

        $formadepago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion['DESC_FORMA_PAGO_CODIGO'])->first()->toArray();
        $cliente = GpCliente::where('id', $cotizacion['cliente_id'])->first()->toArray();
        $sucursal = GpClienteSucursal::where('id', $cotizacion['sucursal_facturacion_id'])->first()->toArray();
        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $cotizacion['PTO_VTA_ID'])->first()->toArray();

        $validezDias = $puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] ?? 0;
        $validezTexto = $validezDias === 0 ? '0 días' : $validezDias . " días habiles";
        $validezReal = $this->calcularDiasHabiles($validezDias, $cotizacion['created_at']);

        $cotizacion['peso'] = $peso;
        $cotizacion['detalle'] = $detalle;
        $cotizacion['tipoimpuesto'] = $tipoImpuesto;
        $cotizacion['sumaimpuesto'] = $sumaImpuesto;
        $cotizacion['formadepago'] = $formadepago['DESC_FORMA_PAGO_TIPO_CREDITO'] ?? '';
        $cotizacion['cliente'] = $cliente;
        $cotizacion['sucursal'] = $sucursal;
        $cotizacion['validez'] = $validezTexto;
        $cotizacion['expira'] = $validezReal;
        $cotizacion['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
        return $cotizacion;
    }

    public function calcularDiasHabiles($dias, $desde)
    {
        $webService = new WebserviceController();
        $validez = $dias;
        $fecha = $desde;

        for ($i = 1; $i < $validez; $i++) {
            $fechaPropuesta = date('Y-m-d', strtotime("+$i days", strtotime($fecha)));
            $parameters = [
                'IDCompania' => env('ID_COMPANIA'),
                'Fecha' => $fechaPropuesta,
            ];

            $respuesta = $webService->cobranzas('ConsultaDiaFeriado', $parameters);
            if ($respuesta['ConsultaDiaFeriadoResult']['Retorno'][0]['Codigo'] == 0) {
                $validez++;
            }
        }

        return date('Y-m-d', strtotime("+$validez days", strtotime($desde)));
    }


    public function updatePrintsQuotation($id)
    {
        $cotizacion = GpCotizacion::find($id);
        $cotizacion->impreso = 'yes';

        if (is_null($cotizacion->fecha_impreso)) {
            $cotizacion->fecha_impreso = date('Y-m-d H:i:s');
        }
        $cotizacion->save();
    }


    /**
     *  consulta de cotizaciones con novedades
     * @param array $data
     * @return JsonResponse
     */

    public function consultQuotationsNews($data)
    {
        $periodo = $data['periodo'] ?? null;
        $sucursal = $data['sucursal'] ?? null;
        $vendedor = $data['vendedor'] ?? null;
        $motivo = $data['motivo'] ?? null;

        if (!$periodo) {
            return $this->buildErrorResponse('Debe ingresar un periodo', 400);
        }
        $fechaInit = date('Y-m-01', strtotime($periodo));
        $fechaEnd = date('Y-m-t', strtotime($periodo));

        if (!is_null($sucursal)) {
            $formattedSucursal = strtoupper(trim($sucursal));
            $sucursal = GpBodega::where('BOD_BODEGA', $formattedSucursal)->first();
            if (!$sucursal) {
                return $this->buildErrorResponse('Sucursal no encontrada', 404);
            }
        }

        if (!is_null($motivo)) {
            $motivo = GpCotizacionVentaMotivo::where('descripcion', [strtolower(trim($motivo))])->first();
            if (!$motivo) {
                return $this->buildErrorResponse('Motivo de no venta no encontrado', 404);
            }
        }

        if (!is_null($vendedor)) {
            $vendedor = GpUsuario::where('USERID', trim($vendedor))->first();
            if (!$vendedor) {
                return $this->buildErrorResponse('Vendedor no encontrado', 404);
            }
        }

        $cotizaciones = GpCotizacion::with(['usuario', 'motivosNoVenta'])
            ->when($periodo, function ($query) use ($fechaInit, $fechaEnd) {
                $query->whereDate('created_at', '>=', $fechaInit)
                    ->whereDate('created_at', '<=', $fechaEnd);
            })->when($sucursal, function ($query) use ($sucursal) {
                $query->where('BOD_BODEGA', $sucursal->BOD_BODEGA);
            })->when($vendedor, function ($query) use ($vendedor) {
                $query->where('user_id', $vendedor->id);
            })->when($motivo, function ($query) use ($motivo) {
                $query->where('cotizacion_venta_motivo_id', $motivo->id);
            })
            ->get();

        $resumen = $cotizaciones->groupBy('user_id')->map(function ($group) {

            $sucursal = $group->first()->BOD_BODEGA;
            $usuario = $group->first()->USERID;
            $total = collect($group)->count();
            $facturados = collect($group)->filter(fn($c) => $c->estado === 'procesado' && is_null($c->observacion))->count();
            $facturadosComentados = $group->filter(fn($c) => $c->estado === 'procesado' && !is_null($c->observacion))->count();
            $nofacturadasSinComentario = $group->filter(fn($c) => $c->estado !== 'procesado' && is_null($c->observacion))->count();
            $nofacturadasComentadas = $group->filter(fn($c) => $c->estado !== 'procesado' && !is_null($c->observacion))->count();
            return [
                'usuario' => $usuario,
                'sucursal' => $sucursal,
                'cotizaciones' => $total,
                'cotizaciones_facturadas' => $facturados,
                'cotizaciones_facturadas_comentadas' => $facturadosComentados,
                'cotizaciones_no_facturadas' => $nofacturadasSinComentario,
                'cotizaciones_comentadas' => $nofacturadasComentadas,
                'porcentaje_no_facturadas' => $total > 0 ? round(($nofacturadasComentadas / $total) * 100, 2) : 0,
            ];
        })->values()->toArray();

        return $this->successResponse('Datos Obtenidos Correctamente', $resumen, 200);
    }


    /** funcion para obtener el detalle de la cotización  */

    public function detailQuotation($data)
    {
        try {
            $credito = $this->apiBillingService->consultCreditClient([
                'ruc' => $data['CLI_RUC'],
                'idCompania' => config('services.api_gp.company'),
            ]);
            $estadoCuenta = [
                'cupoDisponible' => $credito['cupos'][0] ?? []
            ];
            $detalle = GpCotizacionDetalle::where('cotizacion_id', $data['id'])->get();
            $porcentajeMargen = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', 'VTA-BNS-L')->first();

            if (!$porcentajeMargen) {
                throw new \Exception('Porcentaje de margen no encontrado');
            }

            $margenBruto = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
            $descuentoGeneral = $data['porcentage_descuento'] ?? 0;

            foreach ($detalle as $det) {
                if ($det->ART_DECIMAL_CANTIDAD == 0) {
                    $det->ART_STOCK = (int) $det->ART_STOCK;
                    $det->ART_CANTIDAD = (int) $det->ART_CANTIDAD;
                }

                $proveedores = $this->getAllProvidersQuotation($data['id'], $det->id);
                foreach ($proveedores as $prov) {
                    $margen = $this->calculateMargen($margenBruto, $descuentoGeneral, $prov->COSTO_UNITARIO);
                    $prov->margen = number_format($margen, 4, '.', '');
                    if ($det->ART_DECIMAL_CANTIDAD == 0) {
                        $prov->CANTIDAD = (int) $prov->CANTIDAD;
                    }
                }
                $det->proveedores = $proveedores;
            }

            return response([
                'success' => true,
                'estadodecuenta' => $estadoCuenta,
                'detalle' => $detalle,
            ]);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function getAllProvidersQuotation($cotizacionId, $cotizacionDetalleId)
    {
        return GpCotizacionDetalleProveedores::query()
            ->join('gp_proveedors', 'gp_proveedors.id', 'gp_cotizacion_detalle_proveedores.ID_PROVEEDOR')
            ->where('gp_cotizacion_detalle_proveedores.cotizacion_id', $cotizacionId)
            ->where('gp_cotizacion_detalle_proveedores.detalle_id', $cotizacionDetalleId)
            ->select([
                'gp_cotizacion_detalle_proveedores.*',
                'gp_proveedors.PROV_RAZON_SOCIAL'
            ])
            ->get();
    }

    public function calculateMargen($margenBruto, $descuentoGeneral, $costo)
    {
        return ($costo / (1 - ($margenBruto / 100))) / (1 - ($descuentoGeneral / 100));
    }
}

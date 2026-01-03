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
use App\Models\GpArticulo;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Models\GpTempCartProveedores;
use App\Models\GpTempCartStock;
use App\Models\GpVendedor;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QuotationService implements QuotationServiceInterface
{
    use JsonResponseTrait;

    public function __construct(
        protected readonly QuotationRepositoryInterface $quotationRepository,
        protected readonly ApiGpService $apiGpService,
        protected readonly ApiBillingService $apiBillingService,
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly OrderAndQuotationServiceInterface $OrderQuotationService,
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService,
        protected readonly QuotationBuilderInterface $quotationBuilder,
        protected readonly OrderBuilderInterface $orderBuilder,
        protected readonly InvoiceServiceInterface $invoiceService
    ) {

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
        $user = GpUsuario::where('id', Auth::user()->id)->first();
        $cotizacion = GpCotizacion::where('id', $data['cotizacion_id'])->update([
            'fecha_recordatorio' => $data['fecha_recordatorio'],
            'cotizacion_venta_motivo_id' => $data['motivo_id'],
            'observacion' => $data['observacion'],
            'recordar' => $data['recordar'],
            'user_ing_recordatorio' => $user->USERID,

        ]);
        $sendQuote = GpCotizacion::with(['usuario', 'usuariorecordatorio', 'motivosNoVenta'])->find($data['cotizacion_id']);
        $params = $this->quotationBuilder->formPayloadSendStoreReminderGp($sendQuote);
        $this->sendStoreReminderGp($params);
        return null;
    }


    public function sendStoreReminderGp($params)
    {
        $this->apiGpService->storeQuoteReminderGp($params);

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
        $detalles = $this->getQuotationDetailWithSuppliers($data['id']);
        $this->functionService->recalculateIva($detalles);
        $this->functionService->setCartFormQuotation($detalles, $data, $user, 'cotizacion');
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);
        return [
            'cliente' => $clientes,
            'currentSucursal' => $data['sucursal_id'],
            'cotizacion_id' => $data['id'],
            'test' => 'recuperarcotizacion',
            'segmento' => $segmento
        ];
    }

    public function getQuotationDetailWithSuppliers($quotationId)
    {
        $details = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $proveedores = $this->getQuotatioDetailProviders($quotationId, $details);
        $detalleStock = $this->getQuotationDetailStock($quotationId, $details);
        $details->each(function ($item) use ($proveedores, $detalleStock) {
            $item->proveedores = collect($proveedores[$item->id] ?? [])
                ->map(fn($prov) => (object) $prov);
            $item->stock = collect($detalleStock[$item->id] ?? []);
        });
        return $details;
    }

    public function getQuotatioDetailProviders($quotationId, $details)
    {
        return GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
    }
    public function getQuotationDetailStock($quotationId, $details)
    {
        return GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
    }


    public function getSelectedStockByArticle($quotationId, $quotationDetails)
    {
        $detailsIds = collect($quotationDetails)->pluck('id');
        $allStocks = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)
            ->whereIn('detalle_id', $detailsIds)
            ->get()
            ->groupBy('detalle_id');

        $selectedStock = [];

        foreach ($quotationDetails as $item) {
            $stockForItem = collect($allStocks)->get($item->id, collect());
            foreach ($stockForItem as $st) {
                $stArray = $st->toArray();
                $stArray['ART_CODIGO'] = $item->ART_CODIGO;
                $selectedStock[] = $stArray;
            }
        }
        return $selectedStock;
    }

    /** funcion para llenar el stock de llenar cotizacion prospecto */
    public function getStockGeneralRestoreQuotation($articulos, $bodegas)
    {
        $stockRequests = [];
        foreach ($articulos as $art) {
            foreach ($bodegas as $bod) {
                $stockRequests[] = ['articulo' => $art['articulo'], 'bodega' => $bod];
            }
        }
        $stockResults = $this->stockWarehouseService->fetchStock($stockRequests);
        return $this->stockWarehouseService->transformStockQuotationResponse($stockResults);
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

    public function printQuotationPdf($id, $withImages = false)
    {
        $cotizacion = GpCotizacion::where('id', $id)->first()->toArray();

        if (!$cotizacion) {
            abort(404, 'Cotización no encontrada');

        }
        if ($cotizacion['requiere_aprobacion_comercial'] == 'yes' && $cotizacion['aprobado_comercial'] == 'no') {
            abort(403, 'La cotización requiere aprobación comercial y aún no ha sido aprobada');

        }

        if ($cotizacion['requiere_aprobacion_crediticia'] == 'yes' && $cotizacion['aprobado_crediticio'] == 'no') {
            abort(403, 'La cotización requiere aprobación crediticia y aún no ha sido aprobada');

        }
        if ($cotizacion['estado'] == 'caducado') {
            abort(403, 'La cotización ha caducado y no puede ser impresa');
        }

        $this->updatePrintsQuotation($id);
        $detalle = GpCotizacionDetalle::where('cotizacion_id', $id)
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $peso = 0;
        $tipoImpuesto = [];
        $sumaImpuesto = [];
        $imagenes = [];
        $subtotalmenosDescuento = 0;
        $sumIva = 0;
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', collect($detalle)->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');
        foreach ($detalle as &$item) {
            $valorUnitario = (float) $item['ART_VALOR'];
            $cantidad = (float) $item['ART_CANTIDAD'];
            $descuentoUnitario = (float) $item['ART_DESCUENTO'];
            $impuestoPorcentaje = (float) $articulosImpuesto[$item['ART_CODIGO']] ?? 0;
            $lineNet = round(($valorUnitario - $descuentoUnitario) * $cantidad, 2);
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineaSubtotal = $lineNet + $lineaDiscount;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);
            $peso += $item['ART_PESO'] * $item['ART_CANTIDAD'];

            //proceso de imagenes
            if ($withImages) {
                for ($i = 1; $i <= 3; $i++) {
                    $file = 'images/productos/' . $item['ART_CODIGO_TRES_LETRAS'] . '_' . $i . '.jpg';

                    if (File::exists($file)) {
                        $imagenes[] = $file;
                    }
                }
                $item['imagenes'] = $imagenes;
            }

            $tipoImpuesto[$impuestoPorcentaje] = ($tipoImpuesto[$impuestoPorcentaje] ?? 0) + $lineaSubtotal;
            $sumaImpuesto[$impuestoPorcentaje] = ($sumaImpuesto[$impuestoPorcentaje] ?? 0) + $lineaIva;
            $subtotalmenosDescuento += $lineNet;
            $sumIva += $lineaIva;
        }
        unset($item);

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
        $cotizacion['totalCotizacion'] = number_format($subtotalmenosDescuento + $sumIva, 2, '.', '');
        ;
        $cotizacion['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
        return $cotizacion;
    }

    public function calcularDiasHabiles($dias, $desde)
    {

        $validez = $dias;
        $fecha = $desde;

        for ($i = 1; $i < $validez; $i++) {
            $fechaPropuesta = date('Y-m-d', strtotime("+$i days", strtotime($fecha)));
            $parameters = [
                'idCompany' => env('ID_COMPANIA'),
                'fecha' => $fechaPropuesta,
            ];
            $resp = $this->apiBillingService->validateBusinessDate($parameters);
            $data = data_get($resp, 'data', []);
            if (data_get($data, 'codigo') == 0) {
                $validez++;
            }
            // $respuesta = $webService->cobranzas('ConsultaDiaFeriado', $parameters);
            // if ($respuesta['ConsultaDiaFeriadoResult']['Retorno'][0]['Codigo'] == 0) {
            //     $validez++;
            // }
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

    public function consultQuotationsNews($data): array
    {
        $periodo = $data['periodo'] ?? null;
        $sucursal = $data['sucursal'] ?? null;
        $vendedor = $data['vendedor'] ?? null;
        $motivo = $data['motivo'] ?? null;

        if (!$periodo) {
            abort(400, 'Debe ingresar un periodo');
        }
        $fechaInit = date('Y-m-01', strtotime($periodo));
        $fechaEnd = date('Y-m-t', strtotime($periodo));

        if (!is_null($sucursal)) {
            $formattedSucursal = strtoupper(trim($sucursal));
            $sucursal = GpBodega::where('BOD_BODEGA', $formattedSucursal)->first();
            if (!$sucursal) {
                abort(404, 'Sucursal no encontrada');
            }
        }

        if (!is_null($motivo)) {
            $motivo = GpCotizacionVentaMotivo::where('descripcion', [strtolower(trim($motivo))])->first();
            if (!$motivo) {
                abort(404, 'Motivo de no venta no encontrado');
            }
        }

        if (!is_null($vendedor)) {
            $vendedor = GpUsuario::where('USERID', trim($vendedor))->first();
            if (!$vendedor) {
                abort(404, 'Vendedor no encontrado');
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

        return $resumen;
    }


    /** funcion para obtener el detalle de la cotización  */

    public function detailQuotation($data)
    {

        $estadoCuenta = [
            'cupoDisponible' => $this->functionService->getClientCheckAvailableQuota($data['CLI_RUC'])
        ];
        $detalle = GpCotizacionDetalle::where('cotizacion_id', $data['id'])->get();

        $descuentoGeneral = $data['porcentage_descuento'] ?? 0;

        foreach ($detalle as $det) {
            $this->castDecimalValuesTemp($det);
            $this->loadProviderDetailQuotation($data['id'], $det, $descuentoGeneral);
            $this->calculatePorcentilDiscountAndPrice($det);
        }
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);
        return [
            'estadodecuenta' => $estadoCuenta,
            'detalle' => $detalle,
            'segmento' => $segmento
        ];

    }



    public function loadProviderDetailQuotation($quotationId, &$detalle, $descuentoGeneral)
    {
        $margenBruto = 0;
        if ($detalle->ART_TIPO_COMPRA == 'L') {
            $ItemL = $this->getTypeArticleLQuotationOrOrder($detalle->ART_CODIGO);
            $margenBruto = $ItemL->DATOS_ITEMS_L_MARGEN;
            $proveedores = $this->getAllProvidersQuotation($quotationId, $detalle->id);
            $detalle->proveedores = $proveedores->map(function ($prov) use ($detalle, $margenBruto, $descuentoGeneral) {
                $prov->margen = number_format($this->calculateMargen($margenBruto, $descuentoGeneral, $prov->COSTO_UNITARIO), 4, '.', '');
                $prov->CANTIDAD = $detalle->ART_DECIMAL_CANTIDAD == 0 ? (int) $prov->CANTIDAD : $prov->CANTIDAD;
                return $prov;
            })->toArray();
        }
        $detalle->proveedores = $detalle->proveedores ?? [];
    }

    public function getTypeArticleLQuotationOrOrder($artArticulo)
    {
        $itemL = GpArticulo::where('ART_CODIGO', $artArticulo)->first();
        if (!$itemL) {
            throw new \Exception('Item no encontrado', 404);
        }

        $datosL = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', $itemL->ART_CLASE_ARTICULO)->first();
        if (!$datosL) {
            throw new \Exception('Datos L no encontrados', 404);
        }
        return $datosL;
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

    public function calculatePorcentilDiscountAndPrice(&$detalle)
    {
        $detalle->priceChange = round($this->orderAndQuotationService->calculateDiscountFraction($detalle->ART_VALOR_ORIGINAL, $detalle->ART_VALOR), 3);
        $detalle->discountChange = round($this->orderAndQuotationService->calculateDiscountChange($detalle->descuento_original, $detalle->descuento), 3);
    }


    /***
     *  FUNCION PARA llenar el detalle de la cotizacion - llenarcotizacion
     */

    public function fillQuotation($data, $user)
    {
        $artPrecios = [];
        $articulosTemp = $this->quotationRepository->getGpTempCartFillQuotation($data['sucursal_id'], $data['puntodeventa_id'], $data['cliente_id'], $user->id);
        $isCopy = ($data['test'] ?? '') === 'copiarpedido';
        $serial = data_get($data, 'serial', null);
        foreach ($articulosTemp as $artTemp) {
            $artTemp->CHECK_ALTERNATIVO = $isCopy ? 0 : $this->syncAndGetCheckAlternativo($data, $artTemp);

            $artPrecios[] = ['ART_CODIGO' => $artTemp->ART_CODIGO];
            $this->castDecimalValuesTemp($artTemp, $data);
            $this->loadProvidersTemp($artTemp);
            $this->loadStockTemp($artTemp);
            $this->loadImagesTemp($artTemp);
        }

        if (($serial === null || $serial === '') && empty($data['registro_copia'])) {
            $listaPrecios = $this->getPricesByClient($artPrecios);
            $cliente = GpCliente::find($data['cliente_id']);
            foreach ($articulosTemp as $artTemp) {
                $precio = $listaPrecios[$artTemp->ART_CODIGO][$cliente->CLI_NIVEL_PRECIO] ?? null;
                if (!$precio) {
                    continue;
                }
                $nuevoPrecio = data_get($precio, 'valor', 0);
                if (!is_null($precio) && $artTemp->ART_VALOR_ORIGINAL != $nuevoPrecio) {
                    $artTemp->ART_VALOR_ORIGINAL = $nuevoPrecio;
                    $artTemp->ART_VALOR = $nuevoPrecio;
                    $artTemp->modificador = 0;
                }
            }
        }
        $this->functionService->recalculateIva($articulosTemp);
        return $articulosTemp;
    }


    public function castDecimalValuesTemp($artTemp, $data = null)
    {
        if ($artTemp->ART_DECIMAL_CANTIDAD == 0) {
            if (isset($data['test']) && $data['test'] !== 'recuperarcotizacion') {
                $artTemp->ART_STOCK = 0;
            }
            $artTemp->ART_STOCK = (int) $artTemp->ART_STOCK;
            $artTemp->ART_CANTIDAD = (int) $artTemp->ART_CANTIDAD;
        }
    }

    public function loadProvidersTemp(&$artTemp)
    {
        $artTemp->proveedores = GpTempCartProveedores::where('id_tempcart', $artTemp->id)
            ->get()
            ->map(function ($prov) use ($artTemp) {
                $prov->CANTIDAD = round($prov->CANTIDAD, $artTemp->ART_DECIMAL_CANTIDAD);
                return $prov;
            })->toArray();
    }

    public function loadStockTemp(&$artTemp)
    {
        $artTemp->stock = GpTempCartStock::where('id_tempcart', $artTemp->id)
            ->get()
            ->map(function ($st) use ($artTemp) {
                $st->STO_ART_STOCK_DISPONIBLE = $artTemp->ART_DECIMAL_CANTIDAD == 0 ? (int) $st->STO_ART_STOCK_DISPONIBLE : $st->STO_ART_STOCK_DISPONIBLE;
                $st->seleccionado = round($st->seleccionado, $artTemp->ART_DECIMAL_CANTIDAD);
                return $st;
            })->toArray();
    }

    public function loadImagesTemp(&$artTemp)
    {
        $imagenes = [];
        for ($i = 1; $i <= 3; $i++) {
            $file = 'images/productos/' . $artTemp->ART_CODIGO_TRES_LETRAS . '_' . $i . '.jpg';

            if (File::exists($file)) {
                $imagenes[] = env('APP_URL') . '/' . $file;
            }
        }
        $artTemp->imagenes = $imagenes;
    }

    public function getPricesByClient($articulos): array
    {
        $listaPreciosAll = [];
        foreach ($articulos as $articulo) {
            $art = data_get($articulo, 'ART_CODIGO');

            if (!$art) {
                continue;
            }

            // Esto devuelve: array de filas de precios para ese artículo
            $prices = $this->stockWarehouseService->fetchListPricesArticle($art);

            if (empty($prices) || !is_iterable($prices)) {
                continue;
            }

            foreach ($prices as $price) {
                $articleCode = data_get($price, 'item');
                $priceListId = data_get($price, 'idLista');

                if (!$articleCode || !$priceListId) {
                    continue;
                }

                // Queda: [ART_CODIGO][ID_LISTA] = fila precio
                $listaPreciosAll[$articleCode][$priceListId] = $price;
            }
        }

        return $listaPreciosAll;
    }


    /***
     *  Funciones para obtener el stock en tiempo real de los articulos
     *  Ya se manda filtrado los items L desde el Front End
     */

    public function getStockItemForWarehouse($article, $warehouse, $cotizacionId)
    {
        $quoteReservedOriginId = $this->validateExistsReserve($cotizacionId);
        $warehouses = $this->functionService->getBodegas($warehouse, 1);
        $arrArticulos = ['articulo' => $article['ART_CODIGO']];
        $selectedArticleByItem = $this->resolveAndFormatArticleStock($article);
        $stockGeneral = $this->getStockItemByWarehouseRestoreQuotation($arrArticulos, $warehouses);
        $this->mergeSelectedStock($stockGeneral, $selectedArticleByItem);
        $artFormat = $this->stockWarehouseService->computeAvailableStockForArticle($stockGeneral, $article, $quoteReservedOriginId);
        $availableArticulos = [];
        if (!$artFormat['disponible']) {
            $clase = GpArticulo::where('ART_CODIGO', $article['ART_CODIGO'])->first();
            $article->ART_CODIGO = $this->functionService->findAvailableArticleCodeL($availableArticulos, $clase->ART_CLASE_ARTICULO);
            $availableArticulos[] = $article['ART_CODIGO'];
        }
        $this->functionService->storeCartStock($article['id'], $artFormat['stock']);
        $newStock = $this->reassignStockIds($article, $artFormat['stock']);
        $article['stock'] = $newStock;
        $article['ART_STOCK'] = $artFormat['ART_STOCK'];
        $article['stockListo'] = true;
        return $article;
    }


    /** este me tiene el seleccionado del stock pero solo por item hay otra función pero lo hacer por lotes */
    public function resolveAndFormatArticleStock($article)
    {
        $result = [];

        // ✅ Caso 1: Si $article['stock'] ya tiene datos, úsalo directamente
        if (!empty($article['stock'])) {
            foreach ($article['stock'] as $st) {
                $artSt = is_array($st) ? $st : $st->toArray();
                $artSt['ART_CODIGO'] = $article['ART_CODIGO'];
                $artSt['seleccionado'] = (int) $artSt['seleccionado'];
                $result[] = $artSt;
            }
            return $result;
        }

        // ✅ Caso 2: Si está vacío, intentar cargar de GpCotizacionDetalleStock
        $stockDetalle = GpCotizacionDetalleStock::where('detalle_id', $article['detalle_id'])
            ->get()
            ->map(function ($item) use ($article) {
                return array_merge($item->toArray(), ['ART_CODIGO' => $article['ART_CODIGO']]);
            })
            ->toArray();

        // ✅ Caso 2a: Si lo de la BD trajo datos, úsalo
        if (!empty($stockDetalle)) {
            return $stockDetalle;
        }

        // ✅ Caso 3: Si la BD tampoco tiene → fallback al stock original (aunque esté vacío)
        foreach ($article['stock'] as $st) {
            $artSt = is_array($st) ? $st : $st->toArray();
            $artSt['ART_CODIGO'] = $article['ART_CODIGO'];
            $artSt['seleccionado'] = (int) $artSt['seleccionado'];
            $result[] = $artSt;
        }

        return $result;
    }

    public function getStockItemByWarehouseRestoreQuotation($arrArticulos, $warehouses)
    {
        $stockRequests = [];
        foreach ($warehouses as $warehouse) {
            $stockRequests[] = array_merge($arrArticulos, ['bodega' => $warehouse]);
        }
        $stockResults = $this->stockWarehouseService->fetchStock($stockRequests);
        return $this->stockWarehouseService->transformStockQuotationResponse($stockResults);
    }

    public function reassignStockIds($article, $stockArticle)
    {
        $result = [];
        foreach ($stockArticle as $item) {
            $tempStock = GpTempCartStock::where('id_tempcart', $article['id'])
                ->where('STO_ART_BODEGA', $item['STO_ART_BODEGA'])->first();
            $item['id'] = $tempStock->id;
            $item['seleccionado'] = (int) $tempStock->seleccionado;
            $result[] = $item;
        }
        return $result;
    }


    /** proceso para omitir reservas de cotizacion  */
    public function validateExistsReserve($quotationId)
    {
        $quoteReserve = GpCotizacion::where('origen', 'reserva')->find($quotationId);
        if (!$quoteReserve) {
            return;
        }
        $logReserve = GpCotizacionReservaLog::where('cotizacion_destino_id', $quotationId)->first();
        if (!$logReserve) {
            return;
        }
        return $logReserve?->cotizacion_origen_id;
    }

    /***
     *
     * PROCESO DE VOLVER A PEDIR COTIZACION
     *
     */

    public function requestQuotationAgain($data, $user)
    {
        $cliente = $this->functionService->getClient($data);
        $detalles = $this->OrderQuotationService->getDetailQuotationOrOrder($cliente, $data, false, false, 'cotizacion');
        $this->functionService->recalculateIva($detalles);
        $this->functionService->setCartFormQuotation($detalles, $data, $user, 'cotizacion');
        $registroCopiaId = $this->orderAndQuotationService->createRegisterCopy($data['id'], $user, 'cotizacion', 'volver_a_pedir');
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);

        return [
            'cliente' => $cliente,
            'registro_copia' => $registroCopiaId,
            'currentSucursal' => $data['sucursal_id'],
            'test' => 'volver_a_pedir',
            'segmento' => $segmento
        ];
    }


    /***
     *  funciones para el guardado de la cotizacion
     *
     */

    public function storeQuotation($data, $user)
    {

        $cotizacion = $this->prepareQuotationStoreFill($data, $user);

        if (!empty($data['cotizacion_id'])) {
            GpCotizacionDetalle::where('cotizacion_id', $data['cotizacion_id'])->delete();
        }
        $this->saveQuotationDetail($data['cotizacion'], $cotizacion->id);
        $this->OrderQuotationService->clearTempDataQuotation($data['sucursal_id'], $data['PTO_VTA_ID'], $data['cliente_id'], $user->id);
        $this->updateRegisterCopyQuotation($data['registro_copia'], $cotizacion->id);
        return $cotizacion;

    }


    public function prepareQuotationStoreFill($data, $user)
    {
        $cotizacion = isset($data['cotizacion_id']) ? GpCotizacion::find($data['cotizacion_id']) : new GpCotizacion();
        $requiereAprobacionComercial = $this->approvalService->checkoutApprovalCommercialQuotations($data);
        if ($data['cotizacion_id'] && $cotizacion->id) {
            $this->mantenerAprobacionSiSigueValida($cotizacion, $requiereAprobacionComercial);
        } else {
            $requiereAprobacionComercial['estado'] = $requiereAprobacionComercial['requiere'] === 'yes' ? 'espera' : 'aprobado';
            $requiereAprobacionComercial['aprobado'] = 'no';
        }
        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivoLimpio = iconv('UTF-8', 'UTF-8//IGNORE', $requiereAprobacionComercial['motivo']);

        // 2. Si iconv falla (a veces pasa en ciertos servidores), usa este fallback robusto:
        if (!$motivoLimpio) {
            // Detecta si viene en Latin1 (común en bases de datos viejas) y lo pasa a UTF-8
            $encoding = mb_detect_encoding($requiereAprobacionComercial['motivo'], mb_detect_order(), true);
            $motivoLimpio = mb_convert_encoding($requiereAprobacionComercial['motivo'], 'UTF-8', $encoding ?: 'ISO-8859-1');
        }
        $cotizacion->fill([
            'estado' => $requiereAprobacionComercial['estado'],
            'user_id' => $user->id,
            'sucursal_id' => $data['sucursal_id'],
            'sucursal_facturacion_id' => $data['sucursal_facturacion_id'],
            'cliente_id' => $data['cliente_id'],
            'factura_posterior_despacho' => $data['factura_posterior_despacho'],
            'factura_a_demanda' => $data['factura_a_demanda'],
            'factura_siguiente_mes' => $data['factura_siguiente_mes'],
            'BOD_BODEGA' => $data['bodega_facturacion'],
            'PTO_VTA_ID' => $data['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION' => $puntoVenta->PTO_VTA_DESCRIPCION ?? '',
            'USERID' => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO' => $data['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC' => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL' => $data['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI' => $data['CLI_CORREO_ELECT_SRI'],
            'COND_ID' => $data['COND_ID'],
            'COND_ID_ORIGINAL' => $data['COND_ID_ORIGINAL'],
            'VEND_ID' => $data['VEND_ID'],
            'VEND_NOMBRES' => $data['VEND_NOMBRES'],
            'VEND_APELLIDOS' => $data['VEND_APELLIDOS'],
            'despacho' => $data['despacho'],
            'orden_de_compra' => $data['orden_de_compra'],
            'porcentage_descuentooriginal' => $data['porcentage_descuentooriginal'],
            'porcentage_descuento' => $data['porcentage_descuento'],
            'subtotal' => (float) $data['subtotal'] + (float) $data['descuento'],
            'descuento' => (float) $data['descuento'],
            'subtotalmenosdescuento' => (float) $data['subtotal'],
            'iva' => (float) $data['iva'],
            'total' => (float) $data['total'],
            'requiere_aprobacion_comercial' => $requiereAprobacionComercial['requiere'],
            'motivo_aprobacion_comercial' => $motivoLimpio,
            'aprobado_comercial' => $requiereAprobacionComercial['aprobado'],
            'aprobador_comercial_id' => null,
            'requiere_aprobacion_crediticia' => 'no',
            'aprobado_crediticio' => 'no',
            'aprobador_crediticio_id' => null,
            'impreso' => 'no',
            'nota_interna' => $data['nota_interna'],
            'nota_cliente' => $data['nota_cliente'],
            'impuesto' => env('IVA_IMPUESTO'),
        ]);
        if ($data['despacho'] === 'Despacho en ruta') {
            $cotizacion->fecha_entrega = $data['fecha_entrega'];
        }
        $cotizacion->save();
        $cotizacion->cotizacion = 'COT' . str_pad($cotizacion->id, 8, "0", STR_PAD_LEFT);
        $cotizacion->save();
        return $cotizacion;
    }

    public function mantenerAprobacionSiSigueValida($cotizacion, &$requiereAprobacionComercial)
    {
        $estabaAprobada = $cotizacion->requiere_aprobacion_comercial === 'yes' && $cotizacion->aprobado_comercial === 'yes';

        if (!$estabaAprobada) {
            // Si no estaba aprobada antes, no hay nada que mantener.
            // Se aplica el estado que diga la nueva validación.
            $this->resetToPending($requiereAprobacionComercial);
            return;
        }
        // 2. Extraer motivos para comparar
        $motivoNuevo = $requiereAprobacionComercial['motivo'] ?? '';
        $motivoGuardado = $cotizacion->motivo_aprobacion_comercial ?? '';

        // 3. Comparación Estricta
        // Si la nueva validación dice que NO requiere aprobación, genial (aunque raro si antes requería).
        if (($requiereAprobacionComercial['requiere'] ?? 'no') !== 'yes') {
            $requiereAprobacionComercial['estado'] = 'aprobado';
            $requiereAprobacionComercial['aprobado'] = 'yes';
            return; // Dejamos que pase como 'no requiere'
        }

        if ($motivoNuevo === $motivoGuardado) {
            // ... RESTAURAMOS el estado de aprobación de la BD
            $requiereAprobacionComercial['requiere'] = $cotizacion->requiere_aprobacion_comercial;
            $requiereAprobacionComercial['motivo'] = $cotizacion->motivo_aprobacion_comercial; // Redundante pero seguro
            $requiereAprobacionComercial['estado'] = $cotizacion->estado; // Ojo: Asegúrate que 'estado' sea el campo correcto (aprobado/espera)
            $requiereAprobacionComercial['aprobado'] = $cotizacion->aprobado_comercial; // 'yes'
        } else {
            // Si el motivo cambió (ej: pasaste de 20% a 25%), se pierde la aprobación
            $this->resetToPending($requiereAprobacionComercial);
        }
    }

    public function resetToPending(&$data)
    {
        // Si requiere aprobación, pasa a 'espera', si no, 'aprobado' (u otro status default)
        $req = $data['requiere'] ?? 'no';
        if ($req === 'yes') {
            $data['estado'] = 'espera';
            $data['aprobado'] = 'no';
        } else {
            $data['estado'] = 'aprobado';
            $data['aprobado'] = 'yes';
        }
    }



    public function saveQuotationDetail($items, $cotizacionId)
    {
        foreach ($items as $item) {
            $detalle = new GpCotizacionDetalle([
                'cotizacion_id' => $cotizacionId,
                'ART_CODIGO' => $item['ART_CODIGO'],
                'ART_DESCRIPCION' => trim(str_replace('  ', ' ', $item['ART_DESCRIPCION'])),
                'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
                'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
                'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
                'ART_DECIMAL_CANTIDAD' => (int) $item['ART_DECIMAL_CANTIDAD'],
                'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
                'ART_MARCA' => $item['ART_MARCA'],
                'ART_MATERIAL' => $item['ART_MATERIAL'],
                'ART_PESO' => $item['ART_PESO'],
                'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
                'ART_VALOR' => (float) $item['ART_VALOR'],
                'ART_VALOR_ORIGINAL' => (float) $item['ART_VALOR_ORIGINAL'],
                'ART_DESCUENTO' => (float) $item['ART_DESCUENTO'],
                'ART_IVA' => (float) $item['ART_IVA'],
                'ART_CANTIDAD' => (float) $item['ART_CANTIDAD'],
                'ART_STOCK' => (float) $item['ART_STOCK'],
                'ART_IMP_VTA' => (float) $item['ART_IMP_VTA'],
                'ART_COSTO_PROMEDIO' => (float) $item['ART_COSTO_PROMEDIO'],
                'descuento' => (float) $item['descuento'],
                'descuento_original' => (float) $item['descuento_original'],
                'modificador' => (float) $item['modificador'],
            ]);
            $detalle->save();
            if ($item['proveedores']) {
                $this->saveQuotationDetailProviders($item['proveedores'], $detalle->id, $cotizacionId);
            }
            if ($item['stock']) {
                $this->saveQuotationDetailStocks($item['stock'], $detalle->id, $cotizacionId);
            }
        }
    }


    public function saveQuotationDetailProviders($providers, $detalleId, $cotizacionId)
    {
        foreach ($providers as $prov) {
            GpCotizacionDetalleProveedores::create([
                'cotizacion_id' => $cotizacionId,
                'detalle_id' => $detalleId,
                'ID_PROVEEDOR' => $prov['ID_PROVEEDOR'],
                'DESCRIPCION_ARTICULO' => $prov['DESCRIPCION_ARTICULO'],
                'FECHA_ENTREGA' => $prov['FECHA_ENTREGA'],
                'CANTIDAD' => (float) $prov['CANTIDAD'],
                'COSTO_UNITARIO' => (float) $prov['COSTO_UNITARIO'],
                'IMPUESTO' => (float) $prov['IMPUESTO'],
                'TOTAL' => (float) $prov['TOTAL'],
                'TOTALCONIMPUESTO' => (float) $prov['TOTALCONIMPUESTO'],
                'NOTA' => $prov['NOTA'],
                'MARGEN' => $prov['MARGEN']
            ]);
        }
    }

    public function saveQuotationDetailStocks($stocks, $detalleId, $cotizacionId)
    {
        foreach ($stocks as $stock) {
            GpCotizacionDetalleStock::create([
                'cotizacion_id' => $cotizacionId,
                'detalle_id' => $detalleId,
                'STO_ART_BODEGA' => $stock['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => $stock['STO_ART_STOCK_DISPONIBLE'],
                'seleccionado' => $stock['seleccionado']
            ]);
        }
    }




    public function updateRegisterCopyQuotation($registerCopyId, $cotizacionId)
    {
        if (!$registerCopyId || $registerCopyId == null || $registerCopyId == '' || $registerCopyId == 'null') {
            return;
        }
        $registerCopy = GpRegistroCopia::find($registerCopyId);
        if ($registerCopy) {
            $registerCopy->cotizacion_destino = $cotizacionId;
            $registerCopy->save();
        }
    }


    /**
     *  funcion para obtener el margen de un item L
     * @param mixed $artArticulo
     * @return JsonResponse
     */
    public function getMarginItem($artArticulo)
    {

        $itemL = GpArticulo::where('ART_CODIGO', $artArticulo)->first();
        if (!$itemL) {
            abort(404, 'Item no encontrado');
        }
        $datosL = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', $itemL->ART_CLASE_ARTICULO)->first();
        if (!$datosL) {
            abort(404, 'Datos L no encontrados');
        }
        return $datosL;

    }



    /**
     *  FUNCION PARA GUARDAR PROVEEDOR EN TABLA TEMPORAL AL MOMENTO
     *  DE RELIZAR LA COTIZACION
     */


    public function saveProviderQuotationTemp(array $data)
    {
        if (empty($data['proveedores']) || !is_array($data['proveedores'])) {
            return;
        }
        $this->deleteProviderQuotationTemp($data['id']);
        $this->saveProviderQuotationItemTemp($data['proveedores'], $data['id']);
        $this->updateTempCartArtValor($data['id'], $data['ART_VALOR']);
    }

    public function deleteProviderQuotationTemp($idTempCart)
    {
        GpTempCartProveedores::where('id_tempcart', $idTempCart)
            ->delete();
    }

    public function saveProviderQuotationItemTemp($providers, $idTempCart)
    {
        foreach ($providers as $provider) {
            GpTempCartProveedores::create([
                'id_tempcart' => $idTempCart,
                'ID_PROVEEDOR' => $provider['ID_PROVEEDOR'],
                'DESCRIPCION_ARTICULO' => trim(str_replace('  ', ' ', $provider['DESCRIPCION_ARTICULO'])),
                'FECHA_ENTREGA' => $provider['FECHA_ENTREGA'],
                'CANTIDAD' => $provider['CANTIDAD'],
                'COSTO_UNITARIO' => (float) $provider['COSTO_UNITARIO'],
                'IMPUESTO' => (float) $provider['IMPUESTO'],
                'TOTAL' => (float) $provider['TOTAL'],
                'TOTALCONIMPUESTO' => (float) $provider['TOTALCONIMPUESTO'],
                'NOTA' => $provider['NOTA'],
                'MARGEN' => (float) $provider['MARGEN'] ?? 0,
            ]);
        }
    }

    public function updateTempCartArtValor($idTempCart, $artValor)
    {
        GpTempCart::where('id', $idTempCart)
            ->update(['ART_VALOR' => $artValor]);
    }



    /** Seccion de clientes */
    public function getAllSegments()
    {
        $resp = $this->apiGpService->fetchAllSegments();
        return data_get($resp, 'data', []);
    }

    public function getSubSegmentBySegment(int $coAtrPadre)
    {
        if (empty($coAtrPadre)) {
            abort(404, 'Segmento no encontrado');
        }
        $resp = $this->apiGpService->fetchSubSegmentsBySegment(['coAtrPadre' => $coAtrPadre]);
        return data_get($resp, 'data', []);
    }


    public function storeClientQuotation(array $data, GpUsuario $user)
    {

        $this->prepareForValidationDirection($data);

        $nivelPrecio = $this->getNivelPriceSeller($data, $user);
        $params = $this->quotationBuilder->formPayloadSendClientGp($data, $nivelPrecio);
        $this->apiGpService->StoreClientGp($params);
        $this->createClientWeb($data, $nivelPrecio);
        $this->createClientSucursalWeb($data);
        $this->sendToSegmentAndRucToGp($data['CLI_RUC'], $data['CLI_SUBSEGMENTO_ID']);
        $cliente = GpCliente::where('CLI_RUC', $data['CLI_RUC'])->get()->first();
        $vendedor = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
        $cliente->vendedor = $vendedor;

        $sucursales = GpClienteSucursal::where('CLI_SUC_RUC', $cliente->CLI_RUC)->get();
        $cliente->sucursales = $sucursales;
        $cliente->sucursalesfacturacion = $sucursales;
        return $cliente;
    }

    public function prepareForValidationDirection(&$data)
    {
        $data['CLI_CORREO_ELECT_SRI'] = isset($data['CLI_CORREO_ELECT_SRI']) ? mb_strtoupper($data['CLI_CORREO_ELECT_SRI']) : strtolower($data['CLI_CORREO_ELECT_SRI']);
        $data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA'] = isset($data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA']) ? mb_strtoupper($data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA']) : strtolower($data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA']);
    }




    public function createClientWeb(array $data, string $nivelPrecio)
    {
        $attrs = $this->quotationBuilder->formPayloadClientWeb($data, $nivelPrecio);
        Log::info('GpCliente creará con attrs:', $attrs);
        return GpCliente::create($attrs);
    }




    public function createClientSucursalWeb(array $data)
    {
        return GpClienteSucursal::create($this->quotationBuilder->formPayloadClientSucursal($data));
    }

    public function getNivelPriceSeller(array $data, GpUsuario $user)
    {
        if ($data['CLI_PUNTO_VENTA'] !== '005') {
            return 'GENERAL';
        }
        $nivelPrecioApi = $this->apiGpService->getTypeBySeller([
            'usuario' => $user->USERID,
            // 'usuario' => 'oficina',
        ]);
        if ($nivelPrecioApi['success'] === false) {
            return 'GENERAL';
        }
        $payload = $nivelPrecioApi['data'];
        return ((int) $payload['coTipVen'] === 1) ? 'TIENDA' : 'GENERAL';
    }
    public function sendToSegmentAndRucToGp($ruc, $subsegmentoId)
    {
        $this->apiGpService->storeSubsegmentAssociation([
            'ruc' => $ruc,
            'subsegmentoId' => $subsegmentoId,
        ]);
    }


    public function checkClientWithSegmentExist($ruc)
    {
        return $this->apiGpService->fetchClientSegment([
            'ruc' => $ruc,
        ]);
    }


    public function updateClientWithSegment($cliente, $segmentoId, $subsegmentoId)
    {

        $cliente = GpCliente::find($cliente['id']);
        $cliente->CLI_SEGMENTO_ID = $segmentoId;
        $cliente->CLI_SUBSEGMENTO_ID = $subsegmentoId;
        $cliente->save();
        $this->sendToSegmentAndRucToGp($cliente['CLI_RUC'], $subsegmentoId);
        return $this->buildSuccessResponse('Segmento del cliente actualizado correctamente', 200);

    }


}

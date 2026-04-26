<?php

namespace App\Services\Venta;

use App\Enums\Ventas\QuotationMode;
use App\Models\aprobacion\GpReglaAprobacion;
use App\Models\GpBodega;
use App\Models\GpCliente;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpUsuario;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpDatosItemsL;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpCotizacionVentaMotivo;
use Illuminate\Database\Eloquent\Collection;
use App\Models\GpArticulo;
use App\Models\GpCotizacionDetalleStock;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Carbon\Carbon;
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
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService,
        protected readonly QuotationBuilderInterface $quotationBuilder,
        protected readonly ClientQueryServiceInterface $clientService
    ) {}


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

    public function updateNonSaleAndReminderData($data, GpUsuario $user)
    {
        GpCotizacion::where('id', $data['cotizacion_id'])->update([
            'fecha_recordatorio'            => $data['fecha_recordatorio'],
            'cotizacion_venta_motivo_id'    => $data['motivo_id'],
            'observacion'                   => $data['observacion'],
            'recordar'                      => $data['recordar'],
            'user_ing_recordatorio'         => $user->USERID,

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



    /*
    *
    * PROCESO DE RECUPERAR COTIZACION
    *
    */

    public function restoreQuotationV2($quoteId): array
    {
        $quote      = GpCotizacion::findOrFail($quoteId);
        $cliente    = $this->clientService->getAvailabeCreditCustomer($quote->CLI_RUC);
        $detalles   = $this->getQuotationDetailWithSuppliers($quote->id);
        $this->functionService->recalculateIva($detalles);
        $detalles->transform(function ($item) {
            $item->imagenes     = $this->orderAndQuotationService->getImageArticle($item->ART_CODIGO_TRES_LETRAS);
            $item->tipo         = 'cotizacion';
            $item->detalle_id   = $item->id;
            return $item;
        });
        $imagenesOperaciones    = $this->orderAndQuotationService->getAllImagensOperations($quote->id, 'cotizacion');
        $documentosPdf          = $this->orderAndQuotationService->getFormattedPdfMedia($quote);
        return [
            'cotizacion'            => $quote,
            'cliente'               => data_get($cliente, 'cliente'),
            'items'                 => $detalles,
            'imagenesOperaciones'   => $imagenesOperaciones,
            'documentosPdf'         => $documentosPdf,
            'segmento'              => data_get($cliente, 'segmentos'),
            'mode'                  => QuotationMode::RESTORE_QUOTE->value,
        ];
    }

    protected function getQuotationDetailWithSuppliers($quotationId)
    {
        $details        = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $proveedores    = $this->getQuotatioDetailProviders($quotationId, $details);
        $detalleStock   = $this->getQuotationDetailStock($quotationId, $details);
        $details->each(function ($item) use ($proveedores, $detalleStock) {
            $item->proveedores = collect($proveedores[$item->id] ?? [])
                ->map(fn($prov) => (object) $prov);
            $item->stock = collect($detalleStock[$item->id] ?? []);
        });
        return $details;
    }

    protected function getQuotatioDetailProviders($quotationId, $details)
    {
        return GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
    }
    protected function getQuotationDetailStock($quotationId, $details)
    {
        return GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
    }



    /**
     *
     * PROCESO DE ACTUALIZAR CIENTE Y SUCURSALES EN UNA COTIZACION DE PROSPECTO
     */

    public function updateQuoteClientData($quoteId, $clientId)
    {
        $quote                           = GpCotizacion::findOrFail($quoteId);
        $cliente                         = GpCliente::with(['gpvendedor:id,VEND_ID,VEND_NOMBRES,VEND_APELLIDOS'])
            ->findOrFail($clientId);
        $sucursal = GpClienteSucursal::where('CLI_SUC_RUC', $cliente->CLI_RUC)
            ->firstOrFail();
        $quote->cliente_id               = $cliente->id;
        $quote->sucursal_id              = $sucursal->id;
        $quote->sucursal_facturacion_id  = $sucursal->id;
        $quote->CLI_RUC                  = $cliente->CLI_RUC;
        $quote->CLI_RAZON_SOCIAL         = $cliente->CLI_RAZON_SOCIAL;
        $quote->CLI_CORREO_ELECT_SRI     = $cliente->CLI_CORREO_ELECT_SRI;
        if ($cliente->gpvendedor) {
            $quote->VEND_ID                  = $cliente->gpvendedor->VEND_ID;
            $quote->VEND_NOMBRES             = $cliente->gpvendedor->VEND_NOMBRES;
            $quote->VEND_APELLIDOS           = $cliente->gpvendedor->VEND_APELLIDOS;
        }
        $quote->save();
        return $quote;
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


    /***
     *
     * PROCESO DE VOLVER A PEDIR COTIZACION
     *
     */


    public function loadQuotationForCopy($quoteId, GpUsuario $user): array
    {
        $quote      = GpCotizacion::findOrFail($quoteId);
        $cliente    = $this->clientService->getAvailabeCreditCustomer($quote->CLI_RUC);
        $clienteNew =  data_get($cliente, 'cliente');
        $detalles   = $this->orderAndQuotationService->getDetailQuotationOrOrder($quote, $clienteNew, 'cotizacion', false, false);
        $this->functionService->recalculateIva($detalles);
        $detalles->transform(function ($item) {
            $item->imagenes     = $this->orderAndQuotationService->getImageArticle($item->ART_CODIGO_TRES_LETRAS);
            $item->tipo         = 'cotizacion';
            $item->detalle_id   = $item->id;
            return $item;
        });
        $registroCopiaId        = $this->orderAndQuotationService->createRegisterCopy($quote->id, $user, 'cotizacion', 'volver_a_pedir');
        $imagenesOperaciones    = $this->orderAndQuotationService->getAllImagensOperations($quote->id, 'cotizacion');
        $documentosPdf          = $this->orderAndQuotationService->getFormattedPdfMedia($quote);

        return [
            'cotizacion'            => $quote,
            'cliente'               => $clienteNew,
            'items'                 => $detalles,
            'imagenesOperaciones'   => $imagenesOperaciones,
            'documentosPdf'         => $documentosPdf,
            'segmento'              => data_get($cliente, 'segmentos'),
            'registro_copia'        => $registroCopiaId,
            'mode'                  => QuotationMode::REORDER->value,
        ];
    }


    /**
     * LISTA DE ALTERNATIVAS
     */

    public function getItemsAlternatives(string $item)
    {
        if (!$item) {
            abort(404, 'el parametro no puede estar vacio');
        }
        $resp = $this->apiGpService->getListQuotationItemsAlternatives(['item' => $item]);
        $success = data_get($resp, 'success', false);
        $errorMsg = data_get($resp, 'message', '');
        $data = data_get($resp, 'data', []);
        if (!$success) {
            abort(402, $errorMsg);
        }
        if (empty($data)) {
            abort(404, 'No se encontraron alternativas');
        }
        return $data;
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
        $subtotalmenosDescuento = 0;
        $sumIva = 0;
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', collect($detalle)->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');
        foreach ($detalle as &$item) {
            $valorUnitario      = (float) $item['ART_VALOR'];
            $cantidad           = (float) $item['ART_CANTIDAD'];
            $descuentoUnitario  = (float) $item['ART_DESCUENTO'];
            $impuestoPorcentaje = (float) $articulosImpuesto[$item['ART_CODIGO']] ?? 0;
            $lineNet            = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);
            $lineaDiscount      = $descuentoUnitario * $cantidad;
            $lineaSubtotal      = $lineNet + $lineaDiscount;
            $lineaIva           = $lineNet * ($impuestoPorcentaje / 100);
            $peso               += $item['ART_PESO'] * $item['ART_CANTIDAD'];

            //proceso de imagenes
            if ($withImages) {
                $imagenes = [];
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

        $formadepago    = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion['DESC_FORMA_PAGO_CODIGO'])->first()->toArray();
        $cliente        = GpCliente::where('id', $cotizacion['cliente_id'])->first()->toArray();
        $sucursal       = GpClienteSucursal::where('id', $cotizacion['sucursal_facturacion_id'])->first()->toArray();
        $puntodeventa   = GpPuntoVenta::where('PTO_VTA_ID', $cotizacion['PTO_VTA_ID'])->first()->toArray();
        $validezDias    = $puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] ?? 0;
        $validezTexto   = $validezDias === 0 ? '0 días' : $validezDias . " días habiles";
        $validezReal    = $this->calcularDiasHabiles($validezDias, $cotizacion['created_at']);

        $cotizacion['peso']             = $peso;
        $cotizacion['detalle']          = $detalle;
        $cotizacion['tipoimpuesto']     = $tipoImpuesto;
        $cotizacion['sumaimpuesto']     = $sumaImpuesto;
        $cotizacion['formadepago']      = $formadepago['DESC_FORMA_PAGO_TIPO_CREDITO'] ?? '';
        $cotizacion['cliente']          = $cliente;
        $cotizacion['sucursal']         = $sucursal;
        $cotizacion['validez']          = $validezTexto;
        $cotizacion['expira']           = $validezReal;
        $cotizacion['totalCotizacion']  = number_format($subtotalmenosDescuento + $sumIva, 2, '.', '');;
        $cotizacion['direccion']        = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
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

    public function detailQuotation($data, GpUsuario $user)
    {
        $estadoCuenta = [
            'cupoDisponible' => $this->functionService->getClientCheckAvailableQuota($data['CLI_RUC'])
        ];
        $detalle = GpCotizacionDetalle::where('cotizacion_id', $data['id'])->get();
        $codesList = $detalle->reject(function ($item) {
            return $item->ART_TIPO_COMPRA ===  'L';
        })->pluck('ART_CODIGO');
        $groupsByAbc =  $this->orderAndQuotationService->getApiSegmentsAbcItem($codesList);


        $descuentoGeneral = $data['porcentage_descuento'] ?? 0;
        $nivelPrecio            = GpCliente::where('CLI_RUC', $data['CLI_RUC'])->value('CLI_NIVEL_PRECIO');
        foreach ($detalle as $det) {
            $this->castDecimalValuesTemp($det);
            $this->loadProviderDetailQuotation($data['id'], $det, $descuentoGeneral);
            $this->calculatePorcentilDiscountAndPrice($det);
        }
        $quote = GpCotizacion::with('usuario.vendedor.zonas')->find($data['id']);

        $reglasAprobacion = $this->getPorcentilChangeDiscount($user, $quote);
        $detalle->transform(function ($item) use ($groupsByAbc, $reglasAprobacion) {
            $myAbc = $groupsByAbc->get($item->ART_CODIGO, collect());
            $classAbc = ($myAbc && $myAbc->isNotEmpty()) ? data_get($myAbc->first(), 'tuval', '-') : '-';
            $item->clasificacion_tuval = $classAbc;
            $item->reglasAprobacion = $reglasAprobacion;
            return $item;
        });
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);
        return [
            'estadodecuenta'    => $estadoCuenta,
            'detalle'           => $detalle,
            'segmento'          => $segmento,
            'nivelPrecio'       => $nivelPrecio,
        ];
    }


    protected function getPorcentilChangeDiscount(GpUsuario $user, GpCotizacion $quote)
    {

        if (in_array($user->perfil_id, [1, 2])) {
            return [
                'regla_precio'    => ['min' => -100, 'max' => 100],
                'regla_descuento' => ['min' => -100, 'max' => 100],
            ];
        }
        $data = [
            'regla_precio'    => ['min' => 0, 'max' => 0],
            'regla_descuento' => ['min' => 0, 'max' => 0],
        ];
        $vendedorQuote = $quote->usuario->vendedor ?? null;
        if (!$vendedorQuote) {
            return $data;
        }
        $zonasQuote      = $vendedorQuote->zonas->pluck('id')->toArray();
        $typeSellerQuote = $vendedorQuote->tipo_vendedor_id;

        if (empty($zonasQuote) || !$typeSellerQuote) {
            return $data;
        }

        if (in_array($user->perfil_id, [28])) {
            $reglas = GpReglaAprobacion::where('user_autorizador_id', $user->id)
                ->whereIn('zona_id', $zonasQuote)
                // Omitimos el tipo_vendedor_id porque el VP no evalúa por tipo de hijo
                ->whereIn('regla_negocio_id', [2, 3])
                ->select('regla_negocio_id', 'descuento_min', 'descuento_max')
                ->get();
        } else {
            $user->loadMissing(['vendedor.jerarquia.relaciones']);
            $headerAuth = $user->vendedor ?? null;

            if (!$headerAuth || !$headerAuth->jerarquia) {
                return $data;
            }

            $hijosDelJefe = $headerAuth->jerarquia->relaciones->pluck('tipo_vendedor_hijo_id')->toArray();

            // Si el creador de la cotización no es su subordinado, lo bloqueamos
            if (!in_array($typeSellerQuote, $hijosDelJefe)) {
                return $data;
            }

            $typeSellerJefe = $headerAuth->tipo_vendedor_id;
            $reglas = GpReglaAprobacion::where('user_autorizador_id', $user->id)
                ->whereIn('zona_id', $zonasQuote)
                ->where('tipo_vendedor_id', $typeSellerJefe) // Filtro estricto de jerarquía
                ->whereIn('regla_negocio_id', [2, 3])
                ->select('regla_negocio_id', 'descuento_min', 'descuento_max')
                ->get();
        }

        foreach ($reglas as $regla) {
            $key = $regla->regla_negocio_id == 3 ? 'regla_precio' : 'regla_descuento';
            $data[$key]['min'] = round((float)$regla->descuento_min, 2);
            $data[$key]['max'] = round((float)$regla->descuento_max, 2);
        }
        return $data;
    }

    public function castDecimalValuesTemp($artTemp, $data = null)
    {
        if ($artTemp->ART_DECIMAL_CANTIDAD == 0) {
            if (isset($data['test']) && $data['test'] !== 'recuperarcotizacion') {
                $artTemp->ART_STOCK = 0;
            }
            $artTemp->ART_STOCK = (float) $artTemp->ART_STOCK;
            $artTemp->ART_CANTIDAD = (float) $artTemp->ART_CANTIDAD;
        }
    }


    public function loadProviderDetailQuotation($quotationId, &$detalle, $descuentoGeneral)
    {
        $margenBruto = 0;
        if ($detalle->ART_TIPO_COMPRA == 'L') {
            $itemL = $this->getTypeArticleLQuotationOrOrder($detalle->ART_CODIGO);
            $margenBruto = $itemL->DATOS_ITEMS_L_MARGEN;
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
        $detalle->priceChange       = round($this->orderAndQuotationService->calculatePriceVariationPercentage($detalle->ART_VALOR_ORIGINAL, $detalle->ART_VALOR), 3);
        $detalle->discountChange    = round($this->orderAndQuotationService->calculateDiscountChange($detalle->descuento_original, $detalle->descuento), 3);
    }



    /***
     *  Funciones para obtener el stock en tiempo real de los articulos
     *  Ya se manda filtrado los items L desde el Front End
     */

    public function getStockItemsForWarehouse($article, $warehouse, $cotizacionId)
    {
        $quoteReservedOriginId  = $this->validateExistsReserve($cotizacionId);
        $warehouses             = $this->functionService->getBodegas($warehouse, 1);
        $arrArticulos           = ['articulo' => $article['ART_CODIGO']];
        $selectedArticleByItem  = $this->resolveAndFormatArticleStock($article);
        $stockGeneral           = $this->getStockItemByWarehouseRestoreQuotation($arrArticulos, $warehouses);
        $this->mergeSelectedStock($stockGeneral, $selectedArticleByItem);
        $artFormat              = $this->stockWarehouseService->computeAvailableStockForArticle($stockGeneral, $article, $quoteReservedOriginId);
        $availableArticulos     = [];
        if (!$artFormat['disponible']) {
            $clase                  = GpArticulo::where('ART_CODIGO', $article['ART_CODIGO'])->first();
            $article->ART_CODIGO    = $this->functionService->findAvailableArticleCodeL($availableArticulos, $clase->ART_CLASE_ARTICULO);
            $availableArticulos[]   = $article['ART_CODIGO'];
        }
        $article['stock']           = $artFormat['stock'];
        $article['ART_STOCK']       = $artFormat['ART_STOCK'];
        $article['stockListo']      = true;
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
                $artSt['ART_CODIGO']    = $article['ART_CODIGO'];
                $artSt['seleccionado']  = round($artSt['seleccionado'], $article['ART_DECIMAL_CANTIDAD']);
                $result[]               = $artSt;
            }
            return $result;
        }

        // ✅ Caso 2: Si está vacío, intentar cargar de GpCotizacionDetalleStock
        if (!empty($article['detalle_id'])) {
            $stockDetalle = GpCotizacionDetalleStock::where('detalle_id', $article['detalle_id'])
                ->get()
                ->map(function ($item) use ($article) {
                    return array_merge($item->toArray(), ['ART_CODIGO' => $article['ART_CODIGO']]);
                })
                ->toArray();
        }
        // ✅ Caso 2a: Si lo de la BD trajo datos, úsalo
        if (!empty($stockDetalle)) {
            return $stockDetalle;
        }

        // ✅ Caso 3: Si la BD tampoco tiene → fallback al stock original (aunque esté vacío)
        foreach ($article['stock'] as $st) {
            $artSt                  = is_array($st) ? $st : $st->toArray();
            $artSt['ART_CODIGO']    = $article['ART_CODIGO'];
            $artSt['seleccionado']  = round($artSt['seleccionado'], $article['ART_DECIMAL_CANTIDAD']);
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


    /** proceso para omitir reservas de cotizacion  */
    public function validateExistsReserve($quotationId)
    {
        $quoteReserve = GpCotizacion::where('origen', 'reserva')->find($quotationId);
        if (!$quoteReserve) {
            return null;
        }
        $logReserve = GpCotizacionReservaLog::where('cotizacion_destino_id', $quotationId)->first();
        if (!$logReserve) {
            return null;
        }
        return $logReserve?->cotizacion_origen_id;
    }


    public function getStockItemsForWarehouseV2($articles, $warehouse, $quoteId)
    {
        $data                   = [];
        $quoteReservedOriginId  = $this->validateExistsReserve($quoteId);
        $warehouses             = $this->functionService->getBodegas($warehouse, 1);
        foreach ($articles as $article) {
            $articleCode            = data_get($article, 'ART_CODIGO');
            $key                    = data_get($article, 'id_temp');
            $arrArticulos           = ['articulo' => $articleCode];
            $selectedArticleByItem  = $this->resolveAndFormatArticleStock($article);
            $stockGeneral           = $this->getStockItemByWarehouseRestoreQuotation($arrArticulos, $warehouses);
            $this->mergeSelectedStock($stockGeneral, $selectedArticleByItem);
            $artFormat = $this->stockWarehouseService->computeAvailableStockForArticle($stockGeneral, $article, $quoteReservedOriginId);
            $data[$key]     = [
                'stock'             => data_get($artFormat, 'stock', []),
                'ART_STOCK'         => data_get($artFormat, 'ART_STOCK', 0),
                'stockListo'        => true,
                'id_temp'           => $key
            ];
        }
        return $data;
    }






    /**
     *  funcion para obtener el margen de un item L
     * @param mixed $artArticulo
     *
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
        $nivelPrecio            = $this->getNivelPriceSeller($user);
        $params                 = $this->quotationBuilder->formPayloadSendClientGp($data, $nivelPrecio);
        $this->apiGpService->storeClientGp($params);
        $client = $this->createClientWeb($data, $nivelPrecio, $user);
        $this->createClientSucursalWeb($data,$client,  $user);
        $this->sendToSegmentAndRucToGp($data['CLI_RUC'], $data['CLI_SUBSEGMENTO_ID']);
        $cliente                = GpCliente::where('CLI_RUC', $data['CLI_RUC'])->get()->first();
        $vendedor               = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
        $cliente->vendedor      = $vendedor;

        $sucursales             = GpClienteSucursal::where('CLI_SUC_RUC', $cliente->CLI_RUC)->get();
        $cliente->sucursales    = $sucursales;
        $cliente->sucursalesfacturacion = $sucursales;
        return $cliente;
    }

    public function prepareForValidationDirection(&$data)
    {
        $data['CLI_CORREO_ELECT_SRI'] = isset($data['CLI_CORREO_ELECT_SRI']) ? mb_strtoupper($data['CLI_CORREO_ELECT_SRI']) : strtolower($data['CLI_CORREO_ELECT_SRI']);
        $data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA'] = isset($data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA']) ? mb_strtoupper($data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA']) : strtolower($data['CLI_CORREO_ELECT_NOTIFICACION_INTERNA']);
    }




    public function createClientWeb(array $data, string $nivelPrecio, GpUsuario $user)
    {
        $attrs = $this->quotationBuilder->formPayloadClientWeb($data, $nivelPrecio, $user);
        Log::info('GpCliente creará con attrs:', $attrs);
        return GpCliente::create($attrs);
    }




    public function createClientSucursalWeb(array $data, GpCliente $client, GpUsuario $user)
    {
        $sucursal = GpClienteSucursal::create($this->quotationBuilder->formPayloadClientSucursal($data, $client->id, $user));
        $this->updateStatusBranchInGp($sucursal, $user);

    }

    protected function updateStatusBranchInGp(GpClienteSucursal $branch, GpUsuario $userCreator): void
    {
        $fechaCreacion = Carbon::now()->format('Y-m-d H:i:s');
        $this->clientService->createRegisterBranchClient(
            $branch,
            1,
            $userCreator->USERID,
            $fechaCreacion,
            null,
            null,
            false
        );
    }



    protected function getNivelPriceSeller(GpUsuario $user)
    {
        $vendedor = GpVendedor::with(['atributoValores' => function ($q) {
            // Aseguramos que la opción exista y esté activa
            $q->whereHas('opcion', function ($qOpcion) {
                $qOpcion->where('estado', 1)->where('atributo_id', 1);
            })->with('opcion:id,codigo');
        }])
            ->where('user_id', $user->id)
            ->first();

        if (!$vendedor || $vendedor->atributoValores->isEmpty()) {
            return 'GENERAL';
        }

        return $vendedor->atributoValores->first()?->opcion?->codigo ?? 'GENERAL';
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


    public function updateClientWithSegment(string $ruc, int $segmentoId, int $subsegmentoId)
    {
        $cliente                        = GpCliente::where('CLI_RUC', $ruc)->first();
        $cliente->CLI_SEGMENTO_ID       = $segmentoId;
        $cliente->CLI_SUBSEGMENTO_ID    = $subsegmentoId;
        $cliente->save();
        $this->sendToSegmentAndRucToGp($cliente->CLI_RUC, $subsegmentoId);
        return $cliente;
    }
}

<?php

namespace App\Services\Venta;

use App\Enums\bodegas\AbcSegment;
use App\Enums\Ventas\OrderStatus;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpProvinciaCiudadParroquia;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpTerritorio;
use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InventoryQueryService implements InventoryQueryServiceInterface
{

    use JsonResponseTrait;

    public function __construct(
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly ApiGpService $apiGp,
        protected readonly FunctionsServiceInterface $functionsService,
    ) {}



    public function getConsultStock($data)
    {
        $articulo = $data['articulo'];
        $warehouses = $this->stockWarehouseService->getWarehouseNotConsult($data);
        if (empty($warehouses)) {
            return [];
        }
        $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
        $stockResults = $this->stockWarehouseService->fetchStock($stockRequests);
        $formatStock = $this->stockWarehouseService->transformStockResponse($stockResults);
        $art = $this->stockWarehouseService->computeAvailableStockByWarehouseForArticle($formatStock, $articulo); //nose porque hace pero va
        return [
            'stock' => data_get($art, 'stock', []),
            'ART_STOCK' => data_get($art, 'ART_STOCK', 0),
        ];
    }



    public function getArticleExposition(string $artCode, string $pvCode)
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $pvCode)->first();
        $params = [
            'item' => $artCode,
            'puntoVenta' => $pvCode,
        ];
        $response = $this->stockWarehouseService->fetchStockExposicionByArticleAndPv($params);
        return [
            'bodega'        => data_get($bodega, 'PTO_VTA_BOD_BODEGA', ''),
            'disponible'    => data_get($response, 'cantidad', 0),
        ];
    }




    public function getAvailableSpace($ruc)
    {
        $cliente = GpCliente::where('CLI_RUC', $ruc)->first();
        $segmento = $this->getSegmentoCliente($ruc);
        $cupoDisponible = $this->functionsService->getClientCheckAvailableQuota($ruc);

        $cliente->suspendido = (float) $cupoDisponible['suspendido'] ?? 0;
        $cliente->cupo_disponible = number_format($cupoDisponible['cupoDisponibleGp'], 2, '.', ',');
        $cliente->cupo_asignado = number_format($cupoDisponible['cupoAsignado'], 2, '.', ',');
        return [
            'cliente' => $cliente,
            'segmento' => $segmento
        ];
    }

    public function getSegmentoCliente($ruc)
    {
        $response = $this->apiGp->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }





    /**
     *  consulta de items en transito por reposicion
     * estado : pendiente
     * tipo de guia : reposicion
     * @articulo
     * @punto_venta
     * @param mixed $data
     * @return void
     */
    // public function getConsultItemTransito($data): array
    // {
    //     $articuloCodigo = data_get($data, 'articulo.ART_CODIGO');
    //     $warehouses = $this->stockWarehouseService->getWarehouseNotConsult($data);
    //     if (empty($warehouses)) {
    //         throw new \InvalidArgumentException('No se encontraron bodegas para consultar en transito.');
    //     }
    //     $rows = DB::table('gp_guia_remision_detalles as d')
    //         ->join('gp_guia_remisions as g', 'g.id', '=', 'd.guia_remision_id')
    //         ->where('d.GUI_REM_CODI_INTERNO',  $articuloCodigo)
    //         ->where('g.tipo', 'reposicion')
    //         ->where('g.estado', 'enviada')
    //         ->whereIn('g.bodega_destino', $warehouses)
    //         ->where(function ($query) {
    //             $query->where(function ($q2) {
    //                 $q2->where('g.bodega_destino', 'INMACONSA')
    //                     ->where('g.bodega_origen', 'IMPORT');
    //             })
    //                 ->orWhere(function ($q3) {
    //                     $q3->where('g.bodega_destino', '<>', 'INMACONSA');
    //                 });
    //         })
    //         ->groupBy('g.bodega_destino')
    //         ->select([
    //             DB::raw('g.bodega_destino as bodega'),
    //             DB::raw('COALESCE(SUM(COALESCE(d.GUI_REM_CANTIDAD,0) - COALESCE(d.recibido,0)),0) as pendiente'),
    //         ])
    //         ->pluck('pendiente', 'bodega')
    //         ->all();

    //     $items = array_map(function (string $bodega) use ($rows) {
    //         $pendiente =  (float)($rows[$bodega] ?? 0);
    //         return ['bodega' => $bodega, 'pendiente' => max(0, $pendiente)];
    //     }, $warehouses);

    //     return ['transito' => $items];
    // }


    public function getConsultItemTransito($data): array
    {
        $articuloCodigo = data_get($data, 'articulo');
        $ptoVentaId = data_get($data, 'puntodeventa_id');

        $bodegaDestino = GpRelacionPuntoVentaBodega::query()
            ->where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVentaId)
            ->value('PTO_VTA_BOD_BODEGA');

        $items = GpGuiaRemisionDetalle::where('GUI_REM_CODI_INTERNO', $articuloCodigo)
            ->whereHas('guia', function ($query) use ($bodegaDestino) {
                $query->where('tipo', 'reposicion')
                    ->where('estado', 'enviada')
                    ->where('bodega_destino', $bodegaDestino);
            })
            ->value(DB::raw('COALESCE(SUM(COALESCE(GUI_REM_CANTIDAD,0) - (COALESCE(recibido,0))), 0)'));
        return [
            'bodega' => $bodegaDestino,
            'pendiente' => max(0, $items)
        ];
    }



    public function fetchStockInTransit()
    {
        return DB::table('gp_guia_remision_detalles as d')
            ->join('gp_guia_remisions as g', 'g.id', '=', 'd.guia_remision_id')
            ->where('g.tipo', 'reposicion')
            ->where('g.estado', 'enviada')
            ->select([
                DB::raw('g.bodega_destino as bodega'),
                DB::raw('d.GUI_REM_CODI_INTERNO as codigo'),
                DB::raw('COALESCE(SUM(COALESCE(d.GUI_REM_CANTIDAD,0) - COALESCE(d.recibido,0)),0) as pendiente'),
            ])
            ->groupBy('g.bodega_destino', 'd.GUI_REM_CODI_INTERNO')
            ->having('pendiente', '>', 0)
            ->orderBy('bodega')
            ->orderBy('codigo')
            ->get();
    }



    public function listReservedInventory()
    {
        $baseOrders = fn() => DB::table('gp_pedido_detalle_stocks as st')
            ->join('gp_pedido_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_pedidos as p', 'p.id', '=', 'st.pedido_id')
            ->where('st.reservado', '>', 0)
            ->whereNotIn('p.estado', [
                OrderStatus::ELIMINADO->value,
                OrderStatus::RECHAZADO->value,
                OrderStatus::ANULADA->value
            ])
            ->where(function ($q) {
                $q->whereNull('p.despachado')->orWhere('p.despachado', 0);
            })
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.reservado AS reservado,
            'pedido' as tipo"
            );

        //caso a : no requiere comercial, no requiere crediticia
        $pA = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'no')
            ->where('p.requiere_aprobacion_crediticia', 'no');

        //Caso B: no requiere comercial, requiere crediticia y está aprobada
        $pB = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'no')
            ->where('p.requiere_aprobacion_crediticia', 'yes')
            ->where('p.aprobado_crediticio', 'yes');

        // Caso C: requiere comercial y aprobada, no requiere crediticia
        $pC = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'yes')
            ->where('p.aprobado_comercial', 'yes')
            ->where('p.requiere_aprobacion_crediticia', 'no');

        // Caso D: requiere ambas y ambas aprobadas
        $pD = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'yes')
            ->where('p.aprobado_comercial', 'yes')
            ->where('p.requiere_aprobacion_crediticia', 'yes')
            ->where('p.aprobado_crediticio', 'yes');
        $unionOrders = $pA->unionAll($pB)->unionAll($pC)->unionAll($pD);

        $traslados = DB::table('gp_traslados_reposicions as t')
            ->join('gp_articulos as a', 'a.ART_CODIGO', '=', 't.ART_CODIGO')
            ->where(function ($q) {
                $q->whereNull('t.completado')->orWhere('t.completado', 0);
            })
            ->selectRaw(
                "
            t.ART_CODIGO as codigo,
            t.bodega_origen AS bodega,
            (t.cantidad - COALESCE(t.trasladado, 0)) AS reservado,
            'traslado' as tipo"
            );

        // ----- UNION final y ordenamiento -----

        $unionAll = DB::query()->fromSub($unionOrders, 'ped')
            ->unionAll($traslados);
        $response = DB::query()->fromSub($unionAll, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
        $response2 = $this->listReservedInventoryByPendingUse();
        $response3 = $this->listReservedInventoryByTransfers();
        return collect($response)
            ->concat($response2)
            ->concat($response3)
            ->values();
    }

    public function listReservedInventoryByPendingUse()
    {
        $baseQuotation = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->where('st.seleccionado', '>', 0)
            ->where('st.cantidad_traslado', '=', 0)
            ->whereIn('r.estado', ['aprobado', 'traslado'])
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.seleccionado AS reservado,
            p.cotizacion as cotizacion,
            'reserva' as tipo",

            );
        return DB::query()->fromSub($baseQuotation, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
    }

    public function listReservedInventoryByTransfers()
    {
        $baseQuotation = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->join('gp_traslados_reservas as tr', 'tr.stock_id', '=', 'st.id')
            ->where('st.cantidad_traslado', '>', 0)
            ->whereIn('r.estado', ['traslado'])
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
             (
               COALESCE(SUM(tr.cantidad), 0)
                  - COALESCE(SUM(tr.trasladado), 0)
             ) AS reservado,
            p.cotizacion as cotizacion,
            'traslado_reservas' as tipo",
            )
            ->groupBy(
                'st.id',
                'd.ART_CODIGO',
                'st.STO_ART_BODEGA',
                'p.cotizacion',
                'st.cantidad_traslado',
                'st.cantidad_cancelada'
            );
        return DB::query()->fromSub($baseQuotation, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
    }



    public function listReservedWebInventory(?string $item)
    {
        $orders             = $this->getAllOrderReservation($item);
        $traslados          = $this->getAllTransferReposition($item);
        $quoteReservations  = $this->getAllQuoteRerservations($item);
        $quoteTransfers     = $this->getAllPendingReservationTransfer($item);
        return $orders->merge($traslados)->merge($quoteReservations)->merge($quoteTransfers);
    }

    public function getAllOrderReservation(?string $item = null): Collection
    {
        $query = DB::table('gp_pedido_detalle_stocks as st')
            ->join('gp_pedido_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_pedidos as p', 'p.id', '=', 'st.pedido_id')
            ->join('gp_clientes as c', 'c.id', '=', 'p.cliente_id')
            ->where('st.reservado', '>', 0)
            ->whereNotIn('p.estado', [
                OrderStatus::ELIMINADO->value,
                OrderStatus::RECHAZADO->value,
                OrderStatus::ANULADA->value
            ]);
        $query->where(function ($q) {
            $q->whereNull('p.despachado')
                ->orWhere('p.despachado', 0);
        });


        $query->where(function ($mainQ) {

            // Condición 1: Cumple con Comercial (No requiere O requiere y está aprobado)
            $mainQ->where(function ($qCom) {
                $qCom->where('p.requiere_aprobacion_comercial', 'no')
                    ->orWhere(function ($sub) {
                        $sub->where('p.requiere_aprobacion_comercial', 'yes')
                            ->where('p.aprobado_comercial', 'yes');
                    });
            });

            // Y (AND)

            // Condición 2: Cumple con Crediticio (No requiere O requiere y está aprobado)
            $mainQ->where(function ($qCred) {
                $qCred->where('p.requiere_aprobacion_crediticia', 'no')
                    ->orWhere(function ($sub) {
                        $sub->where('p.requiere_aprobacion_crediticia', 'yes')
                            ->where('p.aprobado_crediticio', 'yes');
                    });
            });
        });


        if ($item) {
            $query->where('d.ART_CODIGO', $item);
        }

        $resultados = $query->select(
            'd.ART_CODIGO as codigo',
            'd.ART_DESCRIPCION as descripcion',
            'st.STO_ART_BODEGA as bodega',
            'st.reservado',
            'st.seleccionado',
            'p.pedido',
            'p.created_at as fecha',
            'p.CLI_RUC',
            'c.CLI_RAZON_SOCIAL',
            'd.ART_CANTIDAD as cantidad_pedida',
            'p.USERID as userId'
        )
            ->orderBy('d.ART_CODIGO')
            ->orderBy('st.STO_ART_BODEGA')
            ->get();

        // Mapeo final para estandarizar estructura
        return $resultados->map(function ($articulo) {
            $articulo->tipo = 'pedido';
            $articulo->despachado = $articulo->seleccionado - $articulo->reservado;
            unset($articulo->seleccionado);
            return $articulo;
        });
    }

    public function getAllTransferReposition(?string $item = null): Collection
    {

        $query = DB::table('gp_traslados_reposicions as tr')
            ->join('gp_articulos as art', 'art.ART_CODIGO', '=', 'tr.ART_CODIGO')
            ->where(function ($q) {
                $q->whereNull('tr.completado')
                    ->orWhere('tr.completado', 0);
            });

        if ($item) {
            $query->where('tr.ART_CODIGO', $item);
        }

        $resultados = $query->select(
            'art.ART_CODIGO as codigo',
            'art.ART_DESCRIPCION as descripcion',
            'tr.bodega_origen as bodega',
            'tr.cantidad',
            'tr.trasladado',
            'tr.Documento as pedido',
            'tr.created_at as fecha'
        )->get();

        return $resultados->map(function ($traslado) {
            $traslado->reservado = (float) round($traslado->cantidad - $traslado->trasladado, 3) ?? 0;
            $traslado->despachado = (float) round($traslado->trasladado, 3);
            $traslado->cantidad_pedida = (float) round($traslado->cantidad, 3);
            $traslado->CLI_RUC = 'TUVAL';
            $traslado->CLI_RAZON_SOCIAL = 'TUVAL';
            $traslado->tipo = 'traslado';
            $traslado->userId = 'TUVAL';

            unset($traslado->cantidad);
            unset($traslado->trasladado);

            return $traslado;
        });
    }


    public function getAllQuoteRerservations(?string $item = null): Collection
    {
        $query = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', '=', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_clientes as c', 'c.id', '=', 'p.cliente_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->where('st.seleccionado', '>', 0)
            ->where('st.cantidad_traslado', '=', 0)
            ->whereIn('r.estado', ['aprobado', 'traslado']);

        if ($item) {
            $query->where('d.ART_CODIGO', $item);
        }

        $resultados = $query->select(
            'd.ART_CODIGO as codigo',
            'd.ART_DESCRIPCION as descripcion',
            'st.STO_ART_BODEGA as bodega',
            'st.seleccionado',
            'st.cantidad_reservada',
            'st.cantidad_facturada',
            'st.cantidad_cancelada',
            'p.cotizacion as pedido',
            'p.created_at as fecha',
            'p.CLI_RUC',
            'c.CLI_RAZON_SOCIAL',
            'st.cantidad_reservada as cantidad_pedida',
            'p.USERID as userId'
        )
            ->orderBy('d.ART_CODIGO')
            ->orderBy('st.STO_ART_BODEGA')
            ->get();


        return $resultados->map(function ($articulo) {
            $articulo->tipo = 'cotizacion';
            $articulo->reservado = $articulo->cantidad_reservada - $articulo->cantidad_facturada - $articulo->cantidad_cancelada;

            $articulo->despachado = $articulo->cantidad_facturada;
            unset($articulo->cantidad_facturada);
            unset($articulo->cantidad_cancelada);
            unset($articulo->cantidad_reservada);
            unset($articulo->seleccionado);
            return $articulo;
        });
    }


    public function getAllPendingReservationTransfer(?string $item = null)
    {
        $query = DB::table('gp_traslados_reservas as tr')
            ->join('gp_articulos as art', 'art.ART_CODIGO', '=', 'tr.art_code')
            ->join('gp_cotizacions as ct', 'ct.id', '=', 'tr.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'ct.id')
            ->join('gp_usuarios as u', 'u.id', '=', 'r.user_ingreso_reserva_id')

            ->where(function ($q) {
                $q->whereNull('tr.completado')
                    ->orWhere('tr.completado', 0);
            })
            ->where('tr.cantidad', '>', 0);

        if ($item) {
            $query->where('tr.art_code', $item);
        }

        $resultados = $query->select(
            'art.ART_CODIGO as codigo',
            'art.ART_DESCRIPCION as descripcion',
            'tr.bodega_origen as bodega',
            'tr.cantidad',
            'tr.trasladado',
            'ct.cotizacion as pedido',
            'tr.created_at as fecha',
            'u.USERID as userId'
        )->get();

        return $resultados->map(function ($tr) {
            $tr->reservado = (float) round($tr->cantidad - $tr->trasladado, 3) ?? 0;
            $tr->despachado = (float) round($tr->trasladado, 3) ?? 0;
            $tr->cantidad_pedida = (float) round($tr->cantidad, 3) ?? 0;
            $tr->CLI_RUC = 'TUVAL';
            $tr->CLI_RAZON_SOCIAL = 'TUVAL';
            $tr->tipo = 'traslado_reserva';
            unset($tr->cantidad);
            unset($tr->trasladado);
            return $tr;
        });
    }




    public function searchWithDetails(array $filters)
    {
        $isItemL = $this->isSearchItemsL($filters);
        $articulos = $isItemL
            ? GpArticulo::searchItemLModel($filters)
            : GpArticulo::searchItemModel($filters);

        if ($articulos->isEmpty()) {
            return collect();
        }
        $serviceTypes = ['VTA-SERV-L', 'VTA-BNS-L'];
        $filtersArt = $articulos->reject(function ($art) use ($serviceTypes) {
            return in_array($art->ART_CLASE_ARTICULO, $serviceTypes);
        });
        $groupPrices = collect();
        if ($filtersArt->isNotEmpty()) {
            $codesApi = $filtersArt->pluck('ART_CODIGO')->implode(',') . ',';
            $groupPrices = $this->getApiPriceArticle($codesApi);
        }
        $groupsByAbc = collect();
        if ($filtersArt->isNotEmpty()) {
            $codesList = $filtersArt->pluck('ART_CODIGO');
            $groupsByAbc = $this->getApiSegmentsAbcItem($codesList);
        }
        return $articulos->map(function ($art) use ($filters,  $serviceTypes, $groupPrices, $groupsByAbc) {
            $isService = in_array($art->ART_CLASE_ARTICULO, $serviceTypes);
            if ($isService) {
                $art->ART_TIPO_COMPRA = 'L';
            }
            $art->ART_VALOR = $art->ART_VALOR ?? 0;
            $myPrice = $groupPrices->get($art->ART_CODIGO, collect());
            $nivelPrecio = data_get($filters, 'nivel_precio', null);
            if ($nivelPrecio) {
                $precioEncontrado = $myPrice->firstWhere('idLista', $nivelPrecio);
                $valor = $precioEncontrado ? data_get($precioEncontrado, 'valor', 0) : 0;
                $art->ART_VALOR = (float) round($valor, 4);
                $art->ART_VALOR_ORIGINAL = $art->ART_VALOR;
                $art->ART_DESCRIPCION_ORIGINAL = $art->ART_DESCRIPCION;
            } else {
                $art->precios = $myPrice->values();
            }
            //obtencion de abc
            $myAbc = $groupsByAbc->get($art->ART_CODIGO, collect());
            $classAbc = ($myAbc && $myAbc->isNotEmpty()) ? data_get($myAbc->first(), 'tuval', '-') : '-';
            $art->clasificacion_tuval = $classAbc;
            $art->debe_resaltar = $this->shouldHighlightSegment($classAbc);
            $art->proveedores = [];
            $art->descuento = 0;
            $art->descuento_original = 0;
            $art->modificador = 0;
            $art->imagenes = $this->getImageArticle($art->ART_CODIGO_TRES_LETRAS);

            return $art;
        })->filter(
            function ($articulo) use ($filters) {
                if ($articulo->ART_TIPO_COMPRA === 'L') {
                    return true;
                }
                if (!empty($filters['nivel_precio'])) {
                    return $articulo->ART_VALOR >= 0;
                }
                return count($articulo->precios) > 0;
            }
        )->sortBy('ART_CODIGO_ZAFIRO')->values();
    }

    public function isSearchItemsL(array $filters)
    {
        $campChecked = ['codigo3l', 'text_codigo', 'text_nombre', 'terminal'];
        $valoresObjetivo = ['item l', 'items l', 'l'];

        foreach ($campChecked as $camp) {
            if (!isset($filters[$camp])) {
                continue;
            }

            $normalizeCheck = strtolower(trim($filters[$camp]));
            if (in_array($normalizeCheck, $valoresObjetivo, true)) {
                return true;
            }
        }

        return false;
    }


    public function getApiPriceArticle($article)
    {
        $response = $this->stockWarehouseService->fetchListPricesArticle($article);
        return  collect($response)->groupBy('item');
    }

    public function getApiSegmentsAbcItem($articles)
    {
        $itemList = is_iterable($articles) ? collect($articles)->values()->toArray() : [$articles];
        $response = $this->stockWarehouseService->fetchListSegmentsAbc($itemList);
        return  collect($response)->groupBy('item');
    }

    public function getImageArticle(string $codArt)
    {
        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "images/productos/{$codArt}_{$i}.jpg";
            $pathAbsolute = public_path($path);
            if (File::exists($pathAbsolute)) {
                $images[] = asset($path);
            }
        }
        return $images;
    }

    public function shouldHighlightSegment(?string $clase): bool
    {
        return AbcSegment::shouldHighlight($clase);
    }





    public function fetchAllTerritories()
    {
        return GpTerritorio::orderBy('TERRITORIO_CODIGO', 'desc')->get();
    }

    public function fetchAllProvinces()
    {
        return GpProvinciaCiudadParroquia::selectRaw('MIN(id) as id, provincia')
            ->groupBy('provincia')
            ->orderBy('provincia', 'asc')
            ->get();
    }

    public function fetchCitiesByProvince(string $provId)
    {
        return GpProvinciaCiudadParroquia::selectRaw('MIN(id) as id, ciudad')
            ->where('provincia', $provId)
            ->groupBy('ciudad')
            ->orderBy('ciudad', 'asc')
            ->get();
    }


    /**
     *  funcion para consultar stock reservados por item
     *
     */

    public function listReservedStockForItems(string $itemsRaw)
    {
        $items = explode(',', $itemsRaw);
        $items = array_map('trim', $items);
        $baseOrders = fn() => DB::table('gp_pedido_detalle_stocks as st')
            ->join('gp_pedido_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_pedidos as p', 'p.id', '=', 'st.pedido_id')
            ->whereIn('d.ART_CODIGO', $items)
            ->where('st.reservado', '>', 0)
            ->whereNotIn('p.estado', [
                OrderStatus::ELIMINADO->value,
                OrderStatus::RECHAZADO->value,
                OrderStatus::ANULADA->value
            ])
            ->where(function ($q) {
                $q->whereNull('p.despachado')->orWhere('p.despachado', 0);
            })
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.reservado AS reservado,
            'pedido' as tipo"
            );

        //caso a : no requiere comercial, no requiere crediticia
        $pA = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'no')
            ->where('p.requiere_aprobacion_crediticia', 'no');

        //Caso B: no requiere comercial, requiere crediticia y está aprobada
        $pB = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'no')
            ->where('p.requiere_aprobacion_crediticia', 'yes')
            ->where('p.aprobado_crediticio', 'yes');

        // Caso C: requiere comercial y aprobada, no requiere crediticia
        $pC = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'yes')
            ->where('p.aprobado_comercial', 'yes')
            ->where('p.requiere_aprobacion_crediticia', 'no');

        // Caso D: requiere ambas y ambas aprobadas
        $pD = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'yes')
            ->where('p.aprobado_comercial', 'yes')
            ->where('p.requiere_aprobacion_crediticia', 'yes')
            ->where('p.aprobado_crediticio', 'yes');
        $unionOrders = $pA->unionAll($pB)->unionAll($pC)->unionAll($pD);

        $traslados = DB::table('gp_traslados_reposicions as t')
            ->join('gp_articulos as a', 'a.ART_CODIGO', '=', 't.ART_CODIGO')
            ->whereIn('t.ART_CODIGO', $items)
            ->where(function ($q) {
                $q->whereNull('t.completado')->orWhere('t.completado', 0);
            })
            ->selectRaw(
                "
            t.ART_CODIGO as codigo,
            t.bodega_origen AS bodega,
            (t.cantidad - COALESCE(t.trasladado, 0)) AS reservado,
            'traslado' as tipo"
            );

        // ----- UNION final y ordenamiento -----

        $unionAll = DB::query()->fromSub($unionOrders, 'ped')
            ->unionAll($traslados);
        $response = DB::query()->fromSub($unionAll, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
        $response2 = $this->listReservedInventoryByPendingUseByItems($items);
        $response3 = $this->listReservedInventoryByTransfersByItems($items);
        return collect($response)
            ->concat($response2)
            ->concat($response3)
            ->values();
    }

    public function listReservedInventoryByPendingUseByItems(array $items)
    {
        $baseQuotation = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->whereIn('d.ART_CODIGO', $items)
            ->where('st.seleccionado', '>', 0)
            ->where('st.cantidad_traslado', '=', 0)
            ->whereIn('r.estado', ['aprobado', 'traslado'])
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.seleccionado AS reservado,
            p.cotizacion as cotizacion,
            'reserva' as tipo",

            );
        return DB::query()->fromSub($baseQuotation, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
    }

    public function listReservedInventoryByTransfersByItems(array $items)
    {
        $baseQuotation = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->join('gp_traslados_reservas as tr', 'tr.stock_id', '=', 'st.id')
            ->whereIn('d.ART_CODIGO', $items)
            ->where('st.cantidad_traslado', '>', 0)
            ->whereIn('r.estado', ['traslado'])
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
             (
               COALESCE(SUM(tr.cantidad), 0)
                  - COALESCE(SUM(tr.trasladado), 0)
             ) AS reservado,
            p.cotizacion as cotizacion,
            'traslado_reservas' as tipo",
            )
            ->groupBy(
                'st.id',
                'd.ART_CODIGO',
                'st.STO_ART_BODEGA',
                'p.cotizacion',
                'st.cantidad_traslado',
                'st.cantidad_cancelada'
            );
        return DB::query()->fromSub($baseQuotation, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
    }
}

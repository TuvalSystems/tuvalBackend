<?php

namespace App\Services\Movil\Gestion;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\TaxRateEnum;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Api\Bodegas\ApiGestionArticleService;
use App\Services\Movil\Gestion\Contracts\SteamTrapServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SteamTrapService implements SteamTrapServiceInterface
{
    public function __construct(
        protected readonly ApiGestionArticleService $api,
        protected readonly ClientQueryServiceInterface $client,
        protected readonly StockWarehouseServiceInterface $stockWarehouse,
        protected readonly OrderAndQuotationServiceInterface $OrderQuoteCommon
    ) {}

    public function generateSizingPdf(array $data): string
    {
        $clienteId          = data_get($data, 'customer_id');
        $params             = data_get($data, 'spParams', []);
        $info               = $this->getSteamTrapItems($params);
        $selectedItem       = data_get($info, 'selectedItem');
        if (empty($selectedItem)) {
            throw new \RuntimeException('No se encontró un artículo recomendado.');
        }
        $originItems        = data_get($info, 'originItems', []);
        $itemCode           = data_get($selectedItem, 'txItem');
        $itemImages         = $itemCode ? $this->getImageArticle($itemCode) : [];
        $mainItemImage      = $itemImages[0] ?? null;
        $viewData           = [
            'requestData'   => $data,
            'spParams'      => $params,
            'selectedItem'  => $selectedItem,
            'originItems'   => $originItems,
            'rawResponse'   => $info,
            'cliente'       => GpCliente::find($clienteId),
            'articulo'      => GpArticulo::where('ART_CODIGO_TRES_LETRAS', $itemCode)->first(),
            'mainItemImage' => $mainItemImage,
        ];
        $html   = view('pdf.movil.steam_trap_sizing', $viewData)->render();
        $pdf    = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $fileName   = 'dimensionamiento-trampa-' . now()->format('YmdHis') . '-' . Str::random(6) . '.pdf';
        $path       = 'trampas-vapor/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());
        return asset('storage/' . $path);
    }

    protected function getSteamTrapItems(array $params = [])
    {
        $resp =  $this->api->getTrampasVaporItems($params);
        return data_get($resp, 'data', []);
    }

    protected function getImageArticle(string $codArt): array
    {
        $images = [];

        for ($i = 1; $i <= 3; $i++) {
            $relativePath = "images/productos/{$codArt}_{$i}.jpg";
            $absolutePath = public_path($relativePath);

            if (File::exists($absolutePath)) {
                $images[] = $absolutePath;
            }
        }

        return $images;
    }



    public function generateQuoteDraft(array $payload, GpUsuario $user): mixed
    {
        $clienteId          = data_get($payload, 'customer_id');
        $ruc                = data_get($payload, 'customer_ruc');
        $cliente             = $this->client->getAvailabeCreditCustomer($ruc);
        if (!data_get($cliente, 'cliente')) {
            throw new \RuntimeException('No se encontró información del cliente.');
        }

        $sucursalId           = GpClienteSucursal::where('id', $clienteId)->where('estado', true)->where('CLI_SUC_ID_DIRECCION', 'PRINCIPAL')->value('id');
        if (!$sucursalId) {
            throw new \RuntimeException('La sucursal seleccionada no existe o no se encuentra activa.');
        }

        $condPayment        = data_get($cliente, 'cliente.CLI_CONDICION_PAGO');
        $typePayment           = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $condPayment)->orderBy('id', 'asc')->value('DESC_FORMA_PAGO_CODIGO');
        if (!$typePayment) {
            throw new \RuntimeException('No existen formas de pago relacionadas para la condición de pago del cliente al cual quiere generar el borrador.');
        }

        //bsuqueda de item
        $params             = data_get($payload, 'spParams', []);
        $info               = $this->getSteamTrapItems($params);
        $selectedItem       = data_get($info, 'selectedItem');
        $suggestItems       = data_get($info, 'originItems', []);

        if (empty($selectedItem)) {
            throw new \RuntimeException('No se encontró un artículo recomendado.');
        }

        $itemMetaByCode     = $this->resolveSteamTrapArticleCodes($suggestItems, $selectedItem);
        $artCodes           = $itemMetaByCode->keys()->values();
        $articulos          = GpArticulo::whereIn('ART_CODIGO_TRES_LETRAS', $artCodes)->get();
        //obtener lista de precio de los articulos
        $groupPrices        = $this->getPriceListGroupedByArticle($articulos);
        $this->identifyGroupPricesToArticle($articulos, $groupPrices, $itemMetaByCode, data_get($cliente, 'cliente', []));
        $articulos          = $articulos->sortBy(fn($item) => $item->orden ?? 999)->values();
        $salesPoint         = $this->getPrimarySalesPointWarehouseByUser($user->id);
        $warehouse          = data_get($salesPoint, 'PTO_VTA_BOD_REL_BODEGA');
        $ptoVtaCode         = data_get($salesPoint, 'PTO_VTA_BOD_REL_PUNTO_VENTA');
        $agency             = data_get($salesPoint, 'puntoVenta.PTO_VTA_DESCRIPCION');
        $info = [
            'sucursalId'    => $sucursalId,
            'warehouse'     => $warehouse,
            'ptoVtaCode'    => $ptoVtaCode,
            'agency'        => $agency,
            'typePayment'   => $typePayment,
        ];
        $headerQuote        = $this->payloadQuotationHeaderDraft($user, data_get($cliente, 'cliente'),  $info);
        $this->payloadQuotationDetailDraft($articulos, $headerQuote->id, data_get($cliente, 'cliente'));
        $this->OrderQuoteCommon->recalculateQuotation($headerQuote->id);
        $clienteNombre = data_get($cliente, 'cliente.CLI_RUC', 'el cliente');
        $mensaje = "Se generó la cotización borrador {$headerQuote->cotizacion} para {$clienteNombre}. Revísala desde el sistema Web antes de continuar.";
        $user->notify(new UserNotification('Cotización Borrador', $mensaje));
        return $headerQuote->refresh();
    }

    protected function getPrimarySalesPointWarehouseByUser(int $userId)
    {
        return GpRelacionPuntoVentaBodegaRelacionada::query()
            ->with([
                'relacionBodega:id,BOD_BODEGA,PERMITE_DESPACHO',
                'puntoVenta:id,PTO_VTA_ID,PTO_VTA_DESCRIPCION'

            ])
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '<>', 'CONSULTA')
            ->whereHas('puntoVenta.relacionUsuarioPtoVta', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('orden', 1);
            })
            ->orderBy('id')
            ->first();
    }

    protected function resolveSteamTrapArticleCodes(array  $suggestItems, array $selectedItem)
    {
        $selectedCode = trim((string) data_get($selectedItem, 'txItem'));
        $selected = collect([
            [
                'codigo'    => $selectedCode,
                'cantidad'  => 1,
                'orden'     => 0,
                'tipo'      => 'principal'
            ]
        ]);

        $suggested = collect($suggestItems)
            ->map(function ($itm) {
                return [
                    'codigo'    => trim(data_get($itm, 'txItemDes')),
                    'cantidad'  => (float) data_get($itm, 'neCan', 1),
                    'orden'     => (int) data_get($itm, 'neOrd', 999),
                    'tipo'      => 'sugeridos',
                ];
            });

        return $selected
            ->merge($suggested)
            ->filter(fn($item) => !empty(data_get($item, 'codigo')))
            ->groupBy('codigo')
            ->map(function ($items, $codigo) {
                $first = $items->sortBy('orden')->first();
                return [
                    'codigo'      => $codigo,
                    'cantidad'    => (float) $items->sum('cantidad'),
                    'orden'       => (int) data_get($first, 'orden'),
                    'tipo'        => data_get($first, 'tipo'),
                ];
            });
    }

    protected function getPriceListGroupedByArticle(Collection $articulos): Collection
    {
        $codesApi = $articulos->reject(function ($item) {
            return $item->ART_TIPO_COMPRA === 'L';
        })->pluck('ART_CODIGO')->implode(',') . ',';

        return  $this->fetchPriceListGroupedByArticle($codesApi);
    }

    protected function fetchPriceListGroupedByArticle(string $artCodes)
    {
        $resp = $this->stockWarehouse->fetchListPricesArticle($artCodes);
        return collect($resp)->groupBy('item');
    }

    protected function identifyGroupPricesToArticle(EloquentCollection $articles, Collection $groupPrices, Collection $itemMetaByCode, GpCliente $cliente): EloquentCollection
    {
        $priceListId = data_get($cliente, 'CLI_NIVEL_PRECIO');

        if (!$priceListId) {
            throw new \RuntimeException('El cliente no tiene nivel de precio configurado.');
        }
        $articles->each(function ($item) use ($groupPrices, $itemMetaByCode,  $priceListId) {
            $meta               = $itemMetaByCode->get($item->ART_CODIGO_TRES_LETRAS);
            $item->ART_CANTIDAD = (float) data_get($meta, 'cantidad', 1);
            $item->orden        = (int) data_get($meta, 'orden', 999);

            if ($item->ART_TIPO_COMPRA === 'L') {
                $item->ART_VALOR = 0;
                $item->ART_VALOR_ORIGINAL = 0;
                return;
            }
            $myPrice                  = $groupPrices->get($item->ART_CODIGO, collect());
            $priceFound               = $myPrice->firstWhere('idLista',  $priceListId);
            $valor                    = (float) data_get($priceFound, 'valor', 0);
            $item->ART_VALOR          = round($valor, 4);
            $item->ART_VALOR_ORIGINAL = round($valor, 4);

            return $item;
        });
        return $articles;
    }

    protected function payloadQuotationHeaderDraft(GpUsuario $user, GpCliente $cliente, array $info): GpCotizacion
    {
        $quote = new GpCotizacion();
        $quote->fill([
            'estado'                            => 'borrador',
            'user_id'                           => $user->id,
            'sucursal_id'                       => data_get($info, 'sucursalId'),
            'sucursal_facturacion_id'           => data_get($info, 'sucursalId'),
            'cliente_id'                        => $cliente->id,
            'factura_posterior_despacho'        => 0,
            'factura_a_demanda'                 => 0,
            'factura_siguiente_mes'             => 0,
            'facturado'                         => 0,
            'BOD_BODEGA'                        => data_get($info, 'warehouse'),
            'PTO_VTA_ID'                        => data_get($info, 'ptoVtaCode'),
            'PTO_VTA_DESCRIPCION'               => data_get($info, 'agency'),
            'USERID'                            => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO'            => data_get($info, 'typePayment'),
            'CLI_RUC'                           => $cliente->CLI_RUC,
            'CLI_RAZON_SOCIAL'                  => $cliente->CLI_RAZON_SOCIAL,
            'CLI_CORREO_ELECT_SRI'              => $cliente->CLI_CORREO_ELECT_SRI,
            'COND_ID'                           => $cliente->CLI_CONDICION_PAGO,
            'COND_ID_ORIGINAL'                  => $cliente->CLI_CONDICION_PAGO,
            'VEND_ID'                           => $cliente->gpvendedor->VEND_ID,
            'VEND_NOMBRES'                      => $cliente->gpvendedor->VEND_NOMBRES,
            'VEND_APELLIDOS'                    => $cliente->gpvendedor->VEND_APELLIDOS,
            'despacho'                          => 'Retiro en bodega',
            'orden_de_compra'                   => null,
            'porcentage_descuento'              => $cliente->CLI_DSTO_COMERCIAL,
            'porcentage_descuentooriginal'      => $cliente->CLI_DSTO_COMERCIAL,
            'subtotal'                          => 0,
            'descuento'                         => 0,
            'subtotalmenosdescuento'            => 0,
            'iva'                               => 0,
            'total'                             => 0,
            'requiere_aprobacion_comercial'     => 'no',
            'motivo_aprobacion_comercial'       => null,
            'aprobado_comercial'                => 'no',
            'aprobador_comercial_id'            => null,
            'requiere_aprobacion_crediticia'    => 'no',
            'aprobado_crediticio'               => 'no',
            'aprobador_crediticio_id'           => null,
            'impreso'                           => 'no',
            'nota_interna'                      => null,
            'nota_cliente'                      => null,
            'nota_operaciones'                  => null,
            'impuesto'                          => TaxRateEnum::IVA_15->value,
            'origen'                            => OrderOrigenEnum::APP_MOVIL->value,
            'modificador_global'                => 0,
        ]);
        $quote->save();
        return $quote;
    }


    protected function payloadQuotationDetailDraft(EloquentCollection $articles, int $quteId, GpCliente $cliente)
    {

        foreach ($articles as $art) {
            $qd = new GpCotizacionDetalle();
            $qd->fill([
                'cotizacion_id'                 => $quteId,
                'ART_CODIGO'                    => data_get($art, 'ART_CODIGO'),
                'ART_DESCRIPCION'               => data_get($art, 'ART_DESCRIPCION'),
                'ART_DESCRIPCION_ORIGINAL'      => data_get($art, 'ART_DESCRIPCION'),
                'CHECK_ALTERNATIVO'             => data_get($art, 'CHECK_ALTERNATIVO', 0),
                'ART_CODIGO_TRES_LETRAS'        => data_get($art, 'ART_CODIGO_TRES_LETRAS'),
                'ART_DECIMAL_CANTIDAD'          => data_get($art, 'ART_DECIMAL_CANTIDAD'),
                'ART_PLAN_UMEDIDA'              => data_get($art, 'ART_PLAN_UMEDIDA'),
                'ART_MARCA'                     => data_get($art, 'ART_MARCA'),
                'ART_MATERIAL'                  => data_get($art, 'ART_MATERIAL'),
                'ART_PESO'                      => data_get($art, 'ART_PESO'),
                'ART_TIPO_COMPRA'               => data_get($art, 'ART_TIPO_COMPRA'),
                'ART_VALOR'                     => (float)data_get($art, 'ART_VALOR'),
                'ART_VALOR_ORIGINAL'            => (float)data_get($art, 'ART_VALOR_ORIGINAL'),
                'ART_DESCUENTO'                 => 0,
                'ART_IVA'                       => (float)data_get($art, 'ART_IMP_VTA'),
                'ART_CANTIDAD'                  => (float) round(data_get($art, 'ART_CANTIDAD'), (int)data_get($art, 'ART_DECIMAL_CANTIDAD')),
                'ART_STOCK'                     => data_get($art, 'ART_STOCK', 0),
                'ART_IMP_VTA'                   => (float)data_get($art, 'ART_IMP_VTA'),
                'ART_COSTO_PROMEDIO'            => (float)data_get($art, 'ART_COSTO_PROMEDIO'),
                'descuento'                     => (float)data_get($cliente, 'CLI_DSTO_COMERCIAL'),
                'descuento_original'            => (float)data_get($cliente, 'CLI_DSTO_COMERCIAL'),
                'modificador'                   => 0,
                'valor_original_l'              => 0,
            ]);
            $qd->save();
        }
    }
}

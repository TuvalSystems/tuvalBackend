<?php

namespace App\Services\Movil\Gestion;

use App\Mail\Cotizacion\CotizacionBorradorAppMail;
use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\CondicionesPago\GpDescuentoFormaPago;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodega;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Movil\Gestion\TrampaVapor;
use App\Notifications\User\UserNotification;
use App\Services\Api\ApiGpService;
use App\Services\Api\Bodegas\ApiGestionArticleService;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Services\Movil\Gestion\Contracts\SteamTrapServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SteamTrapService implements SteamTrapServiceInterface
{
    public function __construct(
        protected readonly ApiGestionArticleService $api,
        protected readonly ApiGpService  $apiGp,
        protected readonly ClientQueryServiceInterface $client,
        protected readonly StockWarehouseServiceInterface $stockWarehouse,
        protected readonly OrderAndQuotationServiceInterface $OrderQuoteCommon,
        protected readonly QuotationBuilderInterface $quoteBuilder
    ) {}

    public function generateSizingPdf(array $data, GpUsuario $user): string
    {
        $clienteId          = data_get($data, 'customer_id');
        $params             = data_get($data, 'spParams', []);
        $info               = $this->getSteamTrapItems($params);
        $selectedItem       = data_get($info, 'selectedItem');
        if (empty($selectedItem)) {
            throw new \RuntimeException('No se encontró un artículo recomendado.');
        }
        $originItems        = collect(data_get($info, 'originItems', []));
        $originItemsByCode = $originItems
            ->mapWithKeys(function ($item) {
                $codigo = trim((string) data_get($item, 'txItemDes'));

                return [$codigo => $item];
            });

        $originKeys = $originItemsByCode
            ->keys()
            ->filter()
            ->unique()
            ->values()
            ->all();

        $artSelects = GpArticulo::whereIn('ART_CODIGO_TRES_LETRAS', $originKeys)
            ->get()
            ->map(function ($articulo) use ($originItemsByCode) {
                $codigo = trim((string) $articulo->ART_CODIGO_TRES_LETRAS);

                $originItem = $originItemsByCode->get($codigo);

                $articulo->neCan = data_get($originItem, 'neCan', 1);
                $articulo->neOrd = data_get($originItem, 'neOrd');

                return $articulo;
            });
        $itemCode           = data_get($selectedItem, 'txItem');
        $itemImages         = $itemCode ? $this->getImageArticle($itemCode) : [];
        $mainItemImage      = $itemImages[0] ?? null;
        $viewData           = [
            'requestData'   => $data,
            'spParams'      => $params,
            'selectedItem'  => $selectedItem,
            'originItems'   => $artSelects,
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
        $this->createRegisterSteamTramp($data, $user);
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

    protected function createRegisterSteamTramp(array $payload, GpUsuario $user)
    {
        $new = new TrampaVapor();
        $new->fill([
            'user_id'                           => data_get($user, 'id'),
            'cliente_id'                        => data_get($payload, 'customer_id'),
            'proceso'                           => data_get($payload, 'process'),
            'tipo_trampa'                       => data_get($payload, 'trapType'),
            'metodo_calculo'                    => data_get($payload, 'calculationMethod'),
            'presion_trabajo_psig'              => data_get($payload, 'workingPressurePsig'),
            'presion_retorno_psig'              => data_get($payload, 'returnPressurePsig'),
            'presion_diferencial_psig'          => data_get($payload, 'pressureDifferentialPsig'),
            'fluido'                            => data_get($payload, 'fluid'),
            'volumen_m3'                        => data_get($payload, 'volumeM3'),
            'densidad_kg_m3'                    => data_get($payload, 'densityKgM3'),
            'cp_kj_kg_c'                        => data_get($payload, 'cpKjKgC'),
            'temperatura_inicial_c'             => data_get($payload, 'initialTemperatureC'),
            'temperatura_final_c'               => data_get($payload, 'finalTemperatureC'),
            'tiempo_calentamiento_min'          => data_get($payload, 'heatingTimeMin'),
            'calor_requerido_kj'                => data_get($payload, 'requiredHeatKj'),
            'hfg_kj_kg'                         => data_get($payload, 'hfgKjKg'),
            'carga_conocida_kg_h'               => data_get($payload, 'knownLoadKgH'),
            'potencia_caldera_bhp'              => data_get($payload, 'boilerBhp'),
            'carga_estimada_kg'                 => data_get($payload, 'estimatedLoadKg'),
            'factor_seguridad'                  => data_get($payload, 'safetyFactor'),
            'carga_con_factor_seguridad_kg'     => data_get($payload, 'loadWithSafetyFactorKg'),
            'parametros_sp'                     => data_get($payload, 'spParams'),
        ]);
        $new->save();
    }


    /**
     *
     *  generacion de borrador de cotizacion
     *
     */


    public function generateQuoteDraft(array $payload, GpUsuario $user): mixed
    {
        $clienteId          = data_get($payload, 'customer_id');
        $ruc                = data_get($payload, 'customer_ruc');
        $cliente             = $this->client->getAvailabeCreditCustomer($ruc);
        if (!data_get($cliente, 'cliente')) {
            throw new \RuntimeException('No se encontró información del cliente.');
        }

        $sucursalId           = GpClienteSucursal::where('cliente_id', $clienteId)->where('estado', true)->where('CLI_SUC_ID_DIRECCION', 'PRINCIPAL')->value('id');
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
        $warehouse         = $this->getWarehouseByPtoVta(data_get($payload, 'pvCode'));

        $info = [
            'sucursalId'    => $sucursalId,
            'warehouse'     => $warehouse,
            'ptoVtaCode'    => data_get($payload, 'pvCode'),
            'agency'        => data_get($payload, 'pvDescription'),
            'typePayment'   => $typePayment,
        ];
        $headerQuote        = $this->payloadQuotationHeaderDraft($user, data_get($cliente, 'cliente'),  $info);
        $this->payloadQuotationDetailDraft($articulos, $headerQuote->id, data_get($cliente, 'cliente'));
        $this->OrderQuoteCommon->recalculateQuotation($headerQuote->id);
        $clienteNombre = data_get($cliente, 'cliente.CLI_RUC', 'el cliente');
        $mensaje = "Se generó la cotización borrador {$headerQuote->cotizacion} para {$clienteNombre}. Revísala desde el sistema Web antes de continuar.";
        $user->notify(new UserNotification('Cotización Borrador', $mensaje));

        $this->sendMailQuoteDraft($headerQuote, $user);

        return $headerQuote->refresh();
    }

    protected function getWarehouseByPtoVta(string $ptoVta): ?string
    {
        return GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVta)->value('PTO_VTA_BOD_BODEGA');
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
        $quote = $this->quoteBuilder->payloadQuotationHeaderDraft($user, $cliente, $info);
        $quote->save();
        return $quote;
    }

    protected function payloadQuotationDetailDraft(EloquentCollection $articles, int $quoteId, GpCliente $cliente)
    {
        foreach ($articles as $art) {
            $this->quoteBuilder->payloadQuotationDetailDraft($art, $quoteId, $cliente);
        }
    }

    protected function sendMailQuoteDraft(GpCotizacion $quote, GpUsuario $user)
    {
        $quote->load('detallecotizacion')->refresh();
        $data = $this->searchEmailByUserName($user->USERID);
        $email = data_get($data, 'email');
        if ($email) {
            Log::info('email encontrado para envio de correo de appMovil', [
                'data' => $data
            ]);
            // $email = 'sistemas6@tuvalsa.com';
            Mail::to($email)->queue(new CotizacionBorradorAppMail($quote));
        }
        Log::info('email no encontrado para el usuario ', [
            'usuario' => $user
        ]);
    }


    protected function searchEmailByUserName(string $username)
    {
        $resp = $this->apiGp->getEmailUser([
            'userName' => $username,
        ]);
        return data_get($resp, 'data', []);
    }
}

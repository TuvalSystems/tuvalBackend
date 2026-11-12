<?php

namespace App\Services\Ventas\Cotizaciones\Borradores;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Inventario\Articulos\GpUnidadMedida;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Clientes\ClienteConfiguracion;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Ventas\CondicionesPago\GpDescuentoFormaPago;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Services\Ventas\Compartido\Contracts\OrderAndQuotationServiceInterface;
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuotationItemsDraftServiceInterface;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotationItemsDraftService implements QuotationItemsDraftServiceInterface
{

    public function __construct(
        protected readonly QuotationBuilderInterface $builder,
        protected readonly ApiGpService $apiGp,
        protected readonly StockWarehouseServiceInterface $stockWarehouse,
        protected readonly OrderAndQuotationServiceInterface $orderQuoteCommon,
    ) {}

    public function create(array $data, GpUsuario $user): array
    {
        [$items, $warnings] = $this->resolveDraftItems($data);

        if (!$items) {
            return ['id' => null, 'numero' => null, 'incluidos' => [], 'omitidos' => [], 'advertencias' => $warnings];
        }

        return DB::transaction(function () use ($data, $user, $items, $warnings) {
            $pv     = $this->getAuthorizedPointOfSale($data['pv_id'], $user);
            $client = $this->getActiveClient($data['cliente_ruc']);
            $seller = $this->getConfiguredSeller($client, $pv);
            $client->CLI_DSTO_COMERCIAL = (float) ($client->CLI_DSTO_COMERCIAL ?? 0);
            $headerInfo = $this->prepareDraftHeaderInfo($client, $pv);
            [$articles, $included, $omitted] = $this->prepareDraftArticles($items);

            if (!$articles) {
                return ['id' => null, 'numero' => null, 'incluidos' => [], 'omitidos' => $omitted, 'advertencias' => $warnings];
            }

            $groupPrices = $this->getPriceListGroupedByArticle($articles);
            $this->identifyGroupPricesToArticle($articles, $groupPrices, $client);

            $quote = $this->saveDraftHeader($user, $client, $headerInfo, $seller);
            $this->saveDraftDetails($articles, $quote->id, $client);
            $this->orderQuoteCommon->recalculateQuotation($quote->id);
            $quote->refresh();

            $response = $this->buildDraftResponse($quote, $user, $pv, $client, $included);
            $response['advertencias'] = $warnings;
            $response['omitidos'] = $omitted;

            return $response;
        }, 3);
    }

    protected function resolveDraftItems(array $data): array
    {
        $details = $data['detalle'];
        $response = $this->apiGp->resolveArticles([
            'detalle' => $details,
            'maxCandidatos' => $data['maxCandidatos'] ?? 6,
        ]);

        if (($response['success'] ?? false) !== true || !is_array($response['data'] ?? null)) {
            throw new HttpException(502, 'No se pudieron resolver los artículos en API GP.');
        }

        $items = [];
        $warnings = [];
        $seen = [];
        foreach ($response['data'] as $result) {
            $index = $result['indice'] ?? null;
            if (!is_int($index) || !array_key_exists($index, $details) || isset($seen[$index])) {
                continue;
            }
            $seen[$index] = true;
            $candidates = !empty($result['articulo'])
                ? [$result['articulo']]
                : ($result['candidatos'] ?? []);
            if (!is_array($candidates) || !$candidates) {
                continue;
            }

            if (!empty($result['busquedaTruncada']) || !empty($result['candidatosTruncados'])) {
                $warnings[] = [
                    'indice' => $index,
                    'texto' => $details[$index]['texto'],
                    'mensaje' => 'Se incluyeron los candidatos devueltos; la búsqueda tiene resultados truncados.',
                ];
            }

            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                $code = $candidate['codigoGP'] ?? null;
                if (!is_string($code) || trim($code) === '') {
                    continue;
                }
                $items[] = [
                    'codigo' => $code,
                    'cantidad' => $details[$index]['cantidad'] ?? 1,
                    'indice' => $index,
                    'cantidad_asumida' => !isset($details[$index]['cantidad']),
                ];
            }
        }

        return [$items, $warnings];
    }

    protected function getAuthorizedPointOfSale(int $pvId, GpUsuario $user): GpPuntoVenta
    {
        $pv = GpPuntoVenta::whereKey($pvId)
            ->where('PTO_VTA_ESTADO', 'ACTIVO')
            ->first();

        if (!$pv || !$pv->relacionUsuarioPtoVta()->where('user_id', $user->id)->exists()) {
            throw new HttpException(403, 'El usuario debe tener permiso sobre el punto de venta.');
        }

        return $pv;
    }

    protected function getActiveClient(string $ruc): GpCliente
    {
        $client = GpCliente::where('CLI_RUC', $ruc)
            ->where('CLI_ESTADO', 'ACTIVO')
            ->first();
        if (!$client) {
            throw ValidationException::withMessages([
                'cliente_ruc' => 'Cliente inexistente o inactivo.',
            ]);
        }

        return $client;
    }

    protected function getConfiguredSeller(GpCliente $client, GpPuntoVenta $pv): GpVendedor
    {
        $clientConfig = ClienteConfiguracion::with('vendedor')
            ->where('cliente_id', $client->id)
            ->where('pv_id', $pv->id)
            ->where('estado', 1)
            ->first();
        if (!$clientConfig) {
            throw ValidationException::withMessages([
                'cliente_ruc' => 'El cliente no tiene una configuración activa para este punto de venta.',
            ]);
        }

        $seller = $clientConfig->vendedor;
        if (!$seller) {
            throw ValidationException::withMessages([
                'cliente_ruc' => 'La configuración del cliente no tiene un vendedor válido para este punto de venta.',
            ]);
        }

        return $seller;
    }

    protected function prepareDraftHeaderInfo(GpCliente $client, GpPuntoVenta $pv): array
    {
        $sucursalId = GpClienteSucursal::where('cliente_id', $client->id)
            ->where('estado', 1)
            ->where('CLI_SUC_ID_DIRECCION', 'PRINCIPAL')
            ->orderBy('id')
            ->value('id');

        $payment = $client->CLI_CONDICION_PAGO
            ? GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $client->CLI_CONDICION_PAGO)
                ->orderBy('id')
                ->value('DESC_FORMA_PAGO_CODIGO')
            : null;

        $warehouse = $pv->relacionpuntoventabodegarelacionada()
            ->orderBy('orden')
            ->orderBy('id')
            ->value('PTO_VTA_BOD_REL_BODEGA');
        if (!$warehouse) {
            throw ValidationException::withMessages([
                'pv_id' => 'El punto de venta no tiene una bodega relacionada.',
            ]);
        }

        return [
            'sucursalId' => $sucursalId,
            'warehouse' => $warehouse,
            'pvId' => $pv->id,
            'ptoVtaCode' => $pv->PTO_VTA_ID,
            'agency' => $pv->PTO_VTA_DESCRIPCION,
            'typePayment' => $payment,
        ];
    }

    protected function prepareDraftArticles(array $items): array
    {
        $articlesByCode = GpArticulo::whereIn('ART_CODIGO', array_column($items, 'codigo'))
            ->get()
            ->groupBy('ART_CODIGO');
        $units = GpUnidadMedida::whereIn('UMEDIDA_PLAN', $articlesByCode->flatten(1)->pluck('ART_PLAN_UMEDIDA'))
            ->get()
            ->groupBy('UMEDIDA_PLAN');
        $errors = [];
        $articles = [];
        $included = [];
        $omitted = [];
        foreach ($items as $item) {
            $errorKey = "detalle.{$item['indice']}";
            $matches = $articlesByCode->get($item['codigo'], collect());
            if ($matches->count() !== 1 || $matches->first()->ART_CODIGO !== $item['codigo']) {
                $errors[$errorKey] = ["El código GP {$item['codigo']} no existe o no es inequívoco."];
                continue;
            }
            $article = clone $matches->first();
            $unavailableReasons = [];
            if ($article->ART_ESTADO !== 'ACTIVO') {
                $state = $article->ART_ESTADO ?? 'sin configurar';
                $unavailableReasons[] = "su estado es {$state} (ART_ESTADO); debe ser ACTIVO";
            }
            if (in_array($article->ART_CLASE_ARTICULO, ['BNS-INACT', 'CMP-GASTOS'], true)) {
                $unavailableReasons[] = "su clase {$article->ART_CLASE_ARTICULO} (ART_CLASE_ARTICULO) está excluida de cotizaciones";
            }
            if ($article->es_servicio_logistico) {
                $unavailableReasons[] = 'está marcado como servicio logístico (es_servicio_logistico = true)';
            }
            if ($article->ART_TIPO_COMPRA === 'L' && !$article->disponible) {
                $available = var_export($article->disponible, true);
                $unavailableReasons[] = "su tipo de compra es L (ART_TIPO_COMPRA) y no está habilitado como disponible (disponible = {$available})";
            }
            if ($unavailableReasons) {
                $omitted[] = [
                    'indice' => $item['indice'],
                    'codigo' => $item['codigo'],
                    'art_id' => $article->id,
                    'cantidad' => $item['cantidad'],
                    'razones' => $unavailableReasons,
                ];
                continue;
            }
            $unitMatches = $units->get($article->ART_PLAN_UMEDIDA, collect());
            $unit = $unitMatches->first();
            if ($unitMatches->count() !== 1 || !$unit->UMEDIDA_BASE) {
                $errors[$errorKey] = ["El artículo {$item['codigo']} no tiene una unidad base configurada de forma inequívoca."];
                continue;
            }
            $articleDecimals = filter_var($article->ART_DECIMAL_CANTIDAD, FILTER_VALIDATE_INT);
            $unitDecimals = filter_var($unit->UMEDIDA_CANTIDAD_DECIMAL, FILTER_VALIDATE_INT);
            if ($articleDecimals === false || $unitDecimals === false || $articleDecimals < 0 || $unitDecimals < 0) {
                $errors[$errorKey] = ["Falta configurar la precisión de la unidad del artículo {$item['codigo']}."];
                continue;
            }
            $decimals = min($articleDecimals, $unitDecimals, 2);
            $quantity = (float) $item['cantidad'];
            if (abs($quantity - round($quantity, $decimals)) > 0.0000001) {
                $errors["$errorKey.cantidad"] = ["La unidad del artículo {$item['codigo']} admite hasta $decimals decimales."];
                continue;
            }
            $article->ART_CANTIDAD = $quantity;
            $article->ART_PLAN_UMEDIDA = $unit->UMEDIDA_BASE;
            $articles[] = $article;
            $included[] = [
                'indice' => $item['indice'],
                'codigo' => $article->ART_CODIGO,
                'art_id' => $article->id,
                'cantidad' => $quantity,
                'cantidad_asumida' => $item['cantidad_asumida'],
                'unidad' => $unit->UMEDIDA_BASE,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$articles, $included, $omitted];
    }

    protected function getPriceListGroupedByArticle(array $articles): Collection
    {
        $codes = collect($articles)
            ->reject(fn (GpArticulo $article) => $article->ART_TIPO_COMPRA === 'L')
            ->pluck('ART_CODIGO')
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return collect($this->stockWarehouse->fetchListPricesArticle($codes->implode(',') . ','))
            ->groupBy('item');
    }

    protected function identifyGroupPricesToArticle(array $articles, Collection $groupPrices, GpCliente $client): void
    {
        $priceListId = $client->CLI_NIVEL_PRECIO;
        if (!$priceListId) {
            throw ValidationException::withMessages([
                'cliente_ruc' => 'El cliente no tiene nivel de precio configurado.',
            ]);
        }

        foreach ($articles as $article) {
            $value = 0;
            if ($article->ART_TIPO_COMPRA !== 'L') {
                $prices = $groupPrices->get($article->ART_CODIGO, collect());
                $price = $prices->firstWhere('idLista', $priceListId);
                $value = (float) data_get($price, 'valor', 0);
            }

            $article->ART_VALOR = round($value, 4);
            $article->ART_VALOR_ORIGINAL = round($value, 4);
        }
    }

    protected function saveDraftHeader(GpUsuario $user, GpCliente $client, array $info, GpVendedor $seller): GpCotizacion
    {
        $quote = $this->builder->payloadQuotationHeaderDraft($user, $client, $info, $seller);
        $quote->save();

        return $quote;
    }

    protected function saveDraftDetails(array $articles, int $quoteId, GpCliente $client): void
    {
        foreach ($articles as $article) {
            $this->builder->payloadQuotationDetailDraft($article, $quoteId, $client);
        }
    }

    protected function buildDraftResponse(GpCotizacion $quote, GpUsuario $user, GpPuntoVenta $pv, GpCliente $client, array $included): array
    {
        return [
            'id' => $quote->id,
            'numero' => $quote->cotizacion,
            'estado' => $quote->estado,
            'usuario' => ['id' => $user->id, 'codigo' => $user->USERID],
            'actor' => ['id' => $user->id, 'codigo' => $user->USERID],
            'punto_venta' => [
                'id' => $pv->id,
                'codigo' => $pv->PTO_VTA_ID,
                'descripcion' => $pv->PTO_VTA_DESCRIPCION,
            ],
            'cliente' => ['id' => $client->id, 'ruc' => $client->CLI_RUC],
            'incluidos' => $included,
            'advertencias' => [],
        ];
    }
}

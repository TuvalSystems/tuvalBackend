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
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuotationItemsDraftServiceInterface;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotationItemsDraftService implements QuotationItemsDraftServiceInterface
{

    public function __construct(
        protected readonly QuotationBuilderInterface $builder,
    ) {}

    public function create(array $data, GpUsuario $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            $pv     = $this->getAuthorizedPointOfSale($data['pv_id'], $user);
            $client = $this->getActiveClient($data['cliente_ruc']);
            $seller = $this->getConfiguredSeller($client, $pv);
            $client->CLI_DSTO_COMERCIAL = (float) ($client->CLI_DSTO_COMERCIAL ?? 0);
            $headerInfo = $this->prepareDraftHeaderInfo($client, $pv);
            [$articles, $included] = $this->prepareDraftArticles($data['items']);

            $quote = $this->saveDraftHeader($user, $client, $headerInfo, $seller);
            $this->saveDraftDetails($articles, $quote->id, $client);
            $quote->refresh();

            return $this->buildDraftResponse($quote, $user, $pv, $client, $included);
        }, 3);
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
        foreach ($items as $index => $item) {
            $matches = $articlesByCode->get($item['codigo'], collect());
            if ($matches->count() !== 1 || $matches->first()->ART_CODIGO !== $item['codigo']) {
                $errors["items.$index.codigo"] = ['Código GP exacto inexistente o no inequívoco.'];
                continue;
            }
            $article = $matches->first();
            if (
                $article->ART_ESTADO !== 'ACTIVO'
                || in_array($article->ART_CLASE_ARTICULO, ['BNS-INACT', 'CMP-GASTOS'], true)
                || $article->es_servicio_logistico
                || ($article->ART_TIPO_COMPRA === 'L' && !$article->disponible)
            ) {
                $errors["items.$index.codigo"] = ['Artículo no disponible para cotizar.'];
                continue;
            }
            $unitMatches = $units->get($article->ART_PLAN_UMEDIDA, collect());
            $unit = $unitMatches->first();
            if ($unitMatches->count() !== 1 || !$unit->UMEDIDA_BASE) {
                $errors["items.$index.codigo"] = ['El artículo no tiene una unidad base configurada de forma inequívoca.'];
                continue;
            }
            $articleDecimals = filter_var($article->ART_DECIMAL_CANTIDAD, FILTER_VALIDATE_INT);
            $unitDecimals = filter_var($unit->UMEDIDA_CANTIDAD_DECIMAL, FILTER_VALIDATE_INT);
            if ($articleDecimals === false || $unitDecimals === false || $articleDecimals < 0 || $unitDecimals < 0) {
                $errors["items.$index.codigo"] = ['Falta configurar la precisión de la unidad del artículo.'];
                continue;
            }
            $decimals = min($articleDecimals, $unitDecimals, 2);
            $quantity = (float) $item['cantidad'];
            if (abs($quantity - round($quantity, $decimals)) > 0.0000001) {
                $errors["items.$index.cantidad"] = ["La unidad del artículo admite hasta $decimals decimales."];
                continue;
            }
            $article->ART_CANTIDAD = $quantity;
            $article->ART_PLAN_UMEDIDA = $unit->UMEDIDA_BASE;
            // The existing draft builder uses zero placeholders; do not price or validate them here.
            $article->ART_VALOR = 0;
            $article->ART_VALOR_ORIGINAL = 0;
            $articles[] = $article;
            $included[] = [
                'indice' => $index,
                'codigo' => $article->ART_CODIGO,
                'art_id' => $article->id,
                'cantidad' => $quantity,
                'cantidad_asumida' => false,
                'unidad' => $unit->UMEDIDA_BASE,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$articles, $included];
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

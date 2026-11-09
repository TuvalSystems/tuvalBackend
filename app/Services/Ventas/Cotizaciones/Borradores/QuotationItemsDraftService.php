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
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuotationItemsDraftServiceInterface;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationItemsDraftService implements QuotationItemsDraftServiceInterface
{

    public function __construct(
        protected readonly QuotationBuilderInterface $builder,
    ) {}

    public function create(array $data, GpUsuario $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            
            $pv = GpPuntoVenta::whereKey($data['pv_id'])->where('PTO_VTA_ESTADO', 'ACTIVO')->first();
            abort_unless($pv && $pv->relacionUsuarioPtoVta()->where('user_id', $user->id)->exists(),
                403, 'El usuario debe tener permiso sobre el punto de venta.');
            // Store the draft's identity and items. Commercial validation belongs to the existing quotation flow.
            $client = GpCliente::where('CLI_RUC', $data['cliente_ruc'])->where('CLI_ESTADO', 'ACTIVO')->first();
            $this->requireValue($client, 'cliente_ruc', 'Cliente inexistente o inactivo.');
            $clientConfig = ClienteConfiguracion::with('vendedor')->where('cliente_id', $client->id)
                ->where('pv_id', $pv->id)->where('estado', 1)->first();
            $this->requireValue($clientConfig, 'cliente_ruc', 'El cliente no tiene una configuración activa para este punto de venta.');
            $seller = $clientConfig->vendedor;
            $this->requireValue($seller, 'cliente_ruc', 'La configuración del cliente no tiene un vendedor válido para este punto de venta.');
            $client->CLI_DSTO_COMERCIAL = (float) ($client->CLI_DSTO_COMERCIAL ?? 0);
            $sucursalId = GpClienteSucursal::where('cliente_id', $client->id)
                ->where('estado', 1)->where('CLI_SUC_ID_DIRECCION', 'PRINCIPAL')
                ->orderBy('id')->value('id');
            $payment = $client->CLI_CONDICION_PAGO ? GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $client->CLI_CONDICION_PAGO)
                ->orderBy('id')->value('DESC_FORMA_PAGO_CODIGO') : null;
            $warehouse = $pv->relacionpuntoventabodegarelacionada()
                ->orderBy('orden')->orderBy('id')->value('PTO_VTA_BOD_REL_BODEGA');
            $this->requireValue($warehouse, 'pv_id', 'El punto de venta no tiene una bodega relacionada.');

            $articlesByCode = GpArticulo::whereIn('ART_CODIGO', array_column($data['items'], 'codigo'))->get()->groupBy('ART_CODIGO');
            $units = GpUnidadMedida::whereIn('UMEDIDA_PLAN', $articlesByCode->flatten(1)->pluck('ART_PLAN_UMEDIDA'))->get()->groupBy('UMEDIDA_PLAN');
            $errors = [];
            $articles = [];
            $included = [];
            foreach ($data['items'] as $index => $item) {
                $matches = $articlesByCode->get($item['codigo'], collect());
                if ($matches->count() !== 1 || $matches->first()->ART_CODIGO !== $item['codigo']) {
                    $errors["items.$index.codigo"] = ['Código GP exacto inexistente o no inequívoco.'];
                    continue;
                }
                $article = $matches->first();
                if ($article->ART_ESTADO !== 'ACTIVO' || in_array($article->ART_CLASE_ARTICULO, ['BNS-INACT', 'CMP-GASTOS'], true)
                    || $article->es_servicio_logistico || ($article->ART_TIPO_COMPRA === 'L' && !$article->disponible)) {
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
                $included[] = ['indice' => $index, 'codigo' => $article->ART_CODIGO, 'art_id' => $article->id,
                    'cantidad' => $quantity, 'cantidad_asumida' => false, 'unidad' => $unit->UMEDIDA_BASE];
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $quote = $this->builder->payloadQuotationHeaderDraft($user, $client, [
                'sucursalId' => $sucursalId, 'warehouse' => $warehouse,
                'pvId' => $pv->id, 'ptoVtaCode' => $pv->PTO_VTA_ID, 'agency' => $pv->PTO_VTA_DESCRIPCION, 'typePayment' => $payment,
            ], $seller);
            $quote->save();
            foreach ($articles as $article) {
                $this->builder->payloadQuotationDetailDraft($article, $quote->id, $client);
            }
            $quote->refresh();
            return [
                'id'            => $quote->id, 'numero' => $quote->cotizacion, 'estado' => $quote->estado,
                'usuario'       => ['id' => $user->id, 'codigo' => $user->USERID],
                'actor'         => ['id' => $user->id, 'codigo' => $user->USERID],
                'punto_venta'   => ['id' => $pv->id, 'codigo' => $pv->PTO_VTA_ID, 'descripcion' => $pv->PTO_VTA_DESCRIPCION],
                'cliente'       => ['id' => $client->id, 'ruc' => $client->CLI_RUC],
                'incluidos'     => $included, 'advertencias' => [],
            ];
        }, 3);
    }

    private function requireValue(mixed $value, string $field, string $message): void
    {
        if (!$value) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}

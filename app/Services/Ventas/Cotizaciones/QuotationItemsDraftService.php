<?php

namespace App\Services\Ventas\Cotizaciones;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodega;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Inventario\Articulos\GpUnidadMedida;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Clientes\ClienteConfiguracion;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Ventas\CondicionesPago\GpDescuentoFormaPago;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationItemsDraftServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationItemsDraftService implements QuotationItemsDraftServiceInterface
{
    public function __construct(
        protected readonly QuotationBuilderInterface $builder,
    ) {}

    public function create(array $data, GpUsuario $actor): array
    {
        $requestId = strtolower($data['request_id']);
        $pilot = (bool) ($data['modo_prueba'] ?? false);
        $ownerId = $this->resolveOwnerId($data, $actor, $pilot);
        // Fixed field order and normalized numbers; preserve line order and assumed quantity.
        $items = array_map(fn (array $item) => [
            'codigo' => $item['codigo'],
            'cantidad' => number_format((float) ($item['cantidad'] ?? 1), 2, '.', ''),
            'cantidad_asumida' => !array_key_exists('cantidad', $item),
            'unidad' => $item['unidad'] ?? null,
        ], $data['items']);
        return DB::transaction(function () use ($data, $actor, $items, $requestId, $ownerId, $pilot) {
            // Lock actors/owners in a stable order; serialize writes before reading the key.
            // Both the snapshot and all quote rows commit together; a failed attempt consumes no key.
            $ids = array_values(array_unique([(int) $actor->id, $ownerId]));
            $users = GpUsuario::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actingUser = $users->get($actor->id);
            $user = $users->get($ownerId);
            abort_unless($actingUser && $actingUser->status && $actingUser->perfil?->state === 'active' && !$actingUser->must_change_password, 403);
            abort_unless($user && $user->status && $user->perfil?->state === 'active', 403, 'El propietario no está activo o no tiene perfil web activo.');
            if ($pilot) {
                $data = $this->applyPilotDefaults($data);
            }
            $pv = GpPuntoVenta::whereKey($data['pv_id'])->where('PTO_VTA_ESTADO', 'ACTIVO')->first();
            abort_unless($pv && $pv->relacionUsuarioPtoVta()->whereIn('user_id', $ids)->distinct()->count('user_id') === count($ids),
                403, 'El actor y el propietario deben tener permiso sobre el punto de venta.');
            $hash = hash('sha256', json_encode([$ownerId, $pilot, (int) $data['pv_id'], $data['cliente_ruc'], $items], JSON_THROW_ON_ERROR));

            $previous = DB::table('quotation_item_draft_requests')->where('user_id', $actingUser->id)
                ->where('request_id', $requestId)->lockForUpdate()->first();
            if ($previous) {
                abort_unless(hash_equals($previous->payload_hash, $hash), 409, 'request_id ya fue utilizado con otro contenido.');
                return array_merge(json_decode($previous->response, true, 512, JSON_THROW_ON_ERROR), ['reutilizado' => true]);
            }

            // Store the draft's identity and items. Commercial validation belongs to the existing quotation flow.
            $client = GpCliente::with('gpvendedor')->where('CLI_RUC', $data['cliente_ruc'])->where('CLI_ESTADO', 'ACTIVO')->first();
            $this->requireValue($client, 'cliente_ruc', 'Cliente inexistente o inactivo.');
            $configs = ClienteConfiguracion::with('vendedor')->where('cliente_id', $client->id)
                ->where('pv_id', $pv->id)->where('estado', 1)->get();
            $seller = $configs->count() === 1 ? $configs->first()->vendedor : $client->gpvendedor;
            $client->setRelation('gpvendedor', $seller ?? new GpVendedor());
            $client->CLI_DSTO_COMERCIAL = (float) ($client->CLI_DSTO_COMERCIAL ?? 0);
            $branchIds = GpClienteSucursal::where('cliente_id', $client->id)->where('estado', 1)
                ->where('CLI_SUC_ID_DIRECCION', 'PRINCIPAL')->pluck('id');
            $payment = $client->CLI_CONDICION_PAGO ? GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $client->CLI_CONDICION_PAGO)
                ->orderBy('id')->value('DESC_FORMA_PAGO_CODIGO') : null;
            $warehouses = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $pv->PTO_VTA_ID)
                ->pluck('PTO_VTA_BOD_BODEGA')->unique();
            $this->requireValue($warehouses->count() === 1 && $warehouses->first(), 'pv_id', 'La bodega de facturación no es inequívoca.');
            $dispatches = $pv->tiposDespacho()->where('estado', 1)->where('descripcion', 'Retiro en bodega')->get();

            $articlesByCode = GpArticulo::whereIn('ART_CODIGO', array_column($items, 'codigo'))->get()->groupBy('ART_CODIGO');
            $units = GpUnidadMedida::whereIn('UMEDIDA_PLAN', $articlesByCode->flatten(1)->pluck('ART_PLAN_UMEDIDA'))->get()->groupBy('UMEDIDA_PLAN');
            $errors = [];
            $articles = [];
            $included = [];
            $warnings = [];
            foreach ($items as $index => $item) {
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
                if ($unitMatches->count() !== 1 || !$unit->UMEDIDA_BASE || (isset($item['unidad']) && $item['unidad'] !== $unit->UMEDIDA_BASE)) {
                    $errors["items.$index.unidad"] = ['Unidad incompatible o sin configuración inequívoca; no se convierten unidades.'];
                    continue;
                }
                $articleDecimals = filter_var($article->ART_DECIMAL_CANTIDAD, FILTER_VALIDATE_INT);
                $unitDecimals = filter_var($unit->UMEDIDA_CANTIDAD_DECIMAL, FILTER_VALIDATE_INT);
                if ($articleDecimals === false || $unitDecimals === false || $articleDecimals < 0 || $unitDecimals < 0) {
                    $errors["items.$index.unidad"] = ['Falta configurar la precisión de la unidad del artículo.'];
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
                    'cantidad' => $quantity, 'cantidad_asumida' => $item['cantidad_asumida'], 'unidad' => $unit->UMEDIDA_BASE];
                if ($item['cantidad_asumida']) {
                    $warnings[] = ['indice' => $index, 'codigo' => 'cantidad_asumida', 'mensaje' => 'Se asumió cantidad 1.'];
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $quote = $this->builder->payloadQuotationHeaderDraft($user, $client, [
                'sucursalId' => $branchIds->count() === 1 ? $branchIds->first() : null, 'warehouse' => $warehouses->first(),
                'ptoVtaCode' => $pv->PTO_VTA_ID, 'agency' => $pv->PTO_VTA_DESCRIPCION, 'typePayment' => $payment,
            ]);
            $quote->fill(['pv_id' => $pv->id, 'vendedor_id' => $seller?->id,
                'tipo_despacho_id' => $dispatches->count() === 1 ? $dispatches->first()->id : null, 'origen' => 'cotizacion']);
            $quote->save();
            foreach ($articles as $article) {
                $this->builder->payloadQuotationDetailDraft($article, $quote->id, $client);
            }
            $quote->refresh();
            $result = [
                'id' => $quote->id, 'numero' => $quote->cotizacion, 'estado' => $quote->estado,
                'request_id' => $requestId, 'reutilizado' => false,
                'usuario' => ['id' => $user->id, 'codigo' => $user->USERID],
                'actor' => ['id' => $actingUser->id, 'codigo' => $actingUser->USERID],
                'modo_prueba' => $pilot,
                'punto_venta' => ['id' => $pv->id, 'codigo' => $pv->PTO_VTA_ID, 'descripcion' => $pv->PTO_VTA_DESCRIPCION],
                'cliente' => ['id' => $client->id, 'ruc' => $client->CLI_RUC],
                'incluidos' => $included, 'advertencias' => $warnings,
            ];
            DB::table('quotation_item_draft_requests')->insert([
                'user_id' => $actingUser->id, 'owner_id' => $user->id, 'request_id' => $requestId, 'payload_hash' => $hash,
                'cotizacion_id' => $quote->id, 'response' => json_encode($result, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $result;
        }, 3);
    }

    private function resolveOwnerId(array $data, GpUsuario $actor, bool $pilot): int
    {
        if (!$pilot) {
            abort_if(array_key_exists('user_id', $data), 403, 'user_id solo se admite en el piloto restringido.');
            return (int) $actor->id;
        }

        $settings = config('quotation_item_drafts.pilot');
        $verifiedUrl = rtrim((string) ($settings['verified_app_url'] ?? ''), '/');
        abort_unless(app()->environment(['local', 'testing', 'qa', 'staging']) && ($settings['enabled'] ?? false)
            && $verifiedUrl !== '' && $verifiedUrl === rtrim((string) config('app.url'), '/'), 403, 'Piloto no habilitado para este destino de pruebas.');
        abort_unless(in_array((int) $actor->id, $settings['actor_user_ids'] ?? [], true) && $actor->tokenCan('quotation-items:pilot'),
            403, 'El actor no tiene permiso para ejecutar el piloto.');
        $ownerId = (int) ($settings['owner_user_id'] ?? 0);
        abort_unless($ownerId > 0 && (!array_key_exists('user_id', $data) || (int) $data['user_id'] === $ownerId),
            403, 'El piloto solo admite al propietario configurado.');
        return $ownerId;
    }

    private function applyPilotDefaults(array $data): array
    {
        $settings = config('quotation_item_drafts.pilot');
        if (!array_key_exists('cliente_ruc', $data)) {
            $this->requireValue($settings['default_client_ruc'] ?? null, 'cliente_ruc', 'Falta configurar el cliente del piloto.');
            $data['cliente_ruc'] = $settings['default_client_ruc'];
        }
        if (!array_key_exists('pv_id', $data)) {
            $description = $settings['default_pv_description'] ?? '';
            $this->requireValue($description, 'pv_id', 'Falta configurar el punto de venta del piloto.');
            $ids = GpPuntoVenta::where('PTO_VTA_DESCRIPCION', $description)->where('PTO_VTA_ESTADO', 'ACTIVO')->pluck('id');
            $this->requireValue($ids->count() === 1, 'pv_id', 'El punto de venta del piloto debe existir activo y ser inequívoco.');
            $data['pv_id'] = $ids->first();
        }
        return $data;
    }

    private function requireValue(mixed $value, string $field, string $message): void
    {
        if (!$value) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}

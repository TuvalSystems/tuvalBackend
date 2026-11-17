<?php

namespace App\Services\Ventas\Cotizaciones;

use App\Models\Ventas\Clientes\GpProspecto;
use App\Enums\Ventas\TaxRateEnum;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Inventario\Despachos\TipoDespacho;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use App\Services\Ventas\Cotizaciones\Contracts\ProspectQuoteServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectQuoteService implements ProspectQuoteServiceInterface
{
    public function __construct(private readonly QuotationBuilderInterface $builder) {}

    public function create(array $data, GpUsuario $user): GpCotizacion
    {
        return DB::transaction(function () use ($data, $user) {
            $point = !empty($data['pv_id'])
                ? GpPuntoVenta::findOrFail($data['pv_id'])
                : GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->firstOrFail();
            if (!empty($data['PTO_VTA_ID']) && $point->PTO_VTA_ID !== $data['PTO_VTA_ID']) {
                throw ValidationException::withMessages(['pv_id' => 'El punto de venta no coincide con su código.']);
            }
            $warehouse = $point->bodegas()->where('PTO_VTA_BOD_REL_BODEGA', $data['bodega_facturacion'])->first();
            if (!$warehouse) {
                throw ValidationException::withMessages(['bodega_facturacion' => 'La bodega no pertenece al punto de venta.']);
            }
            $dispatch = $this->resolveDispatch($data['tipo_despacho_id'] ?? null, $data['despacho'] ?? null);
            if ($dispatch->descripcion === 'Despacho en ruta' && !$warehouse->PERMITE_DESPACHO) {
                throw ValidationException::withMessages(['tipo_despacho_id' => 'La bodega no permite despacho en ruta.']);
            }

            // La identificación es la identidad del prospecto, no un ID enviado por la pantalla.
            $document = trim($data['prospecto']['CLI_RUC']);
            $prospect = GpProspecto::where('CLI_RUC', $document)->first() ?? new GpProspecto();
            $prospect->CLI_RUC = $document;
            foreach (['CLI_RAZON_SOCIAL', 'CLI_CORREO_ELECT_SRI', 'CLI_DIRECCION', 'CLI_TELEFONO_1'] as $field) {
                $prospect->$field = trim($data['prospecto'][$field] ?? '');
            }
            $prospect->save();

            $quote = new GpCotizacion();
            $quote->fill([
                'user_id' => $user->id,
                'USERID' => $user->USERID,
                'prospecto_id' => $prospect->id,
                'pv_id' => $point->id,
                'PTO_VTA_ID' => $point->PTO_VTA_ID,
                'PTO_VTA_DESCRIPCION' => $point->PTO_VTA_DESCRIPCION,
                'BOD_BODEGA' => $data['bodega_facturacion'],
                'tipo_despacho_id' => $dispatch->id,
                'despacho' => $dispatch->descripcion,
                'responsable_flete' => $dispatch->responsable_pago,
                'CLI_RUC' => $prospect->CLI_RUC,
                'CLI_RAZON_SOCIAL' => $prospect->CLI_RAZON_SOCIAL,
                'CLI_CORREO_ELECT_SRI' => $prospect->CLI_CORREO_ELECT_SRI,
                'COND_ID' => 'CONTADO',
                'COND_ID_ORIGINAL' => 'CONTADO',
                'DESC_FORMA_PAGO_CODIGO' => $data['DESC_FORMA_PAGO_CODIGO'] ?? null,
                'porcentage_descuentooriginal' => $data['porcentage_descuentooriginal'] ?? 0,
                'porcentage_descuento' => $data['porcentage_descuento'] ?? 0,
                'nota_interna' => $data['nota_interna'] ?? null,
                'nota_cliente' => $data['nota_cliente'] ?? null,
                'orden_de_compra' => $data['orden_de_compra'] ?? null,
                'impuesto' => TaxRateEnum::IVA_15->value,
                // Estado histórico usado por la bandeja de prospectos; no inicia aprobaciones.
                'estado' => 'aprobado',
                'origen' => 'cotizacion',
                'requiere_aprobacion_comercial' => 'no',
                'aprobado_comercial' => 'no',
                'requiere_aprobacion_crediticia' => 'no',
                'impreso' => 'no',
                'factura_posterior_despacho' => false,
                'factura_a_demanda' => false,
                'factura_siguiente_mes' => false,
                'facturar_ahora' => false,
                'fle_valor' => 0,
                'fle_peso' => 0,
            ]);
            $quote->aprobado_crediticio = 'no';
            $items = $this->normalizeItems($data['cotizacion']);
            $quote->fill($this->calculateTotals($items));
            $quote->save();
            foreach ($items as $item) {
                $detail = $this->builder->formPayloadDetailQuotation($quote->id, $item, $point->lista_precios_defecto ?? '');
                $detail->save();
            }
            return $quote->refresh();
        });
    }

    private function resolveDispatch(?int $id, ?string $description): TipoDespacho
    {
        $query = TipoDespacho::where('estado', true)
            ->whereIn('descripcion', ['Retiro en bodega', 'Despacho en ruta']);
        $dispatch = $id ? $query->whereKey($id)->first()
            : $query->where('descripcion', $description ?: 'Retiro en bodega')->first();
        if (!$dispatch) {
            throw ValidationException::withMessages(['tipo_despacho_id' => 'No se encontró un tipo de despacho disponible para prospectos.']);
        }
        return $dispatch;
    }

    private function normalizeItems(array $items): array
    {
        foreach ($items as $index => &$item) {
            $quantity = (float) $item['ART_CANTIDAD'];
            $decimals = (int) $item['ART_DECIMAL_CANTIDAD'];
            if ($quantity <= 0 || abs($quantity - round($quantity, $decimals)) > 0.0000001) {
                throw ValidationException::withMessages(["cotizacion.$index.ART_CANTIDAD" => 'La cantidad no respeta los decimales del artículo.']);
            }
            $price = (float) $item['ART_VALOR'];
            $discount = $price * (float) $item['descuento'] / 100;
            $item['ART_DESCUENTO'] = round($discount, 4);
            $item['subtotal'] = round(($price - $discount) * $quantity, 4);
            $item['ART_IVA'] = round($item['subtotal'] * (float) $item['ART_IMP_VTA'] / 100, 2);
            $item['precio_sin_flete'] = $price;
            $item['fle_valor'] = 0;
            $item['es_servicio_logistico'] = false;
            $item['ART_PESO'] = (float) ($item['ART_PESO'] ?? 0);
            $item['ART_DESCRIPCION_ORIGINAL'] = $item['ART_DESCRIPCION_ORIGINAL'] ?? $item['ART_DESCRIPCION'];
            if (($item['ART_CLASE_ARTICULO'] ?? '') === 'VTA-SERV') {
                $item['ART_TIPO_COMPRA'] = 'SERV';
            }
        }
        return $items;
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = $discount = $tax = $weight = 0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
            $discount += (float) $item['ART_VALOR'] * (float) $item['descuento'] / 100 * (float) $item['ART_CANTIDAD'];
            $tax += $item['subtotal'] * (float) $item['ART_IMP_VTA'] / 100;
            $weight += $item['ART_PESO'] * (float) $item['ART_CANTIDAD'];
        }
        return [
            'subtotal' => round($subtotal, 2) + round($discount, 2),
            'subtotalmenosdescuento' => round($subtotal, 2),
            'descuento' => round($discount, 2),
            'iva' => round($tax, 2),
            'total' => round($subtotal, 2) + round($tax, 2),
            'peso' => round($weight, 2),
        ];
    }

    /** Completa la respuesta de prospectos antiguos sin modificar registros históricos. */
    public function completeLegacyFields(GpCotizacion $quote): void
    {
        if (!$quote->prospecto_id) {
            return;
        }
        $point = $quote->pv_id ? GpPuntoVenta::find($quote->pv_id)
            : GpPuntoVenta::where('PTO_VTA_ID', $quote->PTO_VTA_ID)->firstOrFail();
        $quote->pv_id = $point->id;
        if (!$quote->tipo_despacho_id) {
            $dispatch = $this->resolveDispatch(null, $quote->despacho);
            $quote->tipo_despacho_id = $dispatch->id;
            $quote->despacho = $dispatch->descripcion;
            $quote->responsable_flete = $dispatch->responsable_pago;
        }
    }


    public function restoreProspectQuote(string $ruc): array
    {
        $pr = GpProspecto::where('CLI_RUC', trim($ruc))->firstOrFail();
        return [
            'id'                => $pr->id,
            'identificacion'    => $pr->CLI_RUC,
            'razon_social'      => $pr->CLI_RAZON_SOCIAL,
            'correo_sri'        => $pr->CLI_CORREO_ELECT_SRI,
            'telefono1'         => $pr->CLI_TELEFONO_1,
            'direcciones'       =>  [
                [
                'region_id'     => null,
                'provincia_id'  => null,
                'canton_id'     => null,
                'direccion'     =>  $pr->CLI_DIRECCION,
                'provinces'     =>  [],
                'cantons'       => [],
            ]
            ]
        ];
    }
}

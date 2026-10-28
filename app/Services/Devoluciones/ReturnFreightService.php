<?php

namespace App\Services\Devoluciones;

use App\Models\Devoluciones\GpDevolucionResponsablePagoTransporte;
use App\Models\Inventario\Despachos\TipoDespacho;
use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReturnFreightService
{
    public function __construct(private readonly ReturnFreightPolicy $policy) {}

    public function type(GpPedido $order): ?string
    {
        return $this->policy->dispatchType(TipoDespacho::find($order->tipo_despacho_id)?->descripcion);
    }

    public function responsibles(): Collection
    {
        return GpDevolucionResponsablePagoTransporte::where('estado', true)
            ->whereIn('codigo', ['cliente', 'tuval'])->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion']);
    }

    public function responsible(?int $id): GpDevolucionResponsablePagoTransporte
    {
        $query = GpDevolucionResponsablePagoTransporte::where('estado', true)->whereIn('codigo', ['cliente', 'tuval']);
        $responsible = $id === null ? $query->where('codigo', 'cliente')->first() : $query->find($id);
        if (!$responsible) {
            throw ValidationException::withMessages([
                'devolucion.responsable_pago_transporte_id' => 'Seleccione un responsable de pago de transporte válido.',
            ]);
        }
        return $responsible;
    }

    public function isDispatched(array $items): bool
    {
        return collect($items)->contains(fn ($item) => !$this->isLogistics($item)
            && (bool) data_get($item, 'despacho') && (float) data_get($item, 'despachado', 0) > 0);
    }

    private function isLogistics(array|object $item): bool
    {
        return (bool) data_get($item, 'es_servicio_logistico', false);
    }

    public function context(GpPedido $order, array $items, ?int $responsibleId = null): array
    {
        $type = $this->type($order);
        if ($type === null) {
            return ['mostrar_responsable_pago_transporte' => false, 'responsable_pago_transporte_id' => null,
                'responsables_pago_transporte' => [], 'tipos_devolucion_permitidos' => ['parcial', 'total']];
        }
        $responsible = $this->responsible($responsibleId);
        $dispatched = $this->isDispatched($items);
        return [
            'tipo_despacho' => $type,
            'mostrar_responsable_pago_transporte' => true,
            'responsable_pago_transporte_id' => $responsible->id,
            'responsables_pago_transporte' => $this->responsibles(),
            'tipos_devolucion_permitidos' => $dispatched ? ['parcial', 'total'] : ['total'],
            'material_despachado' => $dispatched,
            'incluye_flete' => $this->policy->includesFreight($dispatched, $responsible->codigo),
        ];
    }

    public function preview(array $items, array $context): array
    {
        if (!$context['mostrar_responsable_pago_transporte']) {
            return $items;
        }
        $include = $context['incluye_flete'];
        return collect($items)->filter(fn ($item) => !($context['tipo_despacho'] === 'paqueteria'
                && !$include && $this->isLogistics($item)))
            ->map(function ($item) use ($context, $include) {
                $item = is_array($item) ? $item : $item->toArray();
                $item['seleccion_automatica'] = $context['tipo_despacho'] === 'paqueteria' && $this->isLogistics($item);
                return $this->policy->creditItem($item, $context['tipo_despacho'], $include);
            })->values()->all();
    }

    /** Rebuild item prices, dispatch flags and available quantities from the saved order. */
    public function normalize(array $data, GpPedido $order, array $available): array
    {
        $type = $this->type($order);
        if ($type === null) {
            $data['devolucion']['responsable_pago_transporte_id'] = null;
            foreach ($data['detallepedido'] ?? [] as $index => $item) {
                unset($data['detallepedido'][$index]['_despacho_verificado'], $data['detallepedido'][$index]['seleccion_automatica']);
            }
            return $data;
        }
        $id = data_get($data, 'devolucion.responsable_pago_transporte_id');
        if ($id !== null && filter_var($id, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['devolucion.responsable_pago_transporte_id' => 'El responsable debe ser un identificador válido.']);
        }
        $context = $this->context($order, $available, $id === null ? null : (int) $id);
        $this->policy->validateReturnType((string) data_get($data, 'devolucion.tipo_devolucion'), $context['material_despachado']);
        $material = collect($available)->reject(fn ($item) => $this->isLogistics($item));
        if (data_get($data, 'devolucion.volver_a_facturar', false)
            && $material->contains(fn ($item) => (bool) data_get($item, 'despacho'))
            && $material->contains(fn ($item) => !(bool) data_get($item, 'despacho'))) {
            throw ValidationException::withMessages([
                'devolucion.volver_a_facturar' => 'No se puede volver a facturar hasta que se despache todo el pedido.',
            ]);
        }
        $data['devolucion']['responsable_pago_transporte_id'] = $context['responsable_pago_transporte_id'];
        // Preserve only fields the user can edit for refactoring.
        $data['pedido'] = array_replace($order->toArray(), array_intersect_key($data['pedido'], array_flip(['orden_de_compra', 'nota_cliente'])));

        $key = fn ($item) => data_get($item, 'id') . ':' . (int) (bool) data_get($item, 'despacho', false);
        $requested = [];
        $valid = collect($available)->keyBy($key);
        foreach ($data['detallepedido'] ?? [] as $item) {
            $itemKey = $key($item);
            $quantity = $item['seleccionado'] ?? 0;
            if ($quantity === '') { $quantity = 0; }
            if (isset($requested[$itemKey]) || !$valid->has($itemKey)
                || !is_numeric($quantity) || !is_finite((float) $quantity) || (float) $quantity < 0
                || (float) $quantity > (float) data_get($valid->get($itemKey), 'disponible')) {
                throw ValidationException::withMessages(['detallepedido' => 'Los ítems o cantidades cambiaron. Actualice el detalle de la devolución.']);
            }
            if ((int) data_get($valid->get($itemKey), 'ART_DECIMAL_CANTIDAD') === 0 && floor((float) $quantity) !== (float) $quantity) {
                throw ValidationException::withMessages(['detallepedido' => 'El artículo no admite cantidades decimales.']);
            }
            $requested[$itemKey] = (float) $quantity;
        }
        $items = [];
        $hasMaterial = false;
        foreach ($this->preview($available, $context) as $item) {
            $quantity = $requested[$key($item)] ?? 0;
            $automatic = $item['seleccion_automatica'];
            if ($automatic) { $quantity = (float) $item['disponible']; }
            if (!$automatic && data_get($data, 'devolucion.tipo_devolucion') === 'total'
                && abs($quantity - (float) $item['disponible']) > 0.000001) {
                throw ValidationException::withMessages(['detallepedido' => 'La devolución total debe incluir todo el material disponible.']);
            }
            $item['seleccionado'] = $quantity;
            $item['_despacho_verificado'] = true;
            if (!$this->isLogistics($item) && $quantity > 0) { $hasMaterial = true; }
            $items[] = $item;
        }
        if (!$hasMaterial) {
            throw ValidationException::withMessages(['detallepedido' => 'Seleccione al menos un artículo de material para devolver.']);
        }
        $data['detallepedido'] = $items;
        return $data;
    }
}

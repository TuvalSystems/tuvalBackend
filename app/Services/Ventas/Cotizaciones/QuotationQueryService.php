<?php

namespace App\Services\Ventas\Cotizaciones;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalleStock;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Services\Ventas\Comercial\CommercialVisibilityService;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class QuotationQueryService
{
    public const SORT_FIELDS = ['cotizacion', 'CLI_RAZON_SOCIAL', 'CLI_RUC', 'USERID', 'COND_ID', 'created_at', 'total', 'estado'];

    public function __construct(private readonly CommercialVisibilityService $commercial) {}

    public function paginate(array $data, GpUsuario $user): LengthAwarePaginator
    {
        $puntoVenta = !empty($data['pv_id'])
            ? GpPuntoVenta::findOrFail($data['pv_id'], ['id'])
            : GpPuntoVenta::where('PTO_VTA_ID', $data['puntodeventa_id'])->firstOrFail(['id']);
        $fields = ['id', 'cotizacion', 'CLI_RAZON_SOCIAL', 'CLI_RUC', 'USERID', 'COND_ID',
            'created_at', 'total', 'estado', 'prospecto_id', 'cliente_id', 'PTO_VTA_ID',
            'BOD_BODEGA', 'tipo_despacho_id', 'requiere_aprobacion_comercial',
            'requiere_aprobacion_crediticia', 'aprobado_comercial', 'aprobado_crediticio',
            'impreso', 'aprobado_comercial_mensaje', 'cotizacion_venta_motivo_id',
            'recordar', 'observacion', 'fecha_recordatorio'];
        $cotizaciones = GpCotizacion::query()->select($fields)
            ->with('reserva:id,cotizacion_id,fe_solicitud_reserva')
            ->where('gp_cotizacions.pv_id', $puntoVenta->id);
        $this->commercial->applyLimitsVisibiliteList($cotizaciones, $user, $puntoVenta->id, 'cotizaciones', 'gp_cotizacions.user_id');
        $data['estado'] = $data['estado'] ?? '';
        if ($data['estado'] == 'pendientes') {
            $cotizaciones = $cotizaciones
                ->where(function ($q) {
                    $q->where('gp_cotizacions.estado', '!=', 'procesado');
                    $q->where('gp_cotizacions.estado', '!=', 'caducado');
                    $q->where('gp_cotizacions.estado', '!=', 'rechazado');
                })
                ->where(function ($q) {
                    $q->whereNull('prospecto_id');
                    $q->orWhere(function ($q) {
                        $q->whereNotNull('prospecto_id');
                        $q->whereNotNull('cliente_id');
                    });
                });
        } else if ($data['estado'] == 'caducado') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.estado', 'caducado')
                ->whereNull('prospecto_id');
        } else if ($data['estado'] == 'prospectos') {
            $cotizaciones = $cotizaciones
                ->where(function ($q) {
                    $q->whereNotNull('prospecto_id');
                    $q->whereNull('cliente_id');
                })
                ->where('gp_cotizacions.estado', '!=', 'procesado');
        } else if ($data['estado'] == 'procesadas') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.estado', 'procesado');
        } else if ($data['estado'] == 'rechazadas') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.estado', 'rechazado');
        }
        if (isset($data['fecha_desde']) && $data['fecha_desde'] != '' && isset($data['fecha_hasta']) && $data['fecha_hasta'] != '') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.created_at', '>=', $data['fecha_desde'] . ' 00:00:00');
            $cotizaciones = $cotizaciones->where('gp_cotizacions.created_at', '<', Carbon::parse($data['fecha_hasta'])->addDay()->startOfDay());
        }
        if (isset($data['cotizacion_filtro']) && $data['cotizacion_filtro'] != '') {
            $cotizaciones = $cotizaciones->where('gp_cotizacions.cotizacion', 'like', '%' . $data['cotizacion_filtro'] . '%');
        }

        if (($data['search'] ?? '') !== '') {
            $cotizaciones->where(function ($query) use ($data) {
                foreach (['cotizacion', 'id', 'CLI_RAZON_SOCIAL', 'CLI_RUC', 'USERID', 'COND_ID', 'created_at'] as $field) {
                    $query->orWhere("gp_cotizacions.$field", 'like', '%' . $data['search'] . '%');
                }
            });
        }
        $sort = in_array($data['sortField'] ?? '', self::SORT_FIELDS, true) ? $data['sortField'] : 'created_at';
        $order = ($data['sortOrder'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $page = $cotizaciones->orderBy("gp_cotizacions.$sort", $order)->orderBy('gp_cotizacions.id', $order)
            ->paginate(min(100, max(1, (int) ($data['perPage'] ?? 20))), ['*'], 'page', $data['page'] ?? 1);
        $ids = $page->getCollection()->pluck('id');
        if ($ids->isEmpty()) return $page;

        // Consultas por página: nunca serializar los accessors que consultan por fila.
        $details = GpCotizacionDetalle::query()->whereIn('cotizacion_id', $ids)
            ->select('cotizacion_id')
            ->selectRaw("MAX(CASE WHEN ART_TIPO_COMPRA = 'L' THEN 1 ELSE 0 END) AS has_l_items")
            ->selectRaw("MAX(CASE WHEN ART_TIPO_COMPRA = 'SERV' THEN 1 ELSE 0 END) AS has_service_items")
            ->selectRaw('COUNT(*) > COUNT(DISTINCT ART_CODIGO) + MAX(CASE WHEN ART_CODIGO IS NULL THEN 1 ELSE 0 END) AS has_duplicate_items')
            ->groupBy('cotizacion_id')->toBase()->get()->keyBy('cotizacion_id');
        $selected = GpCotizacionDetalleStock::whereIn('cotizacion_id', $ids)->where('seleccionado', '>', 0)
            ->distinct()->pluck('cotizacion_id')->flip();
        $invoiced = GpPedido::whereIn('cotizacion_id', $ids)->whereNotNull('factura')
            ->distinct()->pluck('cotizacion_id')->flip();

        return $page->through(function ($quote) use ($details, $selected, $invoiced) {
            $row = $quote->setAppends([])->attributesToArray();
            $flags = $details->get($quote->id);
            $row['reserva'] = $quote->reserva?->setAppends([])->attributesToArray();
            $row['has_reserva'] = $quote->reserva !== null;
            $row['reserva_disabled'] = !$selected->has($quote->id);
            $row['has_l_items'] = (bool) ($flags->has_l_items ?? false);
            $row['has_service_items'] = (bool) ($flags->has_service_items ?? false);
            $row['has_duplicate_items'] = (bool) ($flags->has_duplicate_items ?? false);
            $row['recordatorioventa'] = $invoiced->has($quote->id) ? 1 : 0;
            $row['recuperar'] = 'yes';
            $row['imprimir'] = 'yes';
            $row['facturar'] = 'no';
            if ($quote->requiere_aprobacion_comercial === 'yes' && $quote->aprobado_comercial === 'no') {
                $row['imprimir'] = 'no';
            } elseif ($quote->requiere_aprobacion_crediticia === 'yes' && $quote->aprobado_crediticio === 'no') {
                $row['imprimir'] = 'no';
                $row['recuperar'] = 'no';
            }
            if ($quote->impreso === 'yes') {
                $row['recuperar'] = 'yes';
                $row['facturar'] = 'yes';
            }
            if ($quote->estado === 'rechazado') {
                $row['imprimir'] = 'no';
                $row['recuperar'] = 'no';
            }
            return $row;
        });
    }
}

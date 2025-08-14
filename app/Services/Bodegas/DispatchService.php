<?php

namespace App\Services\Bodegas;

use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use Illuminate\Support\Facades\Cache;


class DispatchService implements DispatchServiceInterface
{
  protected $invoiceService;
    public function __construct(InvoiceServiceInterface $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function guiasDespachadas($bodega, $perpage, $search, $sortBy = null, $sortOrder = 'asc', $fecha_desde, $fecha_hasta, $clienteRuc)
    {
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.pedido',
            'gp_guia_remisions.GUI_REM_NUME_DOCU_SUSTENTO',
            'gp_orden_despachos.orden',
            'gp_orden_despachos.id AS orden_id',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO'
        ])
            ->join('gp_pedidos', 'gp_pedidos.pedido', 'gp_guia_remisions.pedido')
            ->join('gp_orden_despachos', 'gp_orden_despachos.pedido_id', 'gp_pedidos.id')
            ->where('gp_guia_remisions.bodega_origen', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->where('gp_guia_remisions.tipo', 'despacho');


        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.pedido', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_orden_despachos.orden', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }
        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }

        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function transferenciasEnviadas($bodega, $perpage, $search, $sortBy = null, $sortOrder = 'asc', $fecha_desde, $fecha_hasta, $clienteRuc)
    {
        $query = GpGuiaRemision::fromSub(function ($sub) use ($bodega) {
            $sub->select([
                'gp_guia_remisions.id',
                'gp_guia_remisions.pedido',
                'gp_guia_remisions.GUI_REM_GUIA_REMISION',
                'gp_guia_remisions.bodega_origen',
                'gp_guia_remisions.bodega_destino',
                'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
                'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
                'gp_guia_remisions.GUI_REM_FECHA_INICIO',
                'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
                'gp_guia_remisions.GUI_REM_PLACA',
                'gp_guia_remisions.created_at',
                'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
            ])
                ->from('gp_guia_remisions')
                ->where('gp_guia_remisions.bodega_origen', $bodega)
                ->where('gp_guia_remisions.estado', '!=', 'pendiente')
                ->Where('gp_guia_remisions.tipo', 'transferencia');
        }, 'subquery');

        if ($fecha_desde && $fecha_hasta) {
            $query->where('subquery.created_at', '>=', $fecha_desde)
                ->where('subquery.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('subquery.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subquery.pedido', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }
        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('subquery.GUI_REM_FECHA_INICIO');
        }

        return $query->paginate($perpage);
    }

    public function transferenciasRecibidas($bodega, $perpage, $search, $sortBy = null, $sortOrder = 'asc', $fecha_desde, $fecha_hasta, $clienteRuc)
    {

        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.bodega_origen',
            'gp_guia_remisions.bodega_destino',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
        ])
            ->where('gp_guia_remisions.bodega_destino', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->Where('gp_guia_remisions.tipo', 'transferencia');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }

        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }

        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function reposicionesEnviadas($bodega, $perpage, $search, $sortBy = null, $sortOrder = 'asc', $fecha_desde, $fecha_hasta, $clienteRuc)
    {
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.bodega_destino',
            'gp_guia_remisions.pedido',
            // 'gp_guia_remisions.bodega_origen',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
        ])
            ->where('gp_guia_remisions.bodega_origen', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->Where('gp_guia_remisions.tipo', 'reposicion');


        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.pedido', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }

        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }

        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function reposicionesRecibidas($bodega, $perpage, $search, $sortBy = null, $sortOrder = 'asc', $fecha_desde, $fecha_hasta, $clienteRuc)
    {
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.bodega_origen',
            'gp_guia_remisions.pedido',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO'
        ])
            ->where('gp_guia_remisions.bodega_destino', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->Where('gp_guia_remisions.tipo', '=', 'reposicion');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.pedido', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }
        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }
        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function checkTraslado($order)
    {
        $this->invoiceService->checkTrasladado($order);
    }

    public function getAllPtoVta()
    {
          return Cache::remember('pto_vta_all', 300, function () {
            return GpRelacionPuntoVentaBodega::query()
                ->orderBy('PTO_VTA_BOD_BODEGA')               // ajusta campo
                ->get(['id','PTO_VTA_BOD_BODEGA','PTO_VTA_BOD_PUNTO_VENTA']); // solo lo necesario
        });

    }

}

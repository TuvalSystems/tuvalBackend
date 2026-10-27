<?php

namespace App\Http\Controllers\Ventas\Consultas;

use App\Enums\Ventas\OrderStatus;
use App\Models\Cobranzas\Cajas\GpCaja;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Transferencias\GpTrasladosVentas;
use App\Http\Controllers\Compartido\FuncionesController;
use App\Models\Inventario\GuiasRemision\GpGuiaRemisionDetalle;
use App\Models\Inventario\Reposiciones\GpTrasladosReposicion;
use App\Services\Ventas\Clientes\Contracts\ClientQueryServiceInterface;
use App\Services\Inventario\Consultas\Contracts\InventoryQueryServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Foundation\Application;

class ConsultasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly InventoryQueryServiceInterface $inventoryService,
        protected readonly ClientQueryServiceInterface $clientService
    ) {}





    public function consultarstock(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->all();
            $st = $this->inventoryService->getConsultStock($data);
            return $this->successResponse('Stock obtenido correctamente', $st, 200);
        }, 'cotizaciones', 'buscarArticuloL');
    }


    public function consultaStockExposicion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'articulo' => 'required|string',
                'puntoventa' => 'required|string'
            ]);
            $artCode = data_get($validate, 'articulo');
            $pvCode = data_get($validate, 'puntoventa');
            return $this->successResponse('Stock obtenido correctamente', $this->inventoryService->getArticleExposition($artCode, $pvCode), 200);
        }, 'laravel', 'consultaStockExposicion');
    }

    public function consultaItemTransito(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $items = $this->inventoryService->getConsultItemTransito($data);
            return $this->successResponse('Transitos obtenidos correctamente', $items, 200);
        } catch (\Throwable $e) {
            Log::error(
                'Error al consultar Stock (getConsultStock)',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'data' => $data
                ]
            );
            return $this->buildErrorResponse('Error al consultar Transito', 500);
        }
    }


    public function consultStockInTransit(): JsonResponse
    {
        try {
            $items = $this->inventoryService->fetchStockInTransit();
            return $this->successResponse('Transitos obtenidos correctamente', $items, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function consultarStockTransitoTransferenciaVenta()
    {
        return $this->handleApiAction(function () {
            $items = $this->inventoryService->fetchStockInTransitTransfers();
            return $this->successResponse('Transito Obtenido Correctamente', $items);
        }, 'laravel', 'consultarStockTransitoTransferenciaVenta');
    }

    public function consultarStockTransitoReservas()
    {
        return $this->handleApiAction(function () {
            $items = $this->inventoryService->fetchStockTransitReservations();
            return $this->successResponse('Transito de Reservas Obtenido Correctamente', $items);
        }, 'laravel', 'consultarStockTransitoReservas');
    }

    /** unificacion de transito de reposiciones y transferencia de venta */
    public function consultaTransitoConsolidado()
    {
        return $this->handleApiAction(function () {
            $items = $this->inventoryService->fetchStockInTransitConsolidated();
            return $this->successResponse('Transito Consolidado Obtenido', $items);
        }, 'laravel', 'consultarStockTransitoTransferenciaVenta');
    }







    public function consultastockarticulopuntodeventa($articulo, $puntodeventa): Response|Application|ResponseFactory
    {

        $funciones = new FuncionesController();
        $data_bodega = array('PTO_VTA_ID' => $puntodeventa);
        $arr_bodegas = $funciones->gebodegas($data_bodega);

        $consulta_bodega = array();

        foreach ($arr_bodegas as $bodega) {
            $consulta_bodega[] = array(
                'STOCK_ARTICULO' => $articulo . '@' . $bodega,
            );
        }

        $stock_general = $funciones->stockarticulobodega($consulta_bodega, $arr_bodegas);
        return response(
            $stock_general
        );
    }







    public function cupodisponible(string $ruc)
    {
        try {
            $cl = $this->inventoryService->getAvailableSpace($ruc);
            return response([
                'success' => true,
                'cliente' => $cl['cliente'],
                'segmento' => $cl['segmento']
            ]);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

 

    public function puntodeventacaja(): Response|Application|ResponseFactory
    {
        $puntosdeventa = GpPuntoVenta::get();

        foreach ($puntosdeventa as $puntodeventa) {
            $cajas = GpCaja::join('gp_relacion_punto_venta_cajas', 'gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_CAJA', 'gp_cajas.CAJA_CODIGO')
                ->join('gp_relacion_usuario_cajas', 'gp_relacion_usuario_cajas.CAJ_USER_CAJA', 'gp_cajas.CAJA_CODIGO')
                ->join('gp_usuarios', 'gp_usuarios.USERID', 'gp_relacion_usuario_cajas.CAJ_USER_USUARIO')
                ->where('gp_relacion_punto_venta_cajas.PTO_VTA_CAJA_PUNTO_VENTA', $puntodeventa->PTO_VTA_ID)
                ->select('gp_cajas.*', 'gp_usuarios.USERID', 'gp_usuarios.USERNAME')
                ->distinct()
                ->orderBy('gp_cajas.CAJA_DESCRIPCION', 'ASC')
                ->get();
            $puntodeventa->cajas = $cajas;
        }
        return response([
            'success' => true,
            'puntosdeventa' => $puntosdeventa,
        ]);
    }

    public function reservados(): JsonResponse|Response|ResponseFactory
    {
        try {
            return response([
                'reservados' => $this->inventoryService->listReservedInventory(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar reservados', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->buildErrorResponse('Error al consultar reservados', 500);
        }
    }

    public function consultarItemsReservados(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $items      = $request->input('items', "");
            $response   = $this->inventoryService->listReservedStockForItems($items);
            return $this->successResponse('Reservas obtenidas', $response, 200);
        }, 'laravel', 'consultarItemsReservados');
    }
    public function transferenciaspendientes(): Response|Application|ResponseFactory
    {

        $pedidos = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
            ->where(function ($query) {
                $query->where('gp_pedidos.estado', OrderStatus::TRASLADO->value);
                $query->orWhere('gp_pedidos.estado', OrderStatus::ORDEN_DE_COMPRA->value);
            })
            ->join('gp_guia_remision_transferencias', 'gp_guia_remision_transferencias.pedido', 'gp_pedidos.pedido')
            ->join('gp_guia_remisions', 'gp_guia_remisions.id', 'gp_guia_remision_transferencias.guia_remision_id')
            ->join('gp_guia_remision_detalles', 'gp_guia_remision_detalles.guia_remision_id', 'gp_guia_remisions.id')
            ->where('gp_guia_remisions.estado', '!=', 'eliminada')
            ->whereColumn('gp_guia_remision_detalles.GUI_REM_CODI_INTERNO', '=', 'gp_traslados_ventas.ART_CODIGO')
            ->select(
                'gp_pedidos.pedido',
                'gp_guia_remisions.tipo',
                'gp_traslados_ventas.ART_CODIGO',
                'gp_traslados_ventas.bodega_origen',
                'gp_traslados_ventas.bodega_destino',
                'gp_guia_remisions.GUI_REM_GUIA_REMISION',
                'gp_traslados_ventas.cantidad',
                DB::raw('(gp_traslados_ventas.cantidad - gp_traslados_ventas.trasladado) AS pendiente'),
                'gp_guia_remision_detalles.GUI_REM_CANTIDAD AS enviado_en_guia',
            )
            ->get()->toArray();

        $transferencias = GpTrasladosReposicion::where(function ($query) {
            $query->whereNull('gp_traslados_reposicions.completado');
            $query->orWhere('gp_traslados_reposicions.completado', 0);
        })
            ->join('gp_guia_remision_transferencias', 'gp_guia_remision_transferencias.pedido', 'gp_traslados_reposicions.Documento')
            ->join('gp_guia_remisions', 'gp_guia_remisions.id', 'gp_guia_remision_transferencias.guia_remision_id')
            ->join('gp_guia_remision_detalles', 'gp_guia_remision_detalles.guia_remision_id', 'gp_guia_remisions.id')
            ->where('gp_guia_remisions.estado', '!=', 'eliminada')
            ->whereColumn('gp_guia_remision_detalles.GUI_REM_CODI_INTERNO', '=', 'gp_traslados_reposicions.ART_CODIGO')
            ->select(
                'gp_guia_remision_transferencias.pedido',
                'gp_guia_remisions.tipo',
                'gp_traslados_reposicions.ART_CODIGO',
                'gp_traslados_reposicions.bodega_origen',
                'gp_traslados_reposicions.bodega_destino',
                'gp_guia_remisions.GUI_REM_GUIA_REMISION',
                'gp_traslados_reposicions.cantidad',
                DB::raw('(gp_traslados_reposicions.cantidad - gp_traslados_reposicions.trasladado) AS pendiente'),
                'gp_guia_remision_detalles.GUI_REM_CANTIDAD AS enviado_en_guia',
            )
            ->get()->toArray();

        foreach ($transferencias as $transferencia) {
            $pedidos[] = $transferencia;
        }

        return response(
            $pedidos
        );
    }

    public function reservasweb($item = null): JsonResponse|Response|ResponseFactory
    {
        try {
            $data = $this->inventoryService->listReservedWebInventory($item);
            return response([
                'reservados' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }


    public function transferencias_pendientes_ingreso(): Response|Application|ResponseFactory
    {
        $pendientes = GpGuiaRemisionDetalle::join('gp_guia_remisions', 'gp_guia_remisions.id', 'gp_guia_remision_detalles.guia_remision_id')
            //->whereNull('completado')
            ->where('gp_guia_remisions.estado', 'enviada')
            ->where(function ($query) {
                $query->where('gp_guia_remisions.tipo', 'transferencia');
                $query->orWhere('gp_guia_remisions.tipo', 'reposicion');
            })
            ->select(
                'gp_guia_remisions.id as guia_id',
                'gp_guia_remision_detalles.detalle_id',
                'gp_guia_remisions.GUI_REM_FECHA_INICIO as fecha_guia',
                'gp_guia_remisions.GUI_REM_GUIA_REMISION as guia_remision',
                'gp_guia_remisions.tipo',
                'gp_guia_remisions.bodega_origen',
                'gp_guia_remisions.bodega_destino',
                'gp_guia_remision_detalles.GUI_REM_CODI_INTERNO as articulo',
                'gp_guia_remision_detalles.GUI_REM_CANTIDAD as cantidad_total',
                'gp_guia_remision_detalles.recibido as cantidad_ingresada',
            )->get();

        foreach ($pendientes as $traslado) {

            if (!$traslado->cantidad_ingresada)
                $traslado->cantidad_ingresada = 0;

            $traslado->cantidad_pendiente = number_format($traslado->cantidad_total - $traslado->cantidad_ingresada, 2, '.', '');

            if ($traslado->tipo == 'transferencia') {
                $origen = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
                    ->where('gp_traslados_ventas.id', $traslado->detalle_id)
                    ->select('gp_pedidos.pedido')
                    ->first();
                $traslado->documento_pedido = '';
                if ($origen) {
                    $traslado->documento_pedido = $origen->pedido;
                }
            }
            if ($traslado->tipo == 'reposicion') {
                $origen = GpTrasladosReposicion::where('id', $traslado->detalle_id)
                    ->select('Documento')
                    ->first();
                $traslado->documento_pedido = $origen->Documento;
            }

            unset($traslado->guia_id);
            unset($traslado->detalle_id);
        }

        $response = array();
        foreach ($pendientes as $traslado) {
            if ($traslado->cantidad_pendiente > 0) {
                $response[] = $traslado;
            }
        }
        return response([
            'guias' => $response,
        ]);
    }
    public function items_transferencia_venta($item = null): Response|Application|ResponseFactory
    {
        //        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', 006)->first();
        //        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosventas = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
            ->join('gp_bodegas', 'gp_traslados_ventas.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            //            ->where('bodega_origen', $bodega)
            ->where(function ($q) {
                $q->whereNull('gp_traslados_ventas.completado')
                    ->orWhere('gp_traslados_ventas.completado', 0);
            })
            ->where('gp_pedidos.estado', '!=', OrderStatus::ELIMINADO->value);
        $trasladosventas = $trasladosventas->select(
            'gp_traslados_ventas.ART_CODIGO',
            'gp_traslados_ventas.cantidad',
            'gp_traslados_ventas.trasladado',
            'gp_pedidos.pedido',
            'gp_bodegas.BOD_BODEGA',
            'gp_pedidos.created_at AS fecha',
            'gp_pedidos.USERID AS usuario',

        )->get();

        foreach ($trasladosventas as $traslado) {

            if (!$traslado->reservado)
                $traslado->reservado = 0;

            if (!$traslado->trasladado)
                $traslado->trasladado = 0;
            $traslado->pendiente = number_format($traslado->cantidad - $traslado->reservado - $traslado->trasladado, 2, '.', '');

            $traslado->fecha = substr($traslado->fecha, 0, -3);

            unset($traslado->trasladado);
            unset($traslado->reservado);
        }

        $trasladosventas = $trasladosventas->toArray();
        $trasladosventas = array_values($trasladosventas);

        return response(
            $trasladosventas,
        );
    }

    public function items_transferencia_reposicion($item = null): Response|Application|ResponseFactory
    {
        //        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', 006)->first();
        //        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosreposicion = GpTrasladosReposicion::join('gp_bodegas', 'gp_traslados_reposicions.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            //            ->where('bodega_origen', $bodega)
            ->where(function ($q) {
                $q->whereNull('gp_traslados_reposicions.completado')
                    ->orWhere('gp_traslados_reposicions.completado', 0);
            })
            ->select(
                'gp_traslados_reposicions.ART_CODIGO',
                'gp_traslados_reposicions.cantidad',
                'gp_traslados_reposicions.trasladado',
                'gp_traslados_reposicions.Documento',
                'gp_traslados_reposicions.bodega_origen',
                'gp_traslados_reposicions.bodega_destino',
            )
            ->get();

        foreach ($trasladosreposicion as $traslado) {

            if (!$traslado->reservado)
                $traslado->reservado = 0;

            if (!$traslado->trasladado)
                $traslado->trasladado = 0;
            $traslado->pendiente = number_format($traslado->cantidad - $traslado->reservado - $traslado->trasladado, 2, '.', '');

            unset($traslado->trasladado);
            unset($traslado->reservado);
        }

        $trasladosreposicion = $trasladosreposicion->toArray();
        $trasladosreposicion = array_values($trasladosreposicion);

        return response(
            $trasladosreposicion,
        );
    }

    public function reposiciones_pendientes(): Response|Application|ResponseFactory
    {
        //        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', 006)->first();
        //        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosreposicion = GpGuiaRemisionDetalle::join('gp_guia_remisions', 'gp_guia_remisions.id', 'gp_guia_remision_detalles.guia_remision_id')
            ->join('gp_bodegas', 'gp_guia_remisions.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            ->join('gp_traslados_reposicions', 'gp_traslados_reposicions.id', 'gp_guia_remision_detalles.detalle_id')
            //            ->where('gp_guia_remisions.bodega_origen', $bodega)
            ->where('gp_guia_remisions.tipo', 'reposicion')
            ->where('gp_guia_remisions.estado', 'enviada')
            ->select(
                'gp_traslados_reposicions.Documento',
                'gp_guia_remisions.GUI_REM_GUIA_REMISION AS guia_remision',
                'gp_traslados_reposicions.bodega_origen',
                'gp_traslados_reposicions.bodega_destino',
                'gp_traslados_reposicions.ART_CODIGO',
                'gp_guia_remision_detalles.GUI_REM_CANTIDAD',
                'gp_guia_remision_detalles.recibido',
            )
            ->get();

        foreach ($trasladosreposicion as $key => $traslado) {

            if (!$traslado->reservado)
                $traslado->reservado = 0;

            if (!$traslado->trasladado)
                $traslado->trasladado = 0;
            $traslado->pendiente = number_format($traslado->GUI_REM_CANTIDAD - $traslado->recibido, 2, '.', '');

            unset($traslado->GUI_REM_CANTIDAD);
            unset($traslado->recibido);
            unset($traslado->reservado);
            unset($traslado->trasladado);

            if ($traslado->pendiente == 0) {
                unset($trasladosreposicion[$key]);
            }
        }

        $trasladosreposicion = $trasladosreposicion->toArray();
        $trasladosreposicion = array_values($trasladosreposicion);

        return response(
            $trasladosreposicion,
        );
    }

    public function pendientes_despachar(): Response|Application|ResponseFactory
    {
        $pedidos = GpPedidoDetalle::join('gp_pedidos', 'gp_pedidos.id', 'gp_pedido_detalles.pedido_id')
            ->join('gp_orden_despachos', 'gp_orden_despachos.pedido_id', 'gp_pedidos.id')
            ->whereNotNull('factura')
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado');
                $query->orWhere('gp_pedidos.despachado', 0);
            })
            ->where('gp_orden_despachos.estado', 'liberada')
            ->where('gp_pedido_detalles.ART_CANTIDAD', '>', 'gp_pedido_detalles.trasladado')
            ->select(
                'gp_pedidos.CLI_RUC',
                'gp_pedidos.CLI_RAZON_SOCIAL',
                'gp_pedidos.pedido',
                'gp_pedidos.factura',
                'gp_pedidos.fecha_factura',
                'gp_pedidos.BOD_BODEGA',
                'gp_pedido_detalles.ART_CODIGO',
                'gp_pedido_detalles.ART_CANTIDAD',
                DB::raw('IFNULL(gp_pedido_detalles.devuelto, 0) AS devuelto'),
                DB::raw('(gp_pedido_detalles.ART_CANTIDAD - IFNULL(gp_pedido_detalles.trasladado, 0)) as pendiente')
            )
            ->get();

        return response(
            $pedidos,
        );
    }

}

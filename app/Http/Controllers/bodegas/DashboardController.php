<?php

namespace App\Http\Controllers\bodegas;

use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpOrdenDespacho;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\Section;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Bodegas\Contracts\DashboardServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use JsonResponseTrait;

    public function __construct(protected readonly DashboardServiceInterface $dashboard) {}
    public function dashboard(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $request->validate([
            'puntodeventa_id' => '',
        ]);

        $user = $request->user();

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $request->puntodeventa_id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;


        $sections = Section::join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->select('sections.*')
            ->where('perfil_sections.perfil_id', '=', $user->perfil_id)
            ->where('sections.parent_id', 10)
            ->where('sections.id', '!=', 11)
            ->where('sections.id', '!=', 27)
            ->where('sections.id', '!=', 37)
            ->orderBy('sections.order')
            ->get();

        foreach ($sections as $section) {
            if ($section->to == 'bodegas-recibosdecompral') {
                $ordendecompra = GpOrdenDeCompraGenerado::leftJoin(
                    'gp_orden_de_compra_generado_detalles',
                    'gp_orden_de_compra_generados.id',
                    '=',
                    'gp_orden_de_compra_generado_detalles.orden_generada_id'
                )
                    ->where('gp_orden_de_compra_generado_detalles.ORDEN_COMPRA_DETALLE_BODEGA', $bodega)
                    ->where('gp_orden_de_compra_generados.estado', 'generado')
                    ->count();
                $section->total = $ordendecompra;
            }

            if ($section->to == 'bodegas-despachopedidos') {
                $ordendesoacho = GpOrdenDespacho::where('BOD_BODEGA', $bodega)
                    ->where('estado', 'liberada')
                    ->count();
                $section->total = $ordendesoacho;
            }

            if ($section->to == 'bodegas-transferenciasventa') {
                $trasladosventas = GpTrasladosVentas::where('bodega_origen', $bodega)
                    ->whereNull('completado')
                    ->count();
                $section->total = $trasladosventas;
            }
            if ($section->to == 'bodegas-recibosventa') {

                $guias = GpGuiaRemision::where('estado', 'enviada')
                    ->where('bodega_destino', $bodega)
                    ->where('tipo', 'transferencia')
                    ->count();

                $section->total = $guias;
            }
            if ($section->to == 'bodegas-transferenciasreposicion') {
                $trasladosreposicion = GpTrasladosReposicion::where('bodega_origen', $bodega)
                    ->whereNull('completado')
                    ->count();
                $section->total = $trasladosreposicion;
            }
            if ($section->to == 'bodegas-recibosreposicion') {
                $guias = GpGuiaRemision::where('estado', 'enviada')
                    ->where('bodega_destino', $bodega)
                    ->where('tipo', 'reposicion')
                    ->count();
                $section->total = $guias;
            }

            if ($section->to == 'bodegas-devoluciones') {
                $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
                    ->where(function ($query) {
                        $query->where('gp_devoluciones.estado', 'aprobado');
                        $query->orWhere('gp_devoluciones.estado', 'solicitado');
                    })
                    ->where('gp_devoluciones.despachado', 1)
                    ->where('gp_pedidos.BOD_BODEGA', $bodega)

                    ->count();
                $section->total = $devoluciones;
            }
        }

        return response([
            'success' => true,
            'dashboard_sections' => $sections,
        ]);
    }


    public function listadoDespachosPendientes(string $ptoVta): JsonResponse
    {
        try {
            $info = $this->dashboard->listDispatchOrdersByPtoVta($ptoVta);
            return $this->successResponse('Información Obtenida Correctamente', $info, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la información: ' . $e->getMessage(), null, 500);
        }
    }

     public function contadorItemsServiciosDespachos(string $ptoVta): JsonResponse
    {
        try {

            $info = $this->dashboard->countItemServicesDisptach($ptoVta);
            return $this->successResponse('Información Obtenida Correctamente', $info, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la información: ' . $e->getMessage(), null, 500);
        }
    }
}

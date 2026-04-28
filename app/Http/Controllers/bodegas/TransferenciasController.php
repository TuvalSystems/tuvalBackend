<?php

namespace App\Http\Controllers\bodegas;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Symfony\Component\HttpFoundation\Response;

class TransferenciasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly TransferServiceInterface $transferService) {}


    public function transferenciasVentas($id, $pedido = null, $compartimiento = null)
    {
        return $this->handleApiAction(function () use ($id, $pedido, $compartimiento) {
            $data = $this->transferService->fetchSalesTransfers($id, $pedido, $compartimiento);
            return $this->successResponse('Transferencias obtenidas correctamente', $data, 200);
        }, 'laravel', 'transferenciasVentas');
    }


    public function dashboardTransferenciaVentas(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'ptoVtaId' => 'required|string'
            ]);
            $ptoVtaId = data_get($validate, 'ptoVtaId');
            $data = $this->transferService->fetchSalesTransfersSunnaries($ptoVtaId);
            return $this->successResponse('Dashboard obtenido correctamente', $data, 200);
        }, 'guia_ventas', 'transferenciaVentasDashboard');
    }

    public function obtenerCabeceraDetalleTransferenciaPorBodega(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'origen'    => 'required|string',
                'destino'   => 'required|string'
            ]);
            $origen = data_get($validate, 'origen');
            $destino = data_get($validate, 'destino');
            $data = $this->transferService->getSalesTransferHeadersBetweenWarehouses($origen, $destino);
            return $this->successResponse('Cabecera transferencias obtenidas correctamente', $data, 200);
        }, 'guia_ventas', 'obtenerCabeceraDetalleTransferenciaPorBodega');
    }

    public function obtenerListaDetalleTransferenciaPorBodega(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'origen'    => 'required|string',
                'destino'   => 'required|string',
                'pedido'    => 'nullable|string'
            ]);
            $origen = data_get($validate, 'origen');
            $destino = data_get($validate, 'destino');
            $pedido = data_get($validate, 'pedido');
            $data = $this->transferService->getListSalesTransferDetailsBetweenWarehouses($origen, $destino, $pedido);
            return $this->successResponse('Cabecera transferencias obtenidas correctamente', $data, 200);
        }, 'guia_ventas', 'obtenerCabeceraDetalleTransferenciaPorBodega');
    }


    public function actualizarPickingItemsTransferenciaVenta(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'items' => 'required|array'
            ]);
            $payload = data_get($validate, 'items');
            $data = $this->transferService->updatePickingSalesTransfers($payload);
            return $this->successResponse('Transferencias obtenidas correctamente', $data, 200);
        }, 'guia_ventas', 'actualizarPickingItemsTransferenciaVenta');
    }


    public function imprimirPickingTransferenciaVenta(Request $request): Response
    {
        return $this->handlePdfAction(function () use ($request): Response {
            $validated = $request->validate([
                'origen'                => ['required', 'string', 'min:1'],
                'destino'               => ['required', 'string', 'min:1'],
                'pesototal'             => ['nullable', 'string'],
                'items'                 => ['required', 'array', 'min:1'],
                'items.*.id'            => ['required', 'integer', 'distinct', 'exists:gp_traslados_ventas,id'],
                'items.*.seleccionado'  => ['required', 'numeric', 'min:0.001'],
            ]);
            $data = $this->transferService->printPickingSalesTransfers($validated);
            return $this->buildPdfResponse($data);
        }, 'guia_ventas', 'imprimirPickingTransferenciaVenta');
    }



    public function recibosventa($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $guias = GpGuiaRemision::where('estado', 'enviada')
            ->where('bodega_destino', $bodega)
            ->where('tipo', 'transferencia')
            ->get();

        foreach ($guias as $guia) {
            $detalle = GpGuiaRemisionDetalle::leftjoin('gp_traslados_ventas', 'gp_traslados_ventas.id', 'gp_guia_remision_detalles.detalle_id')
                ->leftjoin('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
                ->join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_guia_remision_detalles.GUI_REM_CODI_INTERNO')
                ->where('gp_guia_remision_detalles.guia_remision_id', $guia->id)
                ->select('gp_guia_remision_detalles.*', 'gp_pedidos.pedido', 'gp_articulos.ART_DECIMAL_CANTIDAD')
                ->get();

            $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)->get();

            $pedidos = '';

            foreach ($transferencias as $transferencia) {
                $pedidos .= $transferencia->pedido . ', ';
            }

            foreach ($detalle as $articulo) {
                $articulo->pendiente = number_format(
                    (float) $articulo->GUI_REM_CANTIDAD - (float) ($articulo->recibido ?? 0),
                    2,
                    '.',
                    ''
                );
                $articulo->recibido = $articulo->pendiente;
            }

            $guia->detalle = $detalle;
            $guia->pedidos = substr($pedidos, 0, -2);
        }

        return response([
            'success' => true,
            'recibos' => $guias,
        ]);
    }



    public function cancelarPickingV2(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'payload' => 'required'
            ]);
            $payload    = data_get($validate, 'payload');
            $data       = $this->transferService->cancelPicking($payload, $request->user());
            return $this->successResponse('Picking cancelado', $data, 200);
        }, 'laravel', 'cancelarPickingV2');
    }


    public function imprimirpicking(Request $request): \Illuminate\Http\Response
    {

        $nuevo_detalle = $request->all();

        $peso_total = 0;

        foreach ($nuevo_detalle as $art) {
            $peso_total += $art['ART_PESO'] * $art['seleccionado'];
            $bodega = $art['bodega_origen'];

            if (isset($art['pedido'])) {
                $save = GpTrasladosVentas::where('id', $art['id'])->first();
            } else {
                $save = GpTrasladosReposicion::where('id', $art['id'])->first();
            }
            $save->picking = 1;
            $save->save();
        }

        $data = array();
        $puntodeventa = GpPuntoVenta::join('gp_relacion_punto_venta_bodegas', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', 'gp_punto_ventas.PTO_VTA_ID')
            ->where('gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_BODEGA', $bodega)->first()->toarray();
        $data['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];


        $data['reporte'] = $nuevo_detalle;
        $data['peso_total'] = $peso_total;
        //return view('pickingVentasPDF', $data);
        $pdf = PDF::loadView('pickingVentasPDF', $data);
        return $pdf->stream('picking.pdf');
    }

    public function picking(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $nuevo_detalle = $request->all();

        $picking = $this->generarpicking($nuevo_detalle);

        return response([
            'success' => true,
            'picking' => $picking,
        ]);
    }

    public function generarpicking($detalle): array
    {
        $webservice = new WebserviceController();

        $para_peso = array();

        foreach ($detalle as &$art) {

            if (isset($art['pedido_id'])) {
                $pedido = GpPedido::where('id', $art['pedido_id'])->first();
                $pedido->transferencia_en_proceso = 1;
                $pedido->save();
            }

            $parameters = array(
                'id_articulo' => $art['ART_CODIGO'],
                'id_bodega' => $art['bodega_origen'],
                'id_compartimiento' => '',
            );

            $compartimento = $webservice->stockarticulobodegacompartimiento($parameters);

            if (isset($compartimento['MST_Stock_Articulo_Bodega_CompartimientoResult']['CS_STOCK_ARTICULO_BODEGA_COMPARTIMIENTO'])) {
                $compartimento = $compartimento['MST_Stock_Articulo_Bodega_CompartimientoResult']['CS_STOCK_ARTICULO_BODEGA_COMPARTIMIENTO'];

                $stock = array_column($compartimento, 'STO_ART_COMPART_STOCK_DISPONIBLE');

                array_multisort($stock, SORT_DESC, $compartimento);

                $cantidad = $art['seleccionado'];
                $arr_comp = array();
                foreach ($compartimento as $comp) {
                    $arr_comp[] = $comp;
                    $cantidad -= $comp['STO_ART_COMPART_STOCK_DISPONIBLE'];
                    if ($cantidad <= 0)
                        break;
                }

                $art['mejorStock'] = $arr_comp[0]['STO_ART_COMPART_COMPARTIMIENTO'];

                $art['compartimento'] = $arr_comp;
            } else {
                $art['mejorStock'] = 0;

                $art['compartimento'] = [];
            }

            $para_peso[] = $art;
        }



        $orderby = array_column($detalle, 'mejorStock');
        array_multisort($orderby, SORT_ASC, $detalle);


        $peso_total = 0;
        foreach ($para_peso as $art) {
            $peso_total += number_format($art['ART_PESO'] * $art['seleccionado'], 2, '.', '');
        }

        $picking = array();
        $picking['reporte'] = $detalle;
        $picking['peso_total'] = number_format($peso_total, 2, '.', '');

        return $picking;
    }

    public function checkpedidostraslados()
    {
        try {
            $this->transferService->checkOrderTranfer();
            return $this->buildSuccessResponse('Pedidos de traslados actualizados', 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
}

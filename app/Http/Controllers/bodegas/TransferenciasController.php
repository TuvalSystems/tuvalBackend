<?php

namespace App\Http\Controllers\bodegas;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\GpPedidoDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;

use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Traits\ExecuteApiTransactions;

class TransferenciasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    protected $transferService;
    public function __construct(TransferServiceInterface $transferServiceInterface)
    {
        $this->transferService = $transferServiceInterface;
    }
    public function savetransferenciasventas(GpPedido $pedido, $detalles)
    {

        foreach ($detalles as $detalle) {
            if ($pedido->BOD_BODEGA != $detalle->STO_ART_BODEGA) {
                $traslado = new GpTrasladosVentas();
                $traslado->pedido_id = $pedido->id;
                $traslado->detalle_id = $detalle->id;
                $traslado->detalle_stocks_id = $detalle->detalle_stocks_id;
                $traslado->ART_CODIGO = $detalle->ART_CODIGO;
                $traslado->ART_DESCRIPCION = $detalle->ART_DESCRIPCION_ORIGINAL;
                $traslado->ART_CODIGO_TRES_LETRAS = $detalle->ART_CODIGO_TRES_LETRAS;
                $traslado->ART_PLAN_UMEDIDA = $detalle->ART_PLAN_UMEDIDA;
                $traslado->ART_PESO = $detalle->ART_PESO;
                $traslado->cantidad = $detalle->seleccionado;
                $traslado->bodega_origen = $detalle->STO_ART_BODEGA;
                $traslado->bodega_destino = $pedido->BOD_BODEGA;
                $traslado->save();

                $detalle->traslado = 1;
                $detalle->save();
            }
        }
    }

    public function checktransferencias()
    {
        $pedidos = GpPedido::where('estado', 'traslado')->get();

        foreach ($pedidos as $pedido) {
            $detalles = GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
                ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
                ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
                ->select(['gp_pedido_detalles.*', 'gp_pedido_detalle_stocks.STO_ART_BODEGA', 'gp_pedido_detalle_stocks.seleccionado', 'gp_pedido_detalle_stocks.id AS detalle_stocks_id'])
                ->get();
            $this->savetransferenciasventas($pedido, $detalles);
        }
    }


    public function transferenciasVentas($id, $pedido = null, $compartimiento = null)
    {
        return $this->executeTransaction(function () use ($id, $pedido, $compartimiento) {
            $data = $this->transferService->fetchSalesTransfers($id, $pedido, $compartimiento);
            return $this->successResponse('Transferencias obtenidas', $data, 200);
        }, 'laravel', 'transferenciasVentas');
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

    public function cancelarpicking(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $picking = $request->all();
        $user = $request->user();

        if (GpTrasladosVentas::where('id', $picking['id'])->update(['picking' => null])) {

            $traslados = GpTrasladosVentas::where('pedido_id', $picking['pedido_id'])->get();
            $tiene_picking = false;
            foreach ($traslados as $traslado) {
                if ($traslado->picking) {
                    $tiene_picking = true;
                }
            }

            $pedido = GpPedido::where('id', $picking['pedido_id'])->first();
            if (!$tiene_picking) {
                $pedido->transferencia_en_proceso = null;
                $pedido->save();
            }
            $title = 'Picking Cancelado';
            $msg = 'El usuario ' . $user->USERID . ' canceló el picking del artículo ' . $picking['ART_CODIGO'] . ' del pedido ' . $pedido->pedido;
            $gpUser = GpUsuario::where('id', $user->id)->first();
            if ($gpUser) {
                $gpUser->notify(new UserNotification($title, $msg));
            }
            return response([
                'success' => true,
            ]);
        }
        return response([
            'error' => 'Hubo un error al guardar'
        ], 409);
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

<?php

namespace App\Http\Controllers\bodegas;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use Illuminate\Http\Request;
use App\Models\Notificaciones;
use App\Models\GpPedidoDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\FuncionesController;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class TransferenciasController extends Controller
{
    protected $invoiceService;
    protected $logisticsOperationsService;
    protected $stockWarehouseService;
    public function __construct(InvoiceServiceInterface $invoiceService, LogisticsOperationsServiceInterface $logisticsOperationsService, StockWarehouseServiceInterface $stockWarehouseService)
    {
        $this->invoiceService = $invoiceService;
        $this->logisticsOperationsService = $logisticsOperationsService;
        $this->stockWarehouseService = $stockWarehouseService;
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

    public function transferenciasventa($id, $pedido = null, $compartimiento = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $webservice = new WebserviceController();
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosventas = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
            ->join('gp_bodegas', 'gp_traslados_ventas.bodega_destino', 'gp_bodegas.BOD_BODEGA')
            ->join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_traslados_ventas.ART_CODIGO')
            ->where('bodega_origen', $bodega)
            ->where( function ($q){
                $q->where('gp_traslados_ventas.completado', 0)->orWhereNull('gp_traslados_ventas.completado');
            })
            // ->whereNull('completado')
            // ->where('completado', 0)
            // ->where('gp_pedidos.estado', '!=', 'eliminado');
            ->whereNotIn('gp_pedidos.estado', ['eliminado', 'anulada']);
        if ($pedido && $pedido != 'null') {
            $trasladosventas = $trasladosventas->where('gp_pedidos.pedido', $pedido);
        }
        $trasladosventas = $trasladosventas->select(
            'gp_traslados_ventas.*',
            'gp_pedidos.pedido',
            'gp_bodegas.BOD_DESCRIPCION',
            'gp_bodegas.BOD_BODEGA',
            'gp_pedidos.created_at AS fecha',
            'gp_pedidos.USERID AS usuario',
            'gp_articulos.ART_DECIMAL_CANTIDAD'
        )
            ->get();

        $bodegas = array();

        foreach ($trasladosventas as $traslado) {



            if (!$traslado->reservado)
                $traslado->reservado = 0;

            if (!$traslado->trasladado)
                $traslado->trasladado = 0;
            $traslado->pendiente = number_format($traslado->cantidad - $traslado->reservado - $traslado->trasladado, 2, '.', '');

            $traslado->fecha = substr($traslado->fecha, 0, -3);

            $params = array(
                'articulo' => $traslado->ART_CODIGO,
                'bodega' => $bodega,
                'compartimento' => '',
            );


            $compartimento =  $this->stockWarehouseService->fetchStockWarehouseCompartiment($params);
            $traslado->compartimiento = $compartimento['COMPARTIMIENTO'] ?? '';

            $funciones = new FuncionesController();

            if ($compartimiento && $compartimiento != 'null') {
                if (!$funciones->like_match($compartimiento . '%', $traslado->compartimiento)) {
                    continue;
                }
            }

            $letra_compartimiento = explode('-', $traslado->compartimiento);
            $letra_compartimiento = $letra_compartimiento[0];
            $bodegas[$traslado->bodega_destino]['bodega'] = $traslado->BOD_DESCRIPCION;
            $bodegas[$traslado->bodega_destino]['BOD_BODEGA'] = $traslado->BOD_BODEGA;
            $bodegas[$traslado->bodega_destino]['generar'] = false;
            $bodegas[$traslado->bodega_destino]['traslados'][] = $traslado;
            $bodegas[$traslado->bodega_destino]['pedidos'][$traslado->pedido] = $traslado->pedido;
            $bodegas[$traslado->bodega_destino]['compartimientos'][$letra_compartimiento] = $letra_compartimiento;
            $bodegas[$traslado->bodega_destino]['tipo'] = 'transferencia';
        }

        foreach ($bodegas as $key => &$bod) {
            $guias = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_destino', 'gp_bodegas.BOD_BODEGA')
                ->where('bodega_origen', $bodega)
                ->where('bodega_destino', $key)
                ->where('estado', 'pendiente')
                ->where('tipo', 'transferencia')
                ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
                ->orderBy('created_at')
                ->get()->toArray();
            $bod['guias'] = $guias;

            $guias = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_destino', 'gp_bodegas.BOD_BODEGA')
                ->where('bodega_origen', $bodega)
                ->where('bodega_destino', $key)
                ->where('estado', 'pendiente')
                ->where('tipo', 'transferencia')
                ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
                ->orderBy('created_at', 'DESC')
                ->first();
            $bod['ultimaguia'] = $guias;

            $bod['pedidos'] = array_values($bod['pedidos']);
            $bod['compartimientos'] = array_values($bod['compartimientos']);
            $peso_total = 0;
            foreach ($bod['traslados'] as $traslado) {
                $peso_total += $traslado->ART_PESO * $traslado->pendiente;
            }
            $bod['peso_total'] = $peso_total;
        }

        $bodegas = array_values($bodegas);

        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();
        if (!isset($bodegas[0])) {
            return response([
                'error' => 'No existen resultados'
            ], 401);
        }

        if ($pedido) {
            return response([
                'success' => true,
                'traslados' => $bodegas[0]['traslados'],
            ]);
        }

        return response([
            'success' => true,
            'bodegas' => $bodegas,
        ]);
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
            $detalle = GpGuiaRemisionDetalle::join('gp_traslados_ventas', 'gp_traslados_ventas.id', 'gp_guia_remision_detalles.detalle_id')
                ->join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
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
                $articulo->pendiente = number_format($articulo->GUI_REM_CANTIDAD - $articulo->recibido, 2, '.', '');
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
        $user = Auth::user();

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
            $notificacion = new Notificaciones();
            $notificacion->titulo = 'Picking Cancelado';
            $notificacion->user_id = $pedido->user_id;
            $notificacion->mensaje = 'El usuario ' . $user->USERID . ' canceló el picking del artículo ' . $picking['ART_CODIGO'] . ' del pedido ' . $pedido->pedido;
            $notificacion->estado = 'nuevo';
            $notificacion->save();

            return response([
                'success' => true,
            ]);
        }
        return response([
            'error' => 'Hubo un error al guardar'
        ], 401);
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
        $pedidos = GpPedido::where('estado', 'traslado')
            ->get();
        foreach ($pedidos as $pedido) {
            // $pedidosControl->checkenviarfactura($pedido, true);
            $this->invoiceService->checkSendInvoice($pedido, true);
        }
    }
}

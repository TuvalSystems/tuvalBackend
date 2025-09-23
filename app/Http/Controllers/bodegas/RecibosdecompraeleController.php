<?php

namespace App\Http\Controllers\bodegas;


use App\Services\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use Illuminate\Support\Facades\Log;


class RecibosdecompraeleController extends Controller
{
    use JsonResponseTrait;
    protected $purchaseReceiptLItemService;

    public function __construct(PurchaseReceiptLItemServiceInterface $purchaseReceiptLItemService)
    {
        $this->purchaseReceiptLItemService = $purchaseReceiptLItemService;
    }
    public function bandeja(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $request->validate([
            'puntodeventa_id' => '',
        ]);

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $request->puntodeventa_id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $ordendecompra = GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->leftJoin('gp_pedidos', 'gp_pedidos.pedido', '=', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN')

            ->where('gp_orden_de_compras.ORDEN_COMPRA_BODEGA', $bodega)
            ->where('gp_orden_de_compra_generados.estado', 'generado')
            ->select(
                'gp_orden_de_compra_generados.*',
                'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN',
                'gp_proveedors.PROV_RAZON_SOCIAL',
                'gp_proveedors.PROV_DIRECCION_1',
                'gp_pedidos.CLI_RAZON_SOCIAL',
                'gp_pedidos.USERID',
                DB::raw("CONCAT(gp_pedidos.VEND_NOMBRES,' ',gp_pedidos.VEND_APELLIDOS) AS vendedor"),

                'gp_orden_de_compras.ORDEN_COMPRA_ID_CLIENTE'
            )
            ->orderBy('gp_orden_de_compra_generados.ORDEN_COMPRA_FECHA_ENTREGA', 'ASC')
            ->get();


        $recibidos = GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->where('gp_orden_de_compras.ORDEN_COMPRA_BODEGA', $bodega)
            ->where('gp_orden_de_compra_generados.estado', 'recibido')
            ->select('gp_orden_de_compra_generados.*', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN', 'gp_proveedors.PROV_RAZON_SOCIAL', 'gp_proveedors.PROV_DIRECCION_1')
            ->orderBy('gp_orden_de_compra_generados.ORDEN_COMPRA_FECHA_ENTREGA', 'DESC')
            ->get();

        $eliminados = GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->where('gp_orden_de_compras.ORDEN_COMPRA_BODEGA', $bodega)
            ->where('gp_orden_de_compra_generados.estado', 'eliminado')
            ->select('gp_orden_de_compra_generados.*', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN', 'gp_proveedors.PROV_RAZON_SOCIAL', 'gp_proveedors.PROV_DIRECCION_1')
            ->orderBy('gp_orden_de_compra_generados.ORDEN_COMPRA_FECHA_ENTREGA', 'DESC')
            ->get();

        return response([
            'success' => true,
            'bandeja' => $ordendecompra,
            'recibidos' => $recibidos,
            'eliminados' => $eliminados,
        ]);
    }

    public function detalle(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $detalle = GpOrdenDeCompraGeneradoDetalle::where('orden_generada_id', $data['id'])->get();

        foreach ($detalle as $det) {

            if (!$det->recibido)
                $det->recibido = 0;

            $det->ORDEN_COMPRA_DETALLE_CANTIDAD = number_format($det->ORDEN_COMPRA_DETALLE_CANTIDAD, 2, '.', '');

            $det->pendiente = number_format($det->ORDEN_COMPRA_DETALLE_CANTIDAD - $det->recibido, 2, '.', '');
            $det->seleccionado = 0;
        }

        return response([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    public function recibir(Request $request)
    {
        try {
            $data = $request->all();
            $user = Auth::user();
            DB::beginTransaction();
            $this->purchaseReceiptLItemService->receivedL($data, $user);
            DB::commit();
            return $this->buildSuccessResponse('Orden de compra recibida', 200);
        } catch (\Throwable $th) {
            Log::channel('soap_errors')->error('Error al recibir la orden de compra de Item L en (recibir): ' . $th->getMessage());
            DB::rollBack();
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function imprimirrecibol(Request $request): \Illuminate\Http\Response
    {
        $data = $request->all();
        $pdf = PDF::loadView('ordenReciboLPDF', $data);
        return $pdf->stream('orden_recibo_' . $data['ORDEN_COMPRA_DOCUMENTO'] . '.pdf');
    }

    public function eliminar(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $orden = GpOrdenDeCompraGenerado::where('id', $data['id'])->get()->first();
        $orden->estado = 'eliminado';
        $orden->save();

        return response([
            'success' => true,
            'message' => 'Orden de compra eliminada',
        ]);
    }



    public function setdetalleordendecompra($ordendecompra)
    {
        $detalleOrdendecompra = GpOrdenDeCompraGeneradoDetalle::join('gp_pedido_detalle_proveedores', 'gp_pedido_detalle_proveedores.id', 'gp_orden_de_compra_generado_detalles.detalle_proveedores_id')
            ->join('gp_pedido_detalles', 'gp_pedido_detalles.id', 'gp_pedido_detalle_proveedores.detalle_id')
            ->where('orden_generada_id', $ordendecompra->id)
            ->select('gp_orden_de_compra_generado_detalles.*', 'gp_pedido_detalles.ART_PESO')
            ->get();
        $ordendecompra->subtotal = 0;
        $ordendecompra->iva = 0;
        foreach ($detalleOrdendecompra as $det) {
            $ordendecompra->subtotal += $det->ORDEN_COMPRA_DETALLE_TOTAL;
            $ordendecompra->iva += $det->ORDEN_COMPRA_DETALLE_IMPUESTO;
        }

        $ordendecompra->subtotal = number_format($ordendecompra->subtotal, '2', '.', '');
        $ordendecompra->iva = number_format($ordendecompra->iva, '2', '.', '');
        $ordendecompra->total = number_format($ordendecompra->subtotal + $ordendecompra->iva, '2', '.', '');

        $ordendecompra->detalle = $detalleOrdendecompra;
        return $ordendecompra;
    }



    public function imprimir($id)
    {
        try {
            $ordendecompra = $this->purchaseReceiptLItemService->printPurchaseReceiptL($id);
            $pdf = PDF::loadView('ordendecompraPDF', $ordendecompra->toArray());
            return $pdf->stream('ordendecompra-' . $ordendecompra->ORDEN_COMPRA_DOCUMENTO . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Error al imprimir el recibo de compra: ' . $e->getMessage());
            return $this->buildErrorResponse('Error al imprimir el recibo de compra', 404);
        }
    }
}

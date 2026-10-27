<?php

namespace App\Http\Controllers\Compras\Recepciones;


use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodega;
use App\Models\Compras\OrdenesCompra\GpOrdenDeCompraGenerado;
use App\Models\Compras\OrdenesCompra\GpOrdenDeCompraGeneradoDetalle;
use App\Services\Compras\Recepciones\Contracts\PurchaseReceiptLItemServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;


class RecibosDeCompraController extends Controller
{
    use JsonResponseTrait;

    public function __construct(protected readonly PurchaseReceiptLItemServiceInterface $purchaseReceiptLItemService) {}
    public function bandeja(Request $request): Response|Application|ResponseFactory
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

    public function detalle(Request $request): Response|Application|ResponseFactory
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
            $user = $request->user();
            DB::transaction(function () use ($data, $user) {
                $this->purchaseReceiptLItemService->receivedL($data, $user);
            });
            return $this->buildSuccessResponse('Orden de compra recibida', 200);
        } catch (\Throwable $th) {
            Log::channel('soap_errors')->error('Error al recibir la orden de compra de Item L en (recibir): ' . $th->getMessage());
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function imprimirrecibol(Request $request): Response
    {
        $data = $request->all();
        $pdf = PDF::loadView('ordenReciboLPDF', $data);
        return $pdf->stream('orden_recibo_' . $data['ORDEN_COMPRA_DOCUMENTO'] . '.pdf');
    }

    public function eliminar(Request $request): Response|Application|ResponseFactory
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

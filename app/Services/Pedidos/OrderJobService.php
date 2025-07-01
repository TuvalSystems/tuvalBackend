<?php

namespace App\Services\Pedidos;

use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\WebserviceController;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalleProveedores;
use Illuminate\Support\Facades\Log;

class OrderJobService
{

    protected $webService;
    protected $pedidoController;
    protected $despachoController;
    public function __construct(WebserviceController $webService, PedidosController $pedidoController, DespachosController $despachoController)
    {
        $this->webService = $webService;
        $this->pedidoController = $pedidoController;
        $this->despachoController = $despachoController;
    }



    /** funcion para enviar las facturas del siguiente mes a gp cada 1 de cad mes
     *  remplazo de sendfacturasgp()
     *
    */

    public function sendInvoiceNextMonth()
{
    Log::channel('cron_process')->info('🔄 [OrderJobService] - Iniciando proceso sendInvoiceNextMonth');

    // Procesar pedidos en estado 'espera'
    $pedidos = GpPedido::where('estado', 'espera')->get();
    Log::channel('cron_process')->info("📌 [OrderJobService] - Pedidos en 'espera' encontrados: " . $pedidos->count());

    foreach ($pedidos as $pedido) {
        Log::channel('cron_process')->info("➡️ [OrderJobService] - Enviando factura para Pedido ID: {$pedido->id}");
        try {
            $this->pedidoController->checkenviarfactura($pedido);
        } catch (\Exception $e) {
            Log::channel('cron_process')->error("[OrderJobService] - Error enviando factura para Pedido ID {$pedido->id}: {$e->getMessage()}");
        }
    }

    // Procesar traslados en estado 'orden_de_compra'
    $traslados = GpPedido::where('estado', 'orden_de_compra')->get();
    Log::channel('cron_process')->info("📌 [OrderJobService] - Pedidos en 'orden_de_compra' encontrados: " . $traslados->count());

    foreach ($traslados as $pedido) {
        Log::channel('cron_process')->info("➡️ [OrderJobService] - Procesando traslado Pedido ID: {$pedido->id}");
        $listo = 'yes';

        $detalleProveedores = GpPedidoDetalleProveedores::join('gp_orden_de_compra_generados', 'gp_orden_de_compra_generados.detalle_id', 'gp_pedido_detalle_proveedores.detalle_id')
            ->where('pedido_id', $pedido->id)
            ->select('gp_pedido_detalle_proveedores.*', 'gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO')
            ->get();

        Log::channel('cron_process')->info("   ↳ Detalles proveedores encontrados: " . $detalleProveedores->count());

        if ($detalleProveedores->isEmpty()) {
            $listo = 'no';
            Log::channel('cron_process')->warning("⚠️ [OrderJobService] - Pedido ID {$pedido->id} no tiene detalles de proveedores.");
        }

        foreach ($detalleProveedores as $prov) {
            if ($prov->recibido == 'no') {
                $listo = 'no';
                Log::channel('cron_process')->warning("⚠️ [OrderJobService] - Proveedor detalle ID {$prov->id} marcado como NO recibido.");
            }

            $parameters = [
                'documento_origen' => $prov->ORDEN_COMPRA_RECIBO,
                'tipo_documento' => 1,
            ];

            try {
                $result = $this->webService->transaccional('MST_Estado_Recibo_Compra', $parameters);
                $estado = $result['MST_Estado_Recibo_CompraResult']['CS_ESTADO_RECIBO_COMPRA'][0]['REC_COM_ESTADO_RECEPCION'] ?? 'NO DEFINIDO';

                Log::channel('cron_process')->info("   ↳ Estado recibido del WS para documento {$prov->ORDEN_COMPRA_RECIBO}: {$estado}");

                if ($estado !== 'PROCESADO') {
                    $listo = 'no';
                    Log::channel('cron_process')->warning("⚠️ [OrderJobService] - Documento {$prov->ORDEN_COMPRA_RECIBO} no está procesado en GP.");
                }
            } catch (\Exception $e) {
                $listo = 'no';
                Log::channel('cron_process')->error("[OrderJobService] - Error en WebService para documento {$prov->ORDEN_COMPRA_RECIBO}: {$e->getMessage()}");
            }
        }

        if ($listo === 'yes') {
            Log::channel('cron_process')->info("✅ [OrderJobService] - Pedido ID {$pedido->id} listo para pasar a 'espera' y generar despacho.");
            try {
                $pedido->estado = 'espera';
                $pedido->save();

                $this->despachoController->generarordendespacho($pedido);
                $this->pedidoController->sendfacturagp($pedido);
            } catch (\Exception $e) {
                Log::channel('cron_process')->error("[OrderJobService] - Error procesando despacho/factura para Pedido ID {$pedido->id}: {$e->getMessage()}");
            }
        } else {
            Log::channel('cron_process')->info("❌ [OrderJobService] - Pedido ID {$pedido->id} NO cumple condiciones para pasar a 'espera'.");
        }
    }

    Log::channel('cron_process')->info('✅ [OrderJobService] - Proceso sendInvoiceNextMonth FINALIZADO.');
}

}

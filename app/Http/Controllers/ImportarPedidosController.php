<?php

namespace App\Http\Controllers;

use App\Enums\Ventas\TaxRateEnum;
use App\Imports\PedidosImport;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpVendedor;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\DB;

class ImportarPedidosController extends Controller
{
    function importarpedidos(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');

//        DB::statement("SET FOREIGN_KEY_CHECKS=0;");
//        DB::statement("TRUNCATE TABLE gp_pedidos");
//        DB::statement("TRUNCATE TABLE gp_pedido_detalles");
//        DB::statement("TRUNCATE TABLE gp_pedido_detalle_stocks");
//
//        die();

        $array = Excel::toCollection(new PedidosImport, 'sheet.xlsx');

//        return response([
//            'success' => true,
//            'facturas' => $array
//        ]);

//        $pedidos_importados = $array[0];
//        $this->guardarpedidos($pedidos_importados);

        $detalles_importados = $array[1];
        $this->guardardetalles($detalles_importados);

        return response([
            'success' => true,
            'message' => 'Facturas importadas correctamente'
        ]);

    }

    public function guardardetalles($detalles_importados){
        $orden = 0;
        $pedido_anterior = '';
        foreach($detalles_importados as $key => $importado){
            if(!$key)
                continue;
            foreach($importado as $key => $value){
                $importado[$key] = trim($value);
            }

            $pedido = GpPedido::where('factura', $importado[1])->first();
            $existente = GpPedidoDetalle::where('pedido_id', $pedido->id)->where('ART_CODIGO', $importado[2])->first();
            if($existente)
                continue;

            $detalle = new GpPedidoDetalle;

            $pedido = GpPedido::where('factura', $importado[1])->first();
            if($pedido_anterior != $pedido->id){
                $orden = 1;
            }else{
                $orden++;
            }
            $pedido_anterior = $pedido->id;
            $detalle->pedido_id = $pedido->id;
            $detalle->orden = $orden;

            $articulo = GpArticulo::where('ART_CODIGO', $importado[2])->first();
            if(!$articulo){
                die('No existe el artículo '.$importado[2]);
            }

            $detalle->ART_CODIGO = $articulo->ART_CODIGO;
            $detalle->ART_DESCRIPCION = $importado[3];
            $detalle->ART_DESCRIPCION_ORIGINAL = $articulo->ART_DESCRIPCION;
            $detalle->ART_CODIGO_TRES_LETRAS = $articulo->ART_CODIGO_TRES_LETRAS;
            $detalle->ART_DECIMAL_CANTIDAD = $articulo->ART_DECIMAL_CANTIDAD;
            $detalle->ART_PLAN_UMEDIDA = $articulo->ART_PLAN_UMEDIDA;
            $detalle->ART_MARCA = $articulo->ART_MARCA;
            $detalle->ART_MATERIAL = $articulo->ART_MATERIAL;
            $detalle->ART_PESO = $articulo->ART_PESO;
            $detalle->ART_TIPO_COMPRA = $articulo->ART_TIPO_COMPRA;
            $detalle->ART_VALOR = (float)$importado[9];
            $detalle->ART_VALOR_ORIGINAL = (float)$importado[9];
            $detalle->ART_DESCUENTO = (float)$importado[10];
            $detalle->ART_IVA = number_format(((float)$importado[9] - $detalle->ART_DESCUENTO) * 0.12, 2, '.', '');
            $detalle->ART_CANTIDAD = (float)$importado[6];
            $detalle->ART_STOCK = 0;
            $detalle->ART_IMP_VTA = 12;
            $detalle->ART_COSTO_PROMEDIO = (float)$importado[8];
            $detalle->descuento = 25;
            $detalle->descuento_original = 25;
            $detalle->modificador = 0;
            $detalle->save();

            $stock = new GpPedidoDetalleStock();
            $stock->pedido_id = $pedido->id;
            $stock->detalle_id = $detalle->id;
            $stock->STO_ART_BODEGA = $pedido->BOD_BODEGA;
            $stock->STO_ART_STOCK_DISPONIBLE = 0;
            $stock->seleccionado = $detalle->ART_CANTIDAD;
            $stock->reservado = 0;
            $stock->save();
        }
    }

    public function guardarpedidos($importados){
        foreach($importados as $key => $importado){

//            if($key < 1)
//                continue;
//
//            if($key > 500)
//                break;


            if(!$key)
                continue;

            foreach($importado as $key => $value){
                $importado[$key] = trim($value);
            }

            $pedido = GpPedido::where('factura', $importado[1])->first();
            if($pedido){
               continue;
            }

            $pedido = new GpPedido;
            $ultimoPedido = GpPedido::orderBy('id','DESC')->first();

            if(!$ultimoPedido){
                $ultimoPedido = new \stdClass();
                $ultimoPedido->id = env('SECUENCIAL_PEDIDO');
                $pedido->id = $ultimoPedido->id + 1;
            }

            $pedido->pedido = 'PED'.str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
            $pedido->factura = $importado[1];

            $fecha_factura = Date::excelToDateTimeObject($importado[13])->format('Y-m-d');



            $puntodeventa = GpPuntoVenta::join('gp_relacion_punto_venta_bodegas', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', 'gp_punto_ventas.PTO_VTA_ID')
                ->where('gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_BODEGA', $importado[2])
                ->select('gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
                ->first();


            $pedido->fecha_factura = $fecha_factura;
            $pedido->estado = 'facturado';
            $pedido->user_id = 6;

            $cliente = GpCliente::where('CLI_RUC', $importado[4])->first();
            if(!$cliente){
                die('No existe el cliente: '.$importado[4]);
            }

            $sucursal = GpClienteSucursal::where('CLI_SUC_RUC', $importado[4])->where('CLI_SUC_ID_DIRECCION', $importado[6] )->first();

            if(!$sucursal){
                $sucursal = GpClienteSucursal::where('CLI_SUC_RUC', $importado[4])->first();
            }

            if(!$sucursal){
                die('No existe dirección: '.$importado[6].' para cliente: '.$importado[4]);
            }

            $pedido->sucursal_id = $sucursal->id;

            $sucursal_facturacion = GpClienteSucursal::where('CLI_SUC_RUC', $importado[4])->where('CLI_SUC_ID_DIRECCION', $importado[7] )->first();

            if(!$sucursal_facturacion){
                $sucursal_facturacion = GpClienteSucursal::where('CLI_SUC_RUC', $importado[4])->first();
            }

            $pedido->sucursal_facturacion_id = $sucursal_facturacion->id;


            $pedido->cliente_id = $cliente->id;
            $pedido->factura_posterior_despacho = 0;
            $pedido->factura_a_demanda = 0;
            $pedido->factura_siguiente_mes = 0;
            $pedido->fecha_entrega = null;
            $pedido->cotizacion_id = null;
            $pedido->BOD_BODEGA = $importado[14];
            $pedido->PTO_VTA_ID = $puntodeventa->PTO_VTA_ID;
            $pedido->PTO_VTA_DESCRIPCION = $puntodeventa->PTO_VTA_DESCRIPCION;
            $pedido->USERID = 'adminWeb';

            $forma_de_pago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $importado[8])->where('DESC_FORMA_PAGO_TIPO_CREDITO', $importado[9])->first();

            if(!$forma_de_pago){
                echo 'Factura: '.$pedido->factura.' -> No existe la forma de pago para condición: '.$importado[8].', forma de pago: '.$importado[9].'<br>';
                $forma_de_pago = GpDescuentoFormaPago::where('id', 14)->first();
            }

            $pedido->DESC_FORMA_PAGO_CODIGO = $forma_de_pago->DESC_FORMA_PAGO_CODIGO;
            $pedido->CLI_RUC = $cliente->CLI_RUC;
            $pedido->CLI_RAZON_SOCIAL = $cliente->CLI_RAZON_SOCIAL;
            $pedido->CLI_CORREO_ELECT_SRI = $cliente->CLI_CORREO_ELECT_SRI;
            $pedido->COND_ID = $forma_de_pago->DESC_FORMA_PAGO_CONDICION_PAGO;
            $pedido->COND_ID_ORIGINAL = $forma_de_pago->DESC_FORMA_PAGO_CONDICION_PAGO;

            $vendedor = GpVendedor::where('VEND_ID', $importado[5])->first();
            if(!$vendedor){
                die('No existe vendedor: '.$importado[5]);
            }

            $pedido->VEND_ID = $vendedor->VEND_ID;
            $pedido->VEND_NOMBRES = $vendedor->VEND_NOMBRES;
            $pedido->VEND_APELLIDOS = $vendedor->VEND_APELLIDOS;
            $pedido->despacho = 'Retiro en bodega';
            $pedido->despachado = 1;
            $pedido->porcentage_descuentooriginal = 0;
            $pedido->porcentage_descuento = 0;
            $pedido->subtotal = number_format((float)$importado[15],2,'.','');
            $pedido->descuento = 0;
            $pedido->subtotalmenosdescuento = number_format((float)$importado[15],2,'.','');
            $pedido->iva = number_format((float)$importado[17],2,'.','');
            $pedido->total = number_format((float)$importado[18],2,'.','');
            $pedido->requiere_aprobacion_comercial = 'no';
            $pedido->aprobado_comercial = 'no';
            $pedido->aprobador_comercial_id = null;
            $pedido->requiere_aprobacion_crediticia = 'no';
            $pedido->aprobado_crediticio = 'no';
            $pedido->aprobador_crediticio_id = null;
            $pedido->motivo_crediticio = null;
            $pedido->nota_interna = null;
            $pedido->nota_cliente = null;
            $pedido->orden_de_compra = $importado[10];
            $pedido->created_at = Date::excelToDateTimeObject($importado[13])->format('Y-m-d h:i:s');
            $pedido->updated_at = Date::excelToDateTimeObject($importado[13])->format('Y-m-d h:i:s');
            $pedido->impuesto = TaxRateEnum::IVA_15->value;
            $pedido->save();
        }
    }
}

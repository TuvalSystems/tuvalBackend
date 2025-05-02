<?php

namespace App\Http\Controllers;

use App\Http\Controllers\WebserviceController;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpDatosItemsL;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\GpVendedor;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use Illuminate\Http\Request;
use App\Models\GpTempCart;
use App\Models\GpTempCartProveedores;
use App\Models\GpTempCartStock;

class FuncionesController extends Controller
{
    public function setTempCartFrom($detalle, $data, $user){
        GpTempCart::where('sucursal_id', $data['sucursal_id'])
                ->where('cliente_id', $data['cliente_id'])
                ->where('puntodeventa_id', $data['PTO_VTA_ID'])
                ->delete();

        foreach($detalle as $key => $articulo){
            $tempcart = new GpTempCart;
            $tempcart->detalle_id = $articulo->id;
            $tempcart->sucursal_id = $data['sucursal_id'];
            $tempcart->cliente_id = $data['cliente_id'];
            $tempcart->puntodeventa_id = $data['PTO_VTA_ID'];
            $tempcart->user_id = $user->id;
            $tempcart->USERID = $user->USERID;
            $tempcart->ART_CODIGO = $articulo->ART_CODIGO;
            $tempcart->ART_DESCRIPCION = $articulo->ART_DESCRIPCION;
            $tempcart->ART_DESCRIPCION_ORIGINAL = $articulo->ART_DESCRIPCION_ORIGINAL;
            $tempcart->ART_CODIGO_TRES_LETRAS = $articulo->ART_CODIGO_TRES_LETRAS;
            $tempcart->ART_DECIMAL_CANTIDAD = $articulo->ART_DECIMAL_CANTIDAD;
            $tempcart->ART_PLAN_UMEDIDA = $articulo->ART_PLAN_UMEDIDA;
            $tempcart->ART_MARCA = $articulo->ART_MARCA;
            $tempcart->ART_MATERIAL = $articulo->ART_MATERIAL;
            $tempcart->ART_PESO = $articulo->ART_PESO;
            $tempcart->ART_TIPO_COMPRA = $articulo->ART_TIPO_COMPRA;
            $tempcart->ART_VALOR = $articulo->ART_VALOR;
            $tempcart->ART_IVA = $articulo->ART_IVA;
            $tempcart->ART_VALOR_ORIGINAL = $articulo->ART_VALOR_ORIGINAL;
            $tempcart->ART_DESCUENTO = $articulo->ART_DESCUENTO;
            $tempcart->ART_CANTIDAD = $articulo->ART_CANTIDAD;
            $tempcart->ART_STOCK = $articulo->ART_STOCK;
            $tempcart->ART_IMP_VTA = $articulo->ART_IMP_VTA;
            $tempcart->ART_COSTO_PROMEDIO = $articulo->ART_COSTO_PROMEDIO;
            $tempcart->descuento = $articulo->descuento;
            $tempcart->descuento_original = $articulo->descuento_original;
            $tempcart->modificador = $articulo->modificador;
            $tempcart->orden = $key + 1;
            $tempcart->save();

            $proveedores = $articulo->proveedores;
            foreach($proveedores as $pro){
                $proveedor = new GpTempCartProveedores;
                $proveedor->id_tempcart = $tempcart->id;
                $proveedor->ID_PROVEEDOR = $pro->ID_PROVEEDOR;
                $proveedor->DESCRIPCION_ARTICULO = $pro->DESCRIPCION_ARTICULO;
                $proveedor->FECHA_ENTREGA = $pro->FECHA_ENTREGA;
                $proveedor->CANTIDAD = $pro->CANTIDAD;
                $proveedor->COSTO_UNITARIO = $pro->COSTO_UNITARIO;
                $proveedor->IMPUESTO = $pro->IMPUESTO;
                $proveedor->TOTAL = $pro->TOTAL;
                $proveedor->TOTALCONIMPUESTO = $pro->TOTALCONIMPUESTO;
                $proveedor->NOTA = $pro->NOTA;
                $proveedor->MARGEN = $pro->MARGEN;
                $proveedor->save();
            }

            foreach($articulo->stock as $stockbodega){
                $stock = new GpTempCartStock;
                $stock->id_tempcart = $tempcart->id;
                $stock->STO_ART_BODEGA = $stockbodega['STO_ART_BODEGA'];
                $stock->STO_ART_STOCK_DISPONIBLE = $stockbodega['STO_ART_STOCK_DISPONIBLE'];
                if(!isset($stockbodega['seleccionado'])) {
                    $stockbodega['seleccionado'] = 0;
                }elseif($stockbodega['seleccionado'] > $stock->STO_ART_STOCK_DISPONIBLE){
                    $stockbodega['seleccionado'] = $stock->STO_ART_STOCK_DISPONIBLE;
                }
                $stock->seleccionado = $stockbodega['seleccionado'];
                $stock->save();
            }
        }
    }

    public function stockarticulobodega($consulta_bodega, $arr_bodegas = null): array
    {
        $webservices = new WebserviceController;
        $stock = $webservices->stockarticulobodega($consulta_bodega);
        $stock_general = array();

        if(isset($stock['MST_Stock_Articulo_BodegaResult']['CS_STOCK_ARTICULO_BODEGA'])){
            foreach($stock['MST_Stock_Articulo_BodegaResult']['CS_STOCK_ARTICULO_BODEGA'] as $st){
                unset($st['STO_ART_AUTENTIFICACION']);
                unset($st['STO_ART_DESCRIPCION']);
                unset($st['STO_ART_STOCK_DANADO']);
                unset($st['STO_ART_STOCK_MANO']);
                unset($st['STO_ART_STOCK_RESERVADO']);
                unset($st['STO_ART_STOCK_USO']);
                unset($st['STO_ART_UMEDIDA']);
                $stock_general[$st['STO_ART_ARTICULO']][$st['STO_ART_BODEGA']] = $st;
            }
        }
        $return = array();
        foreach($stock_general as $key => $articulo){
            foreach($arr_bodegas as $keybod => $bodega){
                if(isset($articulo[$keybod]))
                    $return[$key][$keybod] = $articulo[$keybod];
            }
        }

        return $return;
    }

    public function gebodegas($data, $consulta = null): array
    {
        $arr_bodegas = array();
//        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $data['PTO_VTA_ID'])->first()->toArray();
//        $arr_bodegas[$bodega['PTO_VTA_BOD_BODEGA']] = $bodega['PTO_VTA_BOD_BODEGA'];

        $masbodegas = GpRelacionPuntoVentaBodegaRelacionada::where('PTO_VTA_BOD_REL_PUNTO_VENTA', $data['PTO_VTA_ID']);
        if($consulta){
            $masbodegas =  $masbodegas->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA');
        }
        $masbodegas =  $masbodegas->orderBy('orden', 'ASC');
            $masbodegas =  $masbodegas->get()->toArray();



        foreach($masbodegas as $bodega){
            $arr_bodegas[$bodega['PTO_VTA_BOD_REL_BODEGA']] = $bodega['PTO_VTA_BOD_REL_BODEGA'];
        }

        return $arr_bodegas;
    }

    public function getcliente($data){
        $cliente = GpCliente::where('id', $data['cliente_id'])->get()->first();
        $vendedor = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
        $cliente->vendedor = $vendedor;

        $sucursales = GpClienteSucursal::join('gp_vendedors','gp_vendedors.VEND_ID','gp_cliente_sucursals.CLI_SUC_VENDEDOR')
            ->where('CLI_SUC_RUC', $cliente->CLI_RUC)
            ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS' )
            ->orderby('CLI_SUC_ID_DIRECCION','DESC')
            ->get();
        $cliente->sucursales = $sucursales;
        $cliente->sucursalesfacturacion = $sucursales;

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $cliente->CLI_RUC,
        );
        $webservices = new WebserviceController;
        $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);

        $suspendido = $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['SUSPENDIDO'] ?? 0;
        $cliente->suspendido = $suspendido;
        $cliente->cupo_disponible = number_format($cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_DISPONIBLE_GP'],2,'.', ',');
        $cliente->cupo_asignado = number_format($cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_ASIGNADO'],2,'.', ',');

        return $cliente;
    }

    public function stockreservado($articulo, $bodega, $pedido_id = null): int
    {

        $stockReservado = GpPedidoDetalleStock::join('gp_pedido_detalles', 'gp_pedido_detalle_stocks.detalle_id','=','gp_pedido_detalles.id')
        ->join('gp_pedidos','gp_pedidos.id','=','gp_pedido_detalle_stocks.pedido_id')
        ->whereNotNull('gp_pedido_detalle_stocks.reservado')
        ->where(function($q){
            $q->where(function($query){
                $query->where('gp_pedidos.requiere_aprobacion_comercial','no');
            })
            ->orWhere(function($query){
                $query->where('gp_pedidos.requiere_aprobacion_comercial','yes');
                $query->where('gp_pedidos.aprobado_comercial','yes');
            });
        })
        ->where(function($q){
            $q->where(function($query){
                $query->where('gp_pedidos.requiere_aprobacion_crediticia','no');
            })
            ->orWhere(function($query){
                $query->where('gp_pedidos.requiere_aprobacion_crediticia','yes');
                $query->where('gp_pedidos.aprobado_crediticio','yes');
            });
        })
        ->where('gp_pedido_detalle_stocks.reservado','>',0)
        ->where('gp_pedidos.estado', '!=', 'eliminado')
        ->where('gp_pedidos.estado', '!=', 'rechazado');
        if($pedido_id){
            $stockReservado = $stockReservado->where('gp_pedidos.id','!=', $pedido_id);
        }
        $stockReservado = $stockReservado->where('gp_pedido_detalles.ART_CODIGO',$articulo)
        ->where('gp_pedido_detalle_stocks.STO_ART_BODEGA',$bodega)
        ->where(function($query){
            $query->whereNull('gp_pedidos.despachado');
            $query->orWhere('gp_pedidos.despachado', 0);
        })
        ->select('gp_pedido_detalle_stocks.reservado')
        ->get()->toarray();
        $reservado = 0;
        foreach($stockReservado as $res){
            $reservado += $res['reservado'];
        }

        $trasladosreposicion = GpTrasladosReposicion::where('bodega_origen', $bodega)
            ->where('ART_CODIGO',$articulo)
            ->whereNull('gp_traslados_reposicions.completado')
            ->get()->toarray();

        foreach($trasladosreposicion as $res){
            $reservado += $res['cantidad'] - $res['trasladado'];
        }

        return $reservado;
    }

    public function checkarticulodisponible($articulo): bool
    {
        if($articulo->ART_TIPO_COMPRA != 'L')
            return true;

        $busqueda = GpPedidoDetalle::
        join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->where( function ($query){
                $query->where('gp_pedidos.estado', 'espera');
                $query->orWhere('gp_pedidos.estado', 'traslado');
                $query->orWhere('gp_pedidos.estado', 'orden_de_compra');
            })
            ->where('ART_CODIGO', $articulo->ART_CODIGO)
            ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->select(
                'gp_pedido_detalles.id',
            )->get()->first();
        if($busqueda)
            return false;

        $busqueda = GpPedidoDetalle::
        join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->where('gp_pedidos.estado', 'facturado')
            ->where('ART_CODIGO', $articulo->ART_CODIGO)
            ->where(function($query){
                $query->whereNull('gp_pedidos.despachado');
                $query->orWhere('gp_pedidos.despachado', 0);
            })
            ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->select(
                'gp_pedido_detalles.id',
            )->get()->first();
        if($busqueda)
            return false;

        if(isset($articulo->ART_CLASE_ARTICULO)){

            $datos_l = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', $articulo->ART_CLASE_ARTICULO)->first();

            $busqueda = GpPedidoDetalle::
            join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
                ->where('gp_pedidos.estado', 'facturado')
                ->where('ART_CODIGO', $articulo->ART_CODIGO)
                ->where('gp_pedidos.despachado', 1)
                ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
                ->whereDate('fecha_factura', '>', now()->subDays($datos_l->DATOS_ITEMS_L_DIA_MAXIMO)->setTime(0, 0, 0)->toDateString())
                ->select(
                    'gp_pedido_detalles.id',
                )
                ->get()->first();

            if($busqueda)
                return false;
        }else{
            return false;
        }

        $busqueda = GpTempCart::where('ART_CODIGO', $articulo->ART_CODIGO)
            ->get()->first();
        if($busqueda)
            return false;

        // SI HAY UNA DEVOLUCIÓN VOLVER A COLOCAR LOS DÍAS MÁXIMOS

        return true;
    }
    public function like_match($pattern, $subject)
    {
        $pattern = str_replace('%', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match("/^{$pattern}$/i", $subject);
    }
    public function consultastockdetalle($data, $detalle, $pedido_id = null): ?string
    {
        $consulta_bodega = array();
        foreach($detalle as &$linea){
            if($linea['ART_TIPO_COMPRA'] == 'L'){
                continue;
            }
            if(!isset($linea['stock'])){
                $stock = GpPedidoDetalleStock::where('detalle_id', $linea['id'])->get()->toArray();
                $linea['stock'] = $stock;
            }
            foreach($linea['stock'] as $articulo){
                if($articulo['seleccionado'] > 0){
                    $consulta_bodega[] = array(
                        'STOCK_ARTICULO'=>$linea['ART_CODIGO'].'@'.$articulo['STO_ART_BODEGA'],
                    );
                }
            }
        }


        if(count($consulta_bodega)>0){

            $arr_bodegas = $this->gebodegas($data, 1);
            $stock_general = $this->stockarticulobodega($consulta_bodega, $arr_bodegas);
        }
        $alert = null;

        foreach($detalle as &$articulo){

            if($articulo['ART_TIPO_COMPRA'] == 'L' || $articulo['ART_TIPO_COMPRA'] == 'SERV'){
                continue;
            }
            $stocktotal = 0;
            if(isset($stock_general[$articulo['ART_CODIGO']])){
                foreach($stock_general[$articulo['ART_CODIGO']] as $key => $st){
                    if($articulo['ART_DECIMAL_CANTIDAD'] == 0){
                        $st['STO_ART_STOCK_DISPONIBLE'] = (int)$st['STO_ART_STOCK_DISPONIBLE'];
                    }else{
                        $st['STO_ART_STOCK_DISPONIBLE'] = number_format((float)$st['STO_ART_STOCK_DISPONIBLE'], 2, '.', '');
                    }

                    $reservado = $this->stockreservado($articulo['ART_CODIGO'], $st['STO_ART_BODEGA'], $pedido_id);

                    $stock_general[$articulo['ART_CODIGO']][$key]['STO_ART_STOCK_DISPONIBLE'] -= $reservado;

                    if($stock_general[$articulo['ART_CODIGO']][$key]['STO_ART_STOCK_DISPONIBLE'] < 0)
                        $stock_general[$articulo['ART_CODIGO']][$key]['STO_ART_STOCK_DISPONIBLE'] = 0;

                    $stocktotal += $stock_general[$articulo['ART_CODIGO']][$key]['STO_ART_STOCK_DISPONIBLE'];
                }
                $articulo['stock'] = $stock_general[$articulo['ART_CODIGO']];

                $articulo['stock'] = array_values($articulo['stock']);

            }else{
                $articulo['stock'] = array();
            }
            //if($articulo['ART_CODIGO'] != '881-000044-I') {
                if ($stocktotal - $articulo['ART_CANTIDAD'] < 0) {
                    $alert .= 'El artículo ' . $articulo['ART_CODIGO'] . ' disminuyó su stock en este tiempo, solo existen ' . $stocktotal . ' disponibles<br>';
                }
            //}
        }

        return $alert;
    }

    public function formatStock($articulo, $stock_general){
        $stocktotal = 0;
        $articulo->disponible = $this->checkarticulodisponible($articulo);

//        echo '<pre>';
//        print_r($stock_general);
//        die();

        if(isset($stock_general[$articulo->ART_CODIGO])){
            foreach($stock_general[$articulo->ART_CODIGO] as $key => $st){

                if($articulo->ART_DECIMAL_CANTIDAD == 0){
                    $st['STO_ART_STOCK_DISPONIBLE'] = (int)$st['STO_ART_STOCK_DISPONIBLE'];
                }else{
                    $st['STO_ART_STOCK_DISPONIBLE'] = number_format((float)$st['STO_ART_STOCK_DISPONIBLE'], 2, '.', '');
                }

                $reservado = $this->stockreservado($articulo->ART_CODIGO, $st['STO_ART_BODEGA']);
                $stock_general[$articulo->ART_CODIGO][$key]['STO_ART_STOCK_DISPONIBLE'] -= $reservado;

                if($stock_general[$articulo->ART_CODIGO][$key]['STO_ART_STOCK_DISPONIBLE'] < 0)
                    $stock_general[$articulo->ART_CODIGO][$key]['STO_ART_STOCK_DISPONIBLE'] = 0;

                $stocktotal += $stock_general[$articulo->ART_CODIGO][$key]['STO_ART_STOCK_DISPONIBLE'];
            }
            $articulo->stock = $stock_general[$articulo->ART_CODIGO];
        }else{
            $articulo->stock = array();
        }

        $articulo->ART_STOCK = $stocktotal;
    }

    public function buscareledisponible($articulos_array_l, $clase = 'VTA-BNS-L'){
        $articulos = GpArticulo::where('ART_TIPO_COMPRA', 'L')
            ->where('ART_CLASE_ARTICULO', $clase)
            ->inRandomOrder()
            ->limit(300)->get();
        foreach($articulos as $articulo){
            foreach($articulos_array_l as $artL){
                //print_r($articulos_array_l);
                if($articulo->ART_CODIGO == $artL){
                    continue 2;
                }
            }
            $disponible = $this->checkarticulodisponible($articulo);
            if($disponible){
                return $articulo->ART_CODIGO;
            }
        }
    }

    public function dateDiffInDays($date1, $date2): float|int
    {
        // Calculating the difference in timestamps
        $diff = strtotime($date2) - strtotime($date1);

        // 1 day = 24 hours
        // 24 * 60 * 60 = 86400 seconds
        return abs(round($diff / 86400));
    }

    public function getitemproveedores($data, $item, $pendientes = false, $existentes = false): array
    {
        $porcentajeMargen = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE','VTA-BNS-L')->get()->first();
        $proveedores = GpPedidoDetalleProveedores::where('pedido_id', $data['id'])
            ->join('gp_proveedors', 'gp_proveedors.id', 'gp_pedido_detalle_proveedores.ID_PROVEEDOR')
            ->where('detalle_id', $item->id)
            ->select('gp_pedido_detalle_proveedores.*', 'gp_proveedors.PROV_RAZON_SOCIAL')
            ->get();
        if($item->ART_DECIMAL_CANTIDAD == 0){
            $item->ART_STOCK = (int)$item->ART_STOCK;
            $item->ART_CANTIDAD = (int)$item->ART_CANTIDAD;
        }
        foreach($proveedores as $proveedor){

            if(!$proveedor->ingresado)
                $pendientes = true;
            else
                $existentes = true;

            $orden_detalle = GpOrdenDeCompraGeneradoDetalle::join('gp_orden_de_compra_generados','gp_orden_de_compra_generados.id','gp_orden_de_compra_generado_detalles.orden_generada_id')
                ->where('gp_orden_de_compra_generado_detalles.detalle_proveedores_id', $proveedor->id)
                ->select('gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO', 'gp_orden_de_compra_generados.ORDEN_COMPRA_DOCUMENTO')
                ->get();

            $margen = (($proveedor['COSTO_UNITARIO'] / (1 - ($proveedor['MARGEN'] / 100))) / (1 - $item['descuento'] / 100));

            $proveedor->margen = number_format($margen,4, '.', '');
            if($item->ART_DECIMAL_CANTIDAD == 0){
                $proveedor->CANTIDAD = (int)$proveedor->CANTIDAD;
                $proveedor->ingresado = (int)$proveedor->ingresado;
            }
            $proveedor->orden_detalle = $orden_detalle;
        }
        return array('proveedores' => $proveedores, 'pendientes' => $pendientes, 'existentes' => $existentes);
    }

    public function recalcularpedido($pedido): void
    {

        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
        $subtotal = 0;
        $descuento = 0;
        $subtotalmenosdescuento = 0;
        $iva = 0;
        foreach($detalle as $articulo){
            $subtotal += (float)number_format($articulo->ART_VALOR * $articulo->ART_CANTIDAD,2,'.','');
            $descuento += (float)number_format($articulo->ART_DESCUENTO * $articulo->ART_CANTIDAD,2,'.','');
            $subtotalmenosdescuento += (float)number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 2, '.','');

            $articulo->ART_IMP_VTA = GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->first()->ART_IMP_VTA;
            $articulo->ART_IVA = number_format(number_format((($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD), 2, '.','') * ($articulo->ART_IMP_VTA / 100), 2, '.','');
            //(float)number_format((float)number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 4, '.','') * ($articulo->ART_IMP_VTA / 100), 2, '.','');
            $articulo->save();
            $iva += $articulo->ART_IVA;
            //(float)number_format((($articulo->ART_VALOR - ($articulo->ART_DESCUENTO)) * $articulo->ART_CANTIDAD) * ($articulo->ART_IMP_VTA / 100), 2, '.','');
        }

        $subtotalmenosdescuento = number_format($subtotalmenosdescuento, 2, '.','');
        $iva = number_format($iva, 2, '.','');
        $total =  (float)$subtotalmenosdescuento + (float)$iva;

        $pedido->subtotal = $subtotal;
        $pedido->descuento = $descuento;
        $pedido->subtotalmenosdescuento = $subtotalmenosdescuento;
        $pedido->iva = $iva;
        $pedido->total = $total;
        $pedido->save();
    }

    public function recalculariva($detalle)
    {
        foreach ($detalle as $key => $articulo) {
            $nuevo = GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->first();
            $articulo->ART_IMP_VTA = $nuevo->ART_IMP_VTA;
        }
    }
}

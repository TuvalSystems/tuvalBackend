<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FuncionesController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpCaja;
use App\Models\GpPuntoVenta;
use App\Models\Notificaciones;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\traslados\GpTrasladosVentas;

use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpVendedor;
use App\Models\GpTerritorio;
use App\Models\GpProvinciaCiudadParroquia;
use App\Models\GpGrupoCliente;
use App\Models\GpPedidoDetalle;
use App\Models\GpSubGrupoCliente;
use App\Models\GpPedidoDetalleStock;
use App\Services\ApiGpService;
use App\Traits\StockWarehouseTraits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\WebserviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ConsultasController extends Controller
{
    use StockWarehouseTraits;
    protected $apiGp;

    public function __construct(ApiGpService $apiGp)
    {
        $this->apiGp = $apiGp;
    }

    public function buscararticulo(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $busqueda = $request->validate([
            'puntodeventaselect' => '',
            'nivel_precio' => '',
            'codigo3l' => '',
            'text_codigo' => '',
            'select_codigo' => '',
            'select_nombre' => '',
            'text_nombre' => '',
            'terminal' => '',
        ]);
        if (isset($busqueda['puntodeventaselect']) && $busqueda['puntodeventaselect'] != '') {

            $funciones = new FuncionesController();
            $clave = false;

            if (
                strtolower($busqueda['codigo3l']) == 'item l' || strtolower($busqueda['codigo3l']) == 'items l' ||
                strtolower($busqueda['text_codigo']) == 'item l' || strtolower($busqueda['text_codigo']) == 'items l' ||
                strtolower($busqueda['text_nombre']) == 'item l' || strtolower($busqueda['text_nombre']) == 'items l' ||
                strtolower($busqueda['terminal']) == 'item l' || strtolower($busqueda['terminal']) == 'items l' ||
                strtolower($busqueda['terminal']) == 'l'
            ) {
                $articulosL = GpArticulo::busquedaitemsl($busqueda);
                $articulos = array();
                foreach ($articulosL as $key => $articulo) {
                    $articulo->disponible = $funciones->checkarticulodisponible($articulo);
                    if (!$articulo->disponible) {
                        unset($articulosL[$key]);
                        continue;
                    }
                    $articulos[] = $articulo;

                    if (count($articulos) == 10)
                        break;
                }

                $clave = true;
            } else {
                $articulos = GpArticulo::busqueda($busqueda);
            }




            if (!count($articulos)) {
                return response([
                    'error' => 'No existen resultados'
                ], 401);
            }



            $consulta_precio = array();
            foreach ($articulos as $articulo) {

                if ($articulo->ART_CLASE_ARTICULO != 'VTA-SERV-L' && $articulo->ART_CLASE_ARTICULO != 'VTA-BNS-L') {
                    $consulta_precio[] = array(
                        'ART_CODIGO' => $articulo->ART_CODIGO,
                    );
                }
            }

            if (!$clave)
                $articulos = $articulos->values();

            $webservices = new WebserviceController;
            $precios = $webservices->listaprecios($consulta_precio);
            $lista_precios_general = array();
            foreach ($precios as $precio) {
                $lista_precios_general[$precio['LST_PRC_ARTICULO']][$precio['LST_PRC_ID']] = $precio;
            }

            foreach ($articulos as $key => $articulo) {

                if (!$clave) {
                    $articulo->disponible = $funciones->checkarticulodisponible($articulo);
                    if (!$articulo->disponible) {
                        unset($articulos[$key]);
                        continue;
                    }
                }


                if ($articulo->ART_CLASE_ARTICULO == 'VTA-SERV-L' || $articulo->ART_CLASE_ARTICULO == 'VTA-BNS-L') {
                    $articulo->ART_TIPO_COMPRA = 'L';
                }
                if (isset($busqueda['nivel_precio'])) {

                    if (isset($lista_precios_general[$articulo->ART_CODIGO])) {
                        $articulo->ART_VALOR = (float)number_format($lista_precios_general[$articulo->ART_CODIGO][$busqueda['nivel_precio']]['LST_PRC_VALOR'], 4, '.', '');
                        $articulo->ART_VALOR_ORIGINAL = (float)number_format($lista_precios_general[$articulo->ART_CODIGO][$busqueda['nivel_precio']]['LST_PRC_VALOR'], 4, '.', '');
                    } else {
                        $articulo->ART_VALOR = 0;
                        $articulo->ART_VALOR_ORIGINAL = 0;
                    }
                    $articulo->ART_DESCRIPCION_ORIGINAL = $articulo->ART_DESCRIPCION;
                } else {
                    unset($lista_precios_general[$articulo->ART_CODIGO]['LST_PRC_ARTICULO']);
                    unset($lista_precios_general[$articulo->ART_CODIGO]['LST_PRC_AUTENTIFICACION']);
                    unset($lista_precios_general[$articulo->ART_CODIGO]['LST_PRC_MONEDA']);

                    if (isset($lista_precios_general[$articulo->ART_CODIGO])) {
                        $articulo->precios = $lista_precios_general[$articulo->ART_CODIGO];
                    } else {
                        $articulo->precios = array();
                    }
                }

                $articulo->proveedores = array();
                $articulo->descuento = 0;
                $articulo->descuento_original = 0;
                $articulo->modificador = 0;
                $imagenes = array();

                for ($i = 1; $i <= 3; $i++) {
                    $file = 'images/productos/' . $articulo->ART_CODIGO_TRES_LETRAS . '_' . $i . '.jpg';

                    if (File::exists($file)) {
                        $imagenes[] = env('APP_URL') . '/' . $file;
                    }
                }

                $articulo->imagenes = $imagenes;
            }
            if (!$clave)
                $articulos = $articulos->values();

            return response([
                'success' => true,
                'articulos' => $articulos
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al buscar'
            ], 401);
        }
    }

    // public function consultarstock(Request $request)
    // {
    //     $data = $request->all();

    //     $puntodeventaselect = $data['puntodeventa_id'];

    //     $funciones = new FuncionesController();
    //     $data_bodega = array('PTO_VTA_ID' => $puntodeventaselect);
    //     $arr_bodegas = $funciones->gebodegas($data_bodega, $data['cliente_id']);

    //     $consulta_bodega = array();

    //     foreach ($arr_bodegas as $bodega) {
    //         $consulta_bodega[] = array(
    //             'STOCK_ARTICULO' => $data['articulo']['ART_CODIGO'] . '@' . $bodega,
    //         );
    //     }

    //     $stock_general = $funciones->stockarticulobodega($consulta_bodega, $arr_bodegas);

    //     $articulo = (object)$data['articulo'];

    //     $funciones->formatStock($articulo, $stock_general);

    //     $stock = array_values($articulo->stock);
    //     $total = 0;
    //     foreach ($stock as $st) {
    //         $total += $st['STO_ART_STOCK_DISPONIBLE'];
    //     }
    //     return response([
    //         'success' => true,
    //         'stock' => $stock,
    //         'ART_STOCK' => $total,
    //     ]);
    // }

    public function consultarstock(Request $request)
    {
        $data = $request->all();
        $articulo = $data['articulo'];
        $warehouses = $this->getWarehouseNotConsult($data);
        if (empty($warehouses)) {
            return [];
        }

        $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
        $stockResults = array_map(fn($params) => $this->apiGp->getStockWarehouse($params), $stockRequests);
        $formatStock = $this->formatResultStocks($stockResults);

        $art =$this->newFormatStock($formatStock, $articulo); //nose porque hace pero va

        $total = array_sum(array_column($formatStock, 'STO_ART_STOCK_DISPONIBLE'));
        return response([
            'success' => true,
            'stock' => $art['stock'],
            'ART_STOCK' => $total,
        ]);
    }

    public function consultaStockOthers(Request $request)
    {
        $data = $request->all();
        $articulo = $data['articulo'];
        $warehouses = $this->getWarehouse($data);
        if (empty($warehouses)) {
            return [];
        }

        $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
        $stockResults = array_map(fn($params) => $this->apiGp->getStockWarehouse($params), $stockRequests);
        $formatStock = $this->formatResultStocks($stockResults);

        $art =$this->newFormatStock($formatStock, $articulo); //nose porque hace pero va

        $total = array_sum(array_column($formatStock, 'STO_ART_STOCK_DISPONIBLE'));
        return response([
            'success' => true,
            'stock' => $art['stock'],
            'ART_STOCK' => $total,
        ]);
    }









    public function consultastockpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $funciones = new FuncionesController();

        $alert = $funciones->consultastockdetalle($data, $data['cotizacion']);

        if ($alert) {
            return response([
                'success' => false,
                'message' => $alert,
                'cotizacion' => $data['cotizacion'],
            ]);
        }

        //        return response([
        //            'success' => false,
        //            'message' => 'Sin motivo',
        //            'cotizacion' => $data['cotizacion'],
        //        ]);
        return response([
            'success' => true,
            'message' => 'si hay stock'
        ]);
    }

    public function consultastockarticulopuntodeventa($articulo, $puntodeventa): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
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

    public function buscarcliente(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'busqueda' => '',
            'tipo' => '',
        ]);
        $busqueda = $data['busqueda'];
        if (isset($busqueda) && $busqueda != '') {
            $buscar = '%' . str_replace(' ', '%', $busqueda) . '%';



            $clientes = GpCliente::where('CLI_RUC', '!=', '9999999999999')
                ->where('CLI_RUC', 'LIKE', $buscar)
                ->orWhere('CLI_RAZON_SOCIAL', 'LIKE', $buscar)
                ->orWhere('CLI_NOMBRE_CORTO', 'LIKE', $buscar)
                ->orWhere('CLI_CORREO_ELECT_SRI', 'LIKE', $buscar)
                ->limit(100)
                ->get();

            foreach ($clientes as $key => $cliente) {

                if (isset($data['tipo']) && $data['tipo'] == 'cobranza') {
                } else {

                    if ($cliente->CLI_SUSPENDIDO == 'SI') {
                        unset($clientes[$key]);
                        continue;
                    }
                }

                $vendedor = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
                $cliente->vendedor = $vendedor;

                $sucursales = GpClienteSucursal::join('gp_vendedors', 'gp_vendedors.VEND_ID', 'gp_cliente_sucursals.CLI_SUC_VENDEDOR')
                    ->where('CLI_SUC_RUC', $cliente->CLI_RUC)
                    ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS')
                    ->orderby('CLI_SUC_ID_DIRECCION', 'DESC')
                    ->get();

                $cliente->sucursales = $sucursales;
                $cliente->sucursalesfacturacion = $sucursales;
            }

            $clientes = $clientes->toArray();
            $clientes = array_values($clientes);

            return response([
                'success' => true,
                'clientes' => $clientes
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al buscar'
            ], 401);
        }
    }

    public function cupodisponible($ruc)
    {

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $ruc,
        );

        $cliente = GpCliente::where('CLI_RUC', $ruc)->first();
        $webservices = new WebserviceController();
        $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);

        $suspendido = $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['SUSPENDIDO'] ?? 0;
        $cliente->suspendido = $suspendido;
        $cliente->cupo_disponible = number_format($cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_DISPONIBLE_GP'], 2, '.', ',');
        $cliente->cupo_asignado = number_format($cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_ASIGNADO'], 2, '.', ',');
        return response([
            'success' => true,
            'cliente' => $cliente
        ]);
    }

    public function territorios(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $territorios = GpTerritorio::get()->toArray();

        $provincia_ciudad = GpProvinciaCiudadParroquia::orderby('provincia', 'ASC')->get()->toArray();
        $provincia_arr = array();
        foreach ($provincia_ciudad as $pc) {
            $provincia_arr[$pc['provincia']]['provincia'] = $pc['provincia'];
            $provincia_arr[$pc['provincia']]['ciudades'][$pc['ciudad']] = $pc['ciudad'];
        }
        $provincia_arr = array_values($provincia_arr);

        foreach ($provincia_arr as $key => $provincia) {
            $provincia_arr[$key]['ciudades'] = array_values($provincia['ciudades']);
        }

        $grupos = GpGrupoCliente::orderby('GRUPO_CODIGO', 'ASC')->get()->toArray();
        $subgrupos = GpSubGrupoCliente::orderby('GRUPO_CODIGO', 'ASC')->get()->toArray();
        $grupos_arr = array();
        foreach ($grupos as $grupo) {
            $grupos_arr[$grupo['GRUPO_CODIGO']]['GRUPO_CODIGO'] = $grupo['GRUPO_CODIGO'];
            $grupos_arr[$grupo['GRUPO_CODIGO']]['GRUPO_DESCRIPCION'] = $grupo['GRUPO_DESCRIPCION'];
            foreach ($subgrupos as $subgrupo) {
                if ($subgrupo['GRUPO_CODIGO'] == $grupo['GRUPO_CODIGO'])
                    $grupos_arr[$grupo['GRUPO_CODIGO']]['subgrupos'][] = $subgrupo;
            }
        }

        return response([
            'success' => true,
            'territorios' => $territorios,
            'provincias_ciudades' => $provincia_arr,
            'grupos_subgrupos' => array_values($grupos_arr),
        ]);
    }

    public function puntodeventacaja(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
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

    public function reservados(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $articulos = GpPedidoDetalleStock::join('gp_pedido_detalles', 'gp_pedido_detalles.id', 'gp_pedido_detalle_stocks.detalle_id')
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalle_stocks.pedido_id')
            ->whereNotNull('gp_pedido_detalle_stocks.reservado')
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->where('gp_pedidos.requiere_aprobacion_comercial', 'no');
                })
                    ->orWhere(function ($query) {
                        $query->where('gp_pedidos.requiere_aprobacion_comercial', 'yes');
                        $query->where('gp_pedidos.aprobado_comercial', 'yes');
                    });
            })
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->where('gp_pedidos.requiere_aprobacion_crediticia', 'no');
                })
                    ->orWhere(function ($query) {
                        $query->where('gp_pedidos.requiere_aprobacion_crediticia', 'yes');
                        $query->where('gp_pedidos.aprobado_crediticio', 'yes');
                    });
            })
            ->where('gp_pedido_detalle_stocks.reservado', '>', 0)
            ->where('gp_pedidos.estado', '!=', 'eliminado')
            ->where('gp_pedidos.estado', '!=', 'rechazado')
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado');
                $query->orWhere('gp_pedidos.despachado', 0);
            })
            ->select(
                'gp_pedido_detalles.ART_CODIGO as codigo',
                'gp_pedido_detalle_stocks.STO_ART_BODEGA as bodega',
                'gp_pedido_detalle_stocks.reservado'
            )
            ->get();

        $arreglo = array();

        foreach ($articulos as $articulo) {
            $articulo->tipo = 'pedido';
            unset($articulo->seleccionado);
        }

        foreach ($articulos as $articulo) {
            $arreglo[] = $articulo;
        }

        $trasladosreposicion = GpTrasladosReposicion::join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_traslados_reposicions.ART_CODIGO')
            ->whereNull('gp_traslados_reposicions.completado')
            ->select(
                'gp_articulos.ART_CODIGO as codigo',
                'gp_traslados_reposicions.cantidad',
                'gp_traslados_reposicions.trasladado',
                'gp_traslados_reposicions.bodega_origen as bodega'
            )
            ->get();

        foreach ($trasladosreposicion as $traslado) {
            $traslado->reservado = $traslado->cantidad - $traslado->trasladado;
            unset($traslado->cantidad);
            unset($traslado->trasladado);

            $traslado->tipo = 'traslado';
            $arreglo[] = $traslado;
        }

        return response([
            'reservados' => $arreglo,
        ]);
    }

    public function transferenciaspendientes(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $pedidos = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
            ->where(function ($query) {
                $query->where('gp_pedidos.estado', 'traslado');
                $query->orWhere('gp_pedidos.estado', 'orden_de_compra');
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

    public function reservasweb($item = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $articulos = GpPedidoDetalleStock::join('gp_pedido_detalles', 'gp_pedido_detalles.id', 'gp_pedido_detalle_stocks.detalle_id')
            ->join('gp_pedidos', 'gp_pedidos.id', 'gp_pedido_detalles.pedido_id')
            ->join('gp_clientes', 'gp_clientes.id', 'gp_pedidos.cliente_id')
            ->whereNotNull('gp_pedido_detalle_stocks.reservado')
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->where('gp_pedidos.requiere_aprobacion_comercial', 'no');
                })
                    ->orWhere(function ($query) {
                        $query->where('gp_pedidos.requiere_aprobacion_comercial', 'yes');
                        $query->where('gp_pedidos.aprobado_comercial', 'yes');
                    });
            })
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->where('gp_pedidos.requiere_aprobacion_crediticia', 'no');
                })
                    ->orWhere(function ($query) {
                        $query->where('gp_pedidos.requiere_aprobacion_crediticia', 'yes');
                        $query->where('gp_pedidos.aprobado_crediticio', 'yes');
                    });
            })
            ->where('gp_pedidos.estado', '!=', 'eliminado')
            ->where('gp_pedidos.estado', '!=', 'rechazado');
        if ($item) {
            $articulos = $articulos->where('gp_pedido_detalles.ART_CODIGO', $item);
        }
        $articulos = $articulos->where('gp_pedido_detalle_stocks.reservado', '>', 0)
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado');
                $query->orWhere('gp_pedidos.despachado', 0);
            })
            ->select(
                'gp_pedido_detalles.ART_CODIGO as codigo',
                'gp_pedido_detalles.ART_DESCRIPCION as descripcion',
                'gp_pedido_detalle_stocks.STO_ART_BODEGA as bodega',
                'gp_pedido_detalle_stocks.reservado',
                'gp_pedido_detalle_stocks.seleccionado',
                'gp_pedidos.pedido',
                'gp_pedidos.created_at as fecha',
                'gp_pedidos.CLI_RUC',
                'gp_clientes.CLI_RAZON_SOCIAL',
                'gp_pedido_detalles.ART_CANTIDAD as cantidad_pedida'
            )
            ->get();

        $arreglo = array();

        foreach ($articulos as $articulo) {
            $articulo->tipo = 'pedido';
            $articulo->despachado = $articulo->seleccionado - $articulo->reservado;
            unset($articulo->seleccionado);
        }

        foreach ($articulos as $articulo) {
            $arreglo[] = $articulo;
        }

        $trasladosreposicion = GpTrasladosReposicion::join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_traslados_reposicions.ART_CODIGO')
            ->whereNull('gp_traslados_reposicions.completado');
        if ($item) {
            $trasladosreposicion = $trasladosreposicion->where('gp_traslados_reposicions.ART_CODIGO', $item);
        }
        $trasladosreposicion = $trasladosreposicion
            ->select(
                'gp_articulos.ART_CODIGO as codigo',
                'gp_articulos.ART_DESCRIPCION as descripcion',
                'gp_traslados_reposicions.bodega_origen as bodega',
                'gp_traslados_reposicions.cantidad',
                'gp_traslados_reposicions.trasladado',
                'gp_traslados_reposicions.Documento as pedido',
                'gp_traslados_reposicions.created_at as fecha',
                'gp_traslados_reposicions.cantidad as cantidad_pedida'
            )
            ->get();

        foreach ($trasladosreposicion as $traslado) {
            $traslado->reservado = $traslado->cantidad - $traslado->trasladado;
            $traslado->despachado = $traslado->trasladado;
            unset($traslado->cantidad);
            unset($traslado->trasladado);

            $traslado->CLI_RUC = 'TUVAL';
            $traslado->CLI_RAZON_SOCIAL = 'TUVAL';
            $traslado->tipo = 'traslado';
            $arreglo[] = $traslado;
        }

        return response([
            'reservados' => $arreglo,
        ]);
    }

    public function transferencias_pendientes_ingreso(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
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
    public function items_transferencia_venta($item = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        //        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', 006)->first();
        //        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosventas = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
            ->join('gp_bodegas', 'gp_traslados_ventas.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            //            ->where('bodega_origen', $bodega)
            ->whereNull('completado')
            ->where('gp_pedidos.estado', '!=', 'eliminado');
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

    public function items_transferencia_reposicion($item = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        //        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', 006)->first();
        //        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosreposicion = GpTrasladosReposicion::join('gp_bodegas', 'gp_traslados_reposicions.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            //            ->where('bodega_origen', $bodega)
            ->whereNull('completado')
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

    public function reposiciones_pendientes(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
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

    public function pendientes_despachar(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
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

    public function notificaciones(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();
        $notificaciones = Notificaciones::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
        return response([
            'notificaciones' => $notificaciones,
        ]);
    }
}

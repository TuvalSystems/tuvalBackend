<?php

namespace App\Http\Controllers\Compartido;

use App\Http\Controllers\Controller;

use App\Enums\Ventas\OrderStatus;
use App\Services\Integraciones\Soap\LegacyGpSoapService;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Inventario\Articulos\GpDatosItemsL;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\Ventas\Carrito\GpTempCart;
use App\Models\Ventas\Vendedores\GpVendedor;

class FuncionesController extends Controller
{


    public function stockarticulobodega($consulta_bodega, $arr_bodegas = null): array
    {
        $webservices = new LegacyGpSoapService;
        $stock = $webservices->stockarticulobodega($consulta_bodega);
        $stock_general = array();

        if (isset($stock['MST_Stock_Articulo_BodegaResult']['CS_STOCK_ARTICULO_BODEGA'])) {
            foreach ($stock['MST_Stock_Articulo_BodegaResult']['CS_STOCK_ARTICULO_BODEGA'] as $st) {
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
        foreach ($stock_general as $key => $articulo) {
            foreach ($arr_bodegas as $keybod => $bodega) {
                if (isset($articulo[$keybod]))
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
        if ($consulta) {
            $masbodegas = $masbodegas->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA');
        }
        $masbodegas = $masbodegas->orderBy('orden', 'ASC');
        $masbodegas = $masbodegas->get()->toArray();



        foreach ($masbodegas as $bodega) {
            $arr_bodegas[$bodega['PTO_VTA_BOD_REL_BODEGA']] = $bodega['PTO_VTA_BOD_REL_BODEGA'];
        }

        return $arr_bodegas;
    }

    public function getcliente($data)
    {
        $cliente = GpCliente::where('id', $data['cliente_id'])->get()->first();
        $vendedor = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
        $cliente->vendedor = $vendedor;

        $sucursales = GpClienteSucursal::join('gp_vendedors', 'gp_vendedors.VEND_ID', 'gp_cliente_sucursals.CLI_SUC_VENDEDOR')
            ->where('CLI_SUC_RUC', $cliente->CLI_RUC)
            ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS')
            ->orderby('CLI_SUC_ID_DIRECCION', 'DESC')
            ->get();
        $cliente->sucursales = $sucursales;
        $cliente->sucursalesfacturacion = $sucursales;

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $cliente->CLI_RUC,
        );
        $webservices = new LegacyGpSoapService;
        $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);

        $suspendido = $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['SUSPENDIDO'] ?? 0;
        $cliente->suspendido = $suspendido;
        $cliente->cupo_disponible = number_format($cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_DISPONIBLE_GP'], 2, '.', ',');
        $cliente->cupo_asignado = number_format($cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_ASIGNADO'], 2, '.', ',');

        return $cliente;
    }


    public function dateDiffInDays($date1, $date2): float|int
    {
        // Calculating the difference in timestamps
        $diff = strtotime($date2) - strtotime($date1);

        // 1 day = 24 hours
        // 24 * 60 * 60 = 86400 seconds
        return abs(round($diff / 86400));
    }


}

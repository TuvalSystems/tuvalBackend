<?php

namespace App\Http\Controllers;

use App\Http\Controllers\WebserviceController;
use App\Models\GpCronLog;
use App\Models\GpDatosItemsL;
use App\Models\GpUsuario;
use App\Models\GpBodega;
use App\Models\GpCondicionPago;
use App\Models\GpArticulo;
use App\Models\GpArticuloSugerido;
use App\Models\GpPuntoVenta;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\GpRelacionPuntoVentaCaja;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\GpRelacionUsuarioPuntoVenta;
use App\Models\Ventas\Vendedores\GpVendedor;

use App\Models\GpProveedor;
use App\Models\GpClaseCliente;
use App\Models\GpGrupoCliente;
use App\Models\GpSubGrupoCliente;
use App\Models\GpPais;
use App\Models\GpTerritorio;
use App\Models\GpCaja;
use App\Models\GpCajaBancos;
use App\Models\GpUnidadMedida;
use App\Models\GpRelacionUsuarioCaja;

use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\Auth;


class SincronizacionController extends Controller
{
    protected $apiGp;

    public function __construct(ApiGpService $apiGp)
    {
        $this->apiGp = $apiGp;
    }

    /*
     * Sincroniza usuarios desde Dynamics GP
     */

    public function llenausuariosgp(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $obj = new WebserviceController;
        $usuarios = $obj->usuarios();
        foreach ($usuarios as $key => $data) {

            $usuario = GpUsuario::where('USERID', trim($data['USERID']))->first();
            if (!$usuario) {
                $usuario = new GpUsuario;
            }
            $usuario->USERID = trim($data['USERID']);
            $usuario->USERNAME = trim($data['USERNAME']);
            $usuario->NIVEL_AUTORIZA_CRED = (int) trim($data['NIVEL_AUTORIZA_CRED']);
            $usuario->save();
        }
        return response([
            'success' => true,
            'message' => 'Usuarios correctamente sincronizados'
        ]);
    }

    /*
     * Sincroniza bodegas desde Dynamics GP
     */

    public function llenabodegasgp()
    {
        $obj = new WebserviceController;
        $bodegas = $obj->bodegas();

        foreach ($bodegas as $data) {

            $bodega = GpBodega::where('BOD_BODEGA', trim($data['BOD_BODEGA']))->first();
            if (!$bodega) {
                $bodega = new GpBodega;
            }
            $bodega->BOD_BODEGA = trim($data['BOD_BODEGA']);
            $bodega->BOD_DESCRIPCION = trim($data['BOD_DESCRIPCION']);
            $bodega->BOD_ESTADO = trim($data['BOD_ESTADO']);
            $bodega->save();
        }

        $tipoDespachos = $obj->bodegastipodespacho();
        foreach ($tipoDespachos as $tipoDespachos) {
            $bodega = GpBodega::where('BOD_BODEGA', trim($tipoDespachos['BOD_TIPO_DESPACHO_BODEGA']))->first();
            $bodega->PERMITE_DESPACHO = $tipoDespachos['BOD_TIPO_DESPACHO_PERMITE_DESPACHO'];
            $bodega->save();
        }
        return response([
            'success' => true,
            'message' => 'Bodegas correctamente sincronizadas'
        ]);
    }

    /*
     * Sincroniza Condicion de Pago desde Dynamics GP
     */

    public function llenacondicionpagogp()
    {
        $obj = new WebserviceController;
        $condicionespago = $obj->condicionespago();

        $condicionpago = GpCondicionPago::get();
        foreach ($condicionpago as $condicion) {
            $existe = false;
            foreach ($condicionespago as $data) {
                if ($data['COND_ID'] == $condicion->COND_ID) {
                    $existe = true;
                }
            }
            if (!$existe) {
                $condicion->delete();
            }
        }

        foreach ($condicionespago as $key => $data) {

            $condicionpago = GpCondicionPago::where('COND_ID', trim($data['COND_ID']))->first();
            if (!$condicionpago) {
                $condicionpago = new GpCondicionPago;
            }
            $condicionpago->COND_DIAS = (int) trim($data['COND_DIAS']);
            $condicionpago->COND_ID = trim($data['COND_ID']);
            $condicionpago->COND_TIPO_DESCUENTO = trim($data['COND_TIPO_DESCUENTO']);
            $condicionpago->COND_VALOR = (float) trim($data['COND_VALOR']);
            $condicionpago->save();
        }
        return response([
            'success' => true,
            'message' => 'Condiciones de pago correctamente sincronizadas'
        ]);
    }

    /*
     * Sincroniza Condicion de Pago desde Dynamics GP
     */

    public function llenaarticulosgp(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $metodo = 'MST_Articulo';
        $fecha = '';
        $obj = new WebserviceController;

        $cronLog = GpCronLog::where('metodo', $metodo)->orderBy('fecha', 'DESC')->first();
        if ($cronLog) {
            $fecha = $cronLog->fecha;
        }

        $parameters = [
            'FechaModificacion' => $fecha
        ];

        $articulos = $obj->maestros($metodo, $parameters);

        if (!isset($articulos['MST_ArticuloResult']['CS_ARTICULO'])) {
            return response([
                'success' => true,
                'message' => 'Ningun artículo por actualizar',
            ]);
        }

        $articulos = $articulos['MST_ArticuloResult']['CS_ARTICULO'];

        foreach ($articulos as $key => $data) {

            $articulo = GpArticulo::where('ART_CODIGO', trim($data['ART_CODIGO']))->first();
            if (!$articulo) {
                $articulo = new GpArticulo;
            }
            $articulo->ART_CODIGO = trim($data['ART_CODIGO']);
            $articulo->ART_DESCRIPCION = trim($data['ART_DESCRIPCION']);
            $articulo->ART_CODIGO_ZAFIRO = trim($data['ART_CODIGO_ZAFIRO']);
            $articulo->ART_CODIGO_TRES_LETRAS = trim($data['ART_CODIGO_TRES_LETRAS']);
            $articulo->ART_CLASE_ARTICULO = trim($data['ART_CLASE_ARTICULO']);
            $articulo->ART_METODO_VALUACION = trim($data['ART_METODO_VALUACION']);
            $articulo->ART_TIPO_PRODUCTO = trim($data['ART_TIPO_PRODUCTO']);
            $articulo->ART_TIPO_PRODUCTO = trim($data['ART_TIPO_PRODUCTO']);
            $articulo->ART_FECHA_MODIFICACION = trim($data['ART_FECHA_MODIFICACION']['date']);
            $articulo->ART_FECHA_CREACION = trim($data['ART_FECHA_CREACION']['date']);
            $articulo->ART_ESTADO = trim($data['ART_ESTADO']);
            $articulo->ART_IMP_VTA = (float) trim($data['ART_IMP_VTA']);
            $articulo->ART_DECIMAL_CANTIDAD = (int) trim($data['ART_DECIMAL_CANTIDAD']);
            $articulo->ART_DECIMAL_MONEDA = trim($data['ART_DECIMAL_MONEDA']);
            $articulo->ART_COSTO_PROMEDIO = (float) trim($data['ART_COSTO_PROMEDIO']);
            $articulo->ART_PLAN_UMEDIDA = trim($data['ART_PLAN_UMEDIDA']);
            $articulo->ART_IMP_COMPRA = (float) trim($data['ART_IMP_COMPRA']);
            $articulo->ART_SUSTITUTO_1 = trim($data['ART_SUSTITUTO_1']);
            $articulo->ART_SUSTITUTO_2 = trim($data['ART_SUSTITUTO_2']);
            $articulo->ART_MARCA = trim($data['ART_MARCA']);
            $articulo->ART_MATERIAL = trim($data['ART_MATERIAL']);
            $articulo->ART_REFERENCIA_GENERAL = trim($data['ART_REFERENCIA_GENERAL']);
            $articulo->ART_REFERENCIA_DETALLADA = trim($data['ART_REFERENCIA_DETALLADA']);
            $articulo->ART_TIPO_COMPRA = trim($data['ART_TIPO_COMPRA']);
            $articulo->ART_CODIGO_MARCA_MATERIAL = trim($data['ART_CODIGO']) . ' ' . trim($data['ART_MARCA']) . ' ' . trim($data['ART_MATERIAL']);
            $articulo->ART_DESCRIPCION_MARCA_MATERIAL = trim($data['ART_DESCRIPCION']) . ' ' . trim($data['ART_MARCA']) . ' ' . trim($data['ART_MATERIAL']);
            $articulo->ART_PESO = (float) trim($data['ART_PESO']);
            $articulo->ART_TAMANO = (float) trim($data['ART_TAMANO']);
            $articulo->combinado = trim($data['ART_CODIGO']) . ' ' . trim($data['ART_DESCRIPCION']) . ' ' . trim($data['ART_CODIGO_TRES_LETRAS']) . ' ' . trim($data['ART_CODIGO']) . ' ' . trim($data['ART_MARCA']) . ' ' . trim($data['ART_MATERIAL']) . ' ' . trim($data['ART_DESCRIPCION']) . ' ' . trim($data['ART_MARCA']) . ' ' . trim($data['ART_MATERIAL']);
            $articulo->save();

            if (isset($data['ART_SUGERIDO']['CS_ARTICULO_ADI']) && count($data['ART_SUGERIDO']['CS_ARTICULO_ADI']) > 0) {
                $sugeridos = $data['ART_SUGERIDO']['CS_ARTICULO_ADI'];
                GpArticuloSugerido::where('ART_CODIGO', trim($data['ART_CODIGO']))->delete();
                foreach ($sugeridos as $keyS => $dataS) {
                    $sugerido = new GpArticuloSugerido;
                    $sugerido->ART_CODIGO = trim($data['ART_CODIGO']);
                    $sugerido->ART_ADI_CANTIDAD = (int) trim($dataS['ART_ADI_CANTIDAD']);
                    $sugerido->ART_ADI_SUGERIDO = trim($dataS['ART_ADI_SUGERIDO']);
                    $sugerido->save();
                }
            }
        }

        $cronLog = new GpCronLog();
        $cronLog->metodo = $metodo;
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();

        return response([
            'success' => true,
            'message' => 'Artículos correctamente sincronizadas',
            'total' => count($articulos),
        ]);
    }


    public function llenapuntosdeventagp()
    {
        $obj = new WebserviceController;
        $puntosdeventa = $obj->puntodeventa();

        foreach ($puntosdeventa as $key => $data) {

            $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', trim($data['PTO_VTA_ID']))->first();
            if (!$puntodeventa) {
                $puntodeventa = new GpPuntoVenta;
            }
            $puntodeventa->PTO_VTA_ID = trim($data['PTO_VTA_ID']);
            $puntodeventa->PTO_VTA_DESCRIPCION = trim($data['PTO_VTA_DESCRIPCION']);
            $puntodeventa->PTO_VTA_DIRECCION_1 = trim($data['PTO_VTA_DIRECCION_1']);
            $puntodeventa->PTO_VTA_DIRECCION_2 = trim($data['PTO_VTA_DIRECCION_2']);
            $puntodeventa->PTO_VTA_CIUDAD = trim($data['PTO_VTA_CIUDAD']);
            $puntodeventa->PTO_VTA_PROVINCIA = trim($data['PTO_VTA_PROVINCIA']);
            $puntodeventa->PTO_VTA_PAIS = trim($data['PTO_VTA_PAIS']);
            $puntodeventa->PTO_VTA_ESTADO = trim($data['PTO_VTA_ESTADO']);
            $puntodeventa->PTO_VTA_FONO_1 = trim($data['PTO_VTA_FONO_1']);
            if (isset($data['PTO_VTA_FONO_2']))
                $puntodeventa->PTO_VTA_FONO_2 = trim($data['PTO_VTA_FONO_2']);
            $puntodeventa->PTO_VTA_FONO_4 = trim($data['PTO_VTA_FONO_4']);
            $puntodeventa->PTO_VTA_DIAS_DEVOL_SIN_AUT = (int) trim($data['PTO_VTA_DIAS_DEVOL_SIN_AUT']);
            $puntodeventa->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO = (int) trim($data['PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO']);
            $puntodeventa->PTO_VTA_DIA_VALIDEZ_COTIZACION = (int) trim($data['PTO_VTA_DIA_VALIDEZ_COTIZACION']);

            $puntodeventa->save();
        }
        return;
    }

    public function llenacajasgp()
    {
        $obj = new WebserviceController;
        $cajas = $obj->caja();

        foreach ($cajas as $key => $data) {

            $caja = GpCaja::where('CAJA_CODIGO', trim($data['CAJA_CODIGO']))
                ->first();
            if (!$caja) {
                $caja = new GpCaja;
            }
            $caja->CAJA_CODIGO = trim($data['CAJA_CODIGO']);
            $caja->CAJA_DESCRIPCION = trim($data['CAJA_DESCRIPCION']);
            $caja->save();
        }
        return;
    }

    public function llenaunidadmedidasgp()
    {
        $obj = new WebserviceController;
        $unidadmedidas = $obj->unidadmedida();

        foreach ($unidadmedidas as $key => $data) {

            $unidadmedida = GpUnidadMedida::where('UMEDIDA_PLAN', trim($data['UMEDIDA_PLAN']))
                ->first();
            if (!$unidadmedida) {
                $unidadmedida = new GpUnidadMedida;
            }
            $unidadmedida->UMEDIDA_PLAN = trim($data['UMEDIDA_PLAN']);
            $unidadmedida->UMEDIDA_CANTIDAD_DECIMAL = (int) trim($data['UMEDIDA_CANTIDAD_DECIMAL']);
            $unidadmedida->UMEDIDA_BASE = trim($data['UMEDIDA_BASE']);
            $unidadmedida->save();
        }
        return;
    }

    public function llenapuntodeventaycajasgp(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $this->llenapuntosdeventagp();
        $this->llenacajasgp();
        $this->llenacajabancos();
        $this->llenaunidadmedidasgp();
        $this->llenaudatositemsl();
        return response([
            'success' => true,
            'message' => 'Puntos de venta, cajas y unidad de medida correctamente sincronizados'
        ]);
    }

    public function llenaudatositemsl()
    {
        $obj = new WebserviceController;
        $datositemsl = $obj->datositemsl();

        foreach ($datositemsl as $key => $data) {

            $datosl = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', trim($data['DATOS_ITEMS_L_CLASE']))
                ->first();
            if (!$datosl) {
                $datosl = new GpDatosItemsL;
            }
            $datosl->DATOS_ITEMS_L_CLASE = trim($data['DATOS_ITEMS_L_CLASE']);
            $datosl->DATOS_ITEMS_L_DESCRIPCION_CLASE = trim($data['DATOS_ITEMS_L_DESCRIPCION_CLASE']);
            $datosl->DATOS_ITEMS_L_DIA_MAXIMO = (int) trim($data['DATOS_ITEMS_L_DIA_MAXIMO']);
            $datosl->DATOS_ITEMS_L_MARGEN = (int) trim(str_replace('%', '', $data['DATOS_ITEMS_L_MARGEN']));
            $datosl->DATOS_ITEMS_L_TIPO = (int) trim($data['DATOS_ITEMS_L_TIPO']);
            $datosl->save();
        }
        return;
    }

    public function llenadescuentoformapagogp(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $obj = new WebserviceController;
        $descuentos = $obj->descuentoformapago();

        foreach ($descuentos as $key => $data) {

            $descuento = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', trim($data['DESC_FORMA_PAGO_CODIGO']))->first();
            if (!$descuento) {
                $descuento = new GpDescuentoFormaPago;
            }
            $descuento->DESC_FORMA_PAGO_CODIGO = trim($data['DESC_FORMA_PAGO_CODIGO']);
            $descuento->DESC_FORMA_PAGO_CONDICION_PAGO = trim($data['DESC_FORMA_PAGO_CONDICION_PAGO']);
            $descuento->DESC_FORMA_PAGO_DIAS_CREDITO = trim($data['DESC_FORMA_PAGO_DIAS_CREDITO']);
            $descuento->DESC_FORMA_PAGO_EMISOR = trim($data['DESC_FORMA_PAGO_EMISOR']);
            $descuento->DESC_FORMA_PAGO_TIPO_CREDITO = trim($data['DESC_FORMA_PAGO_TIPO_CREDITO']);
            $descuento->DESC_FORMA_PAGO_PORCENTAJE_REBAJAR = (float) trim($data['DESC_FORMA_PAGO_PORCENTAJE_REBAJAR']);
            $descuento->DESC_FORMA_PAGO_SRI = trim($data['DESC_FORMA_PAGO_SRI']);
            $descuento->DESC_FORMA_PAGO_TIPO_COBRO = trim($data['DESC_FORMA_PAGO_TIPO_COBRO']);
            $descuento->save();
        }
        return response([
            'success' => true,
            'message' => 'Descuentos por forma de pago correctamente sincronizados'
        ]);
    }

    public function llenarelacionpuntodeventabodegagp()
    {
        $obj = new WebserviceController;
        $relacionpuntodeventas = $obj->relacionpuntodeventabodega();

        foreach ($relacionpuntodeventas as $key => $data) {

            $relacionpuntodeventa = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_BODEGA', trim($data['PTO_VTA_BOD_BODEGA']))->first();
            if (!$relacionpuntodeventa) {
                $relacionpuntodeventa = new GpRelacionPuntoVentaBodega;
            }
            $relacionpuntodeventa->PTO_VTA_BOD_BODEGA = trim($data['PTO_VTA_BOD_BODEGA']);
            $relacionpuntodeventa->PTO_VTA_BOD_PUNTO_VENTA = trim($data['PTO_VTA_BOD_PUNTO_VENTA']);
            $relacionpuntodeventa->save();
        }
        return response([
            'success' => true,
            'message' => 'Relación punto de venta bodega correctamente sincronizado'
        ]);
    }

    public function llenarelacionpuntodeventabodegarelacionadagp()
    {

        $obj = new WebserviceController;
        $relacionpuntodeventabodegas = $obj->relacionpuntodeventabodegarelacionada();

        GpRelacionPuntoVentaBodegaRelacionada::truncate();
        if (isset($relacionpuntodeventabodegas['MST_Relacion_Punto_Venta_Bodega_RelacionadaResult']['CS_PUNTO_VENTA_BODEGA_RELACIONADA'])) {
            foreach ($relacionpuntodeventabodegas['MST_Relacion_Punto_Venta_Bodega_RelacionadaResult']['CS_PUNTO_VENTA_BODEGA_RELACIONADA'] as $key => $data) {
                $relacionpuntodeventabodega = new GpRelacionPuntoVentaBodegaRelacionada;
                $relacionpuntodeventabodega->PTO_VTA_BOD_REL_BODEGA = trim($data['PTO_VTA_BOD_REL_BODEGA']);
                $relacionpuntodeventabodega->PTO_VTA_BOD_REL_PUNTO_VENTA = trim($data['PTO_VTA_BOD_REL_PUNTO_VENTA']);
                $relacionpuntodeventabodega->PTO_VTA_BOD_REL_NIVEL_ACCESO = trim($data['PTO_VTA_BOD_REL_NIVEL_ACCESO']);
                $relacionpuntodeventabodega->save();
            }
        }
        return response([
            'success' => true,
            'message' => 'Relación punto de venta bodega relacionada correctamente sincronizado'
        ]);
    }

    public function llenarelacionpuntodeventacajagp()
    {
        $obj = new WebserviceController;
        $relacionpuntodeventacajas = $obj->relacionpuntodeventacaja();

        foreach ($relacionpuntodeventacajas as $key => $data) {

            $relacionpuntodeventacaja = GpRelacionPuntoVentaCaja::where('PTO_VTA_CAJA_CAJA', trim($data['PTO_VTA_CAJA_CAJA']))->first();
            if (!$relacionpuntodeventacaja) {
                $relacionpuntodeventacaja = new GpRelacionPuntoVentaCaja;
            }
            $relacionpuntodeventacaja->PTO_VTA_CAJA_CAJA = trim($data['PTO_VTA_CAJA_CAJA']);
            $relacionpuntodeventacaja->PTO_VTA_CAJA_PUNTO_VENTA = trim($data['PTO_VTA_CAJA_PUNTO_VENTA']);
            $relacionpuntodeventacaja->save();
        }
        return response([
            'success' => true,
            'message' => 'Relación punto de venta caja correctamente sincronizada'
        ]);
    }

    public function llenarelacionpuntodeventaidventagp()
    {
        $obj = new WebserviceController;
        $relacionpuntodeventaidventas = $obj->relacionpuntodeventaidventa();

        foreach ($relacionpuntodeventaidventas as $key => $data) {

            $relacionpuntodeventaidventa = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_FACTURA_NC', trim($data['PTO_VTA_ID_FACTURA_NC']))->first();
            if (!$relacionpuntodeventaidventa) {
                $relacionpuntodeventaidventa = new GpRelacionPuntoVentaIdVenta;
            }
            $relacionpuntodeventaidventa->PTO_VTA_ID_FACTURA_NC = trim($data['PTO_VTA_ID_FACTURA_NC']);
            $relacionpuntodeventaidventa->PTO_VTA_ID_PUNTO_VENTA = trim($data['PTO_VTA_ID_PUNTO_VENTA']);
            $relacionpuntodeventaidventa->save();
        }
        return response([
            'success' => true,
            'message' => 'Relación punto de venta Id Venta correctamente sincronizada'
        ]);
    }

    public function llenarelacionusuariopuntoventagp()
    {
        $obj = new WebserviceController;
        $relacionusuariopuntodeventas = $obj->relacionusuariopuntodeventa();
        $usuario = GpUsuario::get()->keyBy('id');
        $puntoVenta = GpPuntoVenta::get()->keyBy('id');
        GpRelacionUsuarioPuntoVenta::truncate();
        foreach ($relacionusuariopuntodeventas as $key => $data) {
            if ($data['USER_PTO_VTA_PUNTO_VENTA'] != '') {
                $relacionusuariopuntodeventa = new GpRelacionUsuarioPuntoVenta;
                $relacionusuariopuntodeventa->user_id = $usuario[$data['USER_PTO_VTA_USUARIO']]->id;
                $relacionusuariopuntodeventa->pv_id = $puntoVenta[$data['USER_PTO_VTA_PUNTO_VENTA']]->id;
                $relacionusuariopuntodeventa->USER_PTO_VTA_USUARIO = trim($data['USER_PTO_VTA_USUARIO']);
                $relacionusuariopuntodeventa->USER_PTO_VTA_PUNTO_VENTA = trim($data['USER_PTO_VTA_PUNTO_VENTA']);
                $relacionusuariopuntodeventa->save();
            }
        }
        return response([
            'success' => true,
            'message' => 'Relación usuario punto de venta correctamente sincronizada'
        ]);
    }

    public function llenarelacionusuariocajagp()
    {
        $obj = new WebserviceController;
        $relacionusuariocajas = $obj->relacionusuariocaja();
        GpRelacionUsuarioCaja::truncate();
        $usuariosMap = GpUsuario::pluck('id', 'USERID')->toArray();
        $cajasMap = GpCaja::pluck('id', 'CAJA_CODIGO')->toArray();
        foreach ($relacionusuariocajas as $key => $data) {
            $userCode = trim($data['CAJ_USER_USUARIO']);
            $cajaCode = trim($data['CAJ_USER_CAJA']);
            $relacionusuariocaja                    = new GpRelacionUsuarioCaja;
            $relacionusuariocaja->user_id           = $usuariosMap[$userCode] ?? null;
            $relacionusuariocaja->caja_id           = $cajasMap[$cajaCode] ?? null;
            $relacionusuariocaja->CAJ_USER_USUARIO  = $userCode;
            $relacionusuariocaja->CAJ_USER_CAJA     = $cajaCode;
            if ($relacionusuariocaja->user_id && $relacionusuariocaja->caja_id) {
                $relacionusuariocaja->save();
            }
        }
        return;
    }



    public function llenarelacionesgp(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $this->llenarelacionpuntodeventabodegagp();
        $this->llenarelacionpuntodeventabodegarelacionadagp();
        $this->llenarelacionpuntodeventacajagp();
        $this->llenarelacionpuntodeventaidventagp();
        $this->llenarelacionusuariopuntoventagp();

        $this->llenarelacionusuariocajagp();
        return response([
            'success' => true,
            'message' => 'Relaciones correctamente sincronizadas'
        ]);
    }

    public function llenavendedorgp()
    {
        $metodo = 'MST_Vendedor';
        $fecha = '';
        $obj = new WebserviceController;

        $cronLog = GpCronLog::where('metodo', $metodo)->orderBy('fecha', 'DESC')->first();
        if ($cronLog) {
            $fecha = $cronLog->fecha;
        }

        $parameters = [
            'FechaModificacion' => $fecha
        ];
        $vendedores = $obj->maestros($metodo, $parameters);

        if (!isset($vendedores['MST_VendedorResult']['CS_VENDEDOR'])) {
            return response([
                'success' => true,
                'message' => 'Ningun vendedor por actualizar',
            ]);
        }

        $vendedores = $vendedores['MST_VendedorResult']['CS_VENDEDOR'];

        foreach ($vendedores as $key => $data) {

            $vendedor = GpVendedor::where('VEND_ID', trim($data['VEND_ID']))->first();
            if (!$vendedor) {
                $vendedor = new GpVendedor;
            }
            $vendedor->VEND_ID = trim($data['VEND_ID']);
            $vendedor->VEND_NOMBRES = trim($data['VEND_NOMBRES']);
            $vendedor->VEND_APELLIDOS = trim($data['VEND_APELLIDOS']);
            $vendedor->VEND_ID_EMPLEADO = trim($data['VEND_ID_EMPLEADO']);
            $vendedor->VEND_DIRECCION = trim($data['VEND_DIRECCION']);
            $vendedor->VEND_CIUDAD = trim($data['VEND_CIUDAD']);
            $vendedor->VEND_PROVINCIA = trim($data['VEND_PROVINCIA']);
            $vendedor->VEND_TERRITORIO = trim($data['VEND_TERRITORIO']);
            $vendedor->VEND_ESTADO = trim($data['VEND_ESTADO']);
            $vendedor->VEND_CORREO_ELECTRONICO = trim($data['VEND_CORREO_ELECTRONICO']);

            $vendedor->save();
        }

        $cronLog = new GpCronLog();
        $cronLog->metodo = $metodo;
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();

        return response([
            'success' => true,
            'message' => 'Vendedores correctamente sincronizados'
        ]);
    }

    public function llenaproveedorgp(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $metodo = 'MST_Proveedor';
        $fecha = '';
        $obj = new WebserviceController;

        $cronLog = GpCronLog::where('metodo', $metodo)->orderBy('fecha', 'DESC')->first();
        if ($cronLog) {
            $fecha = $cronLog->fecha;
        }

        $parameters = [
            'FechaModificacion' => $fecha
        ];
        $proveedores = $obj->maestros($metodo, $parameters);

        if (!isset($proveedores['MST_ProveedorResult']['CS_PROVEEDOR'])) {
            return response([
                'success' => true,
                'message' => 'Ningun proveedor por actualizar',
            ]);
        }

        $proveedores = $proveedores['MST_ProveedorResult']['CS_PROVEEDOR'];

        foreach ($proveedores as $key => $data) {

            $proveedor = GpProveedor::where('PROV_RUC', trim($data['PROV_RUC']))
                ->first();
            if (!$proveedor) {
                $proveedor = new GpProveedor;
            }
            $proveedor->PROV_RUC = trim($data['PROV_RUC']);
            $proveedor->PROV_RAZON_SOCIAL = trim($data['PROV_RAZON_SOCIAL']);
            $proveedor->PROV_CLASE = trim($data['PROV_CLASE']);
            $proveedor->PROV_ID_DIRECCION = trim($data['PROV_ID_DIRECCION']);
            $proveedor->PROV_CONTACTO = trim($data['PROV_CONTACTO']);
            $proveedor->PROV_DIRECCION_1 = trim($data['PROV_DIRECCION_1']);
            $proveedor->PROV_DIRECCION_2 = trim($data['PROV_DIRECCION_2']);
            $proveedor->PROV_DIRECCION_3 = trim($data['PROV_DIRECCION_3']);
            $proveedor->PROV_CIUDAD = trim($data['PROV_CIUDAD']);
            $proveedor->PROV_PROVINCIA = trim($data['PROV_PROVINCIA']);
            $proveedor->PROV_PAIS = trim($data['PROV_PAIS']);
            $proveedor->PROV_TELEFONO_1 = trim($data['PROV_TELEFONO_1']);
            $proveedor->PROV_TELEFONO_2 = trim($data['PROV_TELEFONO_2']);
            $proveedor->PROV_TELEFONO_3 = trim($data['PROV_TELEFONO_3']);
            $proveedor->PROV_TELEFONO_4 = trim($data['PROV_TELEFONO_4']);
            $proveedor->PROV_CONDICION_PAGO = trim($data['PROV_CONDICION_PAGO']);
            $proveedor->PROV_DSTO_COMERCIAL = (float) trim($data['PROV_DSTO_COMERCIAL']);
            $proveedor->PROV_FECHA_MODIFICACION = trim($data['PROV_FECHA_MODIFICACION']['date']);
            $proveedor->PROV_FECHA_CREACION = trim($data['PROV_FECHA_CREACION']['date']);
            $proveedor->PROV_CORREO_ELECT = trim($data['PROV_CORREO_ELECT']);
            $proveedor->PROV_IMPUESTO = (float) trim($data['PROV_IMPUESTO']);
            $proveedor->PROV_ESTADO = trim($data['PROV_ESTADO']);
            $proveedor->save();
        }

        $cronLog = new GpCronLog();
        $cronLog->metodo = $metodo;
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();

        return response([
            'success' => true,
            'message' => 'Proveedores correctamente sincronizados'
        ]);
    }

    public function llenaclaseclientegp()
    {
        $obj = new WebserviceController;
        $claseclientes = $obj->clasecliente();

        foreach ($claseclientes as $key => $data) {

            $clasecliente = GpClaseCliente::where('CLASS_CODIGO', trim($data['CLASS_CODIGO']))
                ->first();
            if (!$clasecliente) {
                $clasecliente = new GpClaseCliente;
            }
            $clasecliente->CLASS_CODIGO = trim($data['CLASS_CODIGO']);
            $clasecliente->CLASS_DESCRIPCION = trim($data['CLASS_DESCRIPCION']);
            $clasecliente->save();
        }
        return response([
            'success' => true,
            'message' => 'Clases de clientes correctamente sincronizados'
        ]);
    }

    public function llenagrupoclientegp()
    {
        $obj = new WebserviceController;
        $grupoclientes = $obj->grupocliente();

        foreach ($grupoclientes as $key => $data) {

            $grupocliente = GpGrupoCliente::where('GRUPO_CODIGO', trim($data['GRUPO_CODIGO']))
                ->first();
            if (!$grupocliente) {
                $grupocliente = new GpGrupoCliente;
            }
            $grupocliente->GRUPO_CODIGO = trim($data['GRUPO_CODIGO']);
            $grupocliente->GRUPO_DESCRIPCION = trim($data['GRUPO_DESCRIPCION']);
            $grupocliente->save();
        }
        return response([
            'success' => true,
            'message' => 'Grupos de clientes correctamente sincronizados'
        ]);
    }

    public function llenasubgrupoclientegp()
    {
        $obj = new WebserviceController;
        $subgrupoclientes = $obj->subgrupocliente();

        if (!isset($subgrupoclientes['MST_SubGrupo_ClienteResult']['CS_SUBGRUPO_CLIENTE']))
            return;

        foreach ($subgrupoclientes['MST_SubGrupo_ClienteResult']['CS_SUBGRUPO_CLIENTE'] as $key => $data) {

            $subgrupocliente = GpSubGrupoCliente::where('SUBGRUPO_CODIGO', trim($data['SUBGRUPO_CODIGO']))
                ->first();
            if (!$subgrupocliente) {
                $subgrupocliente = new GpSubGrupoCliente;
            }
            $subgrupocliente->SUBGRUPO_CODIGO = trim($data['SUBGRUPO_CODIGO']);
            $subgrupocliente->SUBGRUPO_DESCRIPCION = trim($data['SUBGRUPO_DESCRIPCION']);
            $subgrupocliente->GRUPO_CODIGO = trim($data['GRUPO_CODIGO']);
            $subgrupocliente->save();
        }
        return response([
            'success' => true,
            'message' => 'Subgrupos de clientes correctamente sincronizados'
        ]);
    }

    public function llenagrupoysubgrupogp()
    {
        $this->llenagrupoclientegp();
        $this->llenasubgrupoclientegp();
        return response([
            'success' => true,
            'message' => 'Grupos y subgrupos de clientes correctamente sincronizados'
        ]);
    }

    public function llenapaisgp()
    {
        $obj = new WebserviceController;
        $paises = $obj->pais();

        foreach ($paises as $key => $data) {

            $pais = GpPais::where('PAIS_CODIGO', trim($data['PAIS_CODIGO']))
                ->first();
            if (!$pais) {
                $pais = new GpPais;
            }
            $pais->PAIS_CODIGO = trim($data['PAIS_CODIGO']);
            $pais->PAIS_DESCRIPCION = trim($data['PAIS_DESCRIPCION']);
            $pais->save();
        }
        return;
    }

    public function llenaterritoriogp()
    {
        $obj = new WebserviceController;
        $territorios = $obj->territorio();

        foreach ($territorios as $key => $data) {

            $territorio = GpTerritorio::where('TERRITORIO_CODIGO', trim($data['TERRITORIO_CODIGO']))
                ->first();
            if (!$territorio) {
                $territorio = new GpTerritorio;
            }
            $territorio->TERRITORIO_CODIGO = trim($data['TERRITORIO_CODIGO']);
            $territorio->TERRITORIO_DESCRIPCION = trim($data['TERRITORIO_DESCRIPCION']);
            $territorio->save();
        }
        return;
    }

    public function llenapaisesyterritoriosgp()
    {
        $this->llenapaisgp();
        $this->llenaterritoriogp();
        return response([
            'success' => true,
            'message' => 'Paises y territorios correctamente sincronizados'
        ]);
    }

    public function checkusuario()
    {
        $user = Auth::user();
        return response(
            $user
        );
    }

    public function llenacajabancos()
    {
        $obj = new WebserviceController;
        $cajabancos = $obj->consultacajabancos();

        foreach ($cajabancos as $key => $data) {

            $caja = GpCajaBancos::where('CB_ID', trim($data['CB_ID']))
                ->first();
            if (!$caja) {
                $caja = new GpCajaBancos;
                $caja->estado = 'activo';
            }
            $caja->CB_ID = trim($data['CB_ID']);
            $caja->CB_DESCRIPCION = trim($data['CB_DESCRIPCION']);
            $caja->CB_TIPO = trim($data['CB_TIPO']);
            $caja->save();
        }
        return;
    }

    public function updatetodo()
    {

        ini_set('memory_limit', '-1');

        $this->llenausuariosgp();
        $this->llenabodegasgp();
        $this->llenacondicionpagogp();
        $this->llenaarticulosgp();
        $this->llenapuntodeventaycajasgp();
        $this->llenadescuentoformapagogp();
        $this->llenarelacionesgp();
        $this->llenavendedorgp();
        $this->llenaproveedorgp();
        $this->llenaclaseclientegp();
        $this->llenagrupoysubgrupogp();
        $this->llenapaisesyterritoriosgp();
    }

    public function testcliente(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $metodo = 'MST_Cliente';
        $obj = new WebserviceController;
        $parameters = [
            'id_Cliente ' => '',
            'FechaCreacion' => '',
            'FechaModificacion' => '2024-04-14 12:37:29'
        ];
        $clientes = $obj->maestros($metodo, $parameters);

        return response([
            $clientes['MST_ClienteResult']['CS_CLIENTE']
        ]);
    }

    public function testchucknorris()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.chucknorris.io/jokes/random');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $data = json_decode(curl_exec($ch));
        curl_close($ch);
        return response([
            $data->value
        ]);
    }

    public function updateperfilesusuarios()
    {
        GpUsuario::where('USERID', 'adminWeb')
            ->update(['perfil_id' => 1]);
        GpUsuario::where('USERID', 'ma')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'cramirez')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'consultor01')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'consultor04')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'wcampoverde')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'jmarin')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'elino')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'spinargote')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'dobando')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'csensi')
            ->update(['perfil_id' => 2]);
        GpUsuario::where('USERID', 'gbajana')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'pperez')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'kcalle')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'tyepez')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'cvera')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'psolorzano')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'ltang')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'Ventas02')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'ffajardo')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'malcivar')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'oespinoza')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'lizurieta')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'bcalva')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'dguevara')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'bherrera')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'cpaute')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'jmuñoz')
            ->update(['perfil_id' => 8]);
        GpUsuario::where('USERID', 'fruiz')
            ->update(['perfil_id' => 9]);
        GpUsuario::where('USERID', 'ddurazno')
            ->update(['perfil_id' => 12]);
        GpUsuario::where('USERID', 'gona')
            ->update(['perfil_id' => 12]);
        GpUsuario::where('USERID', 'nvasquez')
            ->update(['perfil_id' => 12]);
        GpUsuario::where('USERID', 'erambay')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'avera')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'fchiquito')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'cchavez')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'vcedeno')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'aanaluisa')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'acuadrado')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'dmoreira')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'rzamora')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'efigueroa')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'cchiriguaya')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'mpenafiel')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'hcondor')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'dlituma')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'gvillon')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'mquizhpi')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'cmoran')
            ->update(['perfil_id' => 13]);
        GpUsuario::where('USERID', 'gacurio')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'caguilar')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'jmorales')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'rnoboa')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'ctello')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'mloyola')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'kmosquera')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'ppezo')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'asoledispa')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'mpluas')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'kmanzo')
            ->update(['perfil_id' => 15]);
        GpUsuario::where('USERID', 'wmoran')
            ->update(['perfil_id' => 16]);
        GpUsuario::where('USERID', 'lmorocho')
            ->update(['perfil_id' => 18]);
        GpUsuario::where('USERID', 'vholguin')
            ->update(['perfil_id' => 18]);
        GpUsuario::where('USERID', 'mguzhnay')
            ->update(['perfil_id' => 18]);
        GpUsuario::where('USERID', 'jvillao')
            ->update(['perfil_id' => 18]);
        GpUsuario::where('USERID', 'wponce')
            ->update(['perfil_id' => 18]);
        GpUsuario::where('USERID', 'aarcentales')
            ->update(['perfil_id' => 19]);
    }
}

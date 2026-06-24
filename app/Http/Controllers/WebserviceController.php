<?php

namespace App\Http\Controllers;

use CodeDredd\Soap\Facades\Soap;
use Exception;
use Illuminate\Support\Facades\Log;

class WebserviceController extends Controller
{

    /**
     * Devuelve la lista de usuarios de GP
     */

    public function usuarios()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_USUARIOS_URL'))
            ->ConsultaUsuarios([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        $response->throw();
        $data = $response->json();

        return $data['ConsultaUsuariosResult']['CONSULTA_USUARIOS'][0]['DET_USUARIOS']['USUARIOS'] ?? null;
    }

    /**
     * Devuelve la lista de bodegas de GP
     */

    public function bodegas()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Bodega([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_BodegaResult']['CS_BODEGA'];
    }

    /**
     * Devuelve la lista de bodegas de GP
     */

    public function bodegastipodespacho()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Bodega_Tipo_Despacho([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Bodega_Tipo_DespachoResult']['CS_BODEGA_TIPO_DESPACHO'];
    }

    /**
     * Devuelve la lista de Condiciones de pago de GP
     */

    public function condicionespago()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Condicion_Pago([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Condicion_PagoResult']['CS_CONDICION_PAGO'];
    }



    public function relacionusuariocaja()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Relacion_Usuario_Caja([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Relacion_Usuario_CajaResult']['CS_USUARIO_CAJA'];
    }

    /**
     * Devuelve la lista de precios de los artículos de GP
     */

    public function listaprecios($parameters = null)
    {
        if (!$parameters) {
            $parameters = array('ART_CODIGO' => '');
        }
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Lista_Precio([
                'Lista_Precio_Articulo' => $parameters,
            ]);
        return $response->json()['MST_Lista_PrecioResult']['CS_LISTA_PRECIO'];
    }

    public function maestros($metodo, $parameters)
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->$metodo($parameters);
        $response->throw();
        return $response->json();
    }

    public function puntodeventa()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Punto_Venta([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Punto_VentaResult']['CS_PUNTO_VENTA'];
    }
   
    public function descuentoformapago()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Descuento_Forma_Pago([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Descuento_Forma_PagoResult']['CS_DESC_FORMA_PAGO'];
    }
    public function relacionpuntodeventabodega()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Relacion_Punto_Venta_Bodega([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Relacion_Punto_Venta_BodegaResult']['CS_PUNTO_VENTA_BODEGA'];
    }
    public function relacionpuntodeventabodegarelacionada()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Relacion_Punto_Venta_Bodega_Relacionada([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json();
    }
    public function relacionpuntodeventacaja()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Relacion_Punto_Venta_Caja([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Relacion_Punto_Venta_CajaResult']['CS_PUNTO_VENTA_CAJA'];
    }
    public function relacionpuntodeventaidventa()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Relacion_Punto_Venta_ID_VENTA([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Relacion_Punto_Venta_ID_VENTAResult']['CS_PUNTO_VENTA_ID_VENTA'];
    }
    public function relacionusuariopuntodeventa()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Relacion_Usuario_Punto_Venta([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Relacion_Usuario_Punto_VentaResult']['CS_USUARIO_PUNTO_VENTA'];
    }
    public function vendedor()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Vendedor([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_VendedorResult']['CS_VENDEDOR'];
    }
    public function stockarticulobodega($parameters = null)
    {
        if (!$parameters) {
            $parameters = array('STOCK_ARTICULO' => '');
        }
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Stock_Articulo_Bodega([
                'Lista_Stock_Articulo_Bodega_General' => $parameters
            ]);
        return $response->json();
    }
    public function stockarticulobodegacompartimiento($parameters)
    {

        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Stock_Articulo_Bodega_Compartimiento($parameters);
        return $response->json();
    }
    public function proveedor()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Proveedor([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json();
    }

    public function clasecliente()
    {

        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Clase_Cliente([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Clase_ClienteResult']['CS_CLASE_CLIENTE'];
    }
    public function grupocliente()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Grupo_Cliente([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Grupo_ClienteResult']['CS_GRUPO_CLIENTE'];
    }
    public function subgrupocliente()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_SubGrupo_Cliente([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json();
    }
    public function pais()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Pais([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_PaisResult']['CS_PAIS'];
    }
    public function territorio()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Territorio([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        //$response->throw();
        return $response->json()['MST_TerritorioResult']['CS_TERRITORIO'];
    }
    public function caja()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Caja([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_CajaResult']['CS_CAJA'];
    }
    public function unidadmedida()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Unidad_Medida([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Unidad_MedidaResult']['CS_PLAN_UMEDIDA'];
    }

    public function datositemsl()
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
            ->MST_Datos_Items_L([
                'IDCompania' => env('ID_COMPANIA'),
            ]);
        return $response->json()['MST_Datos_Items_LResult']['CS_DATOS_ITEMS_L'];
    }

    public function documentosvencidoscliente($ruc = null)
    {
        if (!$ruc)
            $ruc = '0100091768001';
        $response = Soap::baseWsdl(env('WEBSERVICES_COBRANZAS_URL'))
            ->ConsultaDocumentosVencidosCliente([
                'IDCompania' => env('ID_COMPANIA'),
                'Ruc' => $ruc,
            ]);
        $response->throw();
        $data = $response->json();
        return $data['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'];
    }

    public function consultacajabancos($tipo = null)
    {

        $response = Soap::baseWsdl(env('WEBSERVICES_COBRANZAS_URL'))
            ->ConsultaCajasBancos([
                'IDCompania' => env('ID_COMPANIA'),
                'Tipo' => $tipo,
            ]);
        $response->throw();
        $data = $response->json();
        return $data['ConsultaCajasBancosResult']['CONSULTA_CAJAS_BANCOS'];
    }

    public function transaccional($metodo, $parameters)
    {
        $response = Soap::baseWsdl(env('WEBSERVICES_TRANSACCIONES_URL'))
            ->$metodo($parameters);
        $response->throw();
        return $response->json();
    }

    public function cobranzas($metodo, $parameters)
    {
        $timeoutSeconds = 60;
        ini_set('default_socket_timeout', $timeoutSeconds);

        $options = [
            'connection_timeout' => 15, // Esperar máx 15 seg para conectar inicialmente
            'keep_alive' => false,      // Forzar cierre de conexión al terminar (evita zombies)
            'trace' => true,            // Útil si necesitas loguear request/response en caso de error
            'exceptions' => true,       // Para que lance Exception en lugar de error fatal
        ];

        try {
            $response = Soap::baseWsdl(env('WEBSERVICES_COBRANZAS_URL'))
                        ->withOptions($options)
                ->$metodo($parameters);
            $response->throw();
            return $response->json();
        } catch (Exception $e) {
            // Si ocurre el timeout, caerá aquí.
            // El Job capturará esta excepción, lo marcará como fallido y liberará el proceso.
            Log::channel('soap_errors')->error("SOAP Exception en método [{$metodo}]: {$e->getMessage()}", [
                'params' => $parameters,
            ]);
            throw $e;
        }
    }

    public function inventario($metodo, $parameters)
    {
        try {
            $response = Soap::baseWsdl(env('WEBSERVICES_INVENTARIO_URL'))
                ->$metodo($parameters);
            if ($response->successful()) {
                return $response->json();
            } else {
                return "Error";
            }
        } catch (Exception $e) {
            return "Error";
        }

    }

}

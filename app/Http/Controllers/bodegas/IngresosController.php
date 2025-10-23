<?php

namespace App\Http\Controllers\bodegas;

use App\Http\Controllers\Controller;
use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngresosController extends Controller
{
    public function ingreso($tipo, $documento, $detalle): void
    {
        switch($tipo){
            case 'devolucion':
                $documento['documento'] = $documento['devolucion'];
                $documento['GUI_REM_GUIA_REMISION'] = null;
                break;
            case 'compra':
                $documento['documento'] = $documento['ORDEN_COMPRA_DOCUMENTO'];
                $documento['GUI_REM_GUIA_REMISION'] = null;
                break;
            case 'traslado_reposicion':
            case 'traslado_venta':
                $documento['documento'] = $documento['pedido'];
                break;
        }

        $user = Auth::user() ?: GpUsuario::find( 6);
        $ingreso = new GpIngreso();
        $ingreso->user_id = $user->id;
        $ingreso->USERID = $user->USERID;
        $ingreso->tipo = $tipo;
        $ingreso->documento = $documento['documento'];
        $ingreso->GUI_REM_GUIA_REMISION = $documento['GUI_REM_GUIA_REMISION'];
        $ingreso->save();

        foreach($detalle as $articulo){

            switch($tipo){
                case 'devolucion':
                    $articulo['ingresado'] = $articulo['devuelto'];
                    break;
                case 'compra':
                    $articulo['ingresado'] = $articulo['seleccionado'];
                    $articulo['ART_CODIGO'] = $articulo['ORDEN_COMPRA_DETALLE_ARTICULO'];
                    break;
                case 'traslado_reposicion':
                case 'traslado_venta':
                    $articulo['ingresado'] = $articulo['recibido'];
                    $articulo['ART_CODIGO'] = $articulo['GUI_REM_CODI_INTERNO'];
                    break;
            }

            if($articulo['ingresado'] > 0){
                $item = new GpIngresosDetalle();
                $item->ingreso_id = $ingreso->id;
                $item->ART_CODIGO = $articulo['ART_CODIGO'];
                $item->ingresado = $articulo['ingresado'];
                $item->save();
            }
        }
    }
}

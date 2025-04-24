<?php

namespace App\Http\Controllers\bodegas;

use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpArticulo;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\reposicion\GpTrasladosReposicionCabecera;
use App\Traits\JsonResponseTrait;
use App\Traits\TransferRepositionTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\WebserviceController;


class ReposicionController extends Controller
{
    use JsonResponseTrait, TransferRepositionTrait;

    public function getreposicion(Request $request)
    {
            set_time_limit(300);
        try {

            $soapRequest = $request->all();
            $this->validateRequest($soapRequest);
            $transferHeader = $this->fectchTransferHeader($request);

            if (empty($transferHeader)) {
                return $this->buildErrorResponse('No existen transferencias que sincronizar', 404);
            }
            $proccessDocument = $this->processDocumentsInBatches($soapRequest, $transferHeader);
            return $proccessDocument;
            return $this->buildResponse($proccessDocument);

        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener las transferencias', $th->getMessage(), 500);
        }
    }











    //     public function getreposicion($fecha, $numero_documento = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    //     {

    //         $webservices = new WebserviceController();

    //         $parameters = array(
    //             'IDCompania' => env('ID_COMPANIA'),
    //             'Fecha' => $fecha,//date('Y-m-d')
    //         );

    //         $respuesta = $webservices->inventario('ConsultaCabeceraTransferencia', $parameters);

    // //        echo '<pre>';
    // //        print_r($respuesta);
    // //        die();
    //         if(!isset($respuesta['ConsultaCabeceraTransferenciaResult']['Cabecera_Transferencias'])){
    //             return response([
    //                 'success' => false,
    //                 'message' => 'No existen transferencias',
    //             ]);
    //         }

    //         $documentos_procesados = array();

    //         if(!$numero_documento){
    //             foreach($respuesta['ConsultaCabeceraTransferenciaResult']['Cabecera_Transferencias'] as &$documento){
    //                 $documentos_procesados[] = $this->getreposicionindividual($documento);
    //             }
    //         }else{
    //             foreach($respuesta['ConsultaCabeceraTransferenciaResult']['Cabecera_Transferencias'] as &$documento){
    //                 if($numero_documento && $numero_documento == $documento['Documento']) {
    //                     $documentos_procesados[] = $this->getreposicionindividual($documento);
    //                 }
    //             }
    //         }

    //         if(count($documentos_procesados)){
    //             return response([
    //                 'success' => true,
    //                 'message' => 'Reposición guardada',
    //                 'documentos_procesados' => $documentos_procesados,
    //             ]);
    //         }else{
    //             return response([
    //                 'success' => false,
    //                 'message' => 'La reposición no ha sido guardada',
    //             ]);
    //         }

    //     }


    // public function getreposicionindividual($documento)
    // {
    //     $webservices = new WebserviceController();
    //     $parameters = array(
    //         'IDCompania' => env('ID_COMPANIA'),
    //         'Documento' => $documento['Documento'],
    //     );

    //     $detalle = $webservices->inventario('ConsultaDetalleTransferencia', $parameters);
    //     //            print_r($detalle);
    //     $documento['detalle'] = $detalle['ConsultaDetalleTransferenciaResult']['Detalle_Transferencia'];

    //     $cabecera = GpTrasladosReposicionCabecera::where('Documento', $documento['Documento'])->first();
    //     if (!$cabecera) {
    //         $cabecera = new GpTrasladosReposicionCabecera;
    //     }
    //     $cabecera->Documento = $documento['Documento'];
    //     $cabecera->Bodega_Origen = $documento['Bodega_Origen'];
    //     $cabecera->Bodega_Destino = $documento['Bodega_Destino'];
    //     $cabecera->Codigo_Motivo = $documento['Codigo_Motivo'];
    //     $cabecera->Motivo = $documento['Motivo'];
    //     $cabecera->Fecha_Documento = substr($documento['Fecha_Documento']['date'], 0, 10);
    //     $cabecera->save();


    //     foreach ($documento['detalle'] as &$item) {
    //         $articulo = GpArticulo::where('ART_CODIGO', $item['Item'])->first();
    //         if (!$articulo) {
    //             return response([
    //                 'error' => 'El item ' . $item['Item'] . ' no se encuentra registrado en la web'
    //             ], 401);
    //         }
    //         $articulo = $articulo->toArray();
    //         $item['ART_CODIGO'] = $articulo['ART_CODIGO'];
    //         $item['ART_DESCRIPCION'] = $articulo['ART_DESCRIPCION'];
    //         $item['ART_CODIGO_TRES_LETRAS'] = $articulo['ART_CODIGO_TRES_LETRAS'];
    //         $item['ART_PLAN_UMEDIDA'] = $articulo['ART_PLAN_UMEDIDA'];
    //         $item['ART_PESO'] = $articulo['ART_PESO'];

    //         $parameters = array(
    //             'id_articulo' => $item['ART_CODIGO'],
    //             'id_bodega' => $item['Bodega_Origen'],
    //             'id_compartimiento' => '',
    //         );
    //         $item['compartimiento'] = '';
    //         $compartimento = $webservices->stockarticulobodegacompartimiento($parameters);
    //         if (isset($compartimento['MST_Stock_Articulo_Bodega_CompartimientoResult']['CS_STOCK_ARTICULO_BODEGA_COMPARTIMIENTO'])) {
    //             $compartimento = $compartimento['MST_Stock_Articulo_Bodega_CompartimientoResult']['CS_STOCK_ARTICULO_BODEGA_COMPARTIMIENTO'];
    //             $stock = array_column($compartimento, 'STO_ART_COMPART_STOCK_DISPONIBLE');
    //             array_multisort($stock, SORT_DESC, $compartimento);
    //             $item['compartimiento'] = $compartimento[0]['STO_ART_COMPART_COMPARTIMIENTO'];
    //         }

    //         $traslado = GpTrasladosReposicion::where('cabecera_id', $cabecera->id)
    //             ->where('ART_CODIGO', $item['ART_CODIGO'])->first();
    //         if (!$traslado) {
    //             $traslado = new GpTrasladosReposicion();
    //         }
    //         $traslado->cabecera_id = $cabecera->id;
    //         $traslado->Documento = $cabecera->Documento;
    //         $traslado->Codigo_Motivo = $cabecera->Codigo_Motivo;
    //         $traslado->ART_CODIGO = $item['ART_CODIGO'];
    //         $traslado->ART_DESCRIPCION = $item['ART_DESCRIPCION'];
    //         $traslado->ART_CODIGO_TRES_LETRAS = $item['ART_CODIGO_TRES_LETRAS'];
    //         $traslado->ART_PLAN_UMEDIDA = $item['ART_PLAN_UMEDIDA'];
    //         $traslado->ART_PESO = $item['ART_PESO'];
    //         $traslado->cantidad = $item['Cantidad'];
    //         $traslado->bodega_origen = $cabecera->Bodega_Origen;
    //         $traslado->bodega_destino = $cabecera->Bodega_Destino;
    //         $traslado->compartimiento = $item['compartimiento'];
    //         $traslado->Fecha_Documento = $cabecera->Fecha_Documento;
    //         $traslado->save();
    //     }

    //     return $cabecera->Documento;
    // }

    public function transferenciasreposicion($id, $documento = null): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $trasladosreposicion = GpTrasladosReposicion::join('gp_bodegas', 'gp_traslados_reposicions.bodega_destino', 'gp_bodegas.BOD_BODEGA')
            ->join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_traslados_reposicions.ART_CODIGO')
            ->where('bodega_origen', $bodega)
            ->whereNull('gp_traslados_reposicions.completado');
        if ($documento) {
            $trasladosreposicion = $trasladosreposicion->where('gp_traslados_reposicions.Documento', $documento);
        }
        $trasladosreposicion = $trasladosreposicion->select('gp_traslados_reposicions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA', 'gp_articulos.ART_DECIMAL_CANTIDAD')
            ->get();

        $bodegas = array();



        foreach ($trasladosreposicion as $traslado) {

            if (!$traslado->reservado)
                $traslado->reservado = 0;

            if (!$traslado->trasladado)
                $traslado->trasladado = 0;
            $traslado->pendiente = number_format($traslado->cantidad - $traslado->reservado - $traslado->trasladado, 2, '.', '');


            $bodegas[$traslado->bodega_destino]['bodega'] = $traslado->BOD_DESCRIPCION;
            $bodegas[$traslado->bodega_destino]['BOD_BODEGA'] = $traslado->BOD_BODEGA;

            $bodegas[$traslado->bodega_destino]['generar'] = false;
            $bodegas[$traslado->bodega_destino]['traslados'][] = $traslado;
            $bodegas[$traslado->bodega_destino]['documentos'][$traslado->Documento] = $traslado->Documento;
            $bodegas[$traslado->bodega_destino]['tipo'] = 'reposicion';
        }

        foreach ($bodegas as $key => &$bod) {
            $guias = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_destino', 'gp_bodegas.BOD_BODEGA')
                ->where('bodega_origen', $bodega)
                ->where('bodega_destino', $key)
                ->where('estado', 'pendiente')
                ->where('tipo', 'reposicion')
                ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
                ->orderBy('created_at')
                ->get()->toArray();
            $bod['guias'] = $guias;

            $guias = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_destino', 'gp_bodegas.BOD_BODEGA')
                ->where('bodega_origen', $bodega)
                ->where('bodega_destino', $key)
                ->where('estado', 'pendiente')
                ->where('tipo', 'reposicion')
                ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
                ->orderBy('created_at', 'DESC')
                ->first();
            $bod['ultimaguia'] = $guias;

            $bod['documentos'] = array_values($bod['documentos']);

            $peso_total = 0;
            foreach ($bod['traslados'] as $traslado) {
                $peso_total += $traslado->ART_PESO * $traslado->pendiente;
            }
            $bod['peso_total'] = $peso_total;
        }

        $bodegas = array_values($bodegas);

        $guias = new GuiasderemisionController();
        $guias->checkguiaremision();

        if ($documento) {
            return response([
                'success' => true,
                'traslados' => $bodegas[0]['traslados'],
            ]);
        }

        return response([
            'success' => true,
            'bodegas' => $bodegas,
        ]);
    }

    public function recibosreposicion($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');

        $webservices = new WebserviceController();
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $guias = GpGuiaRemision::where('estado', 'enviada')
            ->where('bodega_destino', $bodega)
            ->where('tipo', 'reposicion')
            ->get();


        foreach ($guias as $guia) {
            $detalle = GpGuiaRemisionDetalle::join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_guia_remision_detalles.GUI_REM_CODI_INTERNO')
                ->where('guia_remision_id', $guia->id)
                ->select('gp_guia_remision_detalles.*', 'gp_articulos.ART_DECIMAL_CANTIDAD')
                ->get();

            foreach ($detalle as $item) {

                $item->pendiente = number_format($item->GUI_REM_CANTIDAD - $item->recibido, 2, '.', '');
                $item->recibido = $item->pendiente;

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Bodega' => $guia->bodega_destino,
                    'Item' => $item->GUI_REM_CODI_INTERNO,
                );
                $respuesta = $webservices->inventario('ConsultaItemCompartimento', $parameters);
                $item->compartimientos = $respuesta['ConsultaItemCompartimentoResult']['Items_Compartimento'];
                $item->compartimiento = null;
            }

            $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)->get();

            $pedidos = '';

            foreach ($transferencias as $transferencia) {
                $pedidos .= $transferencia->pedido . ', ';
            }



            //$guia->detalle = $detalle;
            $guia->Documentos = substr($pedidos, 0, -2);
        }

        return response([
            'success' => true,
            'recibos' => $guias,
        ]);
    }

    public function recibosreposicionindividual($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $guias = GpGuiaRemision::where('estado', 'enviada')
            ->where('bodega_destino', $bodega)
            ->where('tipo', 'reposicion')
            ->get();


        foreach ($guias as $guia) {
            //

            $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)->get();

            $pedidos = '';

            foreach ($transferencias as $transferencia) {
                $pedidos .= $transferencia->pedido . ', ';
            }



            //$guia->detalle = $detalle;
            $guia->Documentos = substr($pedidos, 0, -2);
        }

        return response([
            'success' => true,
            'recibos' => $guias,
        ]);
    }

    public function detallereciboreposicion($id)
    {

        $guia = GpGuiaRemision::where('id', $id)
            ->first();

        $webservices = new WebserviceController();

        $detalle = GpGuiaRemisionDetalle::join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_guia_remision_detalles.GUI_REM_CODI_INTERNO')
            ->where('guia_remision_id', $guia->id)
            ->select('gp_guia_remision_detalles.*', 'gp_articulos.ART_DECIMAL_CANTIDAD')
            ->get();

        foreach ($detalle as $item) {

            $item->pendiente = number_format($item->GUI_REM_CANTIDAD - $item->recibido, 2, '.', '');
            $item->recibido = $item->pendiente;

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Bodega' => $guia->bodega_destino,
                'Item' => $item->GUI_REM_CODI_INTERNO,
            );
            $respuesta = $webservices->inventario('ConsultaItemCompartimento', $parameters);
            $item->compartimientos = $respuesta['ConsultaItemCompartimentoResult']['Items_Compartimento'];
            $item->compartimiento = null;
        }

        return response([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }
}

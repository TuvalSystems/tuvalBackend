<?php

namespace App\Http\Controllers\bodegas;


use Illuminate\Http\Request;
use App\Traits\JsonResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Http\Controllers\bodegas\GuiasderemisionController;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;


class ReposicionController extends Controller
{
    use JsonResponseTrait;
    protected $transferRepositionService;

    public function __construct(TransferRepositionServiceInterface $transferRepositionService)
    {
        $this->transferRepositionService = $transferRepositionService;
    }


    public function getreposicion(Request $request)
    {
        set_time_limit(300);
        try {
            $soapRequest = $request->all();
            $this->transferRepositionService->validateRequest($soapRequest);
            $transferHeader =  $this->transferRepositionService->fectchTransferHeader($request);
            if (empty($transferHeader)) {
                return $this->buildErrorResponse('No existen transferencias que sincronizar', 404);
            }
            $proccessDocument =  $this->transferRepositionService->processDocumentsInBatches($soapRequest, $transferHeader);

            return $this->transferRepositionService->buildResponse($proccessDocument);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener las transferencias', $th->getMessage(), 500);
        }
    }


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

        $invoice = app(InvoiceServiceInterface::class);
        $notify = app(NotificationServiceInterface::class);
        $deliveryNote = app(DeliveryNoteServiceInterface::class);
        $guias = new GuiasderemisionController($deliveryNote, $invoice,  $notify);
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

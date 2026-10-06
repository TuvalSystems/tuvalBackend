<?php

namespace App\Http\Controllers\Inventario\Bodega;


use Illuminate\Http\Request;
use App\Traits\JsonResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpUsuario;
use App\Services\Inventario\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Inventario\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Support\Facades\Log;

class ReposicionController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;



    public function __construct(
        protected readonly TransferRepositionServiceInterface $transferRepositionService,
        protected readonly LogisticsOperationsServiceInterface $logisticsOperationsService
    ) {}


    public function getreposicion(Request $request)
    {
        set_time_limit(300);

        $validated =  $request->validate([
            'fecha'     => 'required|date_format:Y-m-d',
            'numDoc'    => 'nullable',
        ]);
        try {
            $transferHeader = $this->transferRepositionService->fectchTransferHeader($validated);
            if (empty($transferHeader)) {
                return $this->buildErrorResponse('No existen transferencias que sincronizar', 404);
            }
            $proccessDocument = $this->transferRepositionService->processDocumentsInBatches($validated, $transferHeader);
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
            ->where(function ($q) {
                $q->where('gp_traslados_reposicions.completado', 0)->orWhereNull('gp_traslados_reposicions.completado');
            });
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
        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();

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
        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');
        try {
            $detalle = $this->transferRepositionService->getDetailReceivedReposition($id);
            return response([
                'success' => true,
                'detalle' => $detalle,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener el detalle del recibo de reposición: ', [
                'guideId' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
            ]);
            return $this->errorResponse('Error al obtener el detalle del recibo de reposición', $e->getMessage(), 500);
        }
    }

    /** funcion creada para el caso en el cual se necesitaba recibir una reposicion de +200 items */
    public function procesarRecibirReposicionWeb($id)
    {
        ini_set('max_execution_time', -1);
        try {
            $user = GpUsuario::find(179);
            $guia = $this->transferRepositionService->processReceivedRepositionWeb($id, $user);
            return $this->successResponse('Recibo de reposición procesado con éxito', $guia, 200);
        } catch (\Throwable $e) {
            Log::error('Error al recibir el recibo de reposición: ', [
                'guideId' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
            ]);
            return $this->errorResponse('Error al recibir la reposicion', $e->getMessage(), 500);
        }
    }


    public function obtenerReposicionesPendientes(){
        return $this->handleApiAction(function(){
        $lista = $this->transferRepositionService->getPendingRepositions();
        return $this->successResponse('Información Obtenida Correctamente.!', $lista);
        }, 'stack', 'obtenerReposicionesPendientes');
    }
}

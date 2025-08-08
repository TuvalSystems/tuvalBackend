<?php

namespace App\Http\Controllers\bodegas;

use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use stdClass;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpPuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use App\Http\Controllers\Controller;
use App\Models\GpPedidoDetalleStock;
use Illuminate\Support\Facades\Auth;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GuiasderemisionController extends Controller
{
    protected $deliveryNoteService;
    protected $invoiceService;
    protected $notifyService;
    public function __construct(
        DeliveryNoteServiceInterface $deliveryNoteService,
        InvoiceServiceInterface $invoiceServiceInterface,
        NotificationServiceInterface $notificacionService
    ) {
        $this->deliveryNoteService = $deliveryNoteService;
        $this->invoiceService = $invoiceServiceInterface;
        $this->notifyService = $notificacionService;
    }
    public function generarguiaventa(Request $request): Response|Application|ResponseFactory
    {
        $data = $request->all();

        $datosguia = array();

        $data['pedido'] = $data['pedido']['pedido'];

        $sucursalEnvio = GpClienteSucursal::where('id', $data['pedido']['sucursal_id'])->first()->toArray();

        $datosguia['bodega_origen'] = $data['pedido']['BOD_BODEGA'];
        $datosguia['bodega_destino'] = null;
        $datosguia['pedido'] = $data['pedido']['pedido'];
        $datosguia['tipo'] = 'despacho';
        $datosguia['GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'] = $data['transportista']['nombre'];
        $datosguia['GUI_REM_IDENTIFICACION_TRANSPORTISTA'] = $data['transportista']['identificacion'];
        $datosguia['GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA'] = $data['transportista']['tipo_identificacion'];

        $datosguia['GUI_REM_FECHA_INICIO'] = substr(str_replace('T', ' ', $data['fecha_inicio']), 0, 16);
        $datosguia['GUI_REM_FECHA_FIN'] = substr(str_replace('T', ' ', $data['fecha_fin']), 0, 16);
        $datosguia['GUI_REM_PLACA'] = $data['vehiculo']['placa'];
        if (!isset($data['observacion']))
            $data['observacion'] = '';
        $datosguia['GUI_REM_OBSERVACION'] = $data['observacion'];
        $datosguia['GUI_REM_RAZON_SOCIAL_DESTINATARIO'] = $data['pedido']['CLI_RAZON_SOCIAL'];
        $datosguia['GUI_REM_DIRECCION_DESTINATARIO'] = $sucursalEnvio['CLI_SUC_DIRECCION'];
        $datosguia['GUI_REM_IDENTIFICACION_DESTINATARIO'] = $data['pedido']['CLI_RUC'];
        $datosguia['GUI_REM_MOTIVO_TRASLADO'] = 'Venta';
        if (!$data['ruta'])
            $data['ruta'] = '';
        $datosguia['GUI_REM_RUTA'] = $data['ruta'];

        $datosguia['GUI_REM_CODI_DOCU_SUSTENTO'] = '';

        $GUI_REM_NUME_DOCU_SUSTENTO = '';
        if ($data['pedido']['estado'] == 'facturado') {
            $GUI_REM_NUME_DOCU_SUSTENTO = $data['pedido']['factura'];
            $datosguia['GUI_REM_CODI_DOCU_SUSTENTO'] = '01';
        }


        $datosguia['GUI_REM_NUME_DOCU_SUSTENTO'] = $GUI_REM_NUME_DOCU_SUSTENTO;
        $datosguia['GUI_REM_NUME_AUTO_DOCU_SUSTENTO'] = '';
        $datosguia['GUI_REM_FECHA_EMISION_DOCU_SUSTENTO'] = $data['pedido']['fecha_factura'];


        $datosguia['items'] = $data['items'];

        $guiaderemision = $this->saveguia($datosguia);

        foreach ($datosguia['items'] as $item) {
            $despacho = GpPedidoDetalle::where('id', $item['id'])->first();

            if (!$despacho->reservado)
                $despacho->reservado = 0;

            $despacho->reservado = $despacho->reservado + $item['seleccionado'];
            $despacho->save();
        }

        $this->getguiaremision($guiaderemision);

        return response([
            'success' => true,
        ]);
    }

    public function consultarguiagenerada(Request $request): Response|Application|ResponseFactory
    {

        $data = $request->all();
        $mensaje = '';
        $error = false;

        foreach ($data['items'] as $item) {

            if ($data['tipo'] != 'reposicion') {
                $traslado = GpTrasladosVentas::where('id', $item['id'])->first();
                if (!$traslado) {
                    return response([
                        'error' => 'El pedido ' . $item['pedido'] . ' ha sido eliminado, por lo que no se necesita traslado, por favor actualizar la lista.',
                    ], 401);
                }

                $pedidos_desde = GpPedido::where('pedido_origen', $item['pedido'])->get();

                $item['otros'] = 0;




                foreach ($pedidos_desde as $ped) {
                    $detalle = GpPedidoDetalle::where('pedido_id', $ped->id)
                        ->where('ART_CODIGO', $item['ART_CODIGO'])->first();

                    if ($detalle)
                        $item['otros'] += $detalle->ART_CANTIDAD;
                }

                $cantidad = GpGuiaRemisionDetalle::join('gp_guia_remisions', 'gp_guia_remisions.id', 'gp_guia_remision_detalles.guia_remision_id')
                    ->where('gp_guia_remision_detalles.GUI_REM_CODI_INTERNO', $item['ART_CODIGO'])
                    ->where('gp_guia_remisions.pedido', $item['pedido'])
                    ->where('gp_guia_remisions.tipo', 'despacho')
                    //->get();
                    ->sum('gp_guia_remision_detalles.GUI_REM_CANTIDAD');

                if (($cantidad + $item['seleccionado']) > ($item['pendiente'] + $item['otros'])) {
                    $error = true;
                    $mensaje .= 'El artículo ' . $item['ART_CODIGO'] . ' ya fue enviado en otra guia de remisión. <br>Quedan ' . $item['pendiente'] + $item['otros'] - $cantidad . ' items.<br>';
                }
            }
        }

        //        die();
        if ($error) {
            return response([
                'error' => $mensaje,
            ], 401);
        }

        //        return response([
        //            'error' => 'por si acaso',
        //        ], 401);

        return response([
            'success' => true,
        ]);
    }

    public function generarguiatraslado(Request $request): Response|Application|ResponseFactory
    {
        $data = $request->all();
        $datosguia = array();

        $GUI_REM_DIRECCION_DESTINATARIO = GpRelacionPuntoVentaBodega::join('gp_punto_ventas', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', 'gp_punto_ventas.PTO_VTA_ID')
            ->select('gp_punto_ventas.PTO_VTA_DIRECCION_1', 'gp_punto_ventas.PTO_VTA_DIRECCION_2')
            ->where('PTO_VTA_BOD_BODEGA', $data['items'][0]['bodega_destino'])->first()->toArray();

        if (!isset($data['items'][0]['pedido'])) {
            $data['items'][0]['pedido'] = $data['items'][0]['Documento'];
        }

        $datosguia['bodega_origen'] = $data['items'][0]['bodega_origen'];
        $datosguia['bodega_destino'] = $data['items'][0]['bodega_destino'];
        $datosguia['pedido'] = $data['items'][0]['pedido'];
        $datosguia['tipo'] = $data['tipo'];
        $datosguia['GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'] = $data['transportista']['nombre'];
        $datosguia['GUI_REM_IDENTIFICACION_TRANSPORTISTA'] = $data['transportista']['identificacion'];
        $datosguia['GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA'] = $data['transportista']['tipo_identificacion'];

        $datosguia['GUI_REM_FECHA_INICIO'] = substr(str_replace('T', ' ', $data['fecha_inicio']), 0, 16);
        $datosguia['GUI_REM_FECHA_FIN'] = substr(str_replace('T', ' ', $data['fecha_fin']), 0, 16);
        $datosguia['GUI_REM_PLACA'] = $data['vehiculo']['placa'];
        $datosguia['GUI_REM_OBSERVACION'] = $data['observacion'];
        $datosguia['GUI_REM_RAZON_SOCIAL_DESTINATARIO'] = 'TUVAL S.A.';
        $datosguia['GUI_REM_DIRECCION_DESTINATARIO'] = $GUI_REM_DIRECCION_DESTINATARIO['PTO_VTA_DIRECCION_1'] . ' ' . $GUI_REM_DIRECCION_DESTINATARIO['PTO_VTA_DIRECCION_2'];
        $datosguia['GUI_REM_IDENTIFICACION_DESTINATARIO'] = '0990281866001';
        $datosguia['GUI_REM_MOTIVO_TRASLADO'] = 'Transferencia';
        if (!$data['ruta'])
            $data['ruta'] = '';
        $datosguia['GUI_REM_RUTA'] = $data['ruta'];

        $datosguia['GUI_REM_CODI_DOCU_SUSTENTO'] = '';
        $datosguia['GUI_REM_NUME_DOCU_SUSTENTO'] = '';
        $datosguia['GUI_REM_NUME_AUTO_DOCU_SUSTENTO'] = '';
        $datosguia['GUI_REM_FECHA_EMISION_DOCU_SUSTENTO'] = '1900-01-01';


        $datosguia['items'] = $data['items'];

        $guiaderemision = $this->saveguia($datosguia);



        if ($data['tipo'] == 'transferencia') {
            foreach ($datosguia['items'] as $item) {

                $pedido = GpPedido::where('id', $item['pedido_id'])->first();
                $pedido->transferencia_en_proceso = 1;
                $pedido->save();

                $traslado = GpTrasladosVentas::where('id', $item['id'])->first();
                $traslado->reservado = $traslado->reservado + $item['seleccionado'];
                $traslado->save();
            }
        } elseif ($data['tipo'] == 'reposicion') {
            foreach ($datosguia['items'] as $item) {
                $traslado = GpTrasladosReposicion::where('id', $item['id'])->first();
                $traslado->reservado = $traslado->reservado + $item['seleccionado'];
                $traslado->save();
            }
        }


        $this->getguiaremision($guiaderemision);

        return response([
            'success' => true,
        ]);
    }

    public function saveguia($datosguia): GpGuiaRemision
    {
        $user = Auth::user();
        $bodega = GpRelacionPuntoVentaBodega::join('gp_punto_ventas', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', 'gp_punto_ventas.PTO_VTA_ID')
            ->join('gp_bodegas', 'gp_bodegas.BOD_BODEGA', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_BODEGA')
            ->select('gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DIRECCION_1', 'gp_punto_ventas.PTO_VTA_DIRECCION_2', 'gp_bodegas.BOD_DESCRIPCION')
            ->where('PTO_VTA_BOD_BODEGA', $datosguia['bodega_origen'])->first()->toArray();

        $guiaderemision = new GpGuiaRemision();
        $guiaderemision->tipo = $datosguia['tipo'];
        $guiaderemision->estado = 'pendiente';
        $guiaderemision->bodega_origen = $datosguia['bodega_origen'];
        $guiaderemision->bodega_destino = $datosguia['bodega_destino'];
        $guiaderemision->pedido = $datosguia['pedido'];
        $guiaderemision->user_id = $user->id;
        $guiaderemision->GUI_REM_BODEGA = $bodega['BOD_DESCRIPCION'];
        $guiaderemision->GUI_REM_ESTABLECIMIENTO = $bodega['PTO_VTA_ID'];
        $guiaderemision->GUI_REM_PTO_EMISION = '001';
        $guiaderemision->GUI_REM_SECUENCIAL = $this->getsecuencial($bodega['PTO_VTA_ID'], $guiaderemision->GUI_REM_PTO_EMISION);
        $guiaderemision->GUI_REM_GUIA_REMISION = $guiaderemision->GUI_REM_ESTABLECIMIENTO . '-' . $guiaderemision->GUI_REM_PTO_EMISION . '-' . $guiaderemision->GUI_REM_SECUENCIAL;
        $guiaderemision->GUI_REM_DIRECCION_PARTIDA = $bodega['PTO_VTA_DIRECCION_1'] . ' ' . $bodega['PTO_VTA_DIRECCION_2'];
        $guiaderemision->GUI_REM_RAZON_SOCIAL_TRANSPORTISTA = $datosguia['GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'];
        $guiaderemision->GUI_REM_IDENTIFICACION_TRANSPORTISTA = $datosguia['GUI_REM_IDENTIFICACION_TRANSPORTISTA'];
        $guiaderemision->GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA = $datosguia['GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA'];
        $guiaderemision->GUI_REM_FECHA_INICIO = $datosguia['GUI_REM_FECHA_INICIO'];
        $guiaderemision->GUI_REM_FECHA_FIN = $datosguia['GUI_REM_FECHA_FIN'];
        $guiaderemision->GUI_REM_PLACA = $datosguia['GUI_REM_PLACA'];
        if (!($datosguia['GUI_REM_OBSERVACION']))
            $datosguia['GUI_REM_OBSERVACION'] = '';
        $guiaderemision->GUI_REM_OBSERVACION = $datosguia['GUI_REM_OBSERVACION'];
        $guiaderemision->GUI_REM_RAZON_SOCIAL_DESTINATARIO = $datosguia['GUI_REM_RAZON_SOCIAL_DESTINATARIO'];
        $guiaderemision->GUI_REM_DIRECCION_DESTINATARIO = $datosguia['GUI_REM_DIRECCION_DESTINATARIO'];
        $guiaderemision->GUI_REM_IDENTIFICACION_DESTINATARIO = $datosguia['GUI_REM_IDENTIFICACION_DESTINATARIO'];
        $guiaderemision->GUI_REM_MOTIVO_TRASLADO = $datosguia['GUI_REM_MOTIVO_TRASLADO'];
        $guiaderemision->GUI_REM_RUTA = $datosguia['GUI_REM_RUTA'];
        $guiaderemision->GUI_REM_CODI_DOCU_SUSTENTO = $datosguia['GUI_REM_CODI_DOCU_SUSTENTO'];
        $guiaderemision->GUI_REM_NUME_DOCU_SUSTENTO = $datosguia['GUI_REM_NUME_DOCU_SUSTENTO'];
        $guiaderemision->GUI_REM_NUME_AUTO_DOCU_SUSTENTO = $datosguia['GUI_REM_NUME_AUTO_DOCU_SUSTENTO'];
        $guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO = $datosguia['GUI_REM_FECHA_EMISION_DOCU_SUSTENTO'];
        $guiaderemision->save();

        foreach ($datosguia['items'] as $item) {
            if ($item['seleccionado'] > 0) {
                $detalle = new GpGuiaRemisionDetalle();
                $detalle->detalle_id = $item['id'];
                $detalle->guia_remision_id = $guiaderemision->id;
                $detalle->GUI_REM_CODI_INTERNO = $item['ART_CODIGO'];
                $detalle->GUI_REM_DESCRIPCION = $item['ART_DESCRIPCION'];
                $detalle->GUI_REM_CODI_ADICIONAL = $item['ART_CODIGO_TRES_LETRAS'];
                $detalle->GUI_REM_CANTIDAD = $item['seleccionado'];
                $detalle->GUI_REM_UNIDAD_MEDIDA = $item['ART_PLAN_UMEDIDA'];
                $detalle->save();
            }
        }
        return $guiaderemision;
    }

    public function getsecuencial($GUI_REM_ESTABLECIMIENTO, $GUI_REM_PTO_EMISION): string
    {
        $ultima = GpGuiaRemision::where('GUI_REM_ESTABLECIMIENTO', $GUI_REM_ESTABLECIMIENTO)
            ->where('GUI_REM_PTO_EMISION', $GUI_REM_PTO_EMISION)
            ->orderBy('GUI_REM_SECUENCIAL', 'DESC')
            ->first();

        if (!$ultima) {
            $ultima = new stdClass();

            $ultima->GUI_REM_SECUENCIAL = match ($GUI_REM_ESTABLECIMIENTO) {
                '006' => env('SECUENCIAL_GUIA_INMACONSA'),
                '005' => env('SECUENCIAL_GUIA_CALIFORNIA'),
                '008' => env('SECUENCIAL_GUIA_CUENCA'),
                '009' => env('SECUENCIAL_GUIA_MANTA'),
                '010' => env('SECUENCIAL_GUIA_SANTO_DOMINGO'),
                '011' => env('SECUENCIAL_GUIA_VIA_A_DAULE'),
                '012' => env('SECUENCIAL_GUIA_DURAN'),
                '013' => env('SECUENCIAL_GUIA_QUITO_NORTE'),
                '014' => env('SECUENCIAL_GUIA_QUITO_SUR'),
                default => '000000',
            };
        }

        $numero = (int) $ultima->GUI_REM_SECUENCIAL;

        return str_pad($numero + 1, 9, "0", STR_PAD_LEFT);
    }

    public function getguiaremision(GpGuiaRemision $guiaderemision)
    {

        $cabecera = array(
            array(
                'GUI_REM_GUIA_REMISION' => $guiaderemision->GUI_REM_GUIA_REMISION,
                'GUI_REM_ESTABLECIMIENTO' => $guiaderemision->GUI_REM_ESTABLECIMIENTO,
                'GUI_REM_PTO_EMISION' => $guiaderemision->GUI_REM_PTO_EMISION,
                'GUI_REM_SECUENCIAL' => $guiaderemision->GUI_REM_SECUENCIAL,
                'GUI_REM_DIRECCION_PARTIDA' => $guiaderemision->GUI_REM_DIRECCION_PARTIDA,
                'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA' => $guiaderemision->GUI_REM_RAZON_SOCIAL_TRANSPORTISTA,
                'GUI_REM_IDENTIFICACION_TRANSPORTISTA' => $guiaderemision->GUI_REM_IDENTIFICACION_TRANSPORTISTA,
                'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA' => $guiaderemision->GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA,
                'GUI_REM_RISE' => '',
                'GUI_REM_FECHA_INICIO' => $guiaderemision->GUI_REM_FECHA_INICIO,
                'GUI_REM_FECHA_FIN' => $guiaderemision->GUI_REM_FECHA_FIN,
                'GUI_REM_PLACA' => $guiaderemision->GUI_REM_PLACA,
                'GUI_REM_CORREO_ELECTRONICO' => 'guias@tuvalsa.com',
                'GUI_REM_OBSERVACION' => $guiaderemision->GUI_REM_OBSERVACION,
            )
        );

        if ($guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO == '')
            $guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO = '1900-01-01 00:00:00';

        $destinatario = array(
            array(
                'GUI_REM_GUIA_REMISION' => $guiaderemision->GUI_REM_GUIA_REMISION,
                'GUI_REM_SECUENCIAL_DESTINATARIO' => 1,
                'GUI_REM_IDENTIFICACION_DESTINATARIO' => $guiaderemision->GUI_REM_IDENTIFICACION_DESTINATARIO,
                'GUI_REM_RAZON_SOCIAL_DESTINATARIO' => $guiaderemision->GUI_REM_RAZON_SOCIAL_DESTINATARIO,
                'GUI_REM_DIRECCION_DESTINATARIO' => $guiaderemision->GUI_REM_DIRECCION_DESTINATARIO,
                'GUI_REM_MOTIVO_TRASLADO' => $guiaderemision->GUI_REM_MOTIVO_TRASLADO,
                'GUI_REM_DOCU_ADUANERO' => '',
                'GUI_REM_CODI_ESTABLECIMIENTO_DESTINO' => '', //
                'GUI_REM_DESC_ESTABLECIMIENTO_DESTINO' => '', //'',
                'GUI_REM_RUTA' => $guiaderemision->GUI_REM_RUTA,
                'GUI_REM_CODI_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_CODI_DOCU_SUSTENTO,
                'GUI_REM_NUME_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_NUME_DOCU_SUSTENTO,
                'GUI_REM_NUME_AUTO_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_NUME_AUTO_DOCU_SUSTENTO,
                'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO,
            )
        );

        $detalles = GpGuiaRemisionDetalle::where('guia_remision_id', $guiaderemision->id)->get();

        $DetalleOrden = array();

        foreach ($detalles as $key => $detalle) {

            $DetalleOrden[] = array(
                'GUI_REM_GUIA_REMISION' => $guiaderemision->GUI_REM_GUIA_REMISION,
                'GUI_REM_SECUENCIAL_DESTINATARIO' => 1,
                'GUI_REM_SECUENCIAL_DETALLE' => $key + 1,
                'GUI_REM_IDENTIFICACION_DESTINATARIO' => $guiaderemision->GUI_REM_IDENTIFICACION_DESTINATARIO,
                'GUI_REM_NUME_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_NUME_DOCU_SUSTENTO,
                'GUI_REM_CODI_INTERNO' => $detalle->GUI_REM_CODI_INTERNO,
                'GUI_REM_CODI_ADICIONAL' => '',
                'GUI_REM_DESCRIPCION' => $detalle->GUI_REM_DESCRIPCION,
                'GUI_REM_CANTIDAD' => (int) $detalle->GUI_REM_CANTIDAD,
                'GUI_REM_UNIDAD_MEDIDA' => $detalle->GUI_REM_UNIDAD_MEDIDA,
                'GUI_REM_BODEGA' => $guiaderemision->GUI_REM_BODEGA,
            );
        }

        $parameters = array(
            'Cabecera' => $cabecera,
            'Destinatario' => $destinatario,
            'Detalle' => $DetalleOrden,
        );

        $webservices = new WebserviceController();
        $webservices->transaccional('MST_GUIA_REMISION', $parameters);

        $parameters = array(
            'documento_origen' => $guiaderemision->GUI_REM_GUIA_REMISION,
        );

        $result = $webservices->transaccional('MST_Respuesta_Guia_Remision', $parameters);

        if (isset($result['MST_Respuesta_Guia_RemisionResult']['CS_RESPUESTA_GUIA_REMISION'][0]['CLAVE_ACCESO'])) {
            $guiaderemision->GUI_REM_CODIGO = $result['MST_Respuesta_Guia_RemisionResult']['CS_RESPUESTA_GUIA_REMISION'][0]['CLAVE_ACCESO'];
            $guiaderemision->save();
        }
    }

    public function checkguiaremision()
    {

        $webservices = new WebserviceController();
        $guias = GpGuiaRemision::whereNull('GUI_REM_CODIGO')->get();
        foreach ($guias as $guiaderemision) {
            $parameters = array(
                'documento_origen' => $guiaderemision->GUI_REM_GUIA_REMISION,
            );

            $result = $webservices->transaccional('MST_Respuesta_Guia_Remision', $parameters);

            if (isset($result['MST_Respuesta_Guia_RemisionResult']['CS_RESPUESTA_GUIA_REMISION'][0]['CLAVE_ACCESO'])) {
                $guiaderemision->GUI_REM_CODIGO = $result['MST_Respuesta_Guia_RemisionResult']['CS_RESPUESTA_GUIA_REMISION'][0]['CLAVE_ACCESO'];
                $guiaderemision->save();
            }
        }
    }

    public function imprimirguia($id): Response
    {

        $guia = GpGuiaRemision::where('id', $id)->first();

        if ($guia->tipo == 'despacho') {
            $pedido = GpPedido::where('pedido', $guia->pedido)->first();
            $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->first();

            if (!$orden) {
                $pedido = GpPedido::where('cotizacion_id', $pedido->cotizacion_id)->first();
                $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->first();
            }

            $guia->orden = $orden->orden;
            $guia->factura = $pedido->factura;
        }


        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();
        $peso = 0;
        foreach ($detalle as $key => $det) {
            if ($guia->tipo == 'despacho') {
                $articulo = GpPedidoDetalle::where('id', $det->detalle_id)->first();
            } elseif ($guia->tipo == 'reposicion') {
                $articulo = GpTrasladosReposicion::where('id', $det->detalle_id)->first();
            } elseif ($guia->tipo == 'transferencia') {
                $articulo = GpTrasladosVentas::where('id', $det->detalle_id)->first();
            }
            if ($articulo) {
                if ($articulo->ART_TIPO_COMPRA == 'SERV') {
                    unset($detalle[$key]);
                }
                $peso += (float) number_format($articulo->ART_PESO * $det->GUI_REM_CANTIDAD, 2, '.', '');
            }
            $detTemp = GpPedidoDetalle::where('id', $det->detalle_id)
                ->select('CHECK_ALTERNATIVO')
                ->first();
            $det['CHECK_ALTERNATIVO'] = $detTemp->CHECK_ALTERNATIVO;
        }

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $guia->GUI_REM_ESTABLECIMIENTO)->first()->toarray();

        $data = array();



        $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)->get()->toArray();

        $arr_trans = array();
        foreach ($transferencias as $trans) {
            $arr_trans[$trans['pedido']] = $trans['pedido'];
        }

        $data['peso'] = number_format($peso, 2, '.', '');
        $data['guia'] = $guia;
        $data['detalle'] = $detalle;
        $data['transferencias'] = $arr_trans;
        $data['PTO_VTA_DESCRIPCION'] = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $data['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
        //return view('guiaRemisionPDF', $data);
        $pdf = PDF::loadView('guiaRemisionPDF', $data);
        return $pdf->stream('guia_remision.pdf');
    }

    public function imprimirordenalmacenamiento(Request $request): Response
    {
        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');
        //demonios endemoniados
        $data = $request->all();
        $pdf = PDF::loadView('ordenAlmacenamientoPDF', $data);
        return $pdf->stream('orden_almacenamiento_' . $data['GUI_REM_GUIA_REMISION'] . '.pdf');
    }


    public function imprimirordenalmacenamientodevolucion(Request $request): Response
    {
        $data = $request->all();
        $pdf = PDF::loadView('ordenAlmacenamientoDevolucionPDF', $data);
        return $pdf->stream('orden_almacenamiento_' . $data['devolucion']['devolucion'] . '.pdf');
    }

    public function eliminarguia($id): Response|Application|ResponseFactory
    {
        $guia = GpGuiaRemision::where('id', $id)->first();

        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();

        if ($guia->tipo == 'transferencia') {
            foreach ($detalle as $item) {
                $traslado = GpTrasladosVentas::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        if ($guia->tipo == 'reposicion') {
            foreach ($detalle as $item) {
                $traslado = GpTrasladosReposicion::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        if ($guia->tipo == 'despacho') {
            foreach ($detalle as $item) {
                $traslado = GpPedidoDetalle::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        $guia->estado = 'eliminada';
        $guia->save();

        return response([
            'success' => true,
            'message' => 'Guía de remisión eliminada',
        ]);
    }

    // public function despacharguia($id)
    // {
    //     try {
    //         $guia = GpGuiaRemision::where('id', $id)->first();

    //     if ($guia->estado == 'enviada') {
    //         return response([
    //             'success' => true,
    //             'message' => 'Guía de remisión despachada por otro usuario',
    //         ]);
    //     }

    //     $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();

    //     if ($guia->tipo == 'transferencia') {
    //         return $this->despacharguiatransferencia($guia, $detalle);
    //     }
    //     if ($guia->tipo == 'despacho') {
    //         return $this->despacharguiadespacho($guia, $detalle);
    //     }

    //     if ($guia->tipo == 'reposicion') {
    //         return $this->despacharguiareposicion($guia, $detalle);
    //     }
    //     } catch (\Throwable $e) {
    //        return response([
    //            'success' => false,
    //            'message' => $e->getMessage(),
    //        ]);
    //     }

    // }

    public function despacharguia($id)
    {
        DB::beginTransaction();
        try {
            // Bloquea la fila para este proceso
            $guia = GpGuiaRemision::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($guia->estado === 'enviada') {
                DB::rollBack(); // No es necesario, pero explícito
                return response([
                    'success' => true,
                    'message' => 'Guía de remisión despachada por otro usuario',
                ]);
            }

            $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();

            $response = null;

            switch ($guia->tipo) {
                case 'transferencia':
                    $response = $this->despacharguiatransferencia($guia, $detalle);
                    break;
                case 'despacho':
                    $response = $this->despacharguiadespacho($guia, $detalle);
                    break;
                case 'reposicion':
                    $response = $this->despacharguiareposicion($guia, $detalle);
                    break;
                default:
                    throw new \Exception("Tipo de guía no reconocido: {$guia->tipo}");
            }

            DB::commit();
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error en despachar guia de remision (despacharguia)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'guia_id' => $id
            ]);
            return response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function despacharguiareposicion(GpGuiaRemision $guia, $detalle): Response|Application|ResponseFactory
    {

        $user = Auth::user();

        $documentos = array();

        foreach ($detalle as $item) {
            $traslado = GpTrasladosReposicion::where('id', $item->detalle_id)
                ->whereNull('completado')
                ->first();

            if ($traslado) {
                $item->traslado = $traslado;
                $documentos[$traslado->Documento]['Documento'] = $traslado->Documento;
                $documentos[$traslado->Documento]['items'][] = $item;
            }
        }

        $documentos = array_values($documentos);

        $webservices = new WebserviceController();

        $listo = false;

        foreach ($documentos as $transferencia) {
            $item_arr = array();
            foreach ($transferencia['items'] as $key => $item) {
                $item_arr[$key]['Item'] = $item->GUI_REM_CODI_INTERNO;
                $item_arr[$key]['Compartimento'] = '';
                $item_arr[$key]['Cantidad'] = $item->GUI_REM_CANTIDAD;
            }

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Documento' => $transferencia['Documento'],
                'BodegaOrigen' => $guia->bodega_origen,
                'BodegaDestino' => $guia->bodega_destino,
                'Observacion' => $guia->GUI_REM_OBSERVACION,
                'Usuario' => $user->USERID,
                'Items' => $item_arr
            );

            $respuesta = $webservices->inventario('GeneraTransferenciaReposicion', $parameters);

            if (isset($respuesta['GeneraTransferenciaReposicionResult']['Retorno'][0]['Codigo']) && $respuesta['GeneraTransferenciaReposicionResult']['Retorno'][0]['Codigo'] == '0') {
                foreach ($transferencia['items'] as $key => $item) {
                    $traslado = $item->traslado;
                    $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                    $traslado->trasladado = $traslado->trasladado + $item->GUI_REM_CANTIDAD;

                    if ($traslado->trasladado == $traslado->cantidad) {
                        $traslado->completado = 1;
                    }
                    $traslado->save();

                    $listo = true;
                }

                $docu_transferencia = new GpGuiaRemisionTransferencia();
                $docu_transferencia->guia_remision_id = $guia->id;
                $docu_transferencia->pedido = $transferencia['Documento'];
                $docu_transferencia->codigo = $respuesta['GeneraTransferenciaReposicionResult']['Retorno'][0]['Mensaje'];
                $docu_transferencia->save();
            } else {
                return response([
                    'success' => false,
                    'message' => $respuesta['GeneraTransferenciaReposicionResult']['Retorno'][0]['Mensaje'],
                ]);
            }
        }
        if ($listo) {
            $guia->estado = 'enviada';
            $guia->save();
        }
        return response([
            'success' => true,
            'message' => 'Guía de remisión despachada',
        ]);
    }

    public function despacharguiatransferencia(GpGuiaRemision $guia, $detalle): Response|Application|ResponseFactory
    {

        $user = Auth::user();


        $documentos = array();

        foreach ($detalle as $item) {
            $traslado = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
                ->where('gp_traslados_ventas.id', $item->detalle_id)
                ->whereNull('gp_traslados_ventas.completado')
                ->select('gp_traslados_ventas.*', 'gp_pedidos.pedido')
                ->first();

            if ($traslado) {
                $item->traslado = $traslado;

                $documentos[$traslado->pedido]['pedido'] = $traslado->pedido;
                $documentos[$traslado->pedido]['items'][] = $item;
            }
        }

        $documentos = array_values($documentos);

        $webservices = new WebserviceController();

        $listo = false;

        foreach ($documentos as $transferencia) {
            $item_arr = array();
            foreach ($transferencia['items'] as $key => $item) {
                $item_arr[$key]['Item'] = $item->GUI_REM_CODI_INTERNO;
                $item_arr[$key]['Compartimento'] = '';
                $item_arr[$key]['Cantidad'] = $item->GUI_REM_CANTIDAD;
            }

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Pedido' => $transferencia['pedido'],
                'BodegaOrigen' => $guia->bodega_origen,
                'BodegaDestino' => $guia->bodega_destino,
                'Observacion' => $guia->GUI_REM_OBSERVACION,
                'Usuario' => $user->USERID,
                'Items' => $item_arr
            );



            $respuesta = $webservices->inventario('GeneraTransferenciaVenta', $parameters);



            if (isset($respuesta['GeneraTransferenciaVentaResult']['Retorno'][0]['Codigo']) && $respuesta['GeneraTransferenciaVentaResult']['Retorno'][0]['Codigo'] == '0') {
                foreach ($transferencia['items'] as $key => $item) {
                    $traslado = $item->traslado;
                    $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                    $traslado->trasladado = $traslado->trasladado + $item->GUI_REM_CANTIDAD;

                    if ($traslado->trasladado == $traslado->cantidad) {
                        $traslado->completado = 1;
                    }
                    $traslado->save();



                    $stock = GpPedidoDetalleStock::where('detalle_id', $traslado->detalle_id)
                        ->where('STO_ART_BODEGA', $traslado->bodega_origen)
                        ->first();

                    $stock->reservado = $stock->reservado - $item->GUI_REM_CANTIDAD;
                    $stock->save();

                    $listo = true;
                }

                $docu_transferencia = new GpGuiaRemisionTransferencia();
                $docu_transferencia->guia_remision_id = $guia->id;
                $docu_transferencia->pedido = $transferencia['pedido'];
                $docu_transferencia->codigo = $respuesta['GeneraTransferenciaVentaResult']['Retorno'][0]['Mensaje'];
                $docu_transferencia->save();
            } else {
                return response([
                    'success' => false,
                    'message' => $respuesta['GeneraTransferenciaVentaResult']['Retorno'][0]['Mensaje'],
                ]);
            }
        }
        if ($listo) {
            $guia->estado = 'enviada';
            $guia->save();
        }
        return response([
            'success' => true,
            'message' => 'Guía de remisión despachada',
        ]);
    }

    public function despacharguiadespacho(GpGuiaRemision $guia, $detalle): Response|Application|ResponseFactory
    {
        $user = Auth::user();
        $listo = false;

        foreach ($detalle as $key => $det) {
            $articulo = GpPedidoDetalle::where('id', $det->detalle_id)->first();

            if ($articulo->ART_TIPO_COMPRA == 'SERV') {
                $articulo->reservado = $articulo->reservado - $det->GUI_REM_CANTIDAD;
                $articulo->trasladado = $articulo->trasladado + $det->GUI_REM_CANTIDAD;
                $guia->estado = 'enviada';
                $articulo->save();
                $guia->save();
            }

            if ($articulo->reservado == 0) {
                unset($detalle[$key]);
            }
        }
        if (count($detalle) == 0 && $guia->estado !== 'enviada') {
            $guia->estado = 'enviada';
            $guia->save();
        }

        if (count($detalle) == 0) {
            //$guia->delete();
            $order = app(OrderServiceInterface::class);
            $dispatch = app(DispatchServiceInterface::class);
            $despachos = new DespachosController($order, $dispatch);
            $pedido = $despachos->checkdespacho($guia);
            return response([
                'success' => true,
                'message' => 'Guía de remisión despachada',
                'pedido' => $pedido
            ]);
        }

        $item_arr = array();
        foreach ($detalle as $key => $item) {
            if ($item->ART_TIPO_COMPRA == 'SERV') {
                unset($detalle[$key]);
            }
            $item_arr[$key]['Item'] = $item->GUI_REM_CODI_INTERNO;
            $item_arr[$key]['Compartimento'] = '';
            $item_arr[$key]['Cantidad'] = $item->GUI_REM_CANTIDAD;
        }

        if (count($item_arr) > 0) {

            $item_arr = array_values($item_arr);

            $numero_pedido = $guia->pedido;
            $pedido_nuevo = '';
            $pedido = GpPedido::where('pedido', $guia->pedido)->first();
            if ($pedido->pedido_origen) {
                $numero_pedido = $pedido->pedido_origen;
                $pedido_nuevo = $guia->pedido;
            }

            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Pedido' => $numero_pedido,
                'PedidoNuevo' => $pedido_nuevo,
                'BodegaOrigen' => $guia->bodega_origen,
                'Observacion' => $guia->GUI_REM_OBSERVACION,
                'Usuario' => $user->USERID ?? 'adminWeb',
                'Items' => $item_arr
            );



            $webservices = new WebserviceController();
            $respuesta = $webservices->inventario('GeneraDespachoVenta', $parameters);
            Log::channel('soap_errors')->info(
                'Respuesta al generar la guía de despacho',
                [
                    'Guia'      => $guia->id,
                    'Respuesta' => is_array($respuesta) || is_object($respuesta)
                        ? json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                        : $respuesta,
                ]
            );
            if ($respuesta == 'Error') {
                $guia->procesando = null;
                $guia->save();
                return response([
                    'error' => 'Intente de nuevo en un minuto',
                ], 401);
            }


            if (isset($respuesta['GeneraDespachoVentaResult']['Retorno'][0]['Codigo']) && $respuesta['GeneraDespachoVentaResult']['Retorno'][0]['Codigo'] == '0') {
                if (!$guia->rebajado) {
                    foreach ($detalle as $item) {
                        $traslado = GpPedidoDetalle::where('id', $item->detalle_id)->first();
                        $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                        $traslado->trasladado = $traslado->trasladado + $item->GUI_REM_CANTIDAD;
                        $traslado->save();

                        $stock = GpPedidoDetalleStock::where('detalle_id', $item->detalle_id)
                            ->where('STO_ART_BODEGA', $guia->bodega_origen)
                            ->first();

                        if ($stock) {
                            if (!$stock->reservado)
                                $stock->reservado = 0;

                            $stock->reservado = $stock->reservado - $item->GUI_REM_CANTIDAD;
                            $stock->save();
                        }
                    }
                    $guia->rebajado = 1;
                    $guia->save();
                }
                $listo = true;
            } else {
                $mensaje = $respuesta['GeneraDespachoVentaResult']['Retorno'][0]['Mensaje']
                    ?? 'Error desconocido al generar la guía de despacho';
                Log::error("Error al generar la guía de despacho (despacharguiadespacho): {$mensaje}");
                if ($mensaje !== 'Guía ya despachada por otro usuario') {
                    throw new \Exception($mensaje);
                }
                return response([
                    'success' => false,
                    'message' => $mensaje
                ]);
            }
        }
        if ($listo) {
            $guia->estado = 'enviada';
            $guia->save();
        }
        $order = app(OrderServiceInterface::class);
        $dispatch = app(DispatchServiceInterface::class);
        $despachos = new DespachosController($order, $dispatch);
        $pedido = $despachos->checkdespacho($guia);


        return response([
            'success' => true,
            'message' => 'Guía de remisión despachada',
            'pedido' => $pedido,
        ]);
    }

    public function recibirguia(Request $request)
    {
        $data = $request->all();

        if ($data['tipo'] == 'transferencia') {
            return $this->recibirguiatransferencia($data);
        }
        if ($data['tipo'] == 'reposicion') {
            return $this->recibirguiareposicion($data);
        }
        return;
    }

    public function recibirguiareposicion($data): Response|Application|ResponseFactory
    {
        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');

        $user = Auth::user();
        $documentos = array();

        foreach ($data['detalle'] as $item) {
            if ($item['recibido'] > 0) {
                $traslado = GpTrasladosReposicion::where('id', $item['detalle_id'])
                    ->first();
                $item['traslado'] = $traslado;

                $transferencia = GpGuiaRemisionTransferencia::where('pedido', $traslado->Documento)
                    ->where('guia_remision_id', $data['id'])
                    ->first();

                $documentos[$traslado->Documento]['Documento'] = $traslado->Documento;
                $documentos[$traslado->Documento]['transferencia'] = $transferencia->codigo;
                $documentos[$traslado->Documento]['items'][] = $item;
            }
        }

        $documentos = array_values($documentos);
        $webservices = new WebserviceController();

        foreach ($documentos as $transferencia) {
            $item_arr = array();
            foreach ($transferencia['items'] as $key => $item) {
                if ($item['recibido'] > 0) {
                    $item_arr[$key]['Item'] = $item['GUI_REM_CODI_INTERNO'];
                    $item_arr[$key]['Compartimento'] = $item['compartimiento'];
                    $item_arr[$key]['Cantidad'] = $item['recibido'];
                }
            }
            if (count($item_arr)) {
                $item_arr = array_values($item_arr);

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Documento' => $transferencia['Documento'],
                    'NumeroTransferOri' => $transferencia['transferencia'],
                    'BodegaOrigen' => $data['bodega_origen'],
                    'BodegaDestino' => $data['bodega_destino'],
                    'Observacion' => $data['GUI_REM_OBSERVACION'],
                    'Usuario' => $user->USERID,
                    'Items' => $item_arr
                );

                $respuesta = $webservices->inventario('GeneraRecepcionTransferenciaReposicon', $parameters);
                if (isset($respuesta['GeneraRecepcionTransferenciaReposiconResult']['Retorno'][0]['Codigo']) && $respuesta['GeneraRecepcionTransferenciaReposiconResult']['Retorno'][0]['Codigo'] == '0') {

                    foreach ($transferencia['items'] as $item) {
                        if ($item['recibido'] > 0) {
                            $recibido = GpGuiaRemisionDetalle::where('id', $item['id'])->first();
                            $recibido->recibido += $item['recibido'];
                            $recibido->save();
                        }
                    }
                } else {
                    return response([
                        'success' => false,
                        'message' => $respuesta['GeneraRecepcionTransferenciaReposiconResult']['Retorno'][0]['Mensaje'],
                    ]);
                }
            }
        }

        $guia = GpGuiaRemision::where('id', $data['id'])->first();
        $listo = $this->checkguiacompleta($guia);

        $ingresos = new IngresosController();
        $ingresos->ingreso('traslado_reposicion', $data, $data['detalle']);

        if ($listo) {
            $guia->estado = 'recibida';
            $guia->save();

            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida completamente',
            ]);
        } else {
            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida parcialmente',
            ]);
        }
    }

    public function checkguiacompleta($guia): bool
    {
        $listo = true;
        $recibidos = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();

        foreach ($recibidos as $recibido) {
            if ($recibido->recibido < $recibido->GUI_REM_CANTIDAD) {
                $listo = false;
            }
        }

        return $listo;
    }

    public function recibirguiatransferencia($data): Response|Application|ResponseFactory
    {

        $user = Auth::user();
        $documentos = array();

        foreach ($data['detalle'] as $item) {
            $traslado = GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', 'gp_traslados_ventas.pedido_id')
                ->where('gp_traslados_ventas.id', $item['detalle_id'])
                ->select('gp_traslados_ventas.*', 'gp_pedidos.pedido')
                ->first();
            $item['traslado'] = $traslado;

            $transferencia = GpGuiaRemisionTransferencia::where('pedido', $traslado->pedido)
                ->where('guia_remision_id', $data['id'])
                ->first();

            $documentos[$traslado->pedido]['pedido'] = $traslado->pedido;
            $documentos[$traslado->pedido]['transferencia'] = '';
            if (isset($transferencia->codigo))
                $documentos[$traslado->pedido]['transferencia'] = $transferencia->codigo;
            $documentos[$traslado->pedido]['items'][] = $item;
        }

        $documentos = array_values($documentos);

        //        echo '<pre>';
        //        print_r($documentos);
        //        die();

        $webservices = new WebserviceController();

        foreach ($documentos as $transferencia) {
            $item_arr = array();
            foreach ($transferencia['items'] as $key => $item) {



                if ($item['recibido'] > 0) {
                    $item_arr[$key]['Item'] = $item['GUI_REM_CODI_INTERNO'];
                    $item_arr[$key]['Compartimento'] = '';
                    $item_arr[$key]['Cantidad'] = $item['recibido'];
                }
            }

            if (count($item_arr)) {
                $item_arr = array_values($item_arr);

                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Pedido' => $transferencia['pedido'],
                    'NumeroTransferOri' => $transferencia['transferencia'],
                    'BodegaOrigen' => $data['bodega_origen'],
                    'BodegaDestino' => $data['bodega_destino'],
                    'Observacion' => $data['GUI_REM_OBSERVACION'],
                    'Usuario' => $user->USERID,
                    'Items' => $item_arr
                );


                $respuesta = $webservices->inventario('GeneraRecepcionTransferenciaVenta', $parameters);



                if (
                    isset($respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Codigo'])
                    && ($respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Codigo'] == '0'
                        // || $respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Codigo'] == '1377'
                    )
                ) {
                    foreach ($transferencia['items'] as $item) {

                        if ($item['recibido'] > 0) {
                            $stock = GpPedidoDetalleStock::where('detalle_id', $item['traslado']->detalle_id)
                                ->where('STO_ART_BODEGA', $item['traslado']->bodega_origen)
                                ->first();
                            $stock->seleccionado = $stock->seleccionado - $item['recibido'];
                            $stock->save();

                            $stock2 = GpPedidoDetalleStock::where('detalle_id', $item['traslado']->detalle_id)
                                ->where('STO_ART_BODEGA', $item['traslado']->bodega_destino)
                                ->first();
                            $stock2->seleccionado = $stock2->seleccionado + $item['recibido'];
                            $stock2->reservado = $stock2->reservado + $item['recibido'];
                            $stock2->save();

                            $recibido = GpGuiaRemisionDetalle::where('id', $item['id'])->first();
                            $recibido->recibido += $item['recibido'];
                            $recibido->save();
                        }
                    }
                } else {
                    return response([
                        'success' => false,
                        'message' => $respuesta['GeneraRecepcionTransferenciaVentaResult']['Retorno'][0]['Mensaje'],
                    ]);
                }
            }
        }

        $guia = GpGuiaRemision::where('id', $data['id'])->first();
        $listo = $this->checkguiacompleta($guia);

        $ingresos = new IngresosController();
        $ingresos->ingreso('traslado_venta', $data, $data['detalle']);

        if ($listo) {
            $guia = GpGuiaRemision::where('id', $data['id'])->first();
            $guia->estado = 'recibida';
            $guia->save();

            foreach ($documentos as $transferencia) {
                $pedido = GpPedido::where('pedido', $transferencia['pedido'])
                    ->where('estado', 'traslado')
                    ->first();
                if ($pedido) {

                    $cliente = GpCliente::where('id', $pedido->cliente_id)->first();
                    if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
                        $pedido->factura_posterior_despacho = 1;
                        if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                            $pedido->factura_a_demanda = 1;
                        } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                            $pedido->factura_siguiente_mes = 1;
                        }
                        $pedido->save();
                    }

                    // $pedidosControl->checkenviarfactura($pedido, true);
                    $this->invoiceService->checkSendInvoice($pedido, true);
                    $this->notifyService->sendNotification(
                        $pedido->user_id,
                        'Artículo/s recibidos',
                        'Artículo/s del pedido ' . $pedido->pedido . ' han sido recibidos COMPLETAMENTE en la bodega ' . $pedido->BOD_BODEGA
                    );
                }
                //aqui la notifiacion

            }

            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida completamente',
            ]);
        } else {
            foreach ($documentos as $transferencia) {
                $pedido = GpPedido::where('pedido', $transferencia['pedido'])
                    ->where('estado', 'traslado')
                    ->first();

                if ($pedido) {
                    //  $pedidosControl->checktrasladado($pedido);
                    $this->invoiceService->checkTrasladado($pedido);

                    if ($pedido->estado == 'espera') {
                        // $pedidosControl->checkenviarfactura($pedido, true);
                        $this->invoiceService->checkSendInvoice($pedido, true);
                        //aqui la notifiacion

                        $this->notifyService->sendNotification(
                            $pedido->user_id,
                            'Artículo/s recibidos',
                            'Artículo/s del pedido ' . $pedido->pedido . ' han sido recibidos PARCIALMENTE en la bodega ' . $pedido->BOD_BODEGA
                        );
                    }
                }
            }

            return response([
                'success' => true,
                'message' => 'Guía de remisión recibida parcialmente',
            ]);
        }
    }

    public function tempcheck($id)
    {
        $guia = GpGuiaRemision::where('id', $id)->where('estado', 'enviada')->first();

        if ($guia) {
            $listo = $this->checkguiacompleta($guia);

            if ($listo) {
                echo '<pre>';
                print_r($guia);
                die();
                $guia->estado = 'recibida';
                $guia->save();

                $pedido = GpPedido::where('pedido', $guia->pedido)->first();
                if ($pedido->estado != 'facturado') {
                    // $pedidosControl->checkenviarfactura($pedido, true);
                    $this->invoiceService->checkSendInvoice($pedido, true);
                }
                //aqui la notifiacion
                $this->notifyService->sendNotification(
                    $pedido->user_id,
                    'Artículo/s recibidos',
                    'Artículo/s del pedido ' . $pedido->pedido . ' han sido recibidos COMPLETAMENTE en la bodega ' . $pedido->BOD_BODEGA
                );

                return response([
                    'success' => true,
                    'message' => 'Guía de remisión recibida completamente',
                ]);
            } else {
                $pedido = GpPedido::where('pedido', $guia->pedido)->first();
                //aqui la notifiacion
                $this->notifyService->sendNotification(
                    $pedido->user_id,
                    'Artículo/s recibidos',
                    'Artículo/s del pedido ' . $pedido->pedido . ' han sido recibidos PARCIALMENTE en la bodega ' . $pedido->BOD_BODEGA
                );

                return response([
                    'success' => true,
                    'message' => 'Guía de remisión recibida parcialmente',
                ]);
            }
        }
    }

    public function generarguiatemp()
    {
        $guiaderemision = GpGuiaRemision::where('id', 39549)->first();
        //test
        $cabecera = array(
            array(
                'GUI_REM_GUIA_REMISION' => $guiaderemision->GUI_REM_GUIA_REMISION,
                'GUI_REM_ESTABLECIMIENTO' => $guiaderemision->GUI_REM_ESTABLECIMIENTO,
                'GUI_REM_PTO_EMISION' => $guiaderemision->GUI_REM_PTO_EMISION,
                'GUI_REM_SECUENCIAL' => $guiaderemision->GUI_REM_SECUENCIAL,
                'GUI_REM_DIRECCION_PARTIDA' => $guiaderemision->GUI_REM_DIRECCION_PARTIDA,
                'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA' => $guiaderemision->GUI_REM_RAZON_SOCIAL_TRANSPORTISTA,
                'GUI_REM_IDENTIFICACION_TRANSPORTISTA' => $guiaderemision->GUI_REM_IDENTIFICACION_TRANSPORTISTA,
                'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA' => $guiaderemision->GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA,
                'GUI_REM_RISE' => '',
                'GUI_REM_FECHA_INICIO' => $guiaderemision->GUI_REM_FECHA_INICIO,
                'GUI_REM_FECHA_FIN' => $guiaderemision->GUI_REM_FECHA_FIN,
                'GUI_REM_PLACA' => $guiaderemision->GUI_REM_PLACA,
                'GUI_REM_CORREO_ELECTRONICO' => 'guias@tuvalsa.com',
                'GUI_REM_OBSERVACION' => $guiaderemision->GUI_REM_OBSERVACION,
            )
        );

        if ($guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO == '')
            $guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO = '1900-01-01 00:00:00';

        $destinatario = array(
            array(
                'GUI_REM_GUIA_REMISION' => $guiaderemision->GUI_REM_GUIA_REMISION,
                'GUI_REM_SECUENCIAL_DESTINATARIO' => 1,
                'GUI_REM_IDENTIFICACION_DESTINATARIO' => $guiaderemision->GUI_REM_IDENTIFICACION_DESTINATARIO,
                'GUI_REM_RAZON_SOCIAL_DESTINATARIO' => $guiaderemision->GUI_REM_RAZON_SOCIAL_DESTINATARIO,
                'GUI_REM_DIRECCION_DESTINATARIO' => $guiaderemision->GUI_REM_DIRECCION_DESTINATARIO,
                'GUI_REM_MOTIVO_TRASLADO' => $guiaderemision->GUI_REM_MOTIVO_TRASLADO,
                'GUI_REM_DOCU_ADUANERO' => '',
                'GUI_REM_CODI_ESTABLECIMIENTO_DESTINO' => '', //
                'GUI_REM_DESC_ESTABLECIMIENTO_DESTINO' => '', //'',
                'GUI_REM_RUTA' => $guiaderemision->GUI_REM_RUTA,
                'GUI_REM_CODI_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_CODI_DOCU_SUSTENTO,
                'GUI_REM_NUME_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_NUME_DOCU_SUSTENTO,
                'GUI_REM_NUME_AUTO_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_NUME_AUTO_DOCU_SUSTENTO,
                'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO,
            )
        );

        $detalles = GpGuiaRemisionDetalle::where('guia_remision_id', $guiaderemision->id)->get();

        $DetalleOrden = array();

        foreach ($detalles as $key => $detalle) {

            $DetalleOrden[] = array(
                'GUI_REM_GUIA_REMISION' => $guiaderemision->GUI_REM_GUIA_REMISION,
                'GUI_REM_SECUENCIAL_DESTINATARIO' => 1,
                'GUI_REM_SECUENCIAL_DETALLE' => $key + 1,
                'GUI_REM_IDENTIFICACION_DESTINATARIO' => $guiaderemision->GUI_REM_IDENTIFICACION_DESTINATARIO,
                'GUI_REM_NUME_DOCU_SUSTENTO' => $guiaderemision->GUI_REM_NUME_DOCU_SUSTENTO,
                'GUI_REM_CODI_INTERNO' => $detalle->GUI_REM_CODI_INTERNO,
                'GUI_REM_CODI_ADICIONAL' => '',
                'GUI_REM_DESCRIPCION' => $detalle->GUI_REM_DESCRIPCION,
                'GUI_REM_CANTIDAD' => (int) $detalle->GUI_REM_CANTIDAD,
                'GUI_REM_UNIDAD_MEDIDA' => $detalle->GUI_REM_UNIDAD_MEDIDA,
                'GUI_REM_BODEGA' => $guiaderemision->GUI_REM_BODEGA,
            );
        }

        $parameters = array(
            'Cabecera' => $cabecera,
            'Destinatario' => $destinatario,
            'Detalle' => $DetalleOrden,
        );

        $webservices = new WebserviceController();
        $webservices->transaccional('MST_GUIA_REMISION', $parameters);

        $parameters = array(
            'documento_origen' => $guiaderemision->GUI_REM_GUIA_REMISION,
        );

        $result = $webservices->transaccional('MST_Respuesta_Guia_Remision', $parameters);

        echo '<pre>';
        print_r($result);

        if (isset($result['MST_Respuesta_Guia_RemisionResult']['CS_RESPUESTA_GUIA_REMISION'][0]['CLAVE_ACCESO'])) {
            $guiaderemision->GUI_REM_CODIGO = $result['MST_Respuesta_Guia_RemisionResult']['CS_RESPUESTA_GUIA_REMISION'][0]['CLAVE_ACCESO'];
            $guiaderemision->save();
        }
    }
}

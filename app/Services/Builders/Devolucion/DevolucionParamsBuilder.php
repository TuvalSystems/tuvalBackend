<?php

namespace App\Services\Builders\Devolucion;

use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpPedido;
use App\Models\GpUsuario;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use Illuminate\Support\Arr;

class DevolucionParamsBuilder implements DevolucionParamsBuilderInterface
{
    public function formPayloadDevolutionHeader(array $data, array $calculatedData): GpDevoluciones{
        $dev = new GpDevoluciones();
        $dev->fill([
            'estado'                        => $calculatedData['status'],
            'despachado'                    => $calculatedData['isDispatched'],
            'requiere_aprobacion'           => $calculatedData['requiredApproval'],
            'permite_anulacion'             => $calculatedData['canNullify'],
            'anulacion_habilitada'          => $calculatedData['anulacionHabilitada'],
            'USERID'                        => $calculatedData['USERID'],
            'user_id'                       => $calculatedData['userId'],

            'pedido_id'                     => Arr::get($data, 'pedido.id'),
            'cliente_id'                    => Arr::get($data, 'pedido.cliente_id'),
            'pedido'                        => Arr::get($data, 'pedido.pedido'),
            'factura'                       => Arr::get($data, 'pedido.factura'),
            'volver_a_facturar'             => Arr::get($data, 'devolucion.volver_a_facturar'),
            'tipo_devolucion'               => Arr::get($data, 'devolucion.tipo_devolucion'),
            'cliente_volver_a_facturar_id'  => Arr::get($data, 'devolucion.cliente_volver_a_facturar_id'),

            'sucursal_id'                   => Arr::get($data, 'devolucion.sucursal_id'),
            'sucursal_facturacion_id'       => Arr::get($data, 'devolucion.sucursal_facturacion_id'),
            'motivo'                        => Arr::get($data, 'devolucion.motivo'),
            'orden_de_compra'               => Arr::get($data, 'pedido.orden_de_compra'),
            'nota_cliente'                  => Arr::get($data, 'pedido.nota_cliente'),
            'aprobado'                      => 'no',
            'impuesto'                      => Arr::get($data, 'pedido.impuesto'),
            'observacion'                   => Arr::get($data, 'devolucion.observacion'),
        ]);
        return $dev;
    }


    public function formPayloadDevolutionDetail(int $devolucionId, array $details, bool $dispatched): GpDevolucionesDetalle{
        $dvd = new GpDevolucionesDetalle();
        $dvd->fill([
            'devolucion_id'             => $devolucionId,
            'detalle_id'                => Arr::get($details, 'id'),
            'ART_CODIGO'                => Arr::get($details, 'ART_CODIGO'),
            'ART_DESCRIPCION'           => Arr::get($details, 'ART_DESCRIPCION'),
            'ART_DESCRIPCION_ORIGINAL'  => Arr::get($details, 'ART_DESCRIPCION_ORIGINAL'),
            'CHECK_ALTERNATIVO'         => Arr::get($details, 'CHECK_ALTERNATIVO', 0),
            'ART_CODIGO_TRES_LETRAS'    => Arr::get($details, 'ART_CODIGO_TRES_LETRAS'),
            'ART_DECIMAL_CANTIDAD'      => Arr::get($details, 'ART_DECIMAL_CANTIDAD'),
            'ART_PLAN_UMEDIDA'          => Arr::get($details, 'ART_PLAN_UMEDIDA'),
            'ART_MARCA'                 => Arr::get($details, 'ART_MARCA'),
            'ART_MATERIAL'              => Arr::get($details, 'ART_MATERIAL'),
            'ART_PESO'                  => Arr::get($details, 'ART_PESO'),
            'ART_TIPO_COMPRA'           => Arr::get($details, 'ART_TIPO_COMPRA'),
            'ART_VALOR'                 => Arr::get($details, 'ART_VALOR'),
            'ART_IVA'                   => Arr::get($details, 'ART_IVA'),
            'ART_VALOR_ORIGINAL'        => Arr::get($details, 'ART_VALOR_ORIGINAL'),
            'ART_DESCUENTO'             => Arr::get($details, 'ART_DESCUENTO'),
            'ART_CANTIDAD'              => Arr::get($details, 'ART_CANTIDAD'),
            'ART_STOCK'                 => Arr::get($details, 'ART_STOCK'),
            'ART_IMP_VTA'               => Arr::get($details, 'ART_IMP_VTA'),
            'ART_COSTO_PROMEDIO'        => Arr::get($details, 'ART_COSTO_PROMEDIO'),
            'descuento'                 => Arr::get($details, 'descuento'),
            'descuento_original'        => Arr::get($details, 'descuento_original'),
            'modificador'               => Arr::get($details, 'modificador'),
            'seleccionado'              => Arr::get($details, 'seleccionado'),
            'disponible'                => Arr::get($details, 'disponible'),
            'despachado_parcial'       => $dispatched
        ]);

        return $dvd;
    }

    public function builderParamsDevolutionAdjustment(GpDevoluciones $devolucion, GpPedido $pedido, $userId, $items)
    {
        $header = [
            'idCompany'         => config('services.api_gp.company'),
            'documento'         => $devolucion->devolucion,
            'pedidoOrigen'      => $pedido->pedido,
            'bodega'            => $pedido->BOD_BODEGA,
            'observacion'       => $devolucion->DOC_GENERADO,
            'usuario'           => $userId ?? 'adminWeb',
        ];
        return [
            'cabecera'          => $header,
            'detalle'           => $items
        ];
    }


    public function formPayloadInvoiceAnulation($order, $devolucion, $diario, $documentId, $claveAcceso): GpFacturaAnulada
    {
        $inv = new GpFacturaAnulada();
        $inv->fill([
            'devolucion_id'        => $devolucion->id,
            'pedido_id'           => $order->id,
            'factura'             => $order->factura,
            'pedido'              => $order->pedido,
            'documento_id'        => $documentId,
            'claveacceso'         => $claveAcceso,
            'msj_anulacion_gp'    => 'Factura anulada correctamente en GP',
            'diario_response_gp'  => $diario,
            'estado'              => 'pendiente',
            // 'resp_document'       => ,
        ]);
        return $inv;
    }


     public function createRegisterHistortyHeaderDev($dev, GpUsuario $user){
        $ing = new GpIngreso();
        $ing->fill([
            'user_id'   => $user->id,
            'USERID'    => $user->USERID,
            'tipo'      => 'devolucion',
            'documento' => $dev->devolucion,
        ]);
        return $ing;
    }

    public function createRegisterHistoryDetailDev($items, $ing){
        $d = new GpIngresosDetalle();
        $d->fill([
            'ingreso_id'    => $ing->id,
            'ART_CODIGO'    => $items['ART_CODIGO'],
            'ingresado'     => $items['new_devuelto'],
        ]);
        return $d;
    }

}

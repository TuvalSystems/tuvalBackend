<?php

namespace App\Services\Aprobaciones;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpDatosItemsL;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpPuntoVenta;
use DateTime;
use App\Models\GpPedido;
use Illuminate\Support\Arr;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;

class ApprovalService implements ApprovalServiceInterface
{


    // aprobacion comercial tanto de pedido como de cotizaciones

    public function checkoutApprovalComercial($data, $order = false)
    {
        // TODO: Implement checkoutApprovalComercial() method.
        $return = ['requiere' => 'no', 'motivo' => null];

        $nivelFormaPagoCliente = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL'])->
            orderBy('DESC_FORMA_PAGO_CODIGO')->first();
        if (!$nivelFormaPagoCliente) {
            return [
                'requiere' => 'no',
                'motivo' => 'Forma de pago no encontrada en la base de datos'
            ];
        }
        $nivelCliente = $nivelFormaPagoCliente->nivel;


        $nivelFormaPagoCotizacion = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO'])->
            orderBy('DESC_FORMA_PAGO_CODIGO')->first();
        if (!$nivelFormaPagoCotizacion) {
            return [
                'requiere' => 'no',
                'motivo' => 'Forma de pago de cotizacion no encontrada en la base de datos'
            ];
        }
        $nivelCotizacion = $nivelFormaPagoCotizacion->nivel;


        $porcentajeMargen = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', 'VTA-BNS-L')->get()->first();
        if (!$porcentajeMargen) {
            return [
                'requiere' => 'yes',
                'motivo' => 'Porcentaje de margen no encontrado en la base de datos'
            ];
        }


        if (!empty($data['cotizacion_id']) && isset($data['cotizacion']) && $data['cotizacion_id'] != '') {
            $cotizacion = GpCotizacion::where('id', $data['cotizacion_id'])->first();
            if (!$cotizacion) {
                return [
                    'requiere' => 'no',
                    'motivo' => 'Cotizacion no encontrada en la base de datos'
                ];
            }

            // // ESTA VALICACION ES PARA VALIDAR QUE PIDA AUTORIZACIÓN SI LA COTIZACIÓN SOBRE LA CUAL SE REALIZA LA COPIA O EL VOLVER A PEDIR PIDA AUTORIZACION SI O SI
            // if ($cotizacion->estado == 'espera') {
            //     return [
            //         'requiere' => 'yes',
            //         'motivo' => 'Cotizacion pendiente de aprobacion comercial'
            //     ];
            // }


            $nivelFormPagoCotizacionAnterior = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion->DESC_FORMA_PAGO_CODIGO)
                ->orderBy('DESC_FORMA_PAGO_CODIGO')->first();
            $nivelAnterior = $nivelFormPagoCotizacionAnterior && isset($nivelFormPagoCotizacionAnterior->nivel) ? $nivelFormPagoCotizacionAnterior->nivel : 0;

            // si la forma de pago de la cotizacion es mayor que la del cliente
            if ($nivelCotizacion > $nivelAnterior && $nivelCotizacion > $nivelCliente) {
                $cotizacion->aprobado_comercial = 'no';
                $cotizacion->save();
                return [
                    'requiere' => 'yes',
                    'motivo' => 'Forma de pago de cotizacion mayor que la del cliente modificando cotizacion: ' . $nivelCotizacion . ' - ' . $nivelCliente
                ];
            }

            //detalle de la cotizacion
            foreach ($data['cotizacion'] as $detalle) {
                if (!empty($detalle['detalle_id'])) {
                    $detOriginal = GpCotizacionDetalle::find($detalle['detalle_id']);
                    if (!$detOriginal)
                        continue;

                    //VALIDACIONES PARA EL PEDIDO
                    if ($order) {
                        //validaciones del descuento en caso de que sea pedido
                        if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $detOriginal->descuento) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => 'Descuento del detalle diferente al descuento original -' . $detalle['detalle_id']
                            ];
                        }

                        //validaciones para el valor en caso de que sea pedido
                        if ($detalle['ART_VALOR'] < $detOriginal->ART_VALOR) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => 'Valor menor que el detalle Original -' . $detalle['detalle_id'] . ' - ' . $detalle['ART_VALOR']
                            ];
                        }
                    }

                    //VALIDACIONES POR EL TIO DE ARTICULO
                    if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                        foreach ($detalle['proveedores'] as $proveedor) {
                            $proveedorOriginal = GpCotizacionDetalleProveedores::where('detalle_id', $detOriginal->id)->first();
                            if ($proveedor['MARGEN'] < $proveedorOriginal->MARGEN) {
                                return [
                                    'requiere' => 'yes',
                                    'motivo' => 'Margen del producto L es menor al margen aprobado en la cotización'
                                ];
                            }
                        }
                    } else {
                        if ($detalle['ART_DESCRIPCION'] != $detOriginal->ART_DESCRIPCION) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => 'Descripcion del detalle diferente a la original -' . $detalle['detalle_id']
                            ];
                        }

                        if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] > $detOriginal->descuento) {
                            return [
                                'requiere' => 'yes',
                                'motivo' => 'Descuento del detalle diferente al descuento original -' . $detalle['detalle_id']
                            ];
                        }
                    }
                }
            }


            //chequeo del total corrregido
            if (
                $cotizacion->requiere_aprobacion_comercial == 'yes' &&
                $cotizacion->aprobado_comercial == 'yes' &&
                abs($data['total'] - $cotizacion->total) <= 0.10
            ) {
                return [
                    'requiere' => 'no',
                    'motivo' => 'Total dentro del rango  permitido'
                ];
            }
        }
        if ($order) {
            $cotizacion = GpCotizacion::find($data['cotizacion_id']);
            if ($cotizacion && $cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial != 'yes') {
                return [
                    'requiere' => 'yes',
                    'motivo' => "Requiere aprobacion comercial, no se aprobo la cotizacion: {$cotizacion->cotizacion} sobre el cual se genero el pedido."

                ];
            }
        }
        // validacion para "volver a pedir"
        if (!$order) {
            $registroVolverAPedir = GpRegistroCopia::where('id', $data['registro_copia'])->where('tipo', 'volver_a_pedir')->first();
            if ($registroVolverAPedir) {
                $desde = $registroVolverAPedir->pedido_origen ? 'pedido' : 'cotizacion';
                $origen = $registroVolverAPedir->pedido_origen
                    ? 'PED' . str_pad($registroVolverAPedir->pedido_origen, 8, "0", STR_PAD_LEFT)
                    : 'COT' . str_pad($registroVolverAPedir->cotizacion_origen, 8, "0", STR_PAD_LEFT);
                $fechaOrigen = $registroVolverAPedir->pedido_origen
                    ? optional(GpPedido::find($registroVolverAPedir->pedido_origen))->created_at
                    : optional(GpCotizacion::find($registroVolverAPedir->cotizacion_origen))->created_at;

                $origenEntidad = $registroVolverAPedir->pedido_origen
                    ? GpPedido::find($registroVolverAPedir->pedido_origen)
                    : GpCotizacion::find($registroVolverAPedir->cotizacion_origen);

                // para validar si el origen esta en espera y no ha sido aprobado
                if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial == 'yes' && $origenEntidad->aprobado_comercial != 'yes') {
                    return [
                        'requiere' => 'yes',
                        'motivo' => "Origen en estado 'Espera'. Obligatorio aprobar. Desde $desde: $origen, fecha: " . substr($fechaOrigen, 0, 10)
                    ];
                }

                // Motivo normal por ser copia
                return [
                    'requiere' => 'yes',
                    'motivo' => "Cotización generada desde 'Volver a Pedir' ($desde original: $origen, fecha: " . substr($fechaOrigen, 0, 10) . ")"
                ];
            }

        }

        //validar nivel de forma de pago Mayor que del cliente

        if ($nivelCotizacion > $nivelCliente) {
            return [
                'requiere' => 'yes',
                'motivo' => 'Forma de pago de cotizacion mayor que la del cliente: ' . $nivelCotizacion . ' > ' . $nivelCliente
            ];
        }

        // Porcentaje de descuento general diferente
        if ($data['porcentage_descuento'] != $data['porcentage_descuentooriginal']) {
            return [
                'requiere' => 'yes',
                'motivo' => 'Porcentaje de descuento general diferente a la original'
            ];
        }

        // validacion de cada detallle
        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        foreach ($data['cotizacion'] as $detalle) {
            //validacin de descuento por detalle
            if ($detalle['descuento'] != $detalle['descuento_original']) {
                return [
                    'requiere' => 'yes',
                    'motivo' => 'Porcentaje de descuento general diferenye a la original'
                ];
            }

            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                foreach ($detalle['proveedores'] as $proveedor) {
                    $margen = number_format(($proveedor['COSTO_UNITARIO'] /
                        (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100), 4, '.', '');

                    if ($margen > $detalle['ART_VALOR']) {
                        return [
                            'requiere' => 'yes',
                            'motivo' => 'Margen del producto L es mayor al valor del producto'
                        ];
                    }
                }
            } else {
                if ($detalle['ART_VALOR'] < $detalle['ART_VALOR_ORIGINAL']) {
                    return [
                        'requiere' => 'yes',
                        'motivo' => 'El valor del producto es meor que el valor original'
                    ];
                }

                if ($detalle['ART_VALOR'] > ($detalle['ART_VALOR_ORIGINAL'] * (1 + ($ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO) / 100))) {
                    return [
                        'requiere' => 'yes',
                        'motivo' => 'El valor del producto es mayor que el valor original y supera el margen permitido -' . '' . $porcentajeMargen->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO
                    ];
                }

                if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL']) {
                    return [
                        'requiere' => 'yes',
                        'motivo' => 'La descripcion del producto es diferente a la original'
                    ];
                }
            }
        }
        return $return;
    }

    public function checkoutApprovalDevoluciones($data)
    {

        $tipoDevolucion = Arr::get($data, 'devolucion.tipo_devolucion');
        $volverAFacturar = Arr::get($data, 'devolucion.volver_a_facturar', false);
        $detallePedido = Arr::get($data, 'detallepedido', []);
        $fechaFactura = Arr::get($data, 'pedido.fecha_factura', null);
        $daysDifference = $this->daysDifference($fechaFactura);
        $hasTypeLItems = collect($detallePedido)->contains(
            fn($detalle) => strtolower(Arr::get($detalle, 'ART_TIPO_COMPRA', '')) === 'l'
        );

        // Verifica si el pedido tiene detalles de orden despachados
        // Esto es necesario para determinar si se requiere despacho
        // en caso de que la devolucion sea parcial o total
        // y no se vuelva a facturar
        $requiresDispatch = $this->hasDispatchedOrderDetails(Arr::get($data, 'pedido', []), $detallePedido, $volverAFacturar);


        if ($requiresDispatch) {
            return 'yes';
        }

        if ($tipoDevolucion === 'parcial') {
            if ($volverAFacturar) {
                return 'yes';
            }
            if ($hasTypeLItems) {
                return 'yes';
            }
            return $daysDifference <= 7 ? 'no' : 'yes';
        }

        if ($tipoDevolucion === 'total') {
            if ($volverAFacturar) {
                return 'yes';
            }
            if ($hasTypeLItems) {
                return 'yes';
            }
            return $daysDifference <= 7 ? 'no' : 'yes';
        }

        return 'yes';
    }


    public function daysDifference($fecha)
    {
        if (!$fecha)
            return 0;

        $origin = new DateTime($fecha);
        $target = new DateTime('now');
        return $origin->diff($target)->days;
    }


    public function hasDispatchedOrderDetails($pedido, $detallepedido, $volverAFacturar): bool
    {
        if ($volverAFacturar === true) {
            $lastOrder = GpPedido::where('cotizacion_id', $pedido['cotizacion_id'])
                ->where('estado', '!=', 'anulada')
                ->orderByDesc('id')
                ->first();
            if (!$lastOrder) {
                return false;
            }
            $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
            return GpPedidoDetalle::where('pedido_id', $lastOrder->id)
                ->whereIn('ART_CODIGO', $codigos)
                ->where('trasladado', '>', 0)
                ->exists();
        }

        $pedidoConsulta = GpPedido::find($pedido['id']);
        $tieneFacturaOrigen = !empty($pedidoConsulta->factura_origen);
        if ($tieneFacturaOrigen) {

            //validador para casos donde el pedido tiene despachado null se verifica en caso de que tenga factura origen y si haya items despachados parciales
            if ($pedidoConsulta->despachado === false || $pedidoConsulta->despachado === null) {
                $pedido_origen = GpPedido::where('factura', $pedido['factura_origen'])->orderByDesc('id')->first();
                if (is_null(optional($pedido_origen)->despachado) || $pedido_origen->despachado === false) {
                    return $this->hasDetailDispatched($detallepedido);
                }
            }

            //esta condicional valida que pedido tenga factura origen y despachado
            if ($pedidoConsulta->despachado === true || $pedidoConsulta->despachado === 1) {
                $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
                return GpPedidoDetalle::where('pedido_id', $pedidoConsulta->id)
                    ->whereIn('ART_CODIGO', $codigos)
                    ->where('trasladado', '>', 0)
                    ->exists();
            }
        }

        return $this->hasDetailDispatched($detallepedido);
    }

    public function hasDetailDispatched($detallepedido): bool
    {
        return collect($detallepedido)
            ->contains(function ($item) {
                return !empty($item['despachado'])
                    && (float) $item['despachado'] > 0
                    && isset($item['despacho'])
                    && $item['despacho'] === true;
            });
    }
}

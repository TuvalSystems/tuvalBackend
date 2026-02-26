<?php

namespace App\Services\Aprobaciones;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPuntoVenta;
use App\Models\GpRegistroCopia;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;

class ApprovalQuotationService implements ApprovalQuotationServiceInterface
{

    public function __construct(protected readonly ApprovalFunctionsServiceInterface $approvalFuntions) {}

    public function approveQuotation(array $data): array
    {
        $motivosAprobacionComercial = [];
        $registroVolverAPedir = null;
        $quoteId = data_get($data, 'cotizacion_id', null);
        $quoteOrigin = empty($quoteId) ? null : GpCotizacion::find($quoteId);
        $idRegistroCopiaInput = data_get($data, 'registro_copia', null);

        if ($quoteOrigin) {
            $registroVolverAPedir = GpRegistroCopia::where('cotizacion_destino', $quoteOrigin->id)
                ->where('tipo', 'volver_a_pedir')
                ->where('destino_aprobado', false)
                ->first();
        }

        if (!$registroVolverAPedir && $idRegistroCopiaInput) {
            $registroVolverAPedir = GpRegistroCopia::where('id', $idRegistroCopiaInput)
                ->where('tipo', 'volver_a_pedir')
                ->where('destino_aprobado', false)
                ->first();
        }

        if ($registroVolverAPedir) {
            $esPedido = !empty($registroVolverAPedir->pedido_origen);
            $idOrigen = $esPedido ? $registroVolverAPedir->pedido_origen : $registroVolverAPedir->cotizacion_origen;

            $origenEntidad = $esPedido ? GpPedido::find($idOrigen) : GpCotizacion::find($idOrigen);
            $desde = $esPedido ? 'pedido' : 'cotizacion';
            $prefijo = $esPedido ? 'PED' : 'COT';
            $codigoVisual = $prefijo . str_pad($idOrigen, 8, "0", STR_PAD_LEFT);

            $fechaTxt = optional($origenEntidad)->created_at
                ? optional($origenEntidad)->created_at->format('Y-m-d')
                : 'S/F';

            if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial === 'yes' && $origenEntidad->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Cotización desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt"
                );
            }

            // 2. Motivo por ser copia (siempre se agrega si existe el registro)
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                "Cotización desde Volver a Pedir ($desde original: $codigoVisual, fecha: $fechaTxt)"
            );
        }


        $nivelCliente = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['condicion_pago_original_id']);

        $nivelCotizacion = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['forma_pago_id']);
        $rebajaCotizacion = $this->approvalFuntions->getPorcentilDiscount('DESC_FORMA_PAGO_CODIGO', $data['forma_pago_id']);
        $prcCLienteDescuento = $this->approvalFuntions->getPorcentilDiscountCommercialCliente($data['ruc']);

        /** este descuento global lo utilizamos para calcular el descuento de la cabecera y del detalle de la cotizacion  */
        $dsctGlobalCliente = (float) $prcCLienteDescuento - (float) $rebajaCotizacion;

        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }
        if ($nivelCotizacion > $nivelCliente) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
        }


        if ($quoteOrigin) {

            // Nota: Mantenemos el != si quieres que sea estricto, o > si quieres que sea solo por pérdida
            if ((float) $data['descuento_articulo'] != (float) $data['descuento_original'] && (float) $data['descuento_articulo'] != (float) $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Porcentaje de descuento general modificado respecto al guardado. Nuevo: {$data['descuento_articulo']}% vs Guardado: {$data['descuento_original']}%"
                );
            }
        } else {
            // Si NO hay quoteOrigin (es una cotización NUEVA desde cero),
            // comparamos contra el original que envió el front (el default del sistema)
            if ((float) $data['descuento_articulo'] != (float) $data['descuento_original'] && (float) $data['descuento_articulo'] != (float) $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Porcentaje de descuento modificado. Nuevo: {$data['descuento_articulo']}% vs Original: {$data['descuento_original']}%"
                );
            }
        }


        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['punto_venta_id'])->first();


        $motivosDetalle = $this->validateQuotationDetailToApprove($data['items'], $ptoVenta, $dsctGlobalCliente);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }

        return $this->approvalFuntions->finalizeApprovalReasons($motivosAprobacionComercial);
    }


    public function validateQuotationDetailToApprove($detalles, $ptoVenta, $dsctGlobalCliente): array
    {
        $detailMotivos = [];
        $ids = collect($detalles)->pluck('detalle_id')->filter();
        $dbDetailQuote = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
        $dbDetailOrder = GpPedidoDetalle::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($detalles as $detalle) {

            $detailId = data_get($detalle, 'detalle_id');
            $type = data_get($detalle, 'tipo', 'cotizacion');
            $detailOrigin = $type == 'cotizacion' ? $dbDetailQuote->get($detailId) : $dbDetailOrder->get($detailId);


            // ---------------------------------------------------------
            // NORMALIZACIÓN DE VALORES (El secreto para no duplicar código)
            // ---------------------------------------------------------


            // 2. Descripción de Referencia
            $refDescripcion =  $detailOrigin ? $detailOrigin->ART_DESCRIPCION : data_get($detalle, 'ART_DESCRIPCION_ORIGINAL');
            // 3. Valor de Referencia
            $refValor = $detailOrigin ? $detailOrigin->ART_VALOR : data_get($detalle, 'ART_VALOR_ORIGINAL', 0);

            if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $dsctGlobalCliente) {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Descuento del detalle modificado. Nuevo: {$detalle['descuento']}% vs Original: {$dsctGlobalCliente}%");
            }

            // validacion de descripcion difernete a tipo L
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                if ($detalle['ART_DESCRIPCION'] != $refDescripcion) {
                    $descNueva = substr($detalle['ART_DESCRIPCION'], 0, 20);
                    $descRef = substr($refDescripcion, 0, 20);
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("La descripción del producto cambió. Nueva: '$descNueva...' vs Original: '$descRef...'");
                }
                $price    = round((float)$detalle['ART_VALOR'], 4);
                $priceOrigin = round((float)$refValor, 4);
                if ($price != $priceOrigin) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ha cambiado. Nuevo: {$price} vs Original: $priceOrigin");
                }

                /** proceso para calculo de porcetaje maximo de precio de venta */
                $exceso = 0;
                $excesoPct = 0;
                $valorOriginal = (float) $refValor;
                $porcMaxSubida = (float) $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                $precioLimite = $valorOriginal * (1 + ($porcMaxSubida / 100));
                $precioActual = (float) $detalle['ART_VALOR'];

                if ($precioActual > $precioLimite) {
                    $exceso = $precioActual - $precioLimite;
                    $excesoPct = round(($exceso / $valorOriginal) * 100, 2);
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                        . "(límite: {$precioLimite}), excede en {$exceso} "
                        . "({$excesoPct}% sobre el valor original).");
                }
            }
        }

        // validaciones para tipo L
        if ($detalle['ART_TIPO_COMPRA'] == 'L') {
            //validacion de precio de item L
            $valorOriginalL = round(data_get($detalle, 'valor_original_l', 0), 4);
            $valor = round(data_get($detalle, 'ART_VALOR', 0), 4);

            if ($valorOriginalL > $valor) {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ($" . $valor . ") es INFERIOR al precio mínimo permitido ($" . $valorOriginalL . ")");
            }

            $porcentajeMargen = $this->approvalFuntions->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
            if (!$porcentajeMargen) {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
            }
            $margenConfigurado = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
            foreach ($detalle['proveedores'] as $proveedor) {
                if ((float)$proveedor['MARGEN'] < (float)$margenConfigurado) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                }
            }

            /** validacion del precio total del proveedor <= precio de $300 */
            $providersDetail = data_get($detalle, 'proveedores', []);
            $ids = collect($providersDetail)->pluck('id')->filter();
            $proveedoresOrigen = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
            foreach ($providersDetail as $provider) {
                $hasId = data_get($provider, 'id');
                $valorNuevo = round(data_get($provider, 'TOTAL', 0), 4);
                if ($valorNuevo >= 300) {
                    continue;
                }
                $providerBD = $proveedoresOrigen->get($hasId);
                $valorOriginal = round(data_get($providerBD, 'TOTAL', 0), 4);

                if ($valorNuevo < 300) {
                    $mensaje = "Aprobación requerida: El total del proveedor ha bajado a $" . number_format($valorNuevo, 2);
                    if ($valorOriginal >= 300) {
                        $mensaje .= " (Anteriormente era $" . number_format($valorOriginal, 2) . ")";
                    }
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse($mensaje);
                    continue;
                }
            }
        }

        return $detailMotivos;
    }
}

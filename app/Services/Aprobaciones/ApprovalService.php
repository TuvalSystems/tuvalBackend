<?php

namespace App\Services\Aprobaciones;


use App\Models\GpPedido;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Traits\RegisterAudit;


class ApprovalService implements ApprovalServiceInterface
{
    use JsonResponseTrait, RegisterAudit;

    public function __construct(
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly ApiGpService $apiGpService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly ApprovalFunctionsServiceInterface $approvalFuntions,
    ) {}


    /*
     *
     * aprobacion comercial tanto de pedido como de cotizaciones
     *
     */

    public function approveQuoteReservation(array $data)
    {
        $motivosAprobacionComercial = [];
        $registroVolverAPedir = null;
        $quoteId = data_get($data, 'cotizacion_id', null);
        $quoteOrigin = empty($quoteId) ? null : GpCotizacion::find($quoteId);
        $idRegistroCopiaInput = data_get($data, 'registro_copia');
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

        $nivelCliente           = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);
        $nivelCotizacion        = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        $rebajaCotizacion       = $this->approvalFuntions->getPorcentilDiscount('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        $prcCLienteDescuento    = $this->approvalFuntions->getPorcentilDiscountCommercialCliente($data['CLI_RUC']);

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
            if ((float) $data['porcentage_descuento'] != (float) $data['porcentage_descuentooriginal'] && (float) $data['porcentage_descuento'] != (float) $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Porcentaje de descuento general modificado respecto al guardado. Nuevo: {$data['porcentage_descuento']}% vs Guardado: {$data['porcentage_descuentooriginal']}%"
                );
            }
        } else {
            // Si NO hay quoteOrigin (es una cotización NUEVA desde cero),
            // comparamos contra el original que envió el front (el default del sistema)
            if ((float) $data['porcentage_descuento'] != (float) $data['porcentage_descuentooriginal'] && (float) $data['porcentage_descuento'] != (float) $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Porcentaje de descuento modificado. Nuevo: {$data['porcentage_descuento']}% vs Original: {$data['porcentage_descuentooriginal']}%"
                );
            }
        }


        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivosDetalle = $this->validateQuotationDetailToApprove($data['cotizacion'], $ptoVenta, $dsctGlobalCliente);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }
        return $this->approvalFuntions->finalizeApprovalReasons($motivosAprobacionComercial);
    }

    public function validateQuotationDetailToApprove(array $detalles, GpPuntoVenta $ptoVenta, float $dsctGlobalCliente): array
    {
        $detailMotivos  = [];
        $ids            = collect($detalles)->pluck('id')->filter();
        $dbDetailQuote  = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
        $dbDetailOrder  = GpPedidoDetalle::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($detalles as $detalle) {

            $detailId       = data_get($detalle, 'id');
            $type           = data_get($detalle, 'tipo', 'cotizacion');
            $detailOrigin   = $type == 'cotizacion' ? $dbDetailQuote->get($detailId) : $dbDetailOrder->get($detailId);


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
                    $descNueva          = substr($detalle['ART_DESCRIPCION'], 0, 20);
                    $descRef            = substr($refDescripcion, 0, 20);
                    $detailMotivos[]    = $this->approvalFuntions->requiredApprovalResponse("La descripción del producto cambió. Nueva: '$descNueva...' vs Original: '$descRef...'");
                }

                $price          = round((float)$detalle['ART_VALOR'], 4);
                $priceOrigin    = round((float)$refValor, 4);
                if ($price != $priceOrigin) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ha cambiado. Nuevo: {$price} vs Original: $priceOrigin");
                }

                /** proceso para calculo de porcetaje maximo de precio de venta */
                $exceso         = 0;
                $excesoPct      = 0;
                $valorOriginal  = (float) $refValor;
                $porcMaxSubida  = (float) $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                $precioLimite   = $valorOriginal * (1 + ($porcMaxSubida / 100));
                $precioActual   = (float) $detalle['ART_VALOR'];
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
            $valorOriginalL = round(data_get($detalle, 'valor_original_l', 0), 4);
            $valor          = round(data_get($detalle, 'ART_VALOR', 0), 4);

            if ($valorOriginalL > $valor) {
                $detailMotivos[]    = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ($" . $valor . ") es INFERIOR al precio mínimo permitido ($" . $valorOriginalL . ")");
            }

            $porcentajeMargen       = $this->approvalFuntions->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
            if (!$porcentajeMargen) {
                $detailMotivos[]    = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
            }
            $margenConfigurado      = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
            foreach ($detalle['proveedores'] as $proveedor) {

                if ((float)$proveedor['MARGEN'] < (float)$margenConfigurado) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                }
            }

            /** validacion del precio total del proveedor <= precio de $300 */
            $providersDetail = data_get($detalle, 'proveedores', []);
            foreach ($providersDetail as $provider) {
                $valorProvider = round(data_get($provider, 'TOTAL', 0), 4);
                if ($valorProvider < 300) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse(
                        "El valor total del proveedor ({$valorProvider}) está por debajo del límite permitido de $300."
                    );
                }
            }
        }

        return $detailMotivos;
    }





    public function checkOutApprovalCommercialReservedOrder(array $data)
    {
        $motivosAprobacionComercial = [];
        $quoteId = data_get($data, 'id', null);
        $cotizacion = GpCotizacion::find($quoteId);

        $rgCopy = GpRegistroCopia::where('cotizacion_destino', $quoteId)->where('tipo', 'volver_a_pedir')->where('destino_aprobado', false)->first();
        if ($rgCopy) {
            $esPedido = !empty($rgCopy->pedido_origen);
            $idOrigen = $esPedido ? $rgCopy->pedido_origen : $rgCopy->cotizacion_origen;
            $origenEntidad = $esPedido ? GpPedido::find($idOrigen) : GpCotizacion::find($idOrigen);
            $desde = $esPedido ? 'pedido' : 'cotizacion';
            $prefijo = $esPedido ? 'PED' : 'COT';
            $codigoVisual = $prefijo . str_pad($idOrigen, 8, "0", STR_PAD_LEFT);
            $fechaTxt = optional($origenEntidad)->created_at
                ? optional($origenEntidad)->created_at->format('Y-m-d')
                : 'S/F';

            if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial === 'yes' && $origenEntidad->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Requiere aprobación comercial. La Cotización del pedido es desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt"
                );
            }
        }


        $nivelCliente           = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);
        $nivelCotizacion        = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        $rebajaCotizacion       = $this->approvalFuntions->getPorcentilDiscount('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        $prcCLienteDescuento    = $this->approvalFuntions->getPorcentilDiscountCommercialCliente($cotizacion->CLI_RUC);
        $dsctGlobalCliente      = (float) $prcCLienteDescuento - (float) $rebajaCotizacion;
        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }
        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }


        if ($cotizacion) {
            $nivelCotizacionAnterior = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $cotizacion->DESC_FORMA_PAGO_CODIGO);
            if ($cotizacion->requiere_aprobacion_comercial === 'yes' && $cotizacion->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Requiere aprobación comercial. La cotización no fue aprobada con anterioridad (ID: $quoteId)."
                );
            }

            if ($nivelCotizacion > $nivelCotizacionAnterior && $nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }

            if ($cotizacion->porcentage_descuento != $cotizacion->porcentage_descuentooriginal && $cotizacion->porcentage_descuento != $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original:$cotizacion->porcentage_descuento > $cotizacion->porcentage_descuentooriginal");
            }
        } else {
            if ($nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }
            if ($cotizacion->porcentage_descuento != $cotizacion->porcentage_descuentooriginal && $cotizacion->porcentage_descuento != $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $cotizacion->porcentage_descuento > $cotizacion->porcentage_descuentooriginal");
            }
        }

        if ($nivelCotizacion > $nivelCliente && $data['COND_ID'] != $cotizacion->COND_ID) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
        }

        if ($data['porcentage_descuento'] > $data['porcentage_descuentooriginal'] && $data['porcentage_descuento'] != $cotizacion->porcentage_descuento) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[porcentage_descuento] > $data[porcentage_descuentooriginal]");
        }

        $ptoVenta       = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivosDetalle = $this->validateReservedDetailQuotationType($data['detallecotizacion'], $ptoVenta,  $dsctGlobalCliente);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }
        return $this->approvalFuntions->finalizeApprovalReasons($motivosAprobacionComercial);
    }


    public function validateReservedDetailQuotationType(array $detalles, GpPuntoVenta $ptoVenta, float $dsctGlobalCliente)
    {
        $detailMotivos = [];
        $ids = collect($detalles)->pluck('id')->filter();
        $dbDetails = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($detalles as $detalle) {
            $detailId       = data_get($detalle, 'id');
            $detOriginal    = $detailId ? $dbDetails->get($detailId) : null;

            // 1. DEFINIR LA "VERDAD" ORIGINAL (Normalización)
            // La lógica es: Si existe en BD, esa es la verdad. Si no (es nuevo), confiamos en el 'original' que envía el front.
            $valorOriginalReal      = $detOriginal ? $detOriginal->ART_VALOR : $detalle['ART_VALOR_ORIGINAL'];
            $descOriginalReal       = $detOriginal ? $detOriginal->ART_DESCRIPCION : $detalle['ART_DESCRIPCION_ORIGINAL'];
            $descuentoOriginalReal  = $detOriginal ? $detOriginal->descuento : $detalle['descuento_original'];


            //validaciones del descuento en caso de que sea pedido
            if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $dsctGlobalCliente && $detalle['descuento'] != $descuentoOriginalReal) {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Descuento del detalle modificado. Nuevo: {$detalle['descuento']}% vs Original: {$dsctGlobalCliente}%");
            }

            if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL'] && $detalle['ART_VALOR'] != $valorOriginalReal) {
                // Mensaje genérico de cambio
                $mensaje = "El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: {$detalle['ART_VALOR_ORIGINAL']}";

                // Si quieres mantener el mensaje específico que tenías para items existentes:
                if ($detOriginal) {
                    $mensaje = "El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: $valorOriginalReal";
                }
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse($mensaje);
            }

            // validacion de descripcion difernete a tipo L
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL'] && $detalle['ART_DESCRIPCION'] != $descOriginalReal) {
                    $detailMotivos[] =  $this->approvalFuntions->requiredApprovalResponse("La descripción del producto cambió. Nueva: '{$detalle['ART_DESCRIPCION']}...' vs Original: '$descOriginalReal...'");
                }
                // C.2 Exceso de Precio
                // Usamos la variable normalizada $valorOriginalReal como base
                if ($valorOriginalReal > 0) {
                    $porcMaxSubida  = $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                    $factor         = 1 + ($porcMaxSubida / 100);
                    $precioLimite   = $valorOriginalReal * $factor;
                    $precioActual   = $detalle['ART_VALOR'];
                    if ($precioActual > $precioLimite) {
                        $exceso = $precioActual - $precioLimite;
                        $excesoPct = round(($exceso / $valorOriginalReal) * 100, 2);

                        $detailMotivos[] =  $this->approvalFuntions->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                            . "(límite: {$precioLimite}), excede en {$exceso} "
                            . "({$excesoPct}% sobre el valor original).");
                    }
                }
            }

            // validaciones para tipo L
            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                $valorOriginalL = round(data_get($detalle, 'valor_original_l', 0), 4);
                $valor          = round(data_get($detalle, 'ART_VALOR', 0), 4);

                if ($valorOriginalL > $valor) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ($" . $valor . ") es INFERIOR al precio mínimo permitido ($" . $valorOriginalL . ")");
                }
                // ... (resto de tu lógica L se mantiene igual) ...
                // Solo recuerda revisar lo de las consultas N+1 aquí si el rendimiento es crítico
                $porcentajeMargen =  $this->approvalFuntions->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);

                if (!$porcentajeMargen) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
                } else {
                    foreach ($detalle['proveedores'] as $proveedor) {
                        $margenPermitido = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
                        if ($proveedor['MARGEN'] < $margenPermitido) {
                            $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                        }
                    }
                }

                /** validacion del precio total del proveedor <= precio de $300 */
                $providersDetail = data_get($detalle, 'proveedores', []);
                foreach ($providersDetail as $provider) {
                    $valorProvider = round(data_get($provider, 'TOTAL', 0), 4);
                    if ($valorProvider < 300) {
                        $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse(
                            "El valor total del proveedor ({$valorProvider}) está por debajo del límite permitido de $300."
                        );
                    }
                }
            }
        }

        return $detailMotivos;
    }

    /*** funcion que se encarga de verificar si va a requerir aprobacion crediciticia *** */

    public function checkoutApprovalCredit(string $condId, float $total, string $ruc)
    {
        if ($condId == 'CONTADO') {
            return 'no';
        }
        $cupoDisponible = $this->functionService->getClientCheckAvailableQuota($ruc);
        $cupo = data_get($cupoDisponible, 'cupoDisponibleGp', 0);

        if ($total > $cupo) {
            return 'Cupo';
        }

        $documentosVencidos = $this->functionService->getClientDocumentExpired($ruc);
        $pendiente = data_get($documentosVencidos, 'pendienteGpMc', 0);

        if ($pendiente > 0) {
            return 'Documentos Vencidos';
        }

        $documentosSinSoporte = $this->functionService->getClientDocumentWithoutSupport($ruc);
        $saldo = data_get($documentosSinSoporte, 'saldoPendiente', 0);

        if ($saldo > 0) {
            return 'Saldo Pendiente';
        }

        return 'no';
    }






}

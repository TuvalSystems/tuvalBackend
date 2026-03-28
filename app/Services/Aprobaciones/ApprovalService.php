<?php

namespace App\Services\Aprobaciones;

use App\Enums\Ventas\OrderStatus;
use App\Jobs\Aprobaciones\NotifyApprovalOrdersJob;
use DateTime;
use App\Models\GpPedido;
use Illuminate\Support\Arr;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\DB;
use App\Models\GpCotizacionDetalle;
use Illuminate\Support\Facades\Log;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpUsuario;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Carbon\Carbon;


class ApprovalService implements ApprovalServiceInterface
{
    use JsonResponseTrait;

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

    public function approveQuoteReservation($data)
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

    public function validateQuotationDetailToApprove($detalles, $ptoVenta, $dsctGlobalCliente): array
    {
        $detailMotivos  = [];
        $ids            = collect($detalles)->pluck('detalle_id')->filter();
        $dbDetailQuote  = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
        $dbDetailOrder  = GpPedidoDetalle::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($detalles as $detalle) {

            $detailId       = data_get($detalle, 'detalle_id');
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





    public function checkOutApprovalCommercialReservedOrder($data)
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


    public function validateReservedDetailQuotationType($detalles, $ptoVenta,  $dsctGlobalCliente)
    {
        $detailMotivos = [];
        $ids = collect($detalles)->pluck('detalle_id')->filter();
        $dbDetails = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($detalles as $detalle) {
            $detailId       = data_get($detalle, 'detalle_id');
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

    /*** aprovacion crediciticia *** */

    public function checkoutApprovalCredit($condId, $total, $ruc)
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



    /**
     * DEVOLUCIONES
     *
     *
     */
    public function checkoutApprovalDevoluciones($data)
    {

        $tipoDevolucion     = Arr::get($data, 'devolucion.tipo_devolucion');
        $volverAFacturar    = Arr::get($data, 'devolucion.volver_a_facturar', false);
        $detallePedido      = Arr::get($data, 'detalle', []);
        $fechaFactura       = Arr::get($data, 'pedido.fecha_factura', null);
        $daysDifference     = $this->daysDifference($fechaFactura);
        $hasTypeLItems = collect($detallePedido)->contains(
            fn($detalle) => strtoupper(data_get($detalle, 'ART_TIPO_COMPRA', '')) === 'L'
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
                ->whereNotIn('estado', [OrderStatus::ANULADA, OrderStatus::ELIMINADO, OrderStatus::RECHAZADO])
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

        $pedidoConsulta     = GpPedido::find($pedido['id']);
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
            ->contains(fn($item) =>
            (float) data_get($item, 'despachado', 0) > 0
                && data_get($item, 'despacho', false) === true);
    }



    /**
     * FUNCION PARA LA VERIFICACION DE STOCK DE LOS ARTICULOS DEL PEEDIDO ANTES DE APROBAR
     */

    public function getOrderApprovalData($data)
    {
        try {
            $quoteReservedOriginId  = $this->validateExistsReserve($data['cotizacion_id']);
            $detalles               = $this->getDetailArticlesWithStock($data['id']);
            $flatDetails            = $this->stockFormatterForUpdateApproval($detalles);
            $parmas                 = $this->stockFormatterParametersUpdateApproval($flatDetails);
            $stocksResult           = $this->getStockWarehouseAsyncAndFormantter($parmas);
            $newFormatStock         = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);
            $alert = $this->getAlertWithoutStockArticles($detalles, $newFormatStock, $data['id'], $quoteReservedOriginId);
            if ($alert) {
                return response()->json(['success' => false, 'message' => "Pedido {$data['pedido']} rechazado por falta de Stock :" . $alert], 200);
            }
            return $this->buildSuccessResponse("Pedido {$data['pedido']} aprobado", 200);
        } catch (\Throwable $th) {
            Log::error('Error al realizar aprobacion comercial (getOrderApprovalData)', [
                'error' => $th->getMessage(),
                'data' => $data
            ]);
            return $this->buildErrorResponse('Operación fallida, consulte con el administrador', 402);
        }
    }

    public function validateExistsReserve($quotationId)
    {
        $quoteReserve = GpCotizacion::where('origen', 'reserva')->find($quotationId);
        if (!$quoteReserve) {
            return null;
        }
        $logReserve = GpCotizacionReservaLog::where('cotizacion_destino_id', $quotationId)->first();
        if (!$logReserve) {
            return null;
        }
        return $logReserve?->cotizacion_origen_id;
    }


    public function getAlertWithoutStockArticles($detalles, $newFormatStock, $orderId = null, $quoteReservedOriginId = null): string|null
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockWarehouseService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, $orderId, $quoteReservedOriginId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }

        if ($allFormattedStock->isEmpty()) {
            return null;
        }
        $alert = "";
        $groupStock = $allFormattedStock->unique('stock_id')->groupBy(fn($item) => $item['STO_ART_ARTICULO'] . '|' . $item['STO_ART_BODEGA']);
        foreach ($groupStock as $group) {
            $firstItemStock     = $group->first();
            $art                = data_get($firstItemStock, 'STO_ART_ARTICULO', '');
            $bodega             = data_get($firstItemStock, 'STO_ART_BODEGA', '');
            $disponibleReal     = (float) data_get($firstItemStock, 'STO_ART_STOCK_DISPONIBLE', 0);
            $totalRequerido     = $group->sum('seleccionado');
            if ($disponibleReal < $totalRequerido) {
                $alert .= "El artículo <b>{$art}</b> en bodega <b>{$bodega}</b> tiene stock insuficiente.<br>" .
                    "Stock Disponible: <b>{$disponibleReal}</b> <br>" .
                    "Cantidad Requerida (Suma filas): <b>{$totalRequerido}</b><br><hr>";
            }
        }
        return empty($alert) ? null : $alert;
    }




    /**
     *  FUNCIONES PARA LA APROBACIÓN CREDITICIA DE PEDIDOS
     */


    public function approveCreditOrder($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])
            ->where('estado', OrderStatus::ESPERA)
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->aprobado_crediticio                = 'yes';
        $pedido->aprobador_crediticio_id            = $user->id;
        $pedido->fecha_hora_aprobacion_crediticia   = Carbon::now()->format('Y-m-d H:i:s');
        $pedido->save();
        $this->updateOrderStockOnCreditOrCommercialApproval($pedido, 'crediticia');

        DB::afterCommit(function () use ($pedido) {
            $this->invoiceService->checkSendInvoice($pedido);
        });
        return $pedido;
    }

    public function denyCreditApproval($data, $user)
    {

        $pedido = GpPedido::where('id', $data['id'])
            ->whereNotIn('estado', [OrderStatus::ELIMINADO, OrderStatus::ANULADA])
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->estado                     = OrderStatus::RECHAZADO;
        $pedido->aprobado_crediticio        = 'no';
        $pedido->aprobador_crediticio_id    = $user->id;
        $pedido->fecha_hora_rechazado       = Carbon::now()->format('Y-m-d H:i:s');
        $pedido->rechazado                  = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->updateStockOnOrderApproval($pedido);
        $this->cleanDetailOrderStock($pedido);
        return $pedido;
    }


    public function rejectCreditOrderForInsufficientStock($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])
            ->whereNotIn('estado', [OrderStatus::ELIMINADO, OrderStatus::ANULADA])
            ->lockForUpdate()->first();
        $pedido->estado                     = OrderStatus::RECHAZADO;
        $pedido->aprobado_crediticio        = 'no';
        $pedido->aprobador_crediticio_id    = $user->id;
        $pedido->fecha_hora_rechazado       = Carbon::now()->format('Y-m-d H:i:s');
        $pedido->rechazado                  = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->updateStockOnOrderApproval($pedido);
        $this->cleanDetailOrderStock($pedido);
        DB::afterCommit(function () use ($pedido, $data) {
            NotifyApprovalOrdersJob::dispatch('Pedido rechazado por falta de stock', $data['alert'], $pedido->user_id);
        });
        return [
            'pedido' => $pedido,
            'alert' => $data['alert']

        ];
    }

    public function cleanDetailOrderStock($pedido)
    {
        $bodega = $pedido->BOD_BODEGA;
        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->pluck('id')->toArray();
        GpPedidoDetalleStock::whereIn('detalle_id', $detalles)
            ->where('STO_ART_BODEGA', $bodega)->update(['reservado' => 0]);
    }



    /**  FUNCIONES DE APROBACIÓN COMERCIAL DE PEDIDOS Y COTIZACIONES  */

    public function approveComercialOrder($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])->where('estado', OrderStatus::ESPERA)
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->aprobado_comercial              = 'yes';
        $pedido->aprobador_comercial_id          = $user->id;
        $pedido->fecha_hora_aprobacion_comercial = Carbon::now()->format('Y-m-d H:i:s');
        $pedido->save();
        $this->updateOrderStockOnCreditOrCommercialApproval($pedido, 'comercial');
        DB::afterCommit(function () use ($pedido) {
            $this->invoiceService->checkSendInvoice($pedido);
        });
        return $pedido;
    }

    public function denyApproveComercialOrderForInsufficientStock($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->estado                     = OrderStatus::RECHAZADO;
        $pedido->aprobado_comercial         = 'no';
        $pedido->aprobador_comercial_id     = $user->id;
        $pedido->fecha_hora_rechazado       = date('Y-m-d H:i:s');
        $pedido->rechazado                  = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->updateStockOnOrderApproval($pedido);
        $this->cleanDetailOrderStock($pedido);
        DB::afterCommit(function () use ($pedido, $data) {
            NotifyApprovalOrdersJob::dispatch('Pedido rechazado por falta de stock', $data['alert'], $pedido->user_id);
        });
        return [
            'pedido' => $pedido,
            'alert' => $data['alert']

        ];
    }


    public function denyApproveComercialOrder($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->estado                 = OrderStatus::RECHAZADO;
        $pedido->aprobado_comercial     = 'no';
        $pedido->aprobador_comercial_id = $user->id;
        $pedido->fecha_hora_rechazado   = date('Y-m-d H:i:s');
        $pedido->rechazado              = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->updateStockOnOrderApproval($pedido);
        $this->cleanDetailOrderStock($pedido);
        DB::afterCommit(function () use ($pedido, $data) {
            NotifyApprovalOrdersJob::dispatch('Pedido rechazado', 'El pedido ha sido rechazado', $pedido->user_id);
        });
        return $pedido;
    }


    public function updateOrderStockOnCreditOrCommercialApproval($order, $typeApproval)
    {
        $requiresComercialApproval = $order->requiere_aprobacion_comercial === 'yes';
        $requiresCreditApproval = $order->requiere_aprobacion_crediticia === 'yes';
        $isComercialApproved = $order->aprobado_comercial === 'yes';
        $isCreditApproved = $order->aprobado_crediticio === 'yes';

        // solo requiere aprobacion comercial
        if ($requiresComercialApproval && !$requiresCreditApproval) {
            if ($isComercialApproved && !$isCreditApproved && $typeApproval == 'comercial') {
                $this->updateStockOnOrderApproval($order);
            }
        }

        // solo requiere aprobacion crediticia
        if (!$requiresComercialApproval && $requiresCreditApproval) {
            if (!$isComercialApproved && $isCreditApproved && $typeApproval == 'crediticia') {
                $this->updateStockOnOrderApproval($order);
            }
        }
        // requiere ambas aprobaciones
        if ($requiresComercialApproval && $requiresCreditApproval) {
            if ($isComercialApproved && $isCreditApproved && $typeApproval == 'crediticia') {
                $this->updateStockOnOrderApproval($order);
            }
        }
    }


    public function updateStockOnOrderApproval($order)
    {
        $quoteReservedOriginId = $this->validateExistsReserve($order->cotizacion_id);
        $detalles = $this->getDetailArticlesWithStock($order->id);
        if ($detalles->isEmpty()) {
            return;
        }
        $flatDetails = $this->stockFormatterForUpdateApproval($detalles);
        $parmas = $this->stockFormatterParametersUpdateApproval($flatDetails);

        $stocksResult = $this->getStockWarehouseAsyncAndFormantter($parmas);
        $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);

        $this->updateOrderDetailStockAndGetStockDisponible($detalles, $newFormatStock, $order->id, $quoteReservedOriginId);
    }

    public function getDetailArticlesWithStock($orderId)
    {
        return GpPedidoDetalle::with('pedidoDetalleStocks')
            ->where('pedido_id', $orderId)
            ->where('ART_TIPO_COMPRA', '!=', 'L')->get();
    }
    public function stockFormatterForUpdateApproval($detalles)
    {
        return $detalles->pluck('pedidoDetalleStocks')
            ->flatten()
            ->map(function ($stock) use ($detalles) {
                $detalle = $detalles->firstWhere('id', $stock->detalle_id);
                return [
                    'stock_id'                  => $stock->id,
                    'pedido_id'                 => $stock->pedido_id,
                    'detalle_id'                => $stock->detalle_id,
                    'STO_ART_ARTICULO'          => $detalle->ART_CODIGO,
                    'STO_ART_BODEGA'            => $stock->STO_ART_BODEGA,
                    'STO_ART_STOCK_DISPONIBLE'  => $stock->STO_ART_STOCK_DISPONIBLE,
                    'seleccionado'              => $stock->seleccionado
                ];
            })->toArray();
    }

    public function stockFormatterParametersUpdateApproval($newDetails)
    {
        return collect($newDetails)
            ->map(fn($d) => ['articulo' => $d['STO_ART_ARTICULO'], 'bodega' => $d['STO_ART_BODEGA']])
            ->unique()
            ->values()
            ->toArray();
    }

    public function getStockWarehouseAsyncAndFormantter($parmas)
    {
        $stocksResult = $this->stockWarehouseService->fetchStockBatchWarehouses($parmas);
        Log::info("Resultado de stock para aprobacion: ", [
            'stocksResult' => $stocksResult
        ]);
        return $this->stockWarehouseService->transformStockResponse($stocksResult);
    }



    public function formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult)
    {
        $stockMap = collect($stocksResult)->keyBy(function ($stock) {
            return $stock['STO_ART_ARTICULO'] . '|' . $stock['STO_ART_BODEGA'];
        });
        return collect($flatDetails)->map(function ($detail) use ($stockMap) {
            $key = $detail['STO_ART_ARTICULO'] . '|' . $detail['STO_ART_BODEGA'];
            $stockDisponible = $stockMap->get($key)['STO_ART_STOCK_DISPONIBLE'] ?? 0;
            return [
                'stock_id' => $detail['stock_id'],
                'pedido_id' => $detail['pedido_id'],
                'detalle_id' => $detail['detalle_id'],
                'STO_ART_ARTICULO' => $detail['STO_ART_ARTICULO'],
                'STO_ART_BODEGA' => $detail['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => $stockDisponible,
                'seleccionado' => $detail['seleccionado']
            ];
        });
    }




    public function updateOrderDetailStockAndGetStockDisponible($detalles, $newFormatStock, $orderId, $quoteReservedOriginId = null)
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockWarehouseService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, $orderId, $quoteReservedOriginId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }
        if ($allFormattedStock->isEmpty()) {
            return;
        }
        foreach ($allFormattedStock as $stock) {
            if (isset($stock['stock_id']) && ($st = GpPedidoDetalleStock::find($stock['stock_id']))) {
                $st->STO_ART_STOCK_DISPONIBLE = $stock['STO_ART_STOCK_DISPONIBLE'];
                $st->reservado = $stock['seleccionado'];
                $st->save();
            }
        }
    }




    public function accountStatusClient($ruc)
    {
        $detalle = [];
        $detalle['cupoDisponible'] = $this->functionService->getClientCheckAvailableQuota($ruc);
        $detalle['documentosVencidos'] = $this->functionService->getListClientDocumentExpired($ruc);
        $detalle['documentosSinSoporte'] = $this->functionService->getListClientDocumentWithoutSupport($ruc);
        return $detalle;
    }


    public function denyQuotationBeenWaiting(int $id, GpUsuario $user)
    {
        $quote = GpCotizacion::find($id);
        $quote->estado = 'rechazado';
        $quote->fe_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $quote->user_rechazo_id = $user->id;
        $quote->save();
        return $quote->refresh();
    }


    public function denyOrderBeenWaiting(int $id,  GpUsuario $user)
    {
        $order = GpPedido::find($id);
        $order->estado = OrderStatus::RECHAZADO;
        $order->user_rechazo_id = $user->id;
        $order->fecha_hora_rechazado = Carbon::now()->format('Y-m-d H:i:s');
        $order->rechazado = 1;
        $order->save();
        return $order;
    }


    /** aprobacion comercial de cotizaciones */
    public function approveComercialQuote($id, $user)
    {
        $gpUser = GpUsuario::find($user->id);
        $quote = GpCotizacion::where('id', $id)->where('estado', 'espera')
            ->lockForUpdate()->first();

        if (!$quote) {
            abort(404, "Cotización no encontrada");
        }
        $quote->aprobado_comercial = 'yes';
        $quote->aprobador_comercial_id = $gpUser->id;
        $quote->estado = 'aprobado';
        $quote->fecha_hora_aprobacion_comercial = Carbon::now()->format('Y-m-d H:i:s');
        $quote->save();
        $this->updateRegisterCopyApprovalComercialDestination($quote->id);
        return $quote;
    }

    public function updateRegisterCopyApprovalComercialDestination($quoteId)
    {
        $rgCopy = GpRegistroCopia::where('cotizacion_destino', $quoteId)->where('tipo', 'volver_a_pedir')->first();
        if (!$rgCopy) {
            return;
        }
        $rgCopy->destino_aprobado = true;
        $rgCopy->save();
    }
}

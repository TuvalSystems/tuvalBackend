<?php

namespace App\Services\Aprobaciones;

use App\Jobs\Aprobaciones\NotifyApprovalOrdersJob;
use App\Models\aprobacion\GpReglaAprobacion;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpTempCart;
use DateTime;
use App\Models\GpPedido;
use App\Models\GpArticulo;
use Illuminate\Support\Arr;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpDatosItemsL;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Services\Api\MswService;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\DB;
use App\Models\GpCotizacionDetalle;
use Illuminate\Support\Facades\Log;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpUsuario;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ApprovalService implements ApprovalServiceInterface
{
    use JsonResponseTrait;
    protected $functionService;
    protected $invoiceService;
    protected $mswService;
    protected $apiGpService;
    protected $stockWarehouseService;
    public function __construct(
        FunctionsServiceInterface $functionService,
        InvoiceServiceInterface $invoiceService,
        MswService $mswService,
        ApiGpService $apiGpService,
        StockWarehouseServiceInterface $stockWarehouseServiceInterface
    ) {
        $this->functionService = $functionService;
        $this->invoiceService = $invoiceService;
        $this->mswService = $mswService;
        $this->apiGpService = $apiGpService;
        $this->stockWarehouseService = $stockWarehouseServiceInterface;
    }


    /*
     *
     * aprobacion comercial tanto de pedido como de cotizaciones
     *
     */

    public function checkoutApprovalCommercialQuotations($data)
    {
        $quoteId = data_get($data, 'cotizacion_id', null);

        $quoteOrigin = empty($quoteId) ? null : GpCotizacion::find($quoteId);
        $motivosAprobacionComercial = [];
        $rgcopy = data_get($data, 'registro_copia', null);

        if ($rgcopy) {
            $registroVolverAPedir = GpRegistroCopia::where('id', $data['registro_copia'])->where('tipo', 'volver_a_pedir')->first();
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
                    $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                        "Cotización desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt"
                    );
                }

                // 2. Motivo por ser copia (siempre se agrega si existe el registro)
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Cotización desde Volver a Pedir ($desde original: $codigoVisual, fecha: $fechaTxt)"
                );

            }
        }


        $nivelCliente = $this->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);
        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        $nivelCotizacion = $this->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }
        if ($nivelCotizacion > $nivelCliente) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
        }

        if ($quoteOrigin) {

            // 0. VALIDACIÓN DE ESTADO PREVIO (Persistencia)
            // Si la cotización guardada YA requiere aprobación y NO ha sido aprobada aún,
            // forzamos la aprobación de nuevo. Esto evita que al "Guardar" y "Recuperar" se salte el check.
            if ($quoteOrigin->requiere_aprobacion_comercial === 'yes' && $quoteOrigin->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "La cotización recuperada se encuentra pendiente de aprobación previa (ID: $quoteId)."
                );
            }

            // 3. Validación Descuento General (Comparación de Valores)
            // Ahora comparamos valores. Incluso si son iguales (20% vs 20%),
            // el bloque de arriba (0) ya habrá atrapado la solicitud.
            $pctOriginalRef = $quoteOrigin->porcentage_descuento;

            // Nota: Mantenemos el != si quieres que sea estricto, o > si quieres que sea solo por pérdida
            if ((float) $data['porcentage_descuento'] != (float) $pctOriginalRef) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Porcentaje de descuento general modificado respecto al guardado. Nuevo: {$data['porcentage_descuento']}% vs Guardado: $pctOriginalRef%"
                );
            }
        } else {
            // Si NO hay quoteOrigin (es una cotización NUEVA desde cero),
            // comparamos contra el original que envió el front (el default del sistema)
            if ((float) $data['porcentage_descuento'] != (float) $data['porcentage_descuentooriginal']) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Porcentaje de descuento modificado. Nuevo: {$data['porcentage_descuento']}% vs Original: {$data['porcentage_descuentooriginal']}%"
                );
            }
        }




        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivosDetalle = $this->validateQuotationDetailToApprove($data['cotizacion'], $ptoVenta);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }

        return $this->finalizeApprovalReasons($motivosAprobacionComercial);
    }

    public function validateQuotationDetailToApprove($detalles, $ptoVenta): array
    {
        $detailMotivos = [];
        $ids = collect($detalles)->pluck('detalle_id')->filter();
        $dbDetails = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($detalles as $detalle) {
            $detailId = data_get($detalle, 'detalle_id');
            $detailOrigin = $detailId ? $dbDetails->get($detailId) : null;


            // ---------------------------------------------------------
            // NORMALIZACIÓN DE VALORES (El secreto para no duplicar código)
            // ---------------------------------------------------------

            // 1. Descuento de Referencia
            $refDescuento = $detailOrigin ? $detailOrigin->descuento : $detalle['descuento_original'];

            // 2. Descripción de Referencia
            $refDescripcion = $detailOrigin ? $detailOrigin->ART_DESCRIPCION : $detalle['ART_DESCRIPCION_ORIGINAL'];

            // 3. Valor de Referencia
            $refValor = $detailOrigin ? $detailOrigin->ART_VALOR : $detalle['ART_VALOR_ORIGINAL'];

            if ($detalle['descuento'] != $refDescuento) {
                $detailMotivos[] = $this->requiredApprovalResponse("Descuento del detalle modificado. Nuevo: {$detalle['descuento']}% vs Original: {$refDescuento}%");
            }

            // validacion de descripcion difernete a tipo L
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                if ($detalle['ART_DESCRIPCION'] != $refDescripcion) {
                    $descNueva = substr($detalle['ART_DESCRIPCION'], 0, 20);
                    $descRef = substr($refDescripcion, 0, 20);
                    $detailMotivos[] = $this->requiredApprovalResponse("La descripción del producto cambió. Nueva: '$descNueva...' vs Original: '$descRef...'");
                }

                if ($detalle['ART_VALOR'] != (float) $refValor) {
                    $detailMotivos[] = $this->requiredApprovalResponse("El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: $refValor");
                }

                /** proceso para calculo de porcetaje maximo de precio de venta */
                $exceso = 0;
                $excesoPct = 0;
                $valorOriginal = (float) $refValor;
                $porcMaxSubida = (float) $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                // $factor = 1 + ($porcMaxSubida / 100);
                // $precioLimite = $valorOriginal * $factor;
                $precioLimite = $valorOriginal * (1 + ($porcMaxSubida / 100));
                $precioActual = (float) $detalle['ART_VALOR'];

                if ($precioActual > $precioLimite) {
                    $exceso = $precioActual - $precioLimite;
                    $excesoPct = round(($exceso / $valorOriginal) * 100, 2);
                    $detailMotivos[] = $this->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                        . "(límite: {$precioLimite}), excede en {$exceso} "
                        . "({$excesoPct}% sobre el valor original).");
                }
            }
        }

        // validaciones para tipo L
        if ($detalle['ART_TIPO_COMPRA'] == 'L') {
            $porcentajeMargen = $this->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
            if (!$porcentajeMargen) {
                $detailMotivos[] = $this->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
            }
            $margenConfigurado = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
            foreach ($detalle['proveedores'] as $proveedor) {

                $denominadorMargen = 1 - ($margenConfigurado / 100);
                $denominadorDesc = 1 - ($detalle['descuento'] / 100);
                // Evitar división por cero
                if ($denominadorMargen == 0 || $denominadorDesc == 0) {
                    $detailMotivos[] = $this->requiredApprovalResponse("Error en cálculo de márgenes (división por cero).");
                    continue;
                }

                $valorMinimoCalculado = ($proveedor['COSTO_UNITARIO'] / $denominadorMargen) / $denominadorDesc;
                $valorMinimoCalculado = round($valorMinimoCalculado, 4);

                if ($valorMinimoCalculado > $detalle['ART_VALOR']) {
                    $detailMotivos[] = $this->requiredApprovalResponse("Valor del producto L es mayor al valor del precio minimo");
                }
                if ($proveedor['MARGEN'] < $margenConfigurado) {
                    $detailMotivos[] = $this->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                }
            }
        }

        return $detailMotivos;
    }

    /***
     *  aprobacion comercial de pedidos
     *
     */

    public function checkoutApprovalCommercialOrders($data)
    {
        $quoteId = data_get($data, 'cotizacion_id', null);
        $quoteOrigin = empty($quoteId) ? null : GpCotizacion::find($quoteId);
        $motivosAprobacionComercial = [];

        $rgCopy = GpRegistroCopia::where('cotizacion_destino', $data['cotizacion_id'])->where('tipo', 'volver_a_pedir')->first();
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
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Requiere aprobación comercial. La Cotización del pedido es desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt"
                );
            }

            // 2. Motivo por ser copia (siempre se agrega si existe el registro)
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                "Requiere aprobación comercial. La Cotización desde Volver a Pedir ($desde original: $codigoVisual, fecha: $fechaTxt)"
            );
        }


        $nivelCliente = $this->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);
        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        $nivelCotizacion = $this->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }


        if ($quoteOrigin) {
            if ($quoteOrigin->requiere_aprobacion_comercial === 'yes' && $quoteOrigin->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Requiere aprobación comercial. La cotización no fue aprobada con anterioridad (ID: $quoteId)."
                );
            }
            if ($nivelCotizacion > $nivelCliente && $data['COND_ID'] != $quoteOrigin->COND_ID) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }

            if ($data['porcentage_descuento'] > $data['porcentage_descuentooriginal'] && $data['porcentage_descuento'] != $quoteOrigin->porcentage_descuento) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[porcentage_descuento] > $data[porcentage_descuentooriginal]");
            }
        } else {
            if ($nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }
        }

        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivosDetalle = $this->validateOrdersDetailToApprove($data['cotizacion'], $ptoVenta);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }

        return $this->finalizeApprovalReasons($motivosAprobacionComercial);
    }

    public function validateOrdersDetailToApprove($detalles, $ptoVta): array
    {
        $detailMotivos = [];

        // Pre-cargar detalles de BD
        $ids = collect($detalles)->pluck('detalle_id')->filter();
        $dbDetails = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($detalles as $detalle) {
            $detailId = data_get($detalle, 'detalle_id');
            $detOriginal = $detailId ? $dbDetails->get($detailId) : null;

            // 1. DEFINIR LA "VERDAD" ORIGINAL (Normalización)
            // La lógica es: Si existe en BD, esa es la verdad. Si no (es nuevo), confiamos en el 'original' que envía el front.
            $valorOriginalReal = $detOriginal ? $detOriginal->ART_VALOR : $detalle['ART_VALOR_ORIGINAL'];
            $descOriginalReal = $detOriginal ? $detOriginal->ART_DESCRIPCION : $detalle['ART_DESCRIPCION_ORIGINAL'];
            $descuentoOriginalReal = $detOriginal ? $detOriginal->descuento : $detalle['descuento_original'];

            // --- VALIDACIONES COMUNES ---

            // A. Validación de Descuento
            // Comparamos directamente contra la variable normalizada
            if ($detalle['descuento'] != $descuentoOriginalReal) {
                $detailMotivos[] = $this->requiredApprovalResponse("Descuento del detalle diferente al descuento original - ID: " . ($detailId ?? 'Nuevo'));
            }

            // B. Validación de Valor (Precio)
            // Comparamos directamente contra la variable normalizada
            if ($detalle['ART_VALOR'] != $valorOriginalReal) {
                // Mensaje genérico de cambio
                $mensaje = "El valor del producto es diferente al valor original";

                // Si quieres mantener el mensaje específico que tenías para items existentes:
                if ($detOriginal) {
                    $mensaje = "Valor diferente al detalle Original - {$detalle['detalle_id']} - {$detalle['ART_VALOR']}";
                }

                $detailMotivos[] = $this->requiredApprovalResponse($mensaje);
            }

            // C. Validaciones NO Tipo L (Standard)
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                // C.1 Descripción
                if ($detalle['ART_DESCRIPCION'] != $descOriginalReal) {
                    $detailMotivos[] = $this->requiredApprovalResponse("La descripcion del producto es diferente a la original");
                }

                // C.2 Exceso de Precio
                // Usamos la variable normalizada $valorOriginalReal como base
                if ($valorOriginalReal > 0) {
                    $porcMaxSubida = $ptoVta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                    $factor = 1 + ($porcMaxSubida / 100);
                    $precioLimite = $valorOriginalReal * $factor;
                    $precioActual = $detalle['ART_VALOR'];

                    if ($precioActual > $precioLimite) {
                        $exceso = $precioActual - $precioLimite;
                        $excesoPct = round(($exceso / $valorOriginalReal) * 100, 2);

                        $detailMotivos[] = $this->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                            . "(límite: {$precioLimite}), excede en {$exceso} "
                            . "({$excesoPct}% sobre el valor original).");
                    }
                }
            }

            // D. Validaciones Tipo L
            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                // ... (resto de tu lógica L se mantiene igual) ...
                // Solo recuerda revisar lo de las consultas N+1 aquí si el rendimiento es crítico
                $porcentajeMargen = $this->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);

                if (!$porcentajeMargen) {
                    $detailMotivos[] = $this->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
                } else {
                    foreach ($detalle['proveedores'] as $proveedor) {
                        $porvOrigin = $this->getProviderToValidateMargen($proveedor);

                        // ... Lógica de calculo ...
                        $marginOld = (float) ($porvOrigin->MARGEN ?? 0);
                        $marginNew = (float) ($proveedor['MARGEN'] ?? 0);
                        $EPS = 0.0001;

                        if ($porvOrigin && abs($marginOld - $marginNew) < $EPS) {
                            continue;
                        }

                        $margenPermitido = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
                        $divisorMargen = (1 - ($margenPermitido / 100));
                        $divisorDescuento = (1 - ($detalle['descuento'] / 100));

                        if ($divisorMargen > 0 && $divisorDescuento > 0) {
                            $valorCalculado = number_format(($proveedor['COSTO_UNITARIO'] / $divisorMargen) / $divisorDescuento, 4, '.', '');
                            if ($valorCalculado > $detalle['ART_VALOR']) {
                                $detailMotivos[] = $this->requiredApprovalResponse("Valor del producto L es mayor al valor del precio minimo");
                            }
                        }
                        if ($proveedor['MARGEN'] < $margenPermitido) {
                            $detailMotivos[] = $this->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                        }
                    }
                }
            }
        }

        return $detailMotivos;
    }
    // public function checkOutApprovalCommercial($data, $type)
    // {
    //     $return = ['requiere' => 'no', 'motivo' => null];
    //     // validacion para "volver a pedir"
    //     if ($type == 'cotizacion') {
    //         $registroVolverAPedir = GpRegistroCopia::where('id', $data['registro_copia'])->where('tipo', 'volver_a_pedir')->first();
    //         if ($registroVolverAPedir) {
    //             $desde = $registroVolverAPedir->pedido_origen ? 'pedido' : 'cotizacion';
    //             $origen = $registroVolverAPedir->pedido_origen
    //                 ? 'PED' . str_pad($registroVolverAPedir->pedido_origen, 8, "0", STR_PAD_LEFT)
    //                 : 'COT' . str_pad($registroVolverAPedir->cotizacion_origen, 8, "0", STR_PAD_LEFT);
    //             $fechaOrigen = $registroVolverAPedir->pedido_origen
    //                 ? optional(GpPedido::find($registroVolverAPedir->pedido_origen))->created_at
    //                 : optional(GpCotizacion::find($registroVolverAPedir->cotizacion_origen))->created_at;

    //             $origenEntidad = $registroVolverAPedir->pedido_origen
    //                 ? GpPedido::find($registroVolverAPedir->pedido_origen)
    //                 : GpCotizacion::find($registroVolverAPedir->cotizacion_origen);

    //             // para validar si el origen esta en espera y no ha sido aprobado
    //             if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial == 'yes' && $origenEntidad->aprobado_comercial != 'yes') {
    //                 return $this->requiredApprovalResponse("Cotización desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $origen, fecha: " . substr($fechaOrigen, 0, 10));
    //             }

    //             // Motivo normal por ser copia
    //             return $this->requiredApprovalResponse("Cotización desde 'Volver a Pedir' ($desde original: $origen, fecha: " . substr($fechaOrigen, 0, 10) . ")");
    //         }
    //     }

    //     if ($type == 'pedido') {
    //         $registroVolverAPedir = GpRegistroCopia::where('cotizacion_destino', $data['cotizacion_id'])->where('tipo', 'volver_a_pedir')->first();
    //         if ($registroVolverAPedir) {
    //             $desde = $registroVolverAPedir->pedido_origen ? 'pedido' : 'cotizacion';
    //             $origen = $registroVolverAPedir->pedido_origen
    //                 ? 'PED' . str_pad($registroVolverAPedir->pedido_origen, 8, "0", STR_PAD_LEFT)
    //                 : 'COT' . str_pad($registroVolverAPedir->cotizacion_origen, 8, "0", STR_PAD_LEFT);
    //             $fechaOrigen = $registroVolverAPedir->pedido_origen
    //                 ? optional(GpPedido::find($registroVolverAPedir->pedido_origen))->created_at
    //                 : optional(GpCotizacion::find($registroVolverAPedir->cotizacion_origen))->created_at;

    //             $origenEntidad = $registroVolverAPedir->pedido_origen
    //                 ? GpPedido::find($registroVolverAPedir->pedido_origen)
    //                 : GpCotizacion::find($registroVolverAPedir->cotizacion_origen);

    //             // para validar si el origen esta en espera y no ha sido aprobado
    //             if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial == 'yes' && $origenEntidad->aprobado_comercial != 'yes') {
    //                 return $this->requiredApprovalResponse("Requiere aprobación comercial. La Cotización del pedido es desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $origen, fecha: " . substr($fechaOrigen, 0, 10));
    //             }

    //             // Motivo normal por ser copia
    //             return $this->requiredApprovalResponse("Requiere aprobación comercial. Cotización del pedido es desde 'Volver a Pedir' ($desde original: $origen, fecha: " . substr($fechaOrigen, 0, 10) . ")");
    //         }

    //         $cotizacion = GpCotizacion::find($data['cotizacion_id']);
    //         if ($cotizacion && $cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial != 'yes') {
    //             return $this->requiredApprovalResponse("Requiere aprobación comercial. La cotización  {$cotizacion->cotizacion} no fue aprobada con anterioridad.");
    //         }
    //     }

    //     $nivelCliente = $this->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);
    //     if (!$nivelCliente) {
    //         return $this->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
    //     }

    //     $nivelCotizacion = $this->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
    //     if (!$nivelCotizacion) {
    //         return $this->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
    //     }

    //     if ($type == 'cotizacion') {
    //         if ($nivelCotizacion > $nivelCliente) {
    //             return $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
    //         }

    //         if ($data['porcentage_descuento'] > $data['porcentage_descuentooriginal']) {
    //             return $this->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[porcentage_descuento] > $data[porcentage_descuentooriginal]");
    //         }
    //     }

    //     if ($type == 'pedido') {
    //         $cotizacion = GpCotizacion::find($data['cotizacion_id']);

    //         if ($nivelCotizacion > $nivelCliente && $data['COND_ID'] != $cotizacion->COND_ID) {
    //             return $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
    //         }

    //         if ($data['porcentage_descuento'] > $data['porcentage_descuentooriginal'] && $data['porcentage_descuento'] != $cotizacion->porcentage_descuento) {
    //             return $this->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[porcentage_descuento] > $data[porcentage_descuentooriginal]");
    //         }
    //     }


    //     // validacion de detalle de cotizaciony pedido e items L
    //     $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
    //     $validarDetalles = $this->validateDetailQuotationType($data['cotizacion'], $type, $ptoVenta);
    //     if ($validarDetalles)
    //         return $validarDetalles;

    //     //validacion de cotizacion forma de pago
    //     if ($type == 'cotizacion') {
    //         if (!empty($data['cotizacion_id']) && !empty($data['cotizacion'])) {
    //             $cotizacion = GpCotizacion::find($data['cotizacion_id']);
    //             if ($this->validarCambioFormaPago($nivelCliente, $nivelCotizacion, $cotizacion)) {
    //                 $cotizacion->aprobado_comercial = 'no';
    //                 $cotizacion->save();
    //                 return $this->requiredApprovalResponse("Forma de pago de cotización mayor que la del cliente modificando cotización: $nivelCotizacion - $nivelCliente");
    //             }

    //             if ($cotizacion->requiere_aprobacion_comercial === 'yes' && $cotizacion->aprobado_comercial === 'yes' && abs($data['total'] - $cotizacion->total) <= 0.10) {
    //                 return $this->requiredNotApprovalResponse("Total dentro del rango permitido");
    //             }
    //         }
    //     }



    //     return $return;
    // }

    public function requiredApprovalResponse(string $motivo)
    {
        return ['requiere' => 'yes', 'motivo' => $motivo];
    }

    public function requiredNotApprovalResponse(string $motivo)
    {
        return ['requiere' => 'no', 'motivo' => $motivo];
    }


    public function validarCambioFormaPago($nivelCliente, $nivelCotizacion, $cotizacion)
    {
        $nivelAnterior = $this->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $cotizacion->DESC_FORMA_PAGO_CODIGO);
        return ($nivelCotizacion > $nivelAnterior && $nivelCotizacion > $nivelCliente);
    }
    public function getNivelFormaPago($columna, $codigo)
    {
        $formaPago = GpDescuentoFormaPago::where($columna, $codigo)
            ->orderBy('DESC_FORMA_PAGO_CODIGO')
            ->first();

        return $formaPago?->nivel;
    }

    // public function validateDetailQuotationType(array $detalles, string $type = 'cotizacion', $ptoVenta)
    // {

    //     foreach ($detalles as $detalle) {
    //         if ($type == 'cotizacion') {
    //             if ($detalle['descuento'] != $detalle['descuento_original']) {
    //                 return $this->requiredApprovalResponse("Descuento del detalle diferente al descuento original - {$detalle['descuento']}");
    //             }

    //             // validacion de descripcion difernete a tipo L
    //             if ($detalle['ART_TIPO_COMPRA'] != 'L') {
    //                 if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL']) {
    //                     return $this->requiredApprovalResponse("La descripcion del producto es diferente a la original");
    //                 }

    //                 if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL']) {
    //                     return $this->requiredApprovalResponse("El valor del producto es menor que el valor original");
    //                 }

    //                 /** proceso para calculo de porcetaje maximo de precio de venta */
    //                 $exceso = 0;
    //                 $excesoPct = 0;
    //                 $valorOriginal = $detalle['ART_VALOR_ORIGINAL'];
    //                 $porcMaxSubida = $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
    //                 $factor = 1 + ($porcMaxSubida / 100);
    //                 $precioLimite = $valorOriginal * $factor;
    //                 $precioActual = $detalle['ART_VALOR'];

    //                 if ($precioActual > $precioLimite) {
    //                     $exceso = $precioActual - $precioLimite;
    //                     $excesoPct = round(($exceso / $valorOriginal) * 100, 2);
    //                     return $this->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
    //                         . "(límite: {$precioLimite}), excede en {$exceso} "
    //                         . "({$excesoPct}% sobre el valor original).");
    //                 }
    //             }

    //             // validaciones para tipo L
    //             if ($detalle['ART_TIPO_COMPRA'] == 'L') {
    //                 $porcentajeMargen = $this->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
    //                 if (!$porcentajeMargen) {
    //                     return $this->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
    //                 }

    //                 foreach ($detalle['proveedores'] as $proveedor) {
    //                     $valor = number_format(($proveedor['COSTO_UNITARIO'] /
    //                         (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100), 4, '.', '');
    //                     if ($valor > $detalle['ART_VALOR']) {
    //                         return $this->requiredApprovalResponse("Valor del producto L es mayor al valor del precio minimo");
    //                     }
    //                     if ($proveedor['MARGEN'] < $porcentajeMargen->DATOS_ITEMS_L_MARGEN) {
    //                         return $this->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
    //                     }
    //                 }
    //             }
    //         }
    //         /// validaciones tipo pedido
    //         if ($type == 'pedido') {
    //             $detalleId = $detalle['detalle_id'] ?? null;

    //             if ($detalleId) {
    //                 $detOriginal = GpCotizacionDetalle::find($detalle['detalle_id']);
    //                 if (!$detOriginal)
    //                     continue;

    //                 //validaciones del descuento en caso de que sea pedido
    //                 if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $detOriginal->descuento) {
    //                     return $this->requiredApprovalResponse("Descuento del detalle diferente al descuento original - {$detalle['detalle_id']}");
    //                 }
    //                 //validaciones para el valor en caso de que sea pedido
    //                 if ($detalle['ART_VALOR'] != $detOriginal->ART_VALOR && $detalle['ART_VALOR'] != $detOriginal['ART_VALOR_ORIGINAL']) {
    //                     return $this->requiredApprovalResponse("Valor menor que el detalle Original - {$detalle['detalle_id']} - {$detalle['ART_VALOR']}");
    //                 }

    //                 // validacion de descripcion difernete a tipo L
    //                 if ($detalle['ART_TIPO_COMPRA'] != 'L') {
    //                     if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL'] && $detalle['ART_DESCRIPCION'] != $detOriginal->ART_DESCRIPCION) {
    //                         return $this->requiredApprovalResponse("La descripcion del producto es diferente a la original");
    //                     }

    //                     if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL'] && $detalle['ART_VALOR'] != $detOriginal->ART_VALOR) {
    //                         return $this->requiredApprovalResponse("El valor del producto es diferente al valor original");
    //                     }

    //                     /** proceso para calculo de porcetaje maximo de precio de venta */
    //                     $exceso = 0;
    //                     $excesoPct = 0;
    //                     $valorOriginal = $detalle['ART_VALOR_ORIGINAL'];
    //                     $porcMaxSubida = $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
    //                     $factor = 1 + ($porcMaxSubida / 100);
    //                     $precioLimite = $valorOriginal * $factor;
    //                     $precioActual = $detalle['ART_VALOR'];

    //                     if ($precioActual > $precioLimite && $precioActual != $detOriginal->ART_VALOR) {
    //                         $exceso = $precioActual - $precioLimite;
    //                         $excesoPct = round(($exceso / $valorOriginal) * 100, 2);
    //                         return $this->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
    //                             . "(límite: {$precioLimite}), excede en {$exceso} "
    //                             . "({$excesoPct}% sobre el valor original).");
    //                     }
    //                 }

    //                 // validaciones para tipo L
    //                 if ($detalle['ART_TIPO_COMPRA'] == 'L') {
    //                     $porcentajeMargen = $this->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
    //                     if (!$porcentajeMargen) {
    //                         return $this->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
    //                     }

    //                     foreach ($detalle['proveedores'] as $proveedor) {
    //                         $porvOrigin = $this->getProviderToValidateMargen($proveedor);
    //                         $marginOld = (float) ($porvOrigin->MARGEN ?? 0);
    //                         $marginNew = (float) ($proveedor['MARGEN'] ?? 0);
    //                         $EPS = 0.0001;
    //                         if ($porvOrigin && abs($marginOld - $marginNew) < $EPS) {
    //                             continue;
    //                         }
    //                         $valor = number_format(($proveedor['COSTO_UNITARIO'] /
    //                             (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100), 4, '.', '');
    //                         if ($valor > $detalle['ART_VALOR']) {
    //                             return $this->requiredApprovalResponse("Valor del producto L es mayor al valor del precio minimo");
    //                         }
    //                         if ($proveedor['MARGEN'] < $porcentajeMargen->DATOS_ITEMS_L_MARGEN) {
    //                             return $this->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
    //                         }
    //                     }
    //                 }
    //             } else {
    //                 //validaciones del descuento en caso de que sea pedido
    //                 if ($detalle['descuento'] != $detalle['descuento_original']) {
    //                     return $this->requiredApprovalResponse("Descuento del detalle diferente al descuento original");
    //                 }
    //                 //validaciones para el valor en caso de que sea pedido
    //                 // if ($detalle['ART_VALOR'] <  $detalle['ART_VALOR_ORIGINAL']) {
    //                 //     return $this->requiredApprovalResponse("Valor menor que el detalle Original  - {$detalle['ART_VALOR']}");
    //                 // }

    //                 // validacion de descripcion difernete a tipo L
    //                 if ($detalle['ART_TIPO_COMPRA'] != 'L') {
    //                     if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL']) {
    //                         return $this->requiredApprovalResponse("La descripcion del producto es diferente a la original");
    //                     }

    //                     if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL']) {
    //                         return $this->requiredApprovalResponse("El valor del producto es diferente al valor original");
    //                     }

    //                     /** proceso para calculo de porcetaje maximo de precio de venta */
    //                     $exceso = 0;
    //                     $excesoPct = 0;
    //                     $valorOriginal = $detalle['ART_VALOR_ORIGINAL'];
    //                     $porcMaxSubida = $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
    //                     $factor = 1 + ($porcMaxSubida / 100);
    //                     $precioLimite = $valorOriginal * $factor;
    //                     $precioActual = $detalle['ART_VALOR'];

    //                     if ($precioActual > $precioLimite) {
    //                         $exceso = $precioActual - $precioLimite;
    //                         $excesoPct = round(($exceso / $valorOriginal) * 100, 2);
    //                         return $this->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
    //                             . "(límite: {$precioLimite}), excede en {$exceso} "
    //                             . "({$excesoPct}% sobre el valor original).");
    //                     }
    //                 }
    //                 // validaciones para tipo L
    //                 if ($detalle['ART_TIPO_COMPRA'] == 'L') {
    //                     $porcentajeMargen = $this->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
    //                     if (!$porcentajeMargen) {
    //                         return $this->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
    //                     }

    //                     foreach ($detalle['proveedores'] as $proveedor) {

    //                         $valor = number_format(($proveedor['COSTO_UNITARIO'] /
    //                             (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100), 4, '.', '');
    //                         if ($valor > $detalle['ART_VALOR']) {
    //                             return $this->requiredApprovalResponse("Valor del producto L es mayor al valor del precio minimo");
    //                         }
    //                         if ($proveedor['MARGEN'] < $porcentajeMargen->DATOS_ITEMS_L_MARGEN) {
    //                             return $this->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     }
    //     return null;
    // }

    public function getTypeArticleLQuotationOrOrder($artArticulo)
    {
        $itemL = GpArticulo::where('ART_CODIGO', $artArticulo)->first();
        if (!$itemL) {
            throw new \Exception('Item no encontrado', 404);
        }

        $datosL = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', $itemL->ART_CLASE_ARTICULO)->first();
        if (!$datosL) {
            throw new \Exception('Datos L no encontrados', 404);
        }
        return $datosL;
    }


    public function getProviderToValidateMargen($prov)
    {

        $temp = GpTempCart::find($prov['id_tempcart'] ?? null);
        if (!$temp) {
            return null;
        }
        $proveedorId = $prov['ID_PROVEEDOR'] ?? null;

        $query = GpCotizacionDetalleProveedores::where('detalle_id', $temp->detalle_id);
        if ($proveedorId !== null) {
            $query->where('ID_PROVEEDOR', $proveedorId);
        }

        return $query->first();
    }

    public function getProviderReservedValidateMargen($prov)
    {

        $proveedorId = $prov['ID_PROVEEDOR'] ?? null;

        $query = GpCotizacionDetalleProveedores::where('detalle_id', $prov['detalle_id']);
        if ($proveedorId !== null) {
            $query->where('ID_PROVEEDOR', $proveedorId);
        }

        return $query->first();
    }



    public function checkOutApprovalCommercialReservedOrder($data)
    {
        $motivosAprovacionComercial = [];
        $cotizacion = GpCotizacion::find($data['id']);

        $nivelCliente = $this->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);
        if (!$nivelCliente) {
            $motivosAprovacionComercial[] = $this->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        $nivelCotizacion = $this->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        if (!$nivelCotizacion) {
            $motivosAprovacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }
        if ($nivelCotizacion > $nivelCliente && $data['COND_ID'] != $cotizacion->COND_ID) {
            $motivosAprovacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
        }

        if ($data['porcentage_descuento'] > $data['porcentage_descuentooriginal'] && $data['porcentage_descuento'] != $cotizacion->porcentage_descuento) {
            $motivosAprovacionComercial[] = $this->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[porcentage_descuento] > $data[porcentage_descuentooriginal]");
        }

        if ($cotizacion && $cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial != 'yes') {
            $motivosAprovacionComercial[] = $this->requiredApprovalResponse("La cotización  {$cotizacion->cotizacion} requiere aprobación comercial previa. Pedido en estado pendiente de aprobación comercial.");
        }
        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivosDetalle = $this->validateReservedDetailQuotationType($data['detallecotizacion'], $ptoVenta);


        if (!empty($motivosDetalle)) {
            $motivosAprovacionComercial = array_merge($motivosAprovacionComercial, $motivosDetalle);
        }
        return $this->finalizeApprovalReasons($motivosAprovacionComercial);
    }


    public function validateReservedDetailQuotationType($detalles, $ptoVenta)
    {
        $detailMotivos = [];
        foreach ($detalles as $detalle) {


            //validaciones del descuento en caso de que sea pedido
            if ($detalle['descuento'] != $detalle['descuento_original']) {
                $detailMotivos[] = $this->requiredApprovalResponse("Descuento del detalle diferente al descuento original - {$detalle['id']}");
            }

            // validacion de descripcion difernete a tipo L
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL']) {
                    $detailMotivos[] = $this->requiredApprovalResponse("La descripcion del producto es diferente a la original");
                }

                if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL']) {
                    $detailMotivos[] = $this->requiredApprovalResponse("El valor del producto es diferente al valor original");
                }

                /** proceso para calculo de porcetaje maximo de precio de venta */
                $exceso = 0;
                $excesoPct = 0;
                $valorOriginal = $detalle['ART_VALOR_ORIGINAL'];
                $porcMaxSubida = $ptoVenta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                $factor = 1 + ($porcMaxSubida / 100);
                $precioLimite = $valorOriginal * $factor;
                $precioActual = $detalle['ART_VALOR'];

                if ($precioActual > $precioLimite) {
                    $exceso = $precioActual - $precioLimite;
                    $excesoPct = round(($exceso / $valorOriginal) * 100, 2);
                    $detailMotivos[] = $this->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                        . "(límite: {$precioLimite}), excede en {$exceso} "
                        . "({$excesoPct}% sobre el valor original).");
                }
            }

            // validaciones para tipo L
            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                $porcentajeMargen = $this->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);
                if (!$porcentajeMargen) {
                    $detailMotivos[] = $this->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
                }

                foreach ($detalle['proveedores'] as $proveedor) {

                    $valor = number_format(($proveedor['COSTO_UNITARIO'] /
                        (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100), 4, '.', '');
                    if ($valor > $detalle['ART_VALOR']) {
                        $detailMotivos[] = $this->requiredApprovalResponse("Valor del producto L es mayor al valor del precio minimo");
                    }
                    if ($proveedor['MARGEN'] < $porcentajeMargen->DATOS_ITEMS_L_MARGEN) {
                        $detailMotivos[] = $this->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                    }
                }
            }
        }

        return $detailMotivos;
    }

    public function finalizeApprovalReasons(array $reasons)
    {
        // 1. Aplanar y filtrar vacíos usando Helpers de Laravel
        // flatten() convierte arrays anidados multidimensionales en uno solo nivel
        $collection = collect($reasons)->flatMap(function ($item) {
            // Si es un array y tiene llaves numéricas consecutivas (0, 1, 2...), es una lista anidada
            if (is_array($item) && !empty($item) && array_keys($item) === range(0, count($item) - 1)) {
                return $item;
            }
            // Si es asociativo o string, lo devolvemos envuelto en un array para que flatMap lo integre
            return [$item];
        })->filter();

        if ($collection->isEmpty()) {
            return $this->requiredNotApprovalResponse('No requiere aprobación comercial.');
        }


        // 2. Normalizar datos
        $normalized = $collection->map(function ($item) {
            if (is_string($item)) {
                return $this->requiredApprovalResponse($item);
            }
            // Si ya es un array estructurado
            if (is_array($item)) {
                // Intentamos obtener el mensaje
                $msg = $item['motivo'] ?? $item['message'] ?? null;

                // Si no hay mensaje y tampoco es un array con estructura correcta, lo forzamos a string
                if (!$msg && !isset($item['requiere'])) {
                    $msg = json_encode($item, JSON_UNESCAPED_UNICODE);
                }

                // Si ya tiene el formato correcto (tiene motivo y requiere), lo retornamos tal cual
                if (isset($item['motivo']) && isset($item['requiere'])) {
                    return $item;
                }

                return $this->requiredApprovalResponse((string) $msg);
            }
            return null;
        })->filter();

        // 3. Deduplicar por el texto del motivo
        $unique = $normalized->unique('motivo')->values();

        if ($unique->isEmpty()) {
            return $this->requiredNotApprovalResponse('No requiere aprobación comercial.');
        }

        // ========================================================================
        // CAMBIO CRÍTICO: CONCATENACIÓN EN LUGAR DE SELECCIÓN
        // ========================================================================

        // Si hay un solo motivo, devolvemos ese tal cual
        if ($unique->count() === 1) {
            $single = $unique->first();
            // Aseguramos que marque 'yes'
            if (($single['requiere'] ?? 'no') !== 'yes') {
                $single['requiere'] = 'yes';
            }
            return $single;
        }

        $combinedMessage = $unique->map(function ($item, $key) {
            return ($key + 1) . '. ' . ($item['motivo'] ?? 'Error desconocido');
        })->implode(' || ');

        // Si hay múltiples, los unimos en un solo string separándolos
        // Ejemplo: "1. Descuento modificado | 2. Precio bajo mínimos"
        // Retornamos un ÚNICO objeto de aprobación que contiene TODOS los problemas
        return $this->requiredApprovalResponse($combinedMessage);
    }
    /*** aprovacion crediciticia *** */

    public function checkoutApprovalCredit($data)
    {
        if ($data['COND_ID'] == 'CONTADO') {
            return 'no';
        }

        $cupoDisponible = $this->functionService->getClientCheckAvailableQuota($data['CLI_RUC']);
        if (!$cupoDisponible) {
            return 'no';
        }
        if ($data['total'] > $cupoDisponible['cupoDisponibleGp']) {
            return 'Cupo';
        }

        $documentosVencidos = $this->functionService->getClientDocumentExpired($data['CLI_RUC']);
        if (!$documentosVencidos) {
            return 'no';
        }
        if ($documentosVencidos['pendienteGpMc'] > 0) {
            return 'Documentos Vencidos';
        }

        $documentosSinSoporte = $this->functionService->getClientDocumentWithoutSupport($data['CLI_RUC']);
        if (!$documentosSinSoporte) {
            return 'no';
        }
        if ($documentosSinSoporte['pendienteGpMc'] > 0) {
            return 'Saldo Pendiente';
        }

        return 'no';
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



    /**
     * FUNCION PARA LA VERIFICACION DE STOCK DE LOS ARTICULOS DEL PEEDIDO ANTES DE APROBAR
     */

    public function getOrderApprovalData($data)
    {
        try {
            $detalles = $this->getDetailArticlesWithStock($data['id']);
            $flatDetails = $this->stockFormatterForUpdateApproval($detalles);
            $parmas = $this->stockFormatterParametersUpdateApproval($flatDetails);
            $stocksResult = $this->getStockWarehouseAsyncAndFormantter($parmas);
            $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);
            $alert = $this->getAlertWithoutStockArticles($detalles, $newFormatStock, $data['id']);
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


    public function getAlertWithoutStockArticles($detalles, $newFormatStock, $orderId)
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockWarehouseService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, $orderId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }

        if ($allFormattedStock->isEmpty()) {
            return;
        }
        $alert = null;
        foreach ($allFormattedStock as $stock) {
            if ($stock['STO_ART_STOCK_DISPONIBLE'] < $stock['seleccionado']) {
                $alert .= "El artículo {$stock['STO_ART_ARTICULO']} tiene stock insuficiente. " .
                    "Stock disponible: {$stock['STO_ART_STOCK_DISPONIBLE']} - " .
                    "Cantidad pedido: {$stock['seleccionado']}<br>";
            }
        }
        return $alert;
    }




    /**
     *  FUNCIONES PARA LA APROBACIÓN CREDITICIA DE PEDIDOS
     */


    public function approveCreditOrder($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])->where('estado', 'espera')
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->aprobado_crediticio = 'yes';
        $pedido->aprobador_crediticio_id = $user->id;
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
            ->whereNotIn('estado', ['eliminado', 'anulada'])
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->estado = 'rechazado';
        $pedido->aprobado_crediticio = 'no';
        $pedido->aprobador_crediticio_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
        $pedido->save();
        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        $this->updateStockOnOrderApproval($pedido);
        $this->cleanDetailOrderStock($pedido);
        return $pedido;
    }


    public function rejectCreditOrderForInsufficientStock($data, $user)
    {
        $pedido = GpPedido::where('id', $data['id'])
            ->whereNotIn('estado', ['eliminado', 'anulada'])
            ->lockForUpdate()->first();
        $pedido->estado = 'rechazado';
        $pedido->aprobado_crediticio = 'no';
        $pedido->aprobador_crediticio_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
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
        $pedido = GpPedido::where('id', $data['id'])->where('estado', 'espera')
            ->lockForUpdate()->first();
        if (!$pedido) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $pedido->aprobado_comercial = 'yes';
        $pedido->aprobador_comercial_id = $user->id;
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
        $pedido->estado = 'rechazado';
        $pedido->aprobado_comercial = 'no';
        $pedido->aprobador_comercial_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
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
        $pedido->estado = 'rechazado';
        $pedido->aprobado_comercial = 'no';
        $pedido->aprobador_comercial_id = $user->id;
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
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
        $detalles = $this->getDetailArticlesWithStock($order->id);
        if ($detalles->isEmpty()) {
            return;
        }
        $flatDetails = $this->stockFormatterForUpdateApproval($detalles);
        $parmas = $this->stockFormatterParametersUpdateApproval($flatDetails);

        $stocksResult = $this->getStockWarehouseAsyncAndFormantter($parmas);
        $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);

        $this->updateOrderDetailStockAndGetStockDisponible($detalles, $newFormatStock, $order->id);
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
                    'stock_id' => $stock->id,
                    'pedido_id' => $stock->pedido_id,
                    'detalle_id' => $stock->detalle_id,
                    'STO_ART_ARTICULO' => $detalle->ART_CODIGO,
                    'STO_ART_BODEGA' => $stock->STO_ART_BODEGA,
                    'STO_ART_STOCK_DISPONIBLE' => $stock->STO_ART_STOCK_DISPONIBLE,
                    'seleccionado' => $stock->seleccionado
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




    public function updateOrderDetailStockAndGetStockDisponible($detalles, $newFormatStock, $orderId)
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockWarehouseService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, $orderId);
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


    public function denyQuotationBeenWaiting(int $id)
    {
        $quote = GpCotizacion::find($id);
        $quote->estado = 'rechazado';
        $quote->fe_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $quote->user_rechazo_id = auth()->user()->id;
        $quote->save();
        return $quote;
    }


    public function denyOrderBeenWaiting(int $id)
    {
        $order = GpPedido::find($id);
        $order->estado = 'rechazado';
        $order->user_rechazo_id = auth()->user()->id;
        $order->fecha_hora_rechazado = Carbon::now()->format('Y-m-d H:i:s');
        $order->rechazado = 1;
        $order->save();
        return $order;
    }




    /** listado de aprobaciones de pedidos y cotizaciones */

    public function getAllPendingApprovals(int $ptoVta)
    {
        $user = Auth::user() ? GpUsuario::find(Auth::user()->id) : GpUsuario::find(179);
        // 1. Fetch Orders (Pedidos)
        $query1 = $this->fetchPendingOrders($ptoVta);

        // 2. Fetch Quotes (Cotizaciones)
        $query2 = $this->fetchPendingQuotes($ptoVta);
        $orders = $query1->get();
        $quotes = $query2->get();

        $allRucs = $orders->pluck('CLI_RUC')
            ->merge($quotes->pluck('CLI_RUC'))
            ->unique()
            ->toArray();

        $segmentDataByRuc = $this->getClientSegments($allRucs);
        // $orders = $query1->get();
        // $quotes = $query2->get();
        $orders = $this->mapClientSegmentData($orders, $segmentDataByRuc);
        $quotes = $this->mapClientSegmentData($quotes, $segmentDataByRuc);

        // //aplicacion de reglas de aprobaciones
        // $tipovendedor = 'v_externo';
        $rules = $this->getApprovalrRulesForUser($user->id);
        if ($rules->isNotEmpty()) {
            $orders = $this->filterOrderByRules($orders, $rules);
            $quotes = $this->filterQuoteByRules($quotes, $rules);
        }

        return [
            'pedidos' => $orders->values(),
            'cotizaciones' => $quotes->values(),
        ];
    }

    /** listado de las cotizaciones pendiente de aprobaciones */
    public function getAllQuotesPendingApprovals($ptoVta, int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user() ? GpUsuario::find(Auth::user()->id) : GpUsuario::find(179);
        $query = $this->fetchPendingQuotes($ptoVta);
        $rules = $this->getApprovalrRulesForUser($user->id);
        if ($rules->isNotEmpty()) {
            $quotes = $query->get();
            $quotes = $this->filterQuoteByRules($quotes, $rules);
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $quotes->slice(($currentPage - 1) * $perPage, $perPage)->all();
            return new LengthAwarePaginator(
                $currentItems,
                $quotes->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
        }
        return $query->paginate($perPage);
    }

    public function getAllOrdersPendingApprovals($ptoVta, int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user() ? GpUsuario::find(Auth::user()->id) : GpUsuario::find(179);
        $query = $this->fetchPendingOrders($ptoVta);
        $rules = $this->getApprovalrRulesForUser($user->id);
        if ($rules->isNotEmpty()) {
            $orders = $query->get();
            $orders = $this->filterOrderByRules($orders, $rules);
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $orders->slice(($currentPage - 1) * $perPage, $perPage)->all();
            return new LengthAwarePaginator(
                $currentItems,
                $orders->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
        }
        return $query->paginate($perPage);
    }


    public function fetchPendingOrders(int $ptoVta): Builder
    {
        $excludedStates = ['eliminado', 'anulada', 'rechazado', 'facturado', 'enviando', 'procesado'];
        $query = GpPedido::query()
            ->with([
                'detallepedido',
                // 'gpcotizacion',
                // 'gpcliente' => function ($query) {
                //     $query->select('id', 'CLI_RUC', 'CLI_RAZON_SOCIAL', 'CLI_ID_DIRECCION', 'CLI_TERRITORIO', 'CLI_ESTADO');
                // },
                //  'gpvendedor' => function ($query) {
                //     $query->select('id', 'VEND_ID', 'VEND_TERRITORIO');
                // }
            ])
            ->join('gp_descuento_forma_pagos as dfp', 'dfp.DESC_FORMA_PAGO_CODIGO', 'gp_pedidos.DESC_FORMA_PAGO_CODIGO')
            ->join('gp_punto_ventas as pv', 'pv.PTO_VTA_ID', 'gp_pedidos.PTO_VTA_ID')
            ->select(
                'gp_pedidos.*',
                'dfp.DESC_FORMA_PAGO_TIPO_CREDITO', // Renombrado para claridad
                'pv.PTO_VTA_DESCRIPCION'      // Renombrado para claridad
            )
            ->where('gp_pedidos.PTO_VTA_ID', $ptoVta) // Si es necesario, descomentar
            ->where('requiere_aprobacion_comercial', 'yes')
            ->where('aprobado_comercial', 'no')
            ->whereNotIn('estado', $excludedStates)
            ->orderBy('created_at', 'ASC');
        return $query;
    }

    public function fetchPendingQuotes(int $ptoVta): Builder
    {
        $excludedStates = ['caducado', 'procesado', 'rechazado', 'aprobado', 'eliminado'];

        $query = GpCotizacion::query()
            ->with([
                'detallecotizacion',
                // 'gpcliente' => function ($query) {
                //     $query->select('id', 'CLI_RUC', 'CLI_RAZON_SOCIAL', 'CLI_ID_DIRECCION', 'CLI_TERRITORIO', 'CLI_ESTADO');
                // },
                // 'gpvendedor' => function ($query) {
                //     $query->select('id', 'VEND_ID', 'VEND_TERRITORIO');
                // }
            ])
            ->join('gp_descuento_forma_pagos as dfp', 'dfp.DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions.DESC_FORMA_PAGO_CODIGO')
            ->select(
                'gp_cotizacions.*',
                'dfp.DESC_FORMA_PAGO_TIPO_CREDITO' // Renombrado para claridad
            )
            // Agregamos la condición al Punto de Venta (si es requerida)
            // ->where('gp_cotizacions.PTO_VTA_ID', $ptoVta)
            ->where('requiere_aprobacion_comercial', 'yes')
            ->where('aprobado_comercial', 'no')
            ->whereNotIn('estado', $excludedStates)
            ->orderBy('created_at', 'ASC');
        return $query;
    }

    public function getClientSegments(array $rucs): array
    {
        if (empty($rucs)) {
            return [];
        }

        $segments = [];

        // Realiza N llamadas individuales a la API, pero de forma centralizada.
        foreach ($rucs as $ruc) {
            $response = $this->apiGpService->fetchClientSegment([
                'ruc' => $ruc,
            ]);

            // Mapea y limpia la respuesta de la API
            $data = data_get($response, 'data', []);
            $cleanData = is_array($data) ? $data : (array) $data;

            // Almacena el resultado con el RUC como clave
            $segments[$ruc] = [
                // Usamos 'segment' y 'subsegment' como claves internas estandarizadas
                'segmento' => data_get($cleanData, 'segmento', null),
                'subsegmento' => data_get($cleanData, 'subsegmento', null),
            ];
        }

        return $segments;
    }

    public function mapClientSegmentData(Collection $items, array $segmentDataByRuc): Collection
    {
        return $items->map(function ($item) use ($segmentDataByRuc) {
            $ruc = $item->CLI_RUC;
            // Busca los datos pre-obtenidos
            $segmentData = $segmentDataByRuc[$ruc] ?? ['segmento' => null, 'subsegmento' => null];

            // Asigna las nuevas propiedades en Camel Case para la respuesta
            $item->segmento = data_get($segmentData, 'segmento');
            $item->subsegmento = data_get($segmentData, 'subsegmento');

            return $item;
        });
    }

    public function getApprovalrRulesForUser($userId)
    {
        return GpReglaAprobacion::where('user_autorizador_id', $userId)->get();
    }


    public function filterOrderByRules(Collection $orders, Collection $rules): Collection
    {

        if ($rules->isEmpty()) {
            return collect();
        }

        $hasFullAccessRule = $rules->contains(function (GpReglaAprobacion $rule) {
            return data_get($rule, 'zona') === "ALL" && data_get($rule, 'tipo_vendedor') === "ALL";//&& $rule->descuento_min == 0.000 && $rule->descuento_max == 0.000;
        });

        // Si existe esa regla, el usuario ve todos los pedidos pendientes
        if ($hasFullAccessRule) {
            return $orders;
        }


        return $orders->filter(function (GpPedido $ord) use ($rules) {
            $seller = $this->getTypeSellerByUser($ord->USERID);
            $typeSeller = data_get($seller, 'txDescor', null);
            $territorio = data_get($seller, 'territorio', null);

            if (!$typeSeller) {
                return false;
            }
            if (!$territorio) {
                return false;
            }
            $orderRules = $rules->filter(function (GpReglaAprobacion $rule) use ($typeSeller, $territorio) {
                $matchsTipoVendedor = data_get($rule, 'tipo_vendedor') == $typeSeller;
                $matchZone = data_get($rule, 'zona') === trim(strtoupper($territorio));
                return $matchsTipoVendedor && $matchZone;
            });

            if ($orderRules->isEmpty()) {
                return false;
            }
            if ($ord->detallepedido->isEmpty()) {
                return false;
            }


            return $ord->detallepedido->contains(function ($detail) use ($orderRules) {

                //lo calculas desde precios
                $priceChange = $this->calculateDiscountFraction($detail->ART_VALOR_ORIGINAL, $detail->ART_VALOR);

                $discountChange = $this->calculateDiscountChange($detail->descuento_original, $detail->descuento);
                Log::info('DEBUG CALCULO -filterOrderByRules', [
                    'Original_Raw' => $detail->ART_VALOR_ORIGINAL,
                    'Current_Raw' => $detail->ART_VALOR,
                    'Original_Qty' => $detail->CANTIDAD, // Verifica si esto influye
                    'Calculo' => "({$detail->ART_VALOR} - {$detail->ART_VALOR_ORIGINAL}) / {$detail->ART_VALOR_ORIGINAL}",
                    'Resultado' => $priceChange
                ]);
                return $orderRules->contains(function (GpReglaAprobacion $rule) use ($priceChange, $discountChange) {
                    $matchesPrice = $priceChange >= $rule->descuento_min
                        && $priceChange <= $rule->descuento_max;

                    $matchesDiscount = $discountChange >= $rule->descuento_min
                        && $discountChange <= $rule->descuento_max;

                    return $matchesPrice && $matchesDiscount;
                });
            });

        })->values();
    }

    public function calculateDiscountFraction($originalPrice, $currentPrice)
    {
        if ($originalPrice == 0.0) {
            return 0.0;
        }
        return ($currentPrice - $originalPrice) / $originalPrice;
    }

    public function calculateDiscountChange($originalDiscount, $currentDiscount)
    {
        $original = $originalDiscount / 100;
        $current = $currentDiscount / 100;
        return $current - $original;
    }

    public function filterQuoteByRules(Collection $quotes, Collection $rules)
    {
        if ($rules->isEmpty()) {
            return collect();
        }

        $hasFullAccessRule = $rules->contains(function (GpReglaAprobacion $rule) {
            return data_get($rule, 'zona') === "ALL" && data_get($rule, 'tipo_vendedor') === "ALL"; //&& $rule->descuento_min == 0.000 && $rule->descuento_max == 0.000;
        });

        if ($hasFullAccessRule) {
            return $quotes;
        }

        return $quotes->filter(function (GpCotizacion $ord) use ($rules) {
            $seller = $this->getTypeSellerByUser($ord->USERID);
            $typeSeller = data_get($seller, 'txDescor', null);
            $territorio = data_get($seller, 'territorio', null);

            if (!$typeSeller) {
                return false;
            }
            if (!$territorio) {
                return false;
            }


            $orderRules = $rules->filter(function (GpReglaAprobacion $rule) use ($typeSeller, $territorio) {
                $matchsTipoVendedor = data_get($rule, 'tipo_vendedor') == $typeSeller;
                $matchZone = data_get($rule, 'zona') == trim(strtoupper($territorio));
                return $matchsTipoVendedor && $matchZone;
            });

            if ($orderRules->isEmpty()) {
                return false;
            }
            if ($ord->detallecotizacion->isEmpty()) {
                return false;
            }


            return $ord->detallecotizacion->every(function ($detail) use ($orderRules) {

                //lo calculas desde precios
                $priceChange = $this->calculateDiscountFraction($detail->ART_VALOR_ORIGINAL, $detail->ART_VALOR);

                $discountChange = $this->calculateDiscountChange($detail->descuento_original, $detail->descuento);

                Log::info('DEBUG CALCULO -filterQuoteByRules', [
                    'Original_Raw' => $detail->ART_VALOR_ORIGINAL,
                    'Current_Raw' => $detail->ART_VALOR,
                    'Original_Qty' => $detail->CANTIDAD, // Verifica si esto influye
                    'Calculo' => "({$detail->ART_VALOR} - {$detail->ART_VALOR_ORIGINAL}) / {$detail->ART_VALOR_ORIGINAL}",
                    'Resultado' => $priceChange
                ]);
                return $orderRules->contains(function (GpReglaAprobacion $rule) use ($priceChange, $discountChange) {
                    $matchesPrice = $priceChange >= $rule->descuento_min
                        && $priceChange <= $rule->descuento_max;

                    $matchesDiscount = $discountChange >= $rule->descuento_min
                        && $discountChange <= $rule->descuento_max;

                    return $matchesPrice && $matchesDiscount;
                });
            });

        })->values();
    }


    public function getTypeSellerByUser($userName)
    {
        $resp = $this->apiGpService->fetchDetailsTypeSellerFromGp([
            'username' => $userName
        ]);
        $data = data_get($resp, 'data', []);
        // $typeSeller = data_get($data, 'txDescor', null);
        // $territorio = data_get($data, 'territorio', null);
        return $data;
    }




}

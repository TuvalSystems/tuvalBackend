<?php

namespace App\Services\Aprobaciones;

use App\Enums\Ventas\OrderStatus;
use App\Jobs\Aprobaciones\NotifyApprovalOrdersJob;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpTempCart;
use DateTime;
use App\Models\GpPedido;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use Illuminate\Support\Arr;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpDatosItemsL;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\DB;
use App\Models\GpCotizacionDetalle;
use Illuminate\Support\Facades\Log;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpUsuario;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Carbon\Carbon;


class ApprovalService implements ApprovalServiceInterface
{
    use JsonResponseTrait;

    public function __construct(
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly ApiGpService $apiGpService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService
    ) {}


    /*
     *
     * aprobacion comercial tanto de pedido como de cotizaciones
     *
     */

    public function checkoutApprovalCommercialQuotations($data)
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
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Cotización desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt"
                );
            }

            // 2. Motivo por ser copia (siempre se agrega si existe el registro)
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                "Cotización desde Volver a Pedir ($desde original: $codigoVisual, fecha: $fechaTxt)"
            );
        }



        $nivelCliente = $this->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL']);

        $nivelCotizacion = $this->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        $rebajaCotizacion = $this->getPorcentilDiscount('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO']);
        $prcCLienteDescuento = $this->getPorcentilDiscountCommercialCliente($data['CLI_RUC']);

        /** este descuento global lo utilizamos para calcular el descuento de la cabecera y del detalle de la cotizacion  */
        $dsctGlobalCliente = (float) $prcCLienteDescuento - (float) $rebajaCotizacion;

        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }
        if ($nivelCotizacion > $nivelCliente) {
            $motivosAprobacionComercial[] = $this->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
        }

        if ($quoteOrigin) {

            // Nota: Mantenemos el != si quieres que sea estricto, o > si quieres que sea solo por pérdida
            if ((float) $data['porcentage_descuento'] != (float) $data['porcentage_descuentooriginal'] && (float) $data['porcentage_descuento'] != (float) $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Porcentaje de descuento general modificado respecto al guardado. Nuevo: {$data['porcentage_descuento']}% vs Guardado: {$data['porcentage_descuentooriginal']}%"
                );
            }
        } else {
            // Si NO hay quoteOrigin (es una cotización NUEVA desde cero),
            // comparamos contra el original que envió el front (el default del sistema)
            if ((float) $data['porcentage_descuento'] != (float) $data['porcentage_descuentooriginal'] && (float) $data['porcentage_descuento'] != (float) $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->requiredApprovalResponse(
                    "Porcentaje de descuento modificado. Nuevo: {$data['porcentage_descuento']}% vs Original: {$data['porcentage_descuentooriginal']}%"
                );
            }
        }




        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();
        $motivosDetalle = $this->validateQuotationDetailToApprove($data['cotizacion'], $ptoVenta, $dsctGlobalCliente);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }

        return $this->finalizeApprovalReasons($motivosAprobacionComercial);
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
            $refDescripcion = data_get($detalle, 'ART_DESCRIPCION_ORIGINAL');

            // 3. Valor de Referencia
            $refValor = data_get($detalle, 'ART_VALOR_ORIGINAL');

            if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $dsctGlobalCliente) {
                $detailMotivos[] = $this->requiredApprovalResponse("Descuento del detalle modificado. Nuevo: {$detalle['descuento']}% vs Original: {$dsctGlobalCliente}%");
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


    public function requiredApprovalResponse(string $motivo)
    {
        return ['requiere' => 'yes', 'motivo' => $motivo];
    }

    public function requiredNotApprovalResponse(string $motivo)
    {
        return ['requiere' => 'no', 'motivo' => $motivo];
    }

    public function getNivelFormaPago($columna, $codigo)
    {
        $formaPago = GpDescuentoFormaPago::where($columna, $codigo)
            ->orderBy('DESC_FORMA_PAGO_CODIGO')
            ->first();

        return $formaPago?->nivel;
    }

    public function getPorcentilDiscount($columna, $codigo)
    {
        $valorBase = GpDescuentoFormaPago::where($columna, $codigo)
            ->value('DESC_FORMA_PAGO_PORCENTAJE_REBAJAR');

        return abs((float) $valorBase);
    }

    public function getPorcentilDiscountCommercialCliente($ruc): float
    {
        return (float) GpCliente::where('CLI_RUC', $ruc)->value('CLI_DSTO_COMERCIAL');
    }


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
        $totalOrden = (float) data_get($data, 'total', 0);

        $cupoDisponible = $this->functionService->getClientCheckAvailableQuota($data['CLI_RUC']);
        $cupo = data_get($cupoDisponible, 'cupoDisponibleGp', 0);

        if ($totalOrden > $cupo) {
            return 'Cupo';
        }

        $documentosVencidos = $this->functionService->getClientDocumentExpired($data['CLI_RUC']);
        $pendiente = data_get($documentosVencidos, 'pendienteGpMc', 0);

        if ($pendiente > 0) {
            return 'Documentos Vencidos';
        }

        $documentosSinSoporte = $this->functionService->getClientDocumentWithoutSupport($data['CLI_RUC']);
        $saldo = data_get($documentosSinSoporte, 'saldoPendiente', 0);

        if ($saldo > 0) {
            return 'Saldo Pendiente';
        }

        return 'no';
    }

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
                ->whereNotIn('estado', [OrderStatus::ANULADA, OrderStatus::ELIMINADO])
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

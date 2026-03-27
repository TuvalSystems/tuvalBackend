<?php

namespace App\Services\Aprobaciones;

use App\Models\aprobacion\GpReglaAprobacion;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpRegistroCopia;
use App\Models\GpUsuario;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class ApprovalOrderService implements ApprovalOrderServiceInterface
{

    public function __construct(
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly ApprovalFunctionsServiceInterface $approvalFuntions,
        protected readonly OrderRepositoryInterface $repository

    ) {
        // Inyecta dependencias si es necesario
    }


    /**
     *
     * CHECK DE APROBACION COMERCIAL
     *
     */

    public function approveOrder($data)
    {
        $quoteId = data_get($data, 'cotizacion_id', null);
        $quoteOrigin = empty($quoteId) ? null : GpCotizacion::find($quoteId);
        $motivosAprobacionComercial = [];

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


        $nivelCliente           = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['condicion_pago_original_id']);
        $nivelCotizacion        = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['forma_pago_id']);
        $rebajaCotizacion       = $this->approvalFuntions->getPorcentilDiscount('DESC_FORMA_PAGO_CODIGO', $data['forma_pago_id']);
        $prcCLienteDescuento    = $this->approvalFuntions->getPorcentilDiscountCommercialCliente($data['ruc']);

        $dsctGlobalCliente = (float) $prcCLienteDescuento - (float) $rebajaCotizacion;

        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }


        if ($quoteOrigin) {
            $nivelCotizacionAnterior = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $quoteOrigin->DESC_FORMA_PAGO_CODIGO);
            if ($quoteOrigin->requiere_aprobacion_comercial === 'yes' && $quoteOrigin->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Requiere aprobación comercial. La cotización no fue aprobada con anterioridad (ID: $quoteId)."
                );
            }

            if ($nivelCotizacion > $nivelCotizacionAnterior && $nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }

            if ($data['descuento_articulo'] != $data['descuento_original'] && $data['descuento_articulo'] != $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[descuento_articulo] > $data[descuento_original]");
            }
        } else {
            if ($nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }
            if ($data['descuento_articulo'] != $data['descuento_original'] && $data['descuento_articulo'] != $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[descuento_articulo] > $data[descuento_original]");
            }
        }

        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['punto_venta_id'])->first();
        $motivosDetalle = $this->validateOrdersDetailToApprove($data['items'], $ptoVenta, $dsctGlobalCliente);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }

        return $this->approvalFuntions->finalizeApprovalReasons($motivosAprobacionComercial);
    }

    public function validateOrdersDetailToApprove($detalles, $ptoVta, $dsctGlobalCliente): array
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
            if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $dsctGlobalCliente && $detalle['descuento'] != $descuentoOriginalReal) {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Descuento del detalle modificado. Nuevo: {$detalle['descuento']}% vs Original: {$dsctGlobalCliente}%");
            }

            // B. Validación de Valor (Precio)
            // Comparamos directamente contra la variable normalizada
            if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL'] && $detalle['ART_VALOR'] != $valorOriginalReal) {
                // Mensaje genérico de cambio
                $mensaje = "El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: {$detalle['ART_VALOR_ORIGINAL']}";

                // Si quieres mantener el mensaje específico que tenías para items existentes:
                if ($detOriginal) {
                    $mensaje = "El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: $valorOriginalReal";
                }
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse($mensaje);
            }

            // C. Validaciones NO Tipo L (Standard)
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                // C.1 Descripción
                if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL'] && $detalle['ART_DESCRIPCION'] != $descOriginalReal) {
                    $detailMotivos[] =  $this->approvalFuntions->requiredApprovalResponse("La descripción del producto cambió. Nueva: '{$detalle['ART_DESCRIPCION']}...' vs Original: '$descOriginalReal...'");
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

                        $detailMotivos[] =  $this->approvalFuntions->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                            . "(límite: {$precioLimite}), excede en {$exceso} "
                            . "({$excesoPct}% sobre el valor original).");
                    }
                }
            }

            // D. Validaciones Tipo L
            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                $valorOriginalL = round(data_get($detalle, 'valor_original_l', 0), 4);
                $valor = round(data_get($detalle, 'ART_VALOR', 0), 4);

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
                // $ids = collect($providersDetail)->pluck('id')->filter();
                // $proveedoresOrigen = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');
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




    /**
     *
     *
     * check aprobacion crediticia
     *
     *
     *
     * */
    public function checkoutApprovalCreditOrder($condId, $total, $ruc): string
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



    public function getAllOrdersPendingApprovals(string $ptoVta, GpUsuario $user): Collection
    {
        $filteredOrders = collect([]);
        $user->load(['vendedor.jerarquia.relaciones', 'vendedor.zonas']);
        $query  = $this->repository->fetchPendingOrders($ptoVta)->with(['detallepedido', 'gpcotizacion', 'usuario.vendedor.zonas']);
        $orders = $query->get();
        $orders->each(function ($order) {
            $order->users_autorizadores = $this->getPossibleAuthorizersForOrders($order);
        });

        // para los adminis del sistema
        if (in_array($user->perfil_id, [1, 2])) {
            $filteredOrders =  $orders;
        } elseif (in_array($user->perfil_id, [28])) {
            $filteredOrders =  $this->filterOrdersForVicePresidency($orders, $user);
        } elseif ($user->vendedor) {
            $filteredOrders = $this->filterOrdersByTySeller($orders, $user);
        }

        $allRucs = $orders->pluck('CLI_RUC')
            ->merge($orders->pluck('CLI_RUC'))
            ->unique()
            ->toArray();
        $segmentDataByRuc = $this->approvalFuntions->getClientSegments($allRucs);
        return $filteredOrders->map(function ($order) use ($segmentDataByRuc) {
            $ruc                        = $order->CLI_RUC;
            $order->cotizacion          = $order->gpcotizacion->cotizacion;
            $segmentData                = $segmentDataByRuc[$ruc] ?? ['segmento' => null, 'subsegmento' => null];
            $order->segmento            = data_get($segmentData, 'segmento');
            $order->subsegmento         = data_get($segmentData, 'subsegmento');
            return $order->makeHidden(['detallepedido', 'gpcotizacion', 'usuario.vendedor.zonas', 'usuario']);
        });
    }

    protected function filterOrdersForVicePresidency($orders, GpUsuario $user): Collection
    {
        return $orders->filter(function ($order) use ($user) {
            return in_array($user->id, $order->authorizer_ids_list ?? []);
        })->values();
    }

    protected function filterOrdersByTySeller(Collection $orders, GpUsuario $user): Collection
    {
        return $orders->filter(function ($order) use ($user) {
            return in_array($user->id, $order->authorizer_ids_list ?? []);
        })->values();
    }

    protected function getPossibleAuthorizersForOrders(GpPedido $order): string
    {
        $tempAuthorizers    = [];
        $sellerOrder        = $order->usuario->vendedor ?? null;
        if (!$sellerOrder) {
            return '';
        }
        $hasModifications = $order->detallepedido->contains(function ($detail) {
            $priceChange = $this->approvalFuntions->calculatePriceVariationPercentage($detail->ART_VALOR_ORIGINAL, $detail->ART_VALOR);
            $discount    = $this->approvalFuntions->calculateDiscountChange($detail->descuento_original, $detail->descuento);
            return $priceChange != 0 || $discount != 0;
        });

        $zonasQuote             = $sellerOrder->zonas->pluck('id')->toArray() ?? [];
        $rules = GpReglaAprobacion::with(['usuarioAutorizador.vendedor.jerarquia.relaciones'])
            ->where(function ($q) use ($zonasQuote) {
                $q->whereIn('zona_id', $zonasQuote)
                    ->orWhereHas('usuarioAutorizador', function ($u) {
                        $u->where('perfil_id', 28);
                    });
            })
            ->get();
        $rulesByUser = $rules->groupBy('user_autorizador_id');
        foreach ($rulesByUser as $userId => $ruleUser) {
            $autorizador = $ruleUser->first()->usuarioAutorizador;
            $vendedorAutorizador = $autorizador->vendedor ?? null;
            if (!$hasModifications) {
                $nivelJerarquia = data_get($vendedorAutorizador, 'jerarquia.nivel');
                if ($nivelJerarquia == 2) {
                    continue;
                }
            }
            if ($autorizador->perfil_id !== 28) {
                if (!$vendedorAutorizador || !$vendedorAutorizador->jerarquia) {
                    continue;
                }
                $hijosDelJefe = $vendedorAutorizador->jerarquia->relaciones->pluck('tipo_vendedor_hijo_id')->toArray();
                if (!in_array($sellerOrder->tipo_vendedor_id, $hijosDelJefe)) {
                    continue;
                }
            }
            $puedeAprobar = $order->detallepedido->every(function ($detail) use ($ruleUser) {
                $priceChange = $this->approvalFuntions->calculatePriceVariationPercentage($detail->ART_VALOR_ORIGINAL, $detail->ART_VALOR);
                $discount    = $this->approvalFuntions->calculateDiscountChange($detail->descuento_original, $detail->descuento);

                return $ruleUser->contains(function ($rule) use ($priceChange, $discount) {
                    $priceOk = ($priceChange === 0) || ($priceChange >= $rule->descuento_min && $priceChange <= $rule->descuento_max);
                    $discountOk = ($discount === 0) || ($discount >= $rule->descuento_min && $discount <= $rule->descuento_max);
                    return $priceOk && $discountOk;
                });
            });
            if ($puedeAprobar) {
                $nombre                 = $autorizador->USERID ?? 'Usuario Desconocido';
                $maxDescuentoUsuario    = $ruleUser->max('descuento_max');
                $tempAuthorizers[] = [
                    'id'        => $autorizador->id,
                    'nombre'    => $nombre,
                    'max_desc'  => $maxDescuentoUsuario
                ];
            }
        }
        $sortedAuthorizers = collect($tempAuthorizers)->sortBy('max_desc');
        $order->authorizer_ids_list = $sortedAuthorizers->pluck('id')->toArray();
        return $sortedAuthorizers->pluck('nombre')->implode(', ');
    }
}

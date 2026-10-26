<?php

namespace App\Services\Ventas\Aprobaciones\Utils;

use App\Models\Ventas\Aprobaciones\GpReglaAprobacion;
use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalleProveedores;
use App\Models\Inventario\Articulos\GpDatosItemsL;
use App\Models\Ventas\CondicionesPago\GpDescuentoFormaPago;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Reservas\GpCotizacionReservaLog;
use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Ventas\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class ApprovalFunctionsService implements ApprovalFunctionsServiceInterface
{

    public function __construct(protected readonly ApiGpService $apiGpService) {}
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

    public function getPorcentilDiscountCommercialCliente(int $id): float
    {
        return (float) GpCliente::where('id', $id)->value('CLI_DSTO_COMERCIAL');
    }

    public function getTypeArticleLQuotationOrOrder(string $art): ?GpDatosItemsL
    {
        $itemL = GpArticulo::where('ART_CODIGO', $art)->first();
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
        $id = data_get($prov, 'id', null);
        $provedorId = data_get($prov, 'ID_PROVEEDOR', null);
        if (!$id || !$provedorId) {
            return 0;
        }
        if ($id && $provedorId) {
            return GpCotizacionDetalleProveedores::query()
                ->where('id', $id)
                ->where('ID_PROVEEDOR', $provedorId)
                ->value('MARGEN') ?? 0;
        }
    }



    public function getApprovalRulesForUserByZone(int $userId, array $zonas, int $typeSellerId): Collection
    {
        return GpReglaAprobacion::where('user_autorizador_id', $userId)->whereIn('zona_id', $zonas)
            ->where('tipo_vendedor_id', $typeSellerId)->orderBy('zona_id')->get();
    }

    public function getApprovalRulesByUser(int $userId): Collection
    {
        return GpReglaAprobacion::where('user_autorizador_id', $userId)->get();
    }

    public function calculatePriceVariationPercentage(float $originalPrice, float $currentPrice): float
    {
        if ($originalPrice == 0.0) {
            return $currentPrice > 0 ? 100.0 : 0.0;
        }
        return round((($currentPrice - $originalPrice) / $originalPrice) * 100, 2);
    }

    public function calculateDiscountChange(float $originalDiscount, float $currentDiscount): float
    {
        if ($originalDiscount == 0.0) {
            return $currentDiscount > 0 ? 100.0 : 0.0;
        }
        return  round($originalDiscount - $currentDiscount, 2);
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

    public function validatePendingCommercialApproval(GpPedido $row): bool
    {
        $requiere = strtolower((string) $row->requiere_aprobacion_comercial);
        $aprobado = strtolower((string) $row->aprobado_comercial);
        return $requiere === 'yes' && $aprobado === 'no';
    }


    /** funcion para validacion de stock antes de aprobar pedido*/
    public function validateExistsReserve(int $quotationId)
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


    /** actualizar el campo de validador L para cotizaciones */

    public function updateStatusValidatorExistenteItemL(int $id)
    {
        GpCotizacionDetalle::where('cotizacion_id', $id)
            ->where('ART_TIPO_COMPRA', 'L')->update([
                'validador_l' => true
            ]);
    }
}

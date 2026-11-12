<?php

namespace App\Services\Ventas\Cotizaciones\Borradores;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Cotizaciones\QuotationDraftPilotRequest;
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuotationItemsDraftServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotationDraftPilotService
{
    public function __construct(protected readonly QuotationItemsDraftServiceInterface $drafts) {}

    public function context(int $pvId, GpUsuario $user): array
    {
        $pv = GpPuntoVenta::whereKey($pvId)
            ->where('PTO_VTA_ESTADO', 'ACTIVO')
            ->whereHas('relacionUsuarioPtoVta', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        if (!$pv) {
            throw new HttpException(403, 'El usuario debe tener permiso sobre el punto de venta activo.');
        }

        return [
            'usuario' => ['id' => $user->id, 'codigo' => $user->USERID],
            'punto_venta' => ['id' => $pv->id, 'codigo' => $pv->PTO_VTA_ID, 'descripcion' => $pv->PTO_VTA_DESCRIPCION],
        ];
    }

    public function create(array $data, GpUsuario $user): array
    {
        // Re-check permission even for a replay, before consulting external services.
        $this->context((int) $data['pv_id'], $user);
        $payload = Arr::only($data, ['cliente_ruc', 'pv_id', 'detalle', 'maxCandidatos']);
        $payload['pv_id'] = (int) $payload['pv_id'];
        $payload['maxCandidatos'] = (int) ($payload['maxCandidatos'] ?? 6);
        $payload['detalle'] = array_map(fn (array $item) => [
            'texto' => $item['texto'],
            'cantidad' => isset($item['cantidad']) ? (float) $item['cantidad'] : null,
        ], $payload['detalle']);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $user, $payload, $hash): array {
            $record = QuotationDraftPilotRequest::query()->createOrFirst([
                'user_id' => $user->id,
                'request_id' => strtolower($data['request_id']),
            ], ['payload_hash' => $hash]);
            $record = QuotationDraftPilotRequest::whereKey($record->id)->lockForUpdate()->firstOrFail();

            if (!hash_equals($record->payload_hash, $hash)) {
                throw new HttpException(409, 'El request_id ya se usó con otro detalle, cliente o punto de venta.');
            }
            if ($record->response !== null) {
                return ['data' => $record->response, 'replayed' => true];
            }

            $result = $this->drafts->create($payload, $user);
            $record->response = $result;
            $record->save();

            return ['data' => $result, 'replayed' => false];
        }, 3);
    }
}

<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservationApprovalsChanged;
use App\Mail\Reservas\DemandaMail;
use App\Mail\Reservas\ReservaMail;
use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use App\Notifications\Venta\Reservas\ReservationRequestNotification;
use App\Notifications\Venta\Reservas\WarehouseRequestNotificaction;
use App\Services\Api\ApiGpService;
use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class FanoutReservationRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public function __construct(public int $cotizacionId, public int $actorId)
    {
        $this->onQueue('notifications');   // cola por defecto de este Job
        $this->afterCommit = true;
    }

    public function handle(ApiGpService $apiGpService)
    {
        Log::info('FanoutReservationRequest boot', [
            'env_host' => config('database.redis.default.host'),
            'env_port' => config('database.redis.default.port'),
            'client'   => config('database.redis.client'),
            'queue_connection' => config('queue.default'),
        ]);

        $cotizacion = GpCotizacion::select('id', 'cotizacion', 'BOD_BODEGA', 'PTO_VTA_ID', 'PTO_VTA_DESCRIPCION', 'CLI_RUC', 'CLI_RAZON_SOCIAL', 'created_at')
            ->with([
                'reserva:id,cotizacion_id,fe_solicitud_reserva,tipo_reserva_id'
            ])->find($this->cotizacionId);
        $actor      = GpUsuario::find($this->actorId);
        if (!$cotizacion || !$actor) return;

        event(new ReservationApprovalsChanged('solicitud', $cotizacion->id, $actor->USERID));
        $this->sendNotifyUsersApproval($cotizacion, $actor);
        $this->sendNotifyUsersWarehouse($cotizacion, $actor);
        $this->sendEmailsByReservationType($cotizacion, $actor, $apiGpService);
        // Broadcast global para refrescar grillas de aprobaciones
    }


    public function sendNotifyUsersApproval($cotizacion, $actor)
    {
        // Destinatarios: perfil 9/2 activos (ajusta campos según tu schema)
        $usersApproval = GpUsuario::query()
            ->whereIn('perfil_id', [9, 2])
            ->where('status', 1)
            // ->where('id', '!=', $actor->id)  //comentado para que a mi mismo me llegue la notificación
            ->get();
        if ($usersApproval->isNotEmpty()) {
            Notification::send(
                $usersApproval,
                (new ReservationRequestNotification(
                    'Nueva solicitud de reserva de inventario',
                    $cotizacion->cotizacion,
                    $actor
                )
                )->onQueue('notifications')
            );
        }
    }


    public function sendNotifyUsersWarehouse($cotizacion, $actor)
    {
        GpUsuario::query()
            ->whereIn('perfil_id', [13, 2])
            ->where('status', 1)
            //    ->where('id', '!=', $actor->id)  //comentado para que a mi mismo me llegue la notificación
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($cotizacion, $actor) {

                Notification::send(
                    $chunk,
                    (new WarehouseRequestNotificaction(
                        'Reserva de inventario',
                        $cotizacion->cotizacion,
                        $actor
                    ))->onQueue('notifications')
                );
            }, 'id');
    }


    public function sendEmailsByReservationType($cotizacion, $actor, $apiGpService)
    {
        try {
            $typeR = $this->resolveTypeReserved($cotizacion);
            $ptoVtaR = $this->resolvePtoVtaReserved($cotizacion);
            $resp = $this->fetchRecipientsForApiGp($typeR['conot'], $ptoVtaR, $apiGpService);
            $cfg = $resp['data'] ?? [];

            [$to, $cc] = $this->extractRecipients($cfg);
            // if (empty($to)) {
            //     Log::warning('FanoutReservationRequest: sin destinatarios TO', [
            //         'cotizacionId' => $cotizacion->id,
            //         'tipo' => $typeR,
            //         'pto' => $ptoVtaR
            //     ]);
            //     return;
            // }
            $info = $this->computedFormatQuotationDetailStock($cotizacion->id);
            $asunto = trim((string)($cfg['txAsu'] ?? '')) ?: 'Solicitud de reserva de inventario';
            $mailable = $typeR['type'] === 'RESERVA' ?
                (new ReservaMail($cotizacion, $info, $actor, $asunto))->onConnection('redis')->onQueue('emails') : (new DemandaMail($cotizacion, $info, $actor, $asunto))->onConnection('redis')->onQueue('emails');
            Mail::to('sistemas6@tuvalsa.com')->queue($mailable); //pruebas
            //  Mail::to($to)->cc($cc)->queue($mailable);

        } catch (\Throwable $e) {
            Log::error('FanoutReservationRequest: error enviando email', [
                'cotizacionId' => $cotizacion->id ?? null,
                'error'        => $e->getMessage(),
            ]);
        }
    }


    public function resolveTypeReserved($cotizacion): array
    {
        $map = [
            1 => ['conot' => 37, 'type' => 'RESERVA'],
            2 => ['conot' => 1,  'type' => 'DEMANDA'],
        ];

        $key = (int)($cotizacion->reserva?->tipo_reserva_id ?? 0);
        return $map[$key] ?? null;
    }

    public function resolvePtoVtaReserved($cotizacion)
    {
        return (string) $cotizacion->PTO_VTA_ID;
    }

    public function fetchRecipientsForApiGp($type, $ptoVta, $apiGpService)
    {
        $resp =  $apiGpService->fetchReservedNotifyLocal([
            'ptovta' => $ptoVta,
            'conot' => $type
        ]);
        return is_array($resp) ? $resp : [];
    }



    public function extractRecipients(array $cfg): array
    {
        $to = $this->uniqueValid($this->splitEmails($cfg['txCoreLeDes'] ?? '')); // destinatarios
        $cc = $this->uniqueValid($this->splitEmails($cfg['txCoreLeCop'] ?? '')); // con copia
        return [$to, $cc];
    }

    public function splitEmails(string $s)
    {
        $parts = preg_split('/[;,]+/', $s) ?: [];
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, fn($v) => $v !== ''));
    }

    public function uniqueValid(array $emails): array
    {
        $emails = array_unique($emails);
        return array_values(array_filter($emails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }


    public function computedFormatQuotationDetailStock($quotationId)
    {

        $cotizacion = GpCotizacion::with([
            'detallecotizacion' => function ($q) {
                $q->select([
                    'id',
                    'cotizacion_id',
                    'ART_CODIGO',
                    'ART_CODIGO_TRES_LETRAS',
                    'ART_DESCRIPCION',
                    'ART_PLAN_UMEDIDA',
                    'ART_PESO',
                    'ART_TIPO_COMPRA',
                    'ART_CANTIDAD'
                ]);
            },
            'detallecotizacion.stock' => function ($q) {

                $q->select(['id', 'detalle_id', 'cotizacion_id', 'STO_ART_BODEGA', 'seleccionado'])
                    ->where('seleccionado', '>', 0);
            },
            'detallecotizacion.articulo:id,ART_CODIGO_ZAFIRO',
            // 'reservaTraslado', 'tiporeserva',
        ])->findOrFail($quotationId);

        $rows = $cotizacion->detallecotizacion->flatMap(function ($detalle) use ($cotizacion) {
            if (in_array($detalle->ART_TIPO_COMPRA, ['SERV', 'L'])) {
                return [[
                    'detalle_id'                => $detalle->id,
                    'stock_id'                  => null,
                    'ART_CODIGO'                => $detalle->ART_CODIGO,
                    'ART_CODIGO_TRES_LETRAS'    => $detalle->ART_CODIGO_TRES_LETRAS,
                    'ART_DESCRIPCION'           => $detalle->ART_DESCRIPCION,
                    'ART_PLAN_UMEDIDA'          => $detalle->ART_PLAN_UMEDIDA,
                    'ART_PESO'                  => $detalle->ART_PESO,
                    'STO_ART_BODEGA'            => $cotizacion->BOD_BODEGA,
                    'ART_TIPO_COMPRA'           => $detalle->ART_TIPO_COMPRA,
                    'ART_CANTIDAD'              => $detalle->ART_CANTIDAD,
                ]];
            }
            return $detalle->stock->map(function ($stock) use ($detalle) {
                return [
                    'detalle_id'               => $detalle->id,
                    'stock_id'                 => $stock->id,
                    'ART_CODIGO'               => $detalle->ART_CODIGO,
                    'ART_CODIGO_TRES_LETRAS'   => $detalle->ART_CODIGO_TRES_LETRAS,
                    'ART_DESCRIPCION'          => $detalle->ART_DESCRIPCION,
                    'ART_PLAN_UMEDIDA'         => $detalle->ART_PLAN_UMEDIDA,
                    'ART_PESO'                 => $detalle->ART_PESO,
                    'STO_ART_BODEGA'           => $stock->STO_ART_BODEGA,
                    'ART_TIPO_COMPRA'          => $detalle->ART_TIPO_COMPRA,
                    'ART_CANTIDAD'             => $stock->seleccionado,
                ];
            });
        })->values();
        return  $rows->all();
    }
}

<?php

namespace App\Models\Admin;

use App\Enums\Admin\Sync\StatusSync;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $casts = [
        'payload' => 'array',
        'status' => StatusSync::class,
    ];


    public static function logErrorSync(
        $module,
        $action,
        $method,
        $message,
        $externalId = null,
        $localId = null,
        $payload = null,
        $attempts = 0,
        StatusSync $status = StatusSync::FAILED
    ) {
        return self::create([
            'module'        => $module,
            'action'        => $action,
            'method'        => $method,
            'message'       => is_string($message) ? $message : json_encode($message),
            'external_id'   => $externalId,
            'local_id'      => $localId,
            'payload'       => $payload,
            'attempts'      => $attempts,
            'status'        => $status,
        ]);
    }
}

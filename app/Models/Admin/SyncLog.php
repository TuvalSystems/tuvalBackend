<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $casts = [
        'payload' => 'array'
    ];


    public static function logErrorSync($module, $action, $method, $message, $externalId = null, $localId = null, $payload = null, $attempts = 0)
    {
        return self::create([
            'module'        => $module,
            'action'        => $action,
            'method'        => $method,
            'message'       => $message,
            'external_id'   => $externalId,
            'local_id'      => $localId,
            'payload'       => $payload,
            'attempts'      => $attempts,
            'status'        => StatusSync::FAILED->value
        ]);
    }
}

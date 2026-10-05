<?php

namespace App\Models\Admin;

use App\Enums\Admin\Sync\StatusSync;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $casts = [
        'payload' => 'array',
        'status' => StatusSync::class,
    ];

    protected $fillable = [
        'module',
        'action',
        'method',
        'message',
        'external_id',
        'local_id',
        'payload',
        'attempts',
        'status',
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
        return self::logSync(
            $module,
            $action,
            $method,
            $message,
            $externalId,
            $localId,
            $payload,
            $attempts,
            $status
        );
    }

    public static function logSync(
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


    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(30));
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

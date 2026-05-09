<?php

namespace App\Traits;

use App\Models\Gestion\Auditoria;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Model;

trait RegisterAudit
{
    protected function createHistoryRecord(
        GpUsuario $user,
        Model $model,
        string $action,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        Auditoria::create([
            'user_id'        => $user->id,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->id,
            'action'         => $action,
            'old_values'     => $oldData,
            'new_values'     => $newData,
            'url'            => request()?->fullUrl(),
            'ip_address'     => request()?->ip(),
            'user_agent'     => request()?->header('User-Agent'),
        ]);
    }
}

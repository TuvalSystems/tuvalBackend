<?php

namespace App\Services\Admin;

use App\Models\Admin\SyncLog;
use App\Services\Admin\Contracts\AdminLogServiceInterface;

class AdminLogService implements AdminLogServiceInterface
{

    public function fetchListLogs(array $filters)
    {

        $query = SyncLog::from('sync_logs as sl')
            ->select([
                'sl.id',
                'sl.module',
                'sl.action',
                'sl.method',
                'sl.message',
                'sl.external_id',
                'sl.local_id',
                'sl.payload',
                'sl.attempts',
                'sl.status',
                'sl.created_at',
            ]);
        if ($search = data_get($filters, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sl.module', 'like', "%{$search}%")
                    ->orWhere('sl.action', 'like', "%{$search}%")
                    ->orWhere('sl.message', 'like', "%{$search}%")
                    ->orWhere('sl.external_id', 'like', "%{$search}%");
            });
        }

        if ($module = data_get($filters, 'module')) {
            $query->where('sl.module', $module);
        }

        if ($action = data_get($filters, 'action')) {
            $query->where('sl.action', $action);
        }

        if ($externalId = data_get($filters, 'external_id')) {
            $query->where('sl.external_id', $externalId);
        }

        if (data_get($filters, 'fecha_desde') && data_get($filters, 'fecha_hasta')) {
            $query->whereBetween('sl.created_at', [
                data_get($filters, 'fecha_desde'),
                data_get($filters, 'fecha_hasta')
            ]);
        }

        // Estado del Log (failed, success, warning)
        if ($status = data_get($filters, 'status')) {
            $query->where('sl.status', $status);
        }
        $query->orderBy(data_get($filters, 'sortBy', 'created_at'), data_get($filters, 'sortOrder', 'desc'));
        return $query->paginate(data_get($filters, 'perpage', 10));
    }
}

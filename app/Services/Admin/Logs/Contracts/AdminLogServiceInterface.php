<?php

namespace App\Services\Admin\Logs\Contracts;

use App\Models\Admin\Sync\SyncLog;

interface AdminLogServiceInterface
{
    // Define los métodos
    public function fetchListLogs(array $filters);
    public function getDetail(int $id): SyncLog;
}

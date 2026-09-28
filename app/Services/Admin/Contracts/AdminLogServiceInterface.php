<?php

namespace App\Services\Admin\Contracts;

use App\Models\Admin\SyncLog;

interface AdminLogServiceInterface
{
    // Define los métodos
    public function fetchListLogs(array $filters);
    public function getDetail(int $id): SyncLog;
}

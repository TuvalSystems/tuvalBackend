<?php

namespace App\Services\Admin\Contracts;

interface AdminLogServiceInterface
{
    // Define los métodos
    public function fetchListLogs(array $filters);
}

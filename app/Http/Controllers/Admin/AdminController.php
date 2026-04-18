<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Contracts\AdminLogServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(protected readonly AdminLogServiceInterface $admin) {}

    public function listaLogs(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $filters = $request->input('filters', []);
            $response = $this->admin->fetchListLogs($filters);
            return $this->successResponse('Listado de logs', $response, 200);
        }, 'laravel', 'listaLogs');
    }
}

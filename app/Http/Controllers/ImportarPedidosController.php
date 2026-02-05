<?php

namespace App\Http\Controllers;

use App\Services\Imports\Contracts\OrdersImportInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;

class ImportarPedidosController extends Controller
{
    use JsonResponseTrait;

    public function __construct(protected readonly OrdersImportInterface $import) {}




    public function actualizarPedidosYDetallesSqlServer(Request $request)
    {

        set_time_limit(0);       // 0 = Sin límite de tiempo
        ini_set('memory_limit', '1024M'); // Aumentar memoria a 1GB si es necesario
        $request->validate([
            'fechaInicio' => 'required|date_format:Y-m-d',
            'fechaFin' => 'required|date_format:Y-m-d',
        ]);

        try {
            $updates = $this->import->updateDataSqlServerOrdersAndDetail($request->input('fechaInicio'), $request->input('fechaFin'));
            return $this->successResponse("Registros actualizados", $updates, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }
}

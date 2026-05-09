<?php

use App\Http\Controllers\Movil\AppController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Rutas públicas consumidas por la app móvil
|--------------------------------------------------------------------------
*/

Route::get('/app/latest', [AppController::class, 'latest']);

/*
|--------------------------------------------------------------------------
| Rutas administrativas consumidas por el frontend web Tuval
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('admin/app')->group(function () {
    Route::post('/versions/list', [AppController::class, 'index']);
    Route::post('/versions', [AppController::class, 'store']);
    Route::patch('/versions/{id}/active', [AppController::class, 'active']);
    Route::delete('/versions/{id}', [AppController::class, 'destroy']);
});

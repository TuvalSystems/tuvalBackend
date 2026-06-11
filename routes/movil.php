<?php

use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Movil\Auth\AuthMobileController;
use App\Http\Controllers\Movil\Menu\MobileMenuController;
use App\Http\Controllers\Movil\SteamTrapController;
use App\Http\Controllers\ventas\ConsultasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Login app móvil
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthMobileController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rutas públicas consumidas por la app móvil
|--------------------------------------------------------------------------
*/

Route::get('/app/latest', [AppController::class, 'latest']);

/*
|--------------------------------------------------------------------------
| Rutas autenticadas de la app móvil
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'abilities:mobile'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Usuario autenticado / cambio de contraseña
    |--------------------------------------------------------------------------
    */

    Route::get('user', [UserController::class, 'obtenerUsuarioApp']);
    Route::post('change-password', [UserController::class, 'actualizarContrasena']);

    /*
    |--------------------------------------------------------------------------
    | Rutas móviles protegidas por cambio obligatorio de contraseña
    |--------------------------------------------------------------------------
    */

    Route::middleware(['must.change.password'])->group(function () {

        Route::prefix('trampas-vapor')
            ->controller(SteamTrapController::class)
            ->group(function () {
                Route::post('/dimensionamiento', 'generarDimensionamientoPdf');
                Route::post('/borrador', 'generarCotizacionBorrador');
            });

        //menu
        Route::get('/menu', [MobileMenuController::class, 'index']);
        Route::prefix('cliente')->group(function () {
            Route::post('buscar', [ConsultasController::class, 'buscarClientes']);
        });
    });
});

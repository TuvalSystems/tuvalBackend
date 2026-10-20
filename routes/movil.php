<?php

use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Movil\Auth\AuthMobileController;
use App\Http\Controllers\Movil\Menu\MobileMenuController;
use App\Http\Controllers\Movil\SteamTrapController;
use App\Http\Controllers\Movil\Inventario\GuiaRemisionController;
use App\Http\Controllers\Movil\Inventario\GuiaRemisionArchivoController;
use App\Http\Controllers\ventas\ClienteController;
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

        Route::post('inventario/guias-remision/resolver-qr', [GuiaRemisionArchivoController::class, 'resolverQr']);

        // Inventario: listado de guias recibidas.
        Route::prefix('inventario/guias-remision/{tipo}/{guia}')
            ->where(['tipo' => 'despacho|reserva|reposicion|transferencia', 'guia' => '[0-9]+'])
            ->controller(GuiaRemisionArchivoController::class)->group(function () {
                Route::get('archivos', 'index');
                Route::post('firma', 'firma');
                Route::post('documentos', 'documentos');
                Route::get('archivos/{archivo}', 'show')->whereNumber('archivo');
            });

        Route::prefix('inventario/guias-remision')->controller(GuiaRemisionController::class)->group(function () {
            Route::get('tipos', 'tipos');
            Route::get('bodegas', 'obtenerBodegas');
            Route::get('/', 'index');
        });

        //menu
        Route::get('/menu', [MobileMenuController::class, 'index']);

        //puntos de venta
        Route::get('point-sales', [AuthMobileController::class, 'obtenerPuntosVenta']);

        //clientes
        Route::prefix('clientes')->group(function () {
            Route::post('buscar-clientes', [ClienteController::class, 'buscarClientes']);
        });
    });
});

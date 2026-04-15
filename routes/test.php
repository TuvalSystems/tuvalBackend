<?php

use App\Http\Controllers\SectionsController;
use App\Http\Controllers\test\TestController;
use Illuminate\Support\Facades\Route;

Route::get('crear-orden-compra-items/{id}', [TestController::class, 'crearOrdenCompraItemsL']);
Route::get('enviar-ordenes-compra-l-iniciadas', [TestController::class, 'enviarOrdenesCompraLIniciadas']);
Route::get('generar-orden-compra-pedido/{pedido}', [TestController::class, 'generarOrdenCompraPedido']);
Route::get('obtener-menu', [SectionsController::class, 'obtenerMenu']);

<?php

use App\Http\Controllers\Admin\SectionsController;
use App\Http\Controllers\test\TestController;
use Illuminate\Support\Facades\Route;

Route::get('crear-orden-compra-items/{id}', [TestController::class, 'crearOrdenCompraItemsL']);
Route::get('enviar-ordenes-compra-l-iniciadas', [TestController::class, 'enviarOrdenesCompraLIniciadas']);
Route::get('generar-orden-compra-pedido/{pedido}', [TestController::class, 'generarOrdenCompraPedido']);
Route::get('obtener-menu', [SectionsController::class, 'obtenerMenu']);
//ruta de prueba
Route::get('notificacion-prueba', [TestController::class, 'enviarNotificacion']);
Route::get('enviar-whatsapp', [TestController::class, 'enviarNotificacionWhatsapp']);



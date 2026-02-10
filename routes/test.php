<?php

use App\Http\Controllers\test\TestController;
use Illuminate\Support\Facades\Route;

Route::get('crear-orden-compra-items/{id}', [TestController::class, 'crearOrdenCompraItemsL']);

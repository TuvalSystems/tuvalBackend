<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SincronizacionController;
use App\Http\Controllers\SectionsController;
use App\Http\Controllers\ventas\ConsultasController;
use App\Http\Controllers\ventas\CotizacionesController;
use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\ventas\AprobacionesController;
use App\Http\Controllers\ventas\CobranzasController;
use App\Http\Controllers\bodegas\DashboardController;
use App\Http\Controllers\bodegas\RecibosdecompraeleController;
use App\Http\Controllers\bodegas\TransportesController;
use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\bodegas\TransferenciasController;
use App\Http\Controllers\bodegas\GuiasderemisionController;
use App\Http\Controllers\devoluciones\DevolucionesController;
use App\Http\Controllers\bodegas\ReposicionController;
use App\Http\Controllers\ImportarPedidosController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::get('/notificaciones', [AuthController::class, 'notificaciones']);
    Route::get('/llenausuariosgp', [SincronizacionController::class, 'llenausuariosgp']);
    Route::get('/llenabodegasgp', [SincronizacionController::class, 'llenabodegasgp']);
    Route::get('/llenacondicionpagogp', [SincronizacionController::class, 'llenacondicionpagogp']);
    Route::get('/llenaarticulosgp', [SincronizacionController::class, 'llenaarticulosgp']);
    Route::get('/llenalistadepreciosgp', [SincronizacionController::class, 'llenalistadepreciosgp']);
    Route::get('/llenaclientesgp', [SincronizacionController::class, 'llenaclientesgp']);
    Route::get('/llenapuntodeventaycajasgp', [SincronizacionController::class, 'llenapuntodeventaycajasgp']);
    Route::get('/llenaclientesucursalgp', [SincronizacionController::class, 'llenaclientesucursalgp']);
    Route::get('/llenadescuentoformapagogp', [SincronizacionController::class, 'llenadescuentoformapagogp']);
    Route::get('/llenarelacionesgp', [SincronizacionController::class, 'llenarelacionesgp']);
    Route::get('/llenavendedorgp', [SincronizacionController::class, 'llenavendedorgp']);
    Route::get('/llenastockarticulobodegasgp', [SincronizacionController::class, 'llenastockarticulobodegasgp']);
    Route::get('/llenastockarticulobodegacompartimientogp', [SincronizacionController::class, 'llenastockarticulobodegacompartimientogp']);
    Route::get('/llenaproveedorgp', [SincronizacionController::class, 'llenaproveedorgp']);
    Route::get('/llenagrupoysubgrupogp', [SincronizacionController::class, 'llenagrupoysubgrupogp']);
    Route::get('/llenapaisesyterritoriosgp', [SincronizacionController::class, 'llenapaisesyterritoriosgp']);
    Route::get('/llenaclaseclientegp', [SincronizacionController::class, 'llenaclaseclientegp']);
    Route::post('/checkusuario', [SincronizacionController::class, 'checkusuario']);
    Route::get('/listadesecciones', [SectionsController::class, 'listadesecciones']);
    Route::get('/perfiles', [SectionsController::class, 'perfiles']);
    Route::post('/updateseccion', [SectionsController::class, 'updateseccion']);
    Route::get('/eliminarperfil/{id}', [SectionsController::class, 'eliminarperfil']);
    Route::post('/updateperfil', [SectionsController::class, 'updateperfil']);
    Route::post('/updateperfilsection', [SectionsController::class, 'updateperfilsection']);
    Route::post('/updateusuarioperfil', [SectionsController::class, 'updateusuarioperfil']);
    Route::post('/consultas/buscararticulo', [ConsultasController::class, 'buscararticulo']);
    Route::post('/consultas/buscarcliente', [ConsultasController::class, 'buscarcliente']);
    Route::post('/consultas/consultarstock', [ConsultasController::class, 'consultarstock']);
    Route::post('/cotizaciones/selectarticulo', [CotizacionesController::class, 'selectarticulo']);
    Route::post('/cotizaciones/selectarticuloprospecto', [CotizacionesController::class, 'selectarticuloprospecto']);
    Route::post('/cotizaciones/llenarcotizacion', [CotizacionesController::class, 'llenarcotizacion']);
    Route::post('/cotizaciones/llenarcotizacionprospecto', [CotizacionesController::class, 'llenarcotizacionprospecto']);
    Route::post('/eliminararticulo', [CotizacionesController::class, 'eliminararticulo']);
    Route::post('/cotizaciones/updatecantidad', [CotizacionesController::class, 'updatecantidad']);
    Route::post('/cotizaciones/updatepeso', [CotizacionesController::class, 'updatepeso']);
    Route::post('/cotizaciones/updatemodificador', [CotizacionesController::class, 'updatemodificador']);
    Route::post('/cotizaciones/updatestock', [CotizacionesController::class, 'updatestock']);
    Route::post('/updatedescripcion', [CotizacionesController::class, 'updatedescripcion']);
    Route::post('/cotizaciones/updatevalor', [CotizacionesController::class, 'updatevalor']);
    Route::post('/cotizaciones/updatedescuento', [CotizacionesController::class, 'updatedescuento']);
    Route::post('/saveproveedor', [CotizacionesController::class, 'saveproveedor']);
    Route::post('/cotizaciones/guardarcotizacion', [CotizacionesController::class, 'guardarcotizacion']);
    Route::post('/cotizaciones/guardarcotizacionprospecto', [CotizacionesController::class, 'guardarcotizacionprospecto']);
    Route::post('/cotizaciones/limpiarcotizacion', [CotizacionesController::class, 'limpiarcotizacion']);
    Route::post('/cotizaciones/vercotizaciones', [CotizacionesController::class, 'vercotizaciones']);
    Route::post('/pedidos/verpedidos', [PedidosController::class, 'verpedidos']);
    Route::post('/pedidos/guardarpedido', [PedidosController::class, 'guardarpedido']);
    Route::post('/consultas/consultastockpedido', [ConsultasController::class, 'consultastockpedido']);
    Route::post('/consultas/consultastockcotizacion', [ConsultasController::class, 'consultastockcotizacion']);
    Route::post('/pedidos/facturarpedido', [PedidosController::class, 'facturarpedido']);
    Route::post('/verpedidosycotizacionesporaprobar', [AprobacionesController::class, 'verpedidosycotizacionesporaprobar']);
    Route::post('/aprobaciones/verpedidosporaprobarcrediticios', [AprobacionesController::class, 'verpedidosporaprobarcrediticios']);
    Route::post('/verpedidosporaprobarcrediticios', [AprobacionesController::class, 'verpedidosporaprobarcrediticios']);
    Route::get('/aprobaciones/verdevolucionesporaprobar', [AprobacionesController::class, 'verdevolucionesporaprobar']);
    Route::get('/devoluciones/verdevolucionesporrecibir/{puntodeventa}', [AprobacionesController::class, 'verdevolucionesporrecibir']);
    Route::post('/aprobaciones/aprobarpedido', [AprobacionesController::class, 'aprobarpedido']);
    Route::post('/aprobaciones/aprobarcotizacion', [AprobacionesController::class, 'aprobarcotizacion']);
    Route::post('/aprobaciones/rechazarcotizacion', [AprobacionesController::class, 'rechazarcotizacion']);
    Route::post('/aprobaciones/rechazarpedido', [AprobacionesController::class, 'rechazarpedido']);
    Route::post('/aprobaciones/consultaparaaprobarpedido', [AprobacionesController::class, 'consultaparaaprobarpedido']);
    Route::post('/aprobaciones/aprobarpedidocrediticio', [AprobacionesController::class, 'aprobarpedidocrediticio']);
    Route::post('/aprobaciones/rechazarpedidocrediticio', [AprobacionesController::class, 'rechazarpedidocrediticio']);
    Route::post('/aprobaciones/rechazarpedidocrediticioporstock', [AprobacionesController::class, 'rechazarpedidocrediticioporstock']);
    Route::post('/aprobaciones/rechazarpedidoporstock', [AprobacionesController::class, 'rechazarpedidoporstock']);
    Route::post('/aprobaciones/estadodecuenta', [AprobacionesController::class, 'estadodecuenta']);
    Route::post('/aprobaciones/aprobardevolucion', [AprobacionesController::class, 'aprobardevolucion']);
    Route::post('/aprobaciones/rechazardevolucion', [AprobacionesController::class, 'rechazardevolucion']);
    Route::post('/pedidos/detallepedido', [PedidosController::class, 'detallepedido']);
    Route::post('/pedidos/consultarexistente', [PedidosController::class, 'consultarexistente']);
    Route::post('/pedidos/consultardespachado', [PedidosController::class, 'consultardespachado']);
    Route::post('/pedidos/facturarexistente', [PedidosController::class, 'facturarexistente']);
    Route::post('/pedidos/facturardespachado', [PedidosController::class, 'facturardespachado']);
    Route::post('/cotizaciones/detallecotizacion', [CotizacionesController::class, 'detallecotizacion']);
    Route::post('/cotizaciones/detallecotizacion', [CotizacionesController::class, 'detallecotizacion']);
    Route::post('/cotizaciones/recuperarcotizacion', [CotizacionesController::class, 'recuperarcotizacion']);
    Route::post('/cotizaciones/recuperarcotizacionprospecto', [CotizacionesController::class, 'recuperarcotizacionprospecto']);
    Route::post('/cotizaciones/volverapedircotizacion', [CotizacionesController::class, 'volverapedircotizacion']);
    Route::post('/pedidos/recuperarpedido', [PedidosController::class, 'recuperarpedido']);
    Route::post('/pedidos/copiarpedido', [PedidosController::class, 'copiarpedido']);
    Route::post('/consultas/territorios', [ConsultasController::class, 'territorios']);
    Route::post('/guardarordenpedido', [CotizacionesController::class, 'guardarordenpedido']);
    Route::post('/cotizaciones/guardardireccion', [CotizacionesController::class, 'guardardireccion']);
    Route::post('/cotizaciones/guardarcliente', [CotizacionesController::class, 'guardarcliente']);
    Route::post('/cobranzas/documentosvencidoscliente', [CobranzasController::class, 'documentosvencidoscliente']);
    Route::post('/cobranzas/estadodecuentacliente', [CobranzasController::class, 'estadodecuentacliente']);
    Route::post('/cobranzas/estadodecuentaclientetuval', [CobranzasController::class, 'estadodecuentaclientetuval']);
    Route::post('/cobranzas/registrarcobro', [CobranzasController::class, 'registrarcobro']);
    Route::post('/cobranzas/cobrosregistrados', [CobranzasController::class, 'cobrosregistrados']);
    Route::post('/cobranzas/depositosporaprobar', [CobranzasController::class, 'depositosporaprobar']);
    Route::post('/cobranzas/aprobardeposito', [CobranzasController::class, 'aprobardeposito']);
    Route::post('/cobranzas/rechazardeposito', [CobranzasController::class, 'rechazardeposito']);
    Route::get('/cobranzas/getcajausuario/{puntodeventa}/{USERID}', [CobranzasController::class, 'getcajausuario']);
    Route::post('/bodegas/dashboard', [DashboardController::class, 'dashboard']);
    Route::post('/bodegas/recibosdecompra/bandeja', [RecibosdecompraeleController::class, 'bandeja']);
    Route::post('/bodegas/recibosdecompra/detalle', [RecibosdecompraeleController::class, 'detalle']);
    Route::post('/bodegas/recibosdecompra/recibir', [RecibosdecompraeleController::class, 'recibir']);
    Route::post('/bodegas/recibosdecompra/imprimirrecibol', [RecibosdecompraeleController::class, 'imprimirrecibol']);
    Route::post('/bodegas/recibosdecompra/eliminar', [RecibosdecompraeleController::class, 'eliminar']);
    Route::get('/bodegas/recibosdecompra/imprimir/{id}', [RecibosdecompraeleController::class, 'imprimir']);
    Route::get('/cotizaciones/imprimircotizacion/{id}', [CotizacionesController::class, 'imprimircotizacion']);
    Route::get('/cotizaciones/imprimircotizacionimagenes/{id}', [CotizacionesController::class, 'imprimircotizacionimagenes']);
    Route::get('/cotizaciones/imprimircotizacionprospecto/{id}', [CotizacionesController::class, 'imprimircotizacionprospecto']);
    Route::get('/pedidos/imprimirpedido/{id}', [PedidosController::class, 'imprimirpedido']);
    Route::get('/pedidos/imprimirpedidoimagenes/{id}', [PedidosController::class, 'imprimirpedidoimagenes']);
    Route::get('/pedidos/despachoenproceso/{id}', [PedidosController::class, 'despachoenproceso']);
    Route::get('/cobranzas/imprimircobro/{id}', [CobranzasController::class, 'imprimircobro']);
    Route::get('/cobranzas/imprimircierredecaja/{fecha}/{caja}', [CobranzasController::class, 'imprimircierredecaja']);
    Route::get('/cobranzas/imprimircierredecajausuario/{fecha}/{caja}', [CobranzasController::class, 'imprimircierredecajausuario']);
    Route::get('/cobranzas/resumencaja/{fecha}/{caja}/{USERID}', [CobranzasController::class, 'resumencaja']);
    Route::post('/cobranzas/consultaretencion', [CobranzasController::class, 'consultaretencion']);
    Route::post('/cobranzas/consultacheque', [CobranzasController::class, 'consultacheque']);
    Route::post('/cobranzas/consultadeposito', [CobranzasController::class, 'consultadeposito']);
    Route::post('/pedidos/facturarcotizacion', [PedidosController::class, 'facturarcotizacion']);
    Route::post('/pedidos/guardarmensaje', [PedidosController::class, 'guardarmensaje']);
    Route::post('/pedidos/guardarordendecompra', [PedidosController::class, 'guardarordendecompra']);
    Route::post('/cotizaciones/guardarmensaje', [CotizacionesController::class, 'guardarmensaje']);
    Route::post('/cotizaciones/guardarordendecompra', [CotizacionesController::class, 'guardarordendecompra']);
    Route::post('/cotizaciones/checkrucprospecto', [CotizacionesController::class, 'checkrucprospecto']);
    Route::get('/cobranzas/estadodecuentaclientetuval/{ruc}', [CobranzasController::class, 'estadodecuentaclientetuval']);
    Route::get('/cobranzas/estadodecuentaclientetuvalexcel/{ruc}', [CobranzasController::class, 'estadodecuentaclientetuvalexcel']);
    Route::get('/cobranzas/estadocaja/{fechaActual}/{USERID}', [CobranzasController::class, 'estadocaja']);
    Route::get('/cobranzas/estadocaja/{USERID}', [CobranzasController::class, 'estadocaja']);
    Route::get('/cobranzas/abrircaja/{puntodeventa}/{USERID}', [CobranzasController::class, 'abrircaja']);
    Route::get('/cobranzas/cerrarcaja/{puntodeventa}/{USERID}', [CobranzasController::class, 'cerrarcaja']);
    Route::get('/consultas/puntodeventacaja', [ConsultasController::class, 'puntodeventacaja']);
    Route::get('/consultas/cupodisponible/{ruc}', [ConsultasController::class, 'cupodisponible']);
    Route::post('/cobranzas/consultacierrecajausuario', [CobranzasController::class, 'consultacierrecajausuario']);
    Route::post('/cobranzas/consultacobroscliente', [CobranzasController::class, 'consultacobroscliente']);
    Route::get('/bodegas/transportes/transportistas', [TransportesController::class, 'transportistas']);
    Route::post('/bodegas/transportes/savetransportista', [TransportesController::class, 'savetransportista']);
    Route::post('/bodegas/transportes/savevehiculo', [TransportesController::class, 'savevehiculo']);
    Route::delete('/bodegas/transportes/deletetransportista/{id}', [TransportesController::class, 'deletetransportista']);
    Route::delete('/bodegas/transportes/deletevehiculo/{id}', [TransportesController::class, 'deletevehiculo']);
    Route::get('/bodegas/transferencias/transferenciasventa/{id}/{pedido?}/{compartimiento?}', [TransferenciasController::class, 'transferenciasventa']);
    Route::post('/bodegas/transferencias/picking', [TransferenciasController::class, 'picking']);
    Route::post('/bodegas/transferencias/imprimirpicking', [TransferenciasController::class, 'imprimirpicking']);
    Route::post('/bodegas/transferencias/cancelarpicking', [TransferenciasController::class, 'cancelarpicking']);

    Route::get('/bodegas/reposicion/transferenciasreposicion/{id}/{pedido?}', [ReposicionController::class, 'transferenciasreposicion']);
    Route::get('/bodegas/reposicion/recibosreposicion/{id}', [ReposicionController::class, 'recibosreposicion']);
    Route::get('/bodegas/reposicion/recibosreposicionindividual/{id}', [ReposicionController::class, 'recibosreposicionindividual']);
    Route::get('/bodegas/reposicion/detallereciboreposicion/{id}', [ReposicionController::class, 'detallereciboreposicion']);

    Route::get('/despachos/ordenespendientes/{puntodeventa_id}', [DespachosController::class, 'ordenespendientes']);
    Route::post('/despachos/liberarordendespacho', [DespachosController::class, 'liberarordendespacho']);
    Route::get('/despachos/ordenesliberadas/{puntodeventa_id}/{estado?}', [DespachosController::class, 'ordenesliberadas']);
    Route::get('/despachos/ordenesliberadassindetalle/{puntodeventa_id}/{estado?}', [DespachosController::class, 'ordenesliberadassindetalle']);
    Route::get('/despachos/getultimaguia/{puntodeventa_id}', [DespachosController::class, 'getultimaguia']);
    Route::get('/despachos/detalleordenliberada/{id}', [DespachosController::class, 'detalleordenliberada']);
    Route::get('/despachos/detalleorden/{id}', [DespachosController::class, 'detalleorden']);
    Route::get('/despachos/picking/{pedido_id}', [DespachosController::class, 'picking']);
    Route::get('/despachos/cancelarprocesodespacho/{pedido_id}', [DespachosController::class, 'cancelarprocesodespacho']);
    Route::get('/despachos/imprimirpicking/{pedido_id}', [DespachosController::class, 'imprimirpicking']);
    Route::get('/despachos/imprimirordendespacho/{pedido_id}', [DespachosController::class, 'imprimirordendespacho']);
    Route::get('/despachos/verordendespacho/{pedido_id}', [DespachosController::class, 'verordendespacho']);
    Route::get('/despachos/rechazarordendespacho/{id}', [DespachosController::class, 'rechazarordendespacho']);
    Route::get('/despachos/checkdespachadoscompletos', [DespachosController::class, 'checkdespachadoscompletos']);
    Route::get('/despachos/guiasprocesadas/{id}', [DespachosController::class, 'guiasprocesadas']);

    Route::post('/bodegas/guiasderemision/generarguiatraslado', [GuiasderemisionController::class, 'generarguiatraslado']);
    Route::post('/bodegas/guiasderemision/consultarguiagenerada', [GuiasderemisionController::class, 'consultarguiagenerada']);
    Route::get('/bodegas/guiasderemision/imprimirguia/{id}', [GuiasderemisionController::class, 'imprimirguia']);
    Route::delete('/bodegas/guiasderemision/eliminarguia/{id}', [GuiasderemisionController::class, 'eliminarguia']);
    Route::get('/bodegas/guiasderemision/despacharguia/{id}', [GuiasderemisionController::class, 'despacharguia']);
    Route::post('/bodegas/guiasderemision/recibirguia', [GuiasderemisionController::class, 'recibirguia']);

    Route::get('/bodegas/transferencias/recibosventa/{id}', [TransferenciasController::class, 'recibosventa']);

    Route::post('/bodegas/guiasderemision/generarguiaventa', [GuiasderemisionController::class, 'generarguiaventa']);

    Route::get('/devoluciones/pedidoscliente/{cliente_id}/{puntodeventa_id}', [DevolucionesController::class, 'pedidoscliente']);
    Route::post('/devoluciones/detallepedidodevolucion', [DevolucionesController::class, 'detallepedidodevolucion']);
    Route::post('/devoluciones/enviardevolucion', [DevolucionesController::class, 'enviardevolucion']);
    Route::post('/devoluciones/detalledevolucion', [DevolucionesController::class, 'detalledevolucion']);
    Route::get('/devoluciones/verdevolucionesporrecibir/{puntodeventa}', [DevolucionesController::class, 'verdevolucionesporrecibir']);
    Route::post('/devoluciones/recibirdevolucion', [DevolucionesController::class, 'recibirdevolucion']);
    Route::get('/pedidos/sendfacturasgp', [PedidosController::class, 'sendfacturasgp']);
    Route::post('/pedidos/cancelarpedido', [PedidosController::class, 'cancelarpedido']);
    Route::post('/bodegas/guiasderemision/imprimirordenalmacenamiento', [GuiasderemisionController::class, 'imprimirordenalmacenamiento']);
    Route::post('/bodegas/guiasderemision/imprimirordenalmacenamientodevolucion', [GuiasderemisionController::class, 'imprimirordenalmacenamientodevolucion']);

    Route::get('/bodegas/getreposicion/{fecha}', [ReposicionController::class, 'getreposicion']);

    Route::get('/facturarsiguientemes', [PedidosController::class, 'facturarsiguientemes']);
    Route::get('/devoluciones/procesardevoluciones', [DevolucionesController::class, 'procesardevoluciones']);
    Route::get('/pedidos/imprimirpedido/{id}', [PedidosController::class, 'imprimirpedido']);
    Route::get('/cotizaciones/imprimircotizacion/{id}', [CotizacionesController::class, 'imprimircotizacion']);
    Route::get('/cotizaciones/imprimircotizacionprospecto/{id}', [CotizacionesController::class, 'imprimircotizacionprospecto']);
    Route::get('/devoluciones/imprimirdevolucion/{id}', [DevolucionesController::class, 'imprimirdevolucion']);
    Route::post('/consultas/notificaciones', [ConsultasController::class, 'notificaciones']);
});
Route::post('/login', [AuthController::class, 'login']);



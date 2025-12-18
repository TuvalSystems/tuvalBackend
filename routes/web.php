<?php

use App\Events\DemoMessage;
use App\Events\PingArrived;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SincronizacionController;
use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\ventas\CobranzasController;
use App\Http\Controllers\ventas\CotizacionesController;
use App\Http\Controllers\ventas\ConsultasController;
use App\Http\Controllers\ventas\OrdendecompraController;
use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\devoluciones\DevolucionesController;
use App\Http\Controllers\bodegas\GuiasderemisionController;
use App\Http\Controllers\bodegas\ReposicionController;
use App\Http\Controllers\bodegas\TransferenciasController;
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\ventas\AprobacionesController;
use App\Http\Controllers\ventas\ReservasController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    abort(404);
});
Route::get('/pedidos/sendfacturasgp', [PedidosController::class, 'sendfacturasgp']);
Route::get('/pedidos/corregirenviando', [PedidosController::class, 'corregirenviando']);
Route::get('/cobranzas/enviarcobros', [CobranzasController::class, 'enviarcobros']);
Route::get('/enviarordenesdecompraurl', [OrdendecompraController::class, 'enviarordenesdecompraurl']);
Route::get('/cotizaciones/checkvalidezcotizaciones', [CotizacionesController::class, 'checkvalidezcotizaciones']);
Route::get('/sincronizacion/llenaclientesgp', [SincronizacionController::class, 'llenaclientesgp']);
Route::get('/sincronizacion/llenadatoscoordenadasgp', [SincronizacionController::class, 'llenadatoscoordenadasgp']);
Route::get('/cobranzas/consultacobrosanulados', [CobranzasController::class, 'consultacobrosanulados']);
Route::get('/facturarsiguientemescron', [PedidosController::class, 'facturarsiguientemescron']);
Route::get('/facturarsiguientemes', [PedidosController::class, 'facturarsiguientemes']);


//Route::get('/sincronizacion/llenarelacionesgp', [SincronizacionController::class, 'llenarelacionesgp']);
Route::get('/sincronizacion/updateperfilesusuarios', [SincronizacionController::class, 'updateperfilesusuarios']);
Route::get('/sincronizacion/llenausuariosgp', [SincronizacionController::class, 'llenausuariosgp']);

Route::get('/consultas/reservados', [ConsultasController::class, 'reservados']);
Route::get('/consultas/reservasweb/{item?}', [ConsultasController::class, 'reservasweb']);
Route::get('/consultas/items_transferencia_venta', [ConsultasController::class, 'items_transferencia_venta']);
Route::get('/consultas/items_transferencia_reposicion', [ConsultasController::class, 'items_transferencia_reposicion']);
Route::get('/consultas/transferencias_pendientes_ingreso', [ConsultasController::class, 'transferencias_pendientes_ingreso']);
Route::get('/consultas/reposiciones_pendientes', [ConsultasController::class, 'reposiciones_pendientes']);
Route::get('/consultas/pendientes_despachar', [ConsultasController::class, 'pendientes_despachar']);
Route::get('/despachos/checkdespachosweb/{id}', [DespachosController::class, 'checkdespachosweb']);
Route::get('/pedidos/recalcularpedido/{id}', [PedidosController::class, 'recalcularpedido']);
//Route::get('/devoluciones/testtotaldevolucion', [DevolucionesController::class, 'testtotaldevolucion']);
//Route::get('/pedidos/corregirpedidos', [PedidosController::class, 'corregirpedidos']);
Route::get('/consultas/transferenciaspendientes', [ConsultasController::class, 'transferenciaspendientes']);
//Route::get('/despachos/ordenesliberadas/{puntodeventa_id}/{estado?}', [DespachosController::class, 'ordenesliberadas']);

//Route::get('/procesardevoluciones', [DevolucionesController::class, 'procesardevoluciones']);
//Route::get('/bajaratodos', [DevolucionesController::class, 'bajaratodos']);
//Route::get('/transferircompartimiento', [DevolucionesController::class, 'transferircompartimiento']);
Route::get('/generadevolucionventaurl', [DevolucionesController::class, 'generadevolucionventaurl']);
Route::get('/generadevolucionventaurlalt', [DevolucionesController::class, 'generadevolucionventaurlalt']);
//Route::get('/pedidos/imprimirpedido/{id}', [PedidosController::class, 'imprimirpedido']);
//Route::get('/volverafacturartesturl/{id}', [DevolucionesController::class, 'volverafacturartesturl']);
//Route::get('/checkfacturasdespachos/{id}', [DespachosController::class, 'checkfacturasdespachos']);
//Route::get('/checkcobrosenviados/', [CobranzasController::class, 'checkcobrosenviados']);
//Route::get('/checkeliminardespachourl', [DevolucionesController::class, 'checkeliminardespachourl']);
Route::get('pedidos/volver-facturar-test/{id}', [PedidosController::class, 'volveraFacuturarTest']);
Route::get('/procesardevolucionestest', [DevolucionesController::class, 'procesardevolucionestest']);
Route::get('/checkdespachadoscompletos', [DespachosController::class, 'checkdespachadoscompletos']);
Route::get('/sincronizacion/testcliente', [SincronizacionController::class, 'testcliente']);
Route::get('/sincronizacion/testchucknorris', [SincronizacionController::class, 'testchucknorris']);
Route::get('/bodegas/getreposicion/{fecha}/{documento?}', [ReposicionController::class, 'getreposicion']);
Route::get('/consultastockarticulopuntodeventa/{articulo}/{puntodeventa}', [ConsultasController::class, 'consultastockarticulopuntodeventa']);
Route::get('/pedidos/facturarfaltante', [PedidosController::class, 'facturarfaltante']);
Route::get('/generarguiatemp', [GuiasderemisionController::class, 'generarguiatemp']);
Route::get('/despachos/checkdespachadoscompletos', [DespachosController::class, 'checkdespachadoscompletos']);
Route::get('/transferencias/checkpedidostraslados', [TransferenciasController::class, 'checkpedidostraslados']);



Route::get('/bodegas/getreposicion', [ReposicionController::class, 'getreposicion']);


Route::get('/lista-ordenes-compra-recepcion', [OrdendecompraController::class, 'getViewPurchaseOrder']);
Route::get('/lista-ordenes-despachadas-pendientes', [OrdendecompraController::class, 'getOrdesDispatchPending']);

Route::get('/pedidos/lista-pedidos-sin-coordenadas', [PedidosController::class, 'getListPedidosSinCoordenadas']);


Route::get('/devolucion/generar-nota-credito-errores/{id}', [DevolucionesController::class, 'generarNotaCreditoDevolucionesFallidas']);
Route::get('/devolucion/generar-ajuste-devolucion/{id}', [DevolucionesController::class, 'generarAjusteDevolucionesFallidas']);

Route::get('/enviar-cotizaciones-recordatorio-sql', [CotizacionesController::class, 'sendAllQuotesRecorderSQLServer']);
Route::get('/cotizaciones/lista-items-alternativos', [CotizacionesController::class, 'getItemsAlternatives']);


Route::get('/cotizaciones/listado-cotizaciones-novedades', [CotizacionesController::class, 'consultQuotationsNews']);

Route::get('/consultas/buscarcode/{artCode}', [PedidosController::class, 'buscarIteml']);

/** asignar la nota de credito devolucion  */
Route::get('/devolucion/obtener-nota-credito-devolucion/{id}', [DevolucionesController::class, 'restaurarNotaCreditoDevolucion']);


Route::get('/cotizaciones/obtener-segmentos', [CotizacionesController::class, 'obtenerListaSegmentos']);
Route::get('/cotizaciones/obtener-subsegmentos', [CotizacionesController::class, 'obtenerListaSubSegmentos']);


Route::prefix('pedidos')->group(function () {
    Route::get('/facturar-pedido-test/{codPedido}', [PedidosController::class, 'facturarpedidoTest']);
    Route::get('/generar-orden-despacho/{id}', [PedidosController::class, 'generarOrdenDeDespacho']);
    Route::get('/recalcular-pedido/{id}', [PedidosController::class, 'recalcularPedidoTest']);
    Route::get('/actualizar-pedido-y-recalcular/{id}', [PedidosController::class, 'actualizarPedidoYRecalcular']);

    Route::get('actualizar-datos-pedidos', [PedidosController::class, 'actualizarOrdenCompraYNotaCliente']);

    Route::get('/listar-pedidos-reservas', [PedidosController::class, 'listarPedidosReservas']);
    Route::get('cancelar-orden-despacho/{id}', [DespachosController::class, 'cancelarOrdenDespacho']);
});


Route::prefix('cotizaciones')->group(function () {
    Route::get('reserva/recalcular-cotizacion/{id}', [CotizacionesController::class, 'recalcularCotizacionReserva']);
    Route::get('llenar-cotizacion', [CotizacionesController::class, 'llenarcotizacion']);

    Route::get('verificar-stock-actual-reserva', [ReservasController::class, 'verificarStockCotizacionActual']);

});

Route::prefix('bodegas')->group(function () {
    Route::get('/transferencias/checkpedidostraslados', [TransferenciasController::class, 'checkpedidostraslados']);

    Route::get('/guiasderemision/despacharguia/{id}', [GuiasderemisionController::class, 'despacharguia']);
    Route::get('/guiasderemision/checkguiasremisiones', [GuiasderemisionController::class, 'checkguiaremision']);
    Route::get('/guiasderemision/verificar-ajuste-guia-despacho/{id}', [GuiasderemisionController::class, 'verificarYAsignarAjusteGuiaDespacho']);

    Route::get('guiasderemision/crear-ajuste-guia-remision/{id}', [GuiasderemisionController::class, 'creacionAjusteGpGuiaRemision']);

    Route::get('guiasderemision/reserva-stock-pedidos', [GuiasderemisionController::class, 'reservarStock']);
    Route::get('guiasderemision/obtener-detalle-guia-despacho/{id}', [GuiasderemisionController::class, 'obtenerInfoGUiaRemisionAjustePedido']);
    Route::get('guiasderemision/despahco-pedido-por-guia/{id}', [GuiasderemisionController::class, 'procesarPedidoDespachoPorGuia']);

    Route::get('guiasderemision/lista-guia-despachos', [GuiasderemisionController::class, 'listaGuiaDespachoConAjuste']);

    Route::get('guiasderemision/lista-guia-reposicion-transferencia', [GuiasderemisionController::class, 'listaGuiaTransferenciaReposicionConAjuste']);


    Route::get('guiasderemision/verificar-ajuste-guia-transferencia', [GuiasderemisionController::class, 'procesarReajusteGuiaTransferencia']);
    Route::get('guiasderemision/verificar-ajuste-guia-reposicion', [GuiasderemisionController::class, 'procesarReajusteGuiaReposicion']);

});



/** para pruebas  */

// Route::get('/ping', function () {
//     broadcast(new PingArrived('pong ' . now()->toDateTimeString()));
//     return 'Ping enviado';
// });

// Route::get('/debug/demo-broadcast', function () {
//     event(new DemoMessage(request('msg', 'hola')));
//     return 'ok';
// });


Route::prefix('consultas')->group(function () {
    Route::get('/stock-transito', [ConsultasController::class, 'consultStockInTransit']);
    Route::get('buscararticulo-web', [ConsultasController::class, 'buscarArticuloWeb']);

});

Route::prefix('consultas')->group(function () {
    Route::get('/stock-transito', [ConsultasController::class, 'consultStockInTransit']);
});

Route::group(['prefix' => 'reservas-web'], function () {
    Route::get('transferencias-web/traslados-por-reserva-dashboard/{ptovta}', [ReservasController::class, 'TransferenciasPorReservaDashboard']);
    Route::get('transferencias-web/cabecera-traslados-por-reserva-bodega', [ReservasController::class, 'obtenerDetalleCabeceraTransferenciasReservaPorBodega']);
    Route::get('transferencias-web/lista-traslados-por-reserva-bodega', [ReservasController::class, 'ListaTransferenciasReservaPorBodega']);

    Route::get('enviar-guia-gp/{id}', [GuiasderemisionController::class, 'enviarGuiaRemisionReservaAGp']);
    Route::get('transferencias-web/lista-guias-remision-pendientes', [ReservasController::class, 'listaGuiaRemisionReservasPendientes']);

    Route::get('generar-pdf', [GuiasderemisionController::class, 'generarPdfGuiaRemisionReserva']);
    Route::get('despachar-guia-reserva', [GuiasderemisionController::class, 'despacharGuiaRemisionReserva']);

    Route::get('transferencias-web/lista-transferencias-por-recibir', [ReservasController::class, 'listaTransferenciasReservasPorRecibir']);
    Route::get('transferencias-web/detalle-transferencias-por-recibir', [ReservasController::class, 'detalleTransferenciaReservasPorRecibir']);
    Route::get('transferencias-web/imprimir-ord-almacenamiento', [ReservasController::class, 'imprimirOrdAlmacenamiento']);
    Route::get('transferencias-web/recibir-items-guia-reserva', [ReservasController::class, 'recibirItemsGuiaReserva']);
    Route::get('cancelar-reserva-web', [ReservasController::class, 'cancelarReservaCotizacion']);


    Route::get('despachos/reservas-enviadas', [DespachosController::class, 'listaGuiaReservasEnviadas']);

});

Route::prefix('aprobaciones-web')->group(function () {
    Route::get('lista-items-aprobaciones', [AprobacionesController::class, 'verpedidosycotizacionesporaprobar']);
    Route::get('lista-cotizaciones-pendientes', [AprobacionesController::class, 'listaCotizacionesPendientesPorAprobar']);
    Route::get('lista-pedidos-pendientes', [AprobacionesController::class, 'listaPedidosPendientesPorAprobar']);



});


Route::get('notifications-web/all', [UserNotificationController::class, 'index']);
Route::get('notifications-web/mark-as-read', [UserNotificationController::class, 'marcarNotificacion']);
Route::get('notifications-web/mark-all-as-read', [UserNotificationController::class, 'marcarNotificacion']);

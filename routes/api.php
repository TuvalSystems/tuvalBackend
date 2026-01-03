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
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\ventas\ReservasController;

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

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});
Route::middleware(['auth:sanctum', 'must.change.password'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    /**notify laravel  */

    Route::post('notifications/all', [UserNotificationController::class, 'index']);
    Route::post('notifications/mark-as-read', [UserNotificationController::class, 'marcarNotificacion']);
    Route::post('notifications/mark-all-as-read', [UserNotificationController::class, 'marcarTodasNotificaciones']);

    Route::post('/resetpassword', [AuthController::class, 'resetPassword']);
    Route::post('/change-status-user', [AuthController::class, 'changeStatusGpUser']);
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
    Route::post('/cotizaciones/actualizar-check-item-alternativo', [CotizacionesController::class, 'updateCheckAlternativo']);
    Route::post('/saveproveedor', [CotizacionesController::class, 'saveproveedor']);
    Route::post('/cotizaciones/guardarcotizacion', [CotizacionesController::class, 'guardarcotizacion']);
    Route::post('/cotizaciones/guardarcotizacionprospecto', [CotizacionesController::class, 'guardarcotizacionprospecto']);
    Route::post('/cotizaciones/limpiarcotizacion', [CotizacionesController::class, 'limpiarcotizacion']);
    Route::post('/cotizaciones/vercotizaciones', [CotizacionesController::class, 'vercotizaciones']);
    Route::post('/pedidos/verpedidos', [PedidosController::class, 'verpedidos']);
    Route::post('/pedidos/guardarpedido', [PedidosController::class, 'guardarpedido']);
    Route::post('/consultas/consultastockcotizacion', [ConsultasController::class, 'consultastockcotizacion']);
    Route::post('/pedidos/facturarpedido', [PedidosController::class, 'facturarpedido']);
    Route::post('/verpedidosycotizacionesporaprobar', [AprobacionesController::class, 'verpedidosycotizacionesporaprobar']);
    Route::post('/aprobaciones/verpedidosporaprobarcrediticios', [AprobacionesController::class, 'verpedidosporaprobarcrediticios']);
    Route::post('/verpedidosporaprobarcrediticios', [AprobacionesController::class, 'verpedidosporaprobarcrediticios']);
    Route::get('/aprobaciones/verdevolucionesporaprobar', [AprobacionesController::class, 'verdevolucionesporaprobar']);

    Route::post('/pedidos/detallepedido', [PedidosController::class, 'detallepedido']);
    Route::post('/pedidos/consultarexistente', [PedidosController::class, 'consultarexistente']);
    // Route::post('/pedidos/consultardespachado', [PedidosController::class, 'consultardespachado']);
    Route::post('/pedidos/facturarexistente', [PedidosController::class, 'facturarexistente']);
    // Route::post('/pedidos/facturardespachado', [PedidosController::class, 'facturardespachado']);
    // Route::post('/cotizaciones/detallecotizacion', [CotizacionesController::class, 'detallecotizacion']);

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

    Route::get('/cotizaciones/imprimircotizacion/{id}', [CotizacionesController::class, 'imprimircotizacion']);
    Route::get('/cotizaciones/imprimircotizacionimagenes/{id}', [CotizacionesController::class, 'imprimircotizacionimagenes']);
    Route::get('/cotizaciones/imprimircotizacionprospecto/{id}', [CotizacionesController::class, 'imprimircotizacionprospecto']);

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

    // neuvas rutas de consulta de guiasprocesadas separadas por consulta y no una sola como esta en /despachos/guiasprocesadas/{id}
    Route::get('/despachos/guiasdespachadas/{id}', [DespachosController::class, 'getDispatchedGuides']);
    Route::get('/despachos/transferenciasEnviadas/{id}', [DespachosController::class, 'getDispatchedTransfers']);
    Route::get('/despachos/transferenciasRecibidas/{id}', [DespachosController::class, 'getReceivedTransfers']);
    Route::get('/despachos/reposicionesEnviadas/{id}', [DespachosController::class, 'getDispatchedRepositions']);
    Route::get('/despachos/reposicionesRecibidas/{id}', [DespachosController::class, 'getReceivedRepositions']);
    Route::post('/despachos/reservas-enviadas', [DespachosController::class, 'listaGuiaReservasEnviadas']);
    Route::post('/despachos/reservas-recibidas', [DespachosController::class, 'listaGuiaReservasRecibidas']);




    // Route::post('/devoluciones/enviardevolucion', [DevolucionesController::class, 'enviardevolucion']);

    Route::get('/pedidos/sendfacturasgp', [PedidosController::class, 'sendfacturasgp']);
    Route::post('/pedidos/cancelarpedido', [PedidosController::class, 'cancelarpedido']);


    Route::get('/facturarsiguientemes', [PedidosController::class, 'facturarsiguientemes']);
    Route::get('/pedidos/imprimirpedido/{id}', [PedidosController::class, 'imprimirpedido']);

    Route::post('/consultas/notificaciones', [ConsultasController::class, 'notificaciones']);

    //new routes

    Route::group(['prefix' => 'cotizaciones'], function () {
        Route::get('lista-motivos-no-venta', [CotizacionesController::class, 'getlistNonSaleReasons']);
        Route::put('guardar-registro-no-venta', [CotizacionesController::class, 'updateNonSaleAndReminderData']);

        Route::post('lista-items-alternativos', [CotizacionesController::class, 'getItemsAlternatives']);
        Route::post('articulo-stock-por-bodega', [CotizacionesController::class, 'obtenerStockItemPorBodega']);

        Route::post('obtener-magen-item-l', [CotizacionesController::class, 'obteneMargenItemlL']);


        Route::post('detallecotizacion', [CotizacionesController::class, 'detallecotizacion']);

        /** Proceso Clientes */
        Route::post('obtener-segmentos', [CotizacionesController::class, 'obtenerListaSegmentos']);
        Route::post('obtener-subsegmentos', [CotizacionesController::class, 'obtenerListaSubSegmentos']);
        Route::post('validar-segmento-cliente', [CotizacionesController::class, 'validarSegmentoCliente']);
        Route::post('actualizar-segmento-cliente', [CotizacionesController::class, 'actualizarSegmentoCliente']);


        Route::get('imprimircotizacion/{id}', [CotizacionesController::class, 'imprimircotizacion']);
        Route::get('imprimircotizacionprospecto/{id}', [CotizacionesController::class, 'imprimircotizacionprospecto']);
    });

    Route::group(['prefix' => 'reservas'], function () {
        Route::get('validar-reserva-cotizacion/{id}', [ReservasController::class, 'validarGeneracionReservaCotizacion']);
        Route::post('verificar-stock-actual-reserva', [ReservasController::class, 'verificarStockCotizacionActual']);
        Route::post('guardar-solicitud-reserva', [ReservasController::class, 'guardarReservaCotización']);
        Route::get('lista-tipo-reservas', [ReservasController::class, 'obtenerListaTipoReservas']);
        Route::post('lista-solicitudes-reserva', [ReservasController::class, 'obtenerListaReservasPendientes']);
        Route::post('cancelar-solicitud-reserva', [ReservasController::class, 'cancelarReservaCotizacion']);

        Route::get('cotizacion-con-detalle/{id}', [ReservasController::class, 'obtenerCotizacionConDetalle']);
        Route::get('cotizacion-detalle-stock/{id}', [ReservasController::class, 'obtenerCotizacionDetalleConStock']);
        Route::get('cotizacion-detalle-stock-activo/{id}', [ReservasController::class, 'obtenerCotizacionConDetalleYStockActivo']);
        Route::post('rechazar-solicitud-reserva', [ReservasController::class, 'rechazarSolicitudReservaCotizacion']);
        Route::post('aprobar-solicitud-reserva', [ReservasController::class, 'aprobarSolicitudReservaCotizacion']);

        Route::post('lista-reserva-aprobadas', [ReservasController::class, 'obtenerListaCotizacionesAprobadas']);
        Route::post('procesar-reserva', [ReservasController::class, 'procesarReservaCotizacion']);
        Route::post('procesar-reserva-factura-demanda', [ReservasController::class, 'procesarReservaFacturaDemandaCotizacion']);

        /** transferencias de reservas apis */

        Route::get('transferencias/traslados-por-reserva-dashboard/{ptovta}', [ReservasController::class, 'TransferenciasPorReservaDashboard']);
        Route::post('transferencias/cabecera-traslados-por-reserva-bodega', [ReservasController::class, 'obtenerDetalleCabeceraTransferenciasReservaPorBodega']);
        Route::post('transferencias/lista-traslados-por-reserva-bodega', [ReservasController::class, 'ListaTransferenciasReservaPorBodega']);
        Route::post('transferencias/actualizar-picking-items', [ReservasController::class, 'actualizacionPickingItemsTraslados']);
        Route::post('transferencias/cancelar-picking-item-reserva', [ReservasController::class, 'cancelacionPickingItemTraslados']);
        Route::post('transferencias/pdf-picking-reserva', [ReservasController::class, 'generarPdfPickingReserva']);
        Route::post('transferencias/lista-vehiculos-transportistas', [ReservasController::class, 'listaVehiculosTransporte']);
        Route::post('transferencias/lista-guias-remision-reservas-pendientes', [ReservasController::class, 'listaGuiaRemisionReservasPendientes']);




    });

    Route::group(['prefix' => 'pedidos'], function () {
        Route::get('imprimirpedido/{id}', [PedidosController::class, 'imprimirpedido']);
        Route::get('imprimirpedidoimagenes/{id}', [PedidosController::class, 'imprimirpedidoimagenes']);
        Route::get('imprimirpedidoride/{id}', [PedidosController::class, 'imprimirpedidoride']);
        Route::get('imprimirpedidoride-sin-imagen/{id}', [PedidosController::class, 'imprimirpedidoridesinimagen']);
        Route::get('imprimirpedidoxml/{id}', [PedidosController::class, 'imprimirpedidoxml']);
        Route::get('despachoenproceso/{id}', [PedidosController::class, 'despachoenproceso']);
        Route::post('actualizar-datos-pedidos', [PedidosController::class, 'actualizarOrdenCompraYNotaCliente']);
        Route::post('actualizar-fecha-factura-reserva', [PedidosController::class, 'actualizarFechaReservaPedido']);
        Route::post('listar-pedidos-reservas', [PedidosController::class, 'listarPedidosReservas']);
        Route::post('bloquear-despacho-pedido', [PedidosController::class, 'actualizarBloqueoPendienteDespacho']);
        Route::post('validar-despachos-pedidos', [PedidosController::class, 'validacionExistenciaGuiasEnPedidos']);


    });

    Route::group(['prefix' => 'bodegas'], function () {

        Route::post('dashboard', [DashboardController::class, 'dashboard']);
        Route::post('recibosdecompra/bandeja', [RecibosdecompraeleController::class, 'bandeja']);
        Route::post('recibosdecompra/detalle', [RecibosdecompraeleController::class, 'detalle']);
        Route::post('recibosdecompra/recibir', [RecibosdecompraeleController::class, 'recibir']);
        Route::post('recibosdecompra/imprimirrecibol', [RecibosdecompraeleController::class, 'imprimirrecibol']);
        Route::post('recibosdecompra/eliminar', [RecibosdecompraeleController::class, 'eliminar']);
        Route::get('recibosdecompra/imprimir/{id}', [RecibosdecompraeleController::class, 'imprimir']);

        Route::get('listado-punto-venta', [DespachosController::class, 'obtenetListadoPuntoVenta']);
        Route::post('guiasderemision/detalle-guia-id-tipo', [GuiasderemisionController::class, 'consultarDetalleGuiaPorId']);
        Route::get('puntos-venta/reservas/limites', [DespachosController::class, 'indexPuntosVentaReservaLimits']);
        Route::post('guia-remision-reserva/detalle', [GuiasderemisionController::class, 'consultarDetalleGuiaReservaPorId']);



        Route::post('guiasderemision/generarguiatraslado', [GuiasderemisionController::class, 'generarguiatraslado']);
        Route::post('guiasderemision/consultarguiagenerada', [GuiasderemisionController::class, 'consultarguiagenerada']);
        Route::get('guiasderemision/imprimirguia/{id}', [GuiasderemisionController::class, 'imprimirguia']);
        Route::delete('guiasderemision/eliminarguia/{id}', [GuiasderemisionController::class, 'eliminarguia']);
        Route::get('guiasderemision/despacharguia/{id}', [GuiasderemisionController::class, 'despacharguia']);
        Route::post('guiasderemision/recibirguia', [GuiasderemisionController::class, 'recibirguia']);
        Route::get('transferencias/recibosventa/{id}', [TransferenciasController::class, 'recibosventa']);
        Route::post('guiasderemision/generarguiaventa', [GuiasderemisionController::class, 'generarguiaventa']);

        Route::get('transportes/transportistas', [TransportesController::class, 'transportistas']);
        Route::post('transportes/savetransportista', [TransportesController::class, 'savetransportista']);
        Route::post('transportes/savevehiculo', [TransportesController::class, 'savevehiculo']);
        Route::delete('transportes/deletetransportista/{id}', [TransportesController::class, 'deletetransportista']);
        Route::delete('transportes/deletevehiculo/{id}', [TransportesController::class, 'deletevehiculo']);
        Route::get('transferencias/transferenciasventa/{id}/{pedido?}/{compartimiento?}', [TransferenciasController::class, 'transferenciasVentas']);
        Route::post('transferencias/picking', [TransferenciasController::class, 'picking']);
        Route::post('transferencias/imprimirpicking', [TransferenciasController::class, 'imprimirpicking']);
        Route::post('transferencias/cancelarpicking', [TransferenciasController::class, 'cancelarpicking']);

        Route::get('reposicion/transferenciasreposicion/{id}/{pedido?}', [ReposicionController::class, 'transferenciasreposicion']);
        Route::get('reposicion/recibosreposicion/{id}', [ReposicionController::class, 'recibosreposicion']);
        Route::get('reposicion/recibosreposicionindividual/{id}', [ReposicionController::class, 'recibosreposicionindividual']);
        Route::get('reposicion/detallereciboreposicion/{id}', [ReposicionController::class, 'detallereciboreposicion']);

        Route::post('guiasderemision/imprimirordenalmacenamiento', [GuiasderemisionController::class, 'imprimirordenalmacenamiento']);
        Route::post('guiasderemision/imprimirordenalmacenamientodevolucion', [GuiasderemisionController::class, 'imprimirordenalmacenamientodevolucion']);

        Route::post('getreposicion', [ReposicionController::class, 'getreposicion']);

        //generacion de guia de remision de reservas
        Route::post('reservas/guia-remision/crear-guia-reserva', [GuiasderemisionController::class, 'crearGuiaRemisionReserva']);
        Route::post('reservas/guia-remision/generar-pdf', [GuiasderemisionController::class, 'generarPdfGuiaRemisionReserva']);
        Route::post('reservas/guia-remision/despachar-guia', [GuiasderemisionController::class, 'despacharGuiaRemisionReserva']);
        Route::post('reservas/guia-remision/lista-transferencias-por-recibir', [ReservasController::class, 'listaTransferenciasReservasPorRecibir']);
        Route::post('reservas/guia-remision/detalle-transferencias-por-recibir', [ReservasController::class, 'detalleTransferenciaReservasPorRecibir']);
        Route::post('reservas/guia-remision/detalle-transferencias-por-recibir/pdf', [ReservasController::class, 'imprimirOrdAlmacenamiento']);
        Route::post('reservas/guia-remision/detalle-transferencias-por-recibir/recibir', [ReservasController::class, 'recibirItemsGuiaReserva']);




    });

    Route::group(['prefix' => 'consultas'], function () {
        Route::post('buscararticulo', [ConsultasController::class, 'buscararticulo']);
        Route::post('buscarcliente', [ConsultasController::class, 'buscarcliente']);
        Route::post('consultarstock', [ConsultasController::class, 'consultarstock']);
        Route::post('consultastockothers', [ConsultasController::class, 'consultaStockOthers']);
        Route::post('consulta-stock-transito', [ConsultasController::class, 'consultaItemTransito']);
    });

    Route::group(['prefix' => 'aprobaciones'], function () {

        Route::post('aprobarpedido', [AprobacionesController::class, 'aprobarpedido']);
        Route::post('aprobarcotizacion', [AprobacionesController::class, 'aprobacionComercialCotizacion']);
        Route::post('rechazarcotizacion', [AprobacionesController::class, 'rechazarcotizacion']);
        Route::post('rechazarpedido', [AprobacionesController::class, 'rechazarpedido']);
        Route::post('consultaparaaprobarpedido', [AprobacionesController::class, 'consultaparaaprobarpedido']);
        Route::post('aprobarpedidocrediticio', [AprobacionesController::class, 'aprobarpedidocrediticio']);
        Route::post('rechazarpedidocrediticio', [AprobacionesController::class, 'rechazarpedidocrediticio']);
        Route::post('rechazarpedidocrediticioporstock', [AprobacionesController::class, 'rechazarpedidocrediticioporstock']);
        Route::post('rechazarpedidoporstock', [AprobacionesController::class, 'rechazarpedidoporstock']);
        Route::post('estadodecuenta', [AprobacionesController::class, 'estadodecuenta']);
        Route::post('aprobardevolucion', [AprobacionesController::class, 'aprobardevolucion']);
        Route::post('rechazardevolucion', [AprobacionesController::class, 'rechazardevolucion']);

        Route::post('cotizacion/rechazar-estado-espera', [AprobacionesController::class, 'recharCotizacionEstadoEspera']);
        Route::post('pedido/rechazar-estado-espera', [AprobacionesController::class, 'rechazarPedidoEstadoEspera']);


    });



    Route::group(['prefix' => 'devoluciones'], function () {
        Route::get('pedidoscliente/{cliente_id}/{puntodeventa_id}', [DevolucionesController::class, 'pedidoscliente']);
        Route::post('detallepedidodevolucion', [DevolucionesController::class, 'detallepedidodevolucion']);

        Route::post('enviardevolucion', [DevolucionesController::class, 'enviarDevolucion']);
        Route::post('detalledevolucion', [DevolucionesController::class, 'detalledevolucion']);
        Route::get('verdevolucionesporrecibir/{puntodeventa}', [DevolucionesController::class, 'verdevolucionesporrecibir']);
        // Route::post('recibirdevolucion', [DevolucionesController::class, 'recibirdevolucion']);

        Route::get('procesardevoluciones', [DevolucionesController::class, 'procesardevoluciones']);
        Route::get('imprimirdevolucion/{id}', [DevolucionesController::class, 'imprimirdevolucion']);


        Route::post('pendiente-recepcion', [DevolucionesController::class, 'listaDevolucionesPorRecibir']);
        Route::post('detalle-devolucion-por-recibir', [DevolucionesController::class, 'detalleDevolucionPorRecibir']);
        Route::post('orden-almacenamiento', [DevolucionesController::class, 'imprimirOrdenDeAlmacenamiento']);
        Route::post('recibir-devolucion', [DevolucionesController::class, 'recibirDevolucion']);
        Route::post('rechazar-devolucion', [DevolucionesController::class, 'rechazarDevolucion']);
    });
});
Route::post('/login', [AuthController::class, 'login']);

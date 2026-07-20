<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SectionsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\SincronizacionController;
use App\Http\Controllers\ventas\ConsultasController;
use App\Http\Controllers\ventas\CotizacionesController;
use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\ventas\AprobacionesController;
use App\Http\Controllers\ventas\CobranzasController;
use App\Http\Controllers\Inventario\Bodega\DashboardController;
use App\Http\Controllers\Inventario\Bodega\RecibosdecompraeleController;
use App\Http\Controllers\Inventario\Transporte\TransportesController;
use App\Http\Controllers\Inventario\Bodega\DespachosController;
use App\Http\Controllers\Inventario\Bodega\TransferenciasController;
use App\Http\Controllers\Inventario\Bodega\GuiasderemisionController;
use App\Http\Controllers\devoluciones\DevolucionesController;
use App\Http\Controllers\Inventario\Bodega\ReposicionController;
use App\Http\Controllers\Borradores\CotizacionBorradorController;
use App\Http\Controllers\Gestion\GestionSucursalesController;
use App\Http\Controllers\Admin\UserNotificationController;
use App\Http\Controllers\Inventario\Articulo\ArticuloController;
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


Route::post('/login', [AuthController::class, 'login']);


Route::middleware(['auth:sanctum',  'abilities:web'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('logout', [AuthController::class, 'logout']);
    Route::post('usuario/change-password', [UserController::class, 'actualizarContrasena']);

    /*
    |--------------------------------------------------------------------------
    | Rutas bloqueadas si must_change_password = 1
    |--------------------------------------------------------------------------
    */

    Route::middleware(['must.change.password'])->group(function () {

        /**notify laravel  */

        Route::post('notifications/all', [UserNotificationController::class, 'index']);
        Route::post('notifications/mark-as-read', [UserNotificationController::class, 'marcarNotificacion']);
        Route::post('notifications/mark-all-as-read', [UserNotificationController::class, 'marcarTodasNotificaciones']);


        Route::post('obtener-condiciones-pago', [AuthController::class, 'obtenerCondicionesDePago']);
        Route::post('obtener-metodos-pago', [AuthController::class, 'obtenerMetodosDePago']);


        Route::group(['prefix' => 'usuario'], function () {
            Route::post('lista-usuarios', [UserController::class, 'listaUsuarios']);
            Route::post('acceso-app-movil', [UserController::class, 'actualizarAccesoApp']);
            Route::post('acceso-admin-cobros', [UserController::class, 'actualizarAdminCobros']);
            Route::post('change-status-user', [UserController::class, 'actualizarEstadoUsuario']);
            // Route::post('change-password', [UserController::class, 'actualizarContrasena']);
            Route::post('reset-password', [UserController::class, 'resetearContrasenaUsuario']);
            Route::post('obtener-bodegas-por-punto-de-venta', [AuthController::class, 'obtenerBodegasPorPuntoDeVentaUsuario']);

            Route::post('perfiles-web', [UserController::class, 'obtenerListaPerfilesWeb']);
            Route::post('perfiles-app', [UserController::class, 'obtenerListaPerfilesApp']);
            Route::post('detalle-usuario', [UserController::class, 'obtenerDetalleUsuario']);
            Route::post('actualizar-perfiles', [UserController::class, 'actualizarPerfilesUsuario']);
            Route::post('obtener-secciones-activas', [SectionsController::class, 'obtenerMenuActivo']);
        });

        Route::group(['prefix' => 'secciones'], function () {
            Route::get('lista-secciones', [SectionsController::class, 'index']);
            Route::get('options', [SectionsController::class, 'options']);
            Route::post('/', [SectionsController::class, 'store']);
            Route::put('/{section}', [SectionsController::class, 'update']);
            Route::post('eliminar-seccion', [SectionsController::class, 'eliminarSeccion']);
        });


        Route::group(['prefix' => 'perfiles'], function () {
            Route::get('lista', [ProfileController::class, 'lista']);
            Route::post('crear', [ProfileController::class, 'create']);
            Route::post('actualizar', [ProfileController::class, 'update']);
            Route::post('eliminar', [ProfileController::class, 'delete']);
            Route::get('secciones-asignadas', [ProfileController::class, 'assignedSections']);
            Route::post('secciones/guardar', [ProfileController::class, 'saveSections']);
        });


        Route::get('/llenausuariosgp', [SincronizacionController::class, 'llenausuariosgp']);
        Route::get('/llenabodegasgp', [SincronizacionController::class, 'llenabodegasgp']);
        Route::get('/llenacondicionpagogp', [SincronizacionController::class, 'llenacondicionpagogp']);
        Route::get('/llenaarticulosgp', [SincronizacionController::class, 'llenaarticulosgp']);
        Route::get('/llenalistadepreciosgp', [SincronizacionController::class, 'llenalistadepreciosgp']);
        Route::get('/llenapuntodeventaycajasgp', [SincronizacionController::class, 'llenapuntodeventaycajasgp']);
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


        Route::post('/verpedidosporaprobarcrediticios', [AprobacionesController::class, 'verpedidosporaprobarcrediticios']);
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



        Route::get('/facturarsiguientemes', [PedidosController::class, 'facturarsiguientemes']);


        //new routes

        Route::group(['prefix' => 'cotizaciones'], function () {


            Route::get('lista-tipo-identificaciones', [CotizacionesController::class, 'obtenerListaTipoIdentificacion']);
            Route::get('lista-motivos-no-venta', [CotizacionesController::class, 'getlistNonSaleReasons']);
            Route::put('guardar-registro-no-venta', [CotizacionesController::class, 'updateNonSaleAndReminderData']);

            Route::post('lista-items-alternativos', [CotizacionesController::class, 'getItemsAlternatives']);
            Route::post('articulo-stock-por-bodega', [CotizacionesController::class, 'obtenerStockItemPorBodega']);
            Route::post('articulo-stock-por-bodega-v2', [CotizacionesController::class, 'obtenerStockItemsLote']);

            Route::post('obtener-magen-item-l', [CotizacionesController::class, 'obteneMargenItemlL']);


            Route::post('detallecotizacion', [CotizacionesController::class, 'detallecotizacion']);

            /** Proceso Clientes */
            Route::post('obtener-segmentos', [CotizacionesController::class, 'obtenerListaSegmentos']);
            Route::post('obtener-subsegmentos', [CotizacionesController::class, 'obtenerListaSubSegmentos']);
            Route::post('validar-segmento-cliente', [CotizacionesController::class, 'validarSegmentoCliente']);
            Route::post('actualizar-segmento-cliente', [CotizacionesController::class, 'actualizarSegmentoCliente']);
            Route::post('guardarcliente', [CotizacionesController::class, 'guardarcliente']);
            Route::post('guardar-nueva-direccion-sucursal', [CotizacionesController::class, 'guardarNuevaDireccionSucursal']);



            Route::get('imprimircotizacion/{id}', [CotizacionesController::class, 'imprimircotizacion']);
            Route::get('imprimircotizacionprospecto/{id}', [CotizacionesController::class, 'imprimircotizacionprospecto']);



            /** Proceso nuevo de guardar cotizaciones */
            Route::post('crear-cotizacion-v2', [CotizacionesController::class, 'store']);
            Route::post('recuperar-cotizacion-v2', [CotizacionesController::class, 'recuperarCotizacionV2']);
            Route::post('volver-a-pedir-cotizacion-v2', [CotizacionesController::class, 'volverAPedirCotizacionV2']);


            Route::get('imprimircotizacion/{id}', [CotizacionesController::class, 'imprimircotizacion']);
            Route::get('imprimircotizacionimagenes/{id}', [CotizacionesController::class, 'imprimircotizacionimagenes']);
            Route::get('imprimircotizacionprospecto/{id}', [CotizacionesController::class, 'imprimircotizacionprospecto']);
            Route::post('guardarmensaje', [CotizacionesController::class, 'guardarmensaje']);
            Route::post('guardarordendecompra', [CotizacionesController::class, 'guardarordendecompra']);


            /** proceso de actualizacion de registros de prospecto s */
            Route::prefix('prospecto')->group(function () {
                Route::post('actualizar', [CotizacionesController::class, 'actualizarCotizacionPropectoV2']);
                Route::post('obtener-lista-precio-defecto', [CotizacionesController::class, 'obtenerListaPrecioDefectoPuntoVenta']);
            });
            Route::post('selectarticuloprospecto', [CotizacionesController::class, 'selectarticuloprospecto']);
            Route::post('llenarcotizacionprospecto', [CotizacionesController::class, 'llenarcotizacionprospecto']);
            Route::post('guardarcotizacionprospecto', [CotizacionesController::class, 'guardarcotizacionprospecto']);
            Route::post('limpiarcotizacion', [CotizacionesController::class, 'limpiarcotizacion']);
            Route::post('vercotizaciones', [CotizacionesController::class, 'vercotizaciones']);
            Route::post('selectarticulo', [CotizacionesController::class, 'selectarticulo']);


            Route::post('recuperarcotizacionprospecto', [CotizacionesController::class, 'recuperarcotizacionprospecto']);
            Route::post('guardardireccion', [CotizacionesController::class, 'guardardireccion']);
            Route::post('checkrucprospecto', [CotizacionesController::class, 'checkrucprospecto']);
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
            Route::post('verificar-stock-reserva', [ReservasController::class, 'consultarReservaAprobarRechazar']);

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
            Route::post('detallepedido', [PedidosController::class, 'detallepedido']);


            /** nuevo proceso de copiar pedido y cotizacion */
            Route::post('copiar-cotizacion-o-pedido-v2', [PedidosController::class, 'copiarCotizacionOPedido']);
            Route::post('voler-a-pedir-pedido-v2', [PedidosController::class, 'volverAPedirPedido']);
            Route::post('crear-pedido-v2', [PedidosController::class, 'storeOrderV2']);
            Route::post('verpedidos', [PedidosController::class, 'verpedidos']);
            Route::post('facturarpedido', [PedidosController::class, 'facturarpedido']);
            Route::post('consultarexistente', [PedidosController::class, 'consultarexistente']);
            Route::post('facturarexistente', [PedidosController::class, 'facturarexistente']);
            Route::post('guardarmensaje', [PedidosController::class, 'guardarmensaje']);
            Route::post('guardarordendecompra', [PedidosController::class, 'guardarordendecompra']);
            Route::get('sendfacturasgp', [PedidosController::class, 'sendfacturasgp']);
            Route::post('cancelarpedido', [PedidosController::class, 'cancelarpedido']);
            Route::get('imprimirpedido/{id}', [PedidosController::class, 'imprimirpedido']);
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
            // Route::get('guiasderemision/despacharguia/{id}', [GuiasderemisionController::class, 'despacharguia']);
            Route::post('guiasderemision/recibirguia', [GuiasderemisionController::class, 'recibirguia']);
            Route::get('transferencias/recibosventa/{id}', [TransferenciasController::class, 'recibosventa']);
            Route::post('guiasderemision/generarguiaventa', [GuiasderemisionController::class, 'generarguiaventa']);

            Route::get('transportes/transportistas', [TransportesController::class, 'transportistas']);
            Route::post('transportes/savetransportista', [TransportesController::class, 'savetransportista']);
            Route::post('transportes/savevehiculo', [TransportesController::class, 'savevehiculo']);
            Route::delete('transportes/deletetransportista/{id}', [TransportesController::class, 'deletetransportista']);
            Route::delete('transportes/deletevehiculo/{id}', [TransportesController::class, 'deletevehiculo']);
            // Route::get('transferencias/transferenciasventa/{id}/{pedido?}/{compartimiento?}', [TransferenciasController::class, 'transferenciasVentas']);
            Route::post('transferencias/picking', [TransferenciasController::class, 'picking']);
            Route::post('transferencias/imprimirpicking', [TransferenciasController::class, 'imprimirpicking']);
            // Route::post('transferencias/cancelarpicking', [TransferenciasController::class, 'cancelarpicking']);

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
            Route::post('reservas/guia-remision/detalle-transferencias-por-recibir/recibir', [GuiasderemisionController::class, 'recibirItemsGuiaReserva']);



            //generacion de guia de remision de despachos
            Route::post('despachos/guia-remision/crear-guia-despacho', [GuiasderemisionController::class, 'crearGuiaRemisionDespacho']);
            Route::post('despachos/guia-remision/despachar-guia', [GuiasderemisionController::class, 'despacharGuiaRemisionDespacho']);



            //transferencia de ventas
            Route::post('transferencia-venta/dashboard', [TransferenciasController::class, 'dashboardTransferenciaVentas']);
            Route::post('transferencia-venta/cabecera', [TransferenciasController::class, 'obtenerCabeceraDetalleTransferenciaPorBodega']);
            Route::post('transferencia-venta/lista', [TransferenciasController::class, 'obtenerListaDetalleTransferenciaPorBodega']);
            Route::post('transferencia-venta/lista-guias-pendientes', [TransferenciasController::class, 'listaGuiaRemisionPendientes']);
            Route::post('transferencia-venta/actualizar-picking', [TransferenciasController::class, 'actualizarPickingItemsTransferenciaVenta']);
            Route::post('transferencia-venta/imprimir-picking', [TransferenciasController::class, 'imprimirPickingTransferenciaVenta']);

            //creacion de guia de remision de transferencia de venta
            Route::post('transferencia-venta/cancelar-picking', [TransferenciasController::class, 'cancelarPickingV2']);
            Route::post('transferencia-venta/guia-remision/crear-guia-transferencia-venta', [GuiasderemisionController::class, 'crearGuiaRemisionVenta']);
            Route::post('transferencia-venta/guia-remision/verificar-guia-transferencia-venta-v1', [GuiasderemisionController::class, 'consultarGuiaGeneradaV1']);
            Route::post('transferencia-venta/guia-remision/generar/pdf', [GuiasderemisionController::class, 'generarPdfGuiaRemisionVenta']);
            Route::post('transferencia-venta/guia-remision/despachar-guia-transferencia-venta', [GuiasderemisionController::class, 'despacharGuiaRemisionVenta']);
            Route::post('transferencia-venta/guia-remision/recibir-guia-transferencia-venta', [GuiasderemisionController::class, 'recibirGuiaRemisionVenta']);



            //creacion de guia de reposicion
            Route::post('reposiciones/guia-remision/crear-guia-reposicion', [GuiasderemisionController::class, 'crearGuiaRemisionReposicion']);
            Route::post('reposiciones/guia-remision/despachar-guia-reposicion', [GuiasderemisionController::class, 'despacharGuiaRemisionReposicion']);
            Route::post('reposiciones/guia-remision/recibir-guia-reposicion', [GuiasderemisionController::class, 'recibirGuiaRemisionReposicion']);
        });

        Route::group(['prefix' => 'inventario'], function () {
            Route::prefix('articulos')->group(function () {
                Route::get('configuracion-nivel', [ArticuloController::class, 'obtenerConfiguracionColorSegmento']);
                Route::post('configuracion-segmentacion', [ArticuloController::class, 'obtenerConfiguracionClasificacionSegmento']);
            });
        });
        Route::group(['prefix' => 'consultas'], function () {
            Route::post('consultastockcotizacion', [ConsultasController::class, 'consultastockcotizacion']);
            Route::post('territorios', [ConsultasController::class, 'territorios']);
            Route::get('puntodeventacaja', [ConsultasController::class, 'puntodeventacaja']);
            Route::get('cupodisponible/{ruc}', [ConsultasController::class, 'cupodisponible']);
            Route::post('buscararticulo', [ConsultasController::class, 'buscararticulo']);
            Route::post('buscarcliente', [ConsultasController::class, 'buscarcliente']);
            Route::post('consultarstock', [ConsultasController::class, 'consultarstock']);
            Route::post('consulta-stock-transito', [ConsultasController::class, 'consultaItemTransito']);
            Route::post('stock-exposicion', [ConsultasController::class, 'consultaStockExposicion']);


            /** nuevas api para proceso de consulta de cliente para crear cotizaciones */
            Route::post('buscar-clientes', [ConsultasController::class, 'buscarClientes']);
            Route::get('cliente/cupo-disponible/{ruc}', [ConsultasController::class, 'obtenerCupoDisponibleCliente']);
            Route::post('cliente/sucursales', [ConsultasController::class, 'obtenerSucursalesCliente']);


            Route::post('new-territorios', [ConsultasController::class, 'obtenerListaTerritorio']);
            Route::post('provincias', [ConsultasController::class, 'obtenerListaProvincias']);
            Route::post('ciudades', [ConsultasController::class, 'obtenerListaCiudadesPorProvincia']);
            Route::post('articulos/lista', [ConsultasController::class, 'buscarArticuloWeb']);
        });

        Route::group(['prefix' => 'aprobaciones'], function () {

            // Route::post('aprobarpedido', [AprobacionesController::class, 'aprobacionComercialPedido']);
            Route::post('aprobarcotizacion', [AprobacionesController::class, 'aprobacionComercialCotizacion']);
            Route::post('rechazarcotizacion', [AprobacionesController::class, 'rechazarcotizacion']);
            // Route::post('rechazarpedido', [AprobacionesController::class, 'rechazarpedido']);
            // Route::post('aprobarpedidocrediticio', [AprobacionesController::class, 'aprobarpedidocrediticio']);
            // Route::post('rechazarpedidocrediticio', [AprobacionesController::class, 'rechazarpedidocrediticio']);
            // Route::post('rechazarpedidocrediticioporstock', [AprobacionesController::class, 'rechazarpedidocrediticioporstock']);
            // Route::post('rechazarpedidoporstock', [AprobacionesController::class, 'rechazarpedidoporstock']);
            Route::post('aprobardevolucion', [AprobacionesController::class, 'aprobardevolucion']);
            Route::post('rechazardevolucion', [AprobacionesController::class, 'rechazardevolucion']);



            Route::get('verdevolucionesporaprobar', [AprobacionesController::class, 'verdevolucionesporaprobar']);
            Route::post('verpedidosporaprobarcrediticios', [AprobacionesController::class, 'verpedidosporaprobarcrediticios']);



            Route::post('verificar-stock-pedido', [AprobacionesController::class, 'verificarStockPedidoParaAprobacion']);


            Route::prefix('comercial')->group(function () {
                Route::post('consulta/lista-cotizaciones-pendientes', [AprobacionesController::class, 'listaCotizacionesPendientesConsulta']);
                Route::post('rechazar-cotizacion-estado-espera', [AprobacionesController::class, 'recharCotizacionEstadoEspera']);
                Route::post('lista-cotizaciones-pendientes', [AprobacionesController::class, 'listaCotizacionesPendientesPorAprobar']);


                Route::post('consulta/lista-pedidos-pendientes', [AprobacionesController::class, 'listaPedidosPendientesConsulta']);
                Route::post('rechazar-pedido-estado-espera', [AprobacionesController::class, 'rechazarPedidoEstadoEspera']);
                Route::post('lista-pedidos-pendientes', [AprobacionesController::class, 'listaPedidosPendientesPorAprobar']);
                Route::post('rechazar-pedido', [AprobacionesController::class, 'rechazarAprobacionComercialPedido']);
                Route::post('rechazar-pedido-stock', [AprobacionesController::class, 'rechazarAprobacionComercialPedidoStock']);
                Route::post('aprobar-pedido', [AprobacionesController::class, 'aprobacionComercialPedido']);
            });


            Route::prefix('crediticia')->group(function () {
                Route::post('lista-pedidos-pendientes', [AprobacionesController::class,  'listaPedidosPendientesAprobacionCrediticia']);
                Route::post('detalle-pedido', [PedidosController::class, 'obtenerPedidoDetalleCrediticio']);
                Route::post('estado-cuenta', [AprobacionesController::class, 'estadoCuentaCliente']);

                Route::post('aprobar-pedido', [AprobacionesController::class, 'aprobarpedidocrediticio']);
                Route::post('rechazar-pedido', [AprobacionesController::class, 'rechazarAprobacionCrediticiaPedido']);
                Route::post('rechazar-pedido-stock', [AprobacionesController::class, 'rechazarAprobacionCrediticiaPedidoStock']);
            });
        });



        Route::group(['prefix' => 'devoluciones'], function () {
            Route::get('pedidoscliente/{cliente_id}/{puntodeventa_id}', [DevolucionesController::class, 'pedidoscliente']);
            Route::post('detallepedidodevolucion', [DevolucionesController::class, 'detallepedidodevolucion']);

            Route::post('enviardevolucion', [DevolucionesController::class, 'enviarDevolucion']);
            Route::post('detalledevolucion', [DevolucionesController::class, 'detalledevolucion']);
            Route::get('procesardevoluciones', [DevolucionesController::class, 'procesardevoluciones']);
            Route::get('imprimirdevolucion/{id}', [DevolucionesController::class, 'imprimirdevolucion']);


            Route::post('pendiente-recepcion', [DevolucionesController::class, 'listaDevolucionesPorRecibir']);
            Route::post('detalle-devolucion-por-recibir', [DevolucionesController::class, 'detalleDevolucionPorRecibir']);
            Route::post('orden-almacenamiento', [DevolucionesController::class, 'imprimirOrdenDeAlmacenamiento']);
            Route::post('recibir-devolucion', [DevolucionesController::class, 'recibirDevolucion']);
            Route::post('rechazar-devolucion', [DevolucionesController::class, 'rechazarDevolucion']);
        });

        Route::prefix('borradores')->group(function () {
            Route::post('sync', [CotizacionBorradorController::class, 'sincronizarBorrador']);
            Route::post('list', [CotizacionBorradorController::class, 'listarBorradores']);
            Route::post('get', [CotizacionBorradorController::class, 'obtenerBorrador']);
            Route::post('delete', [CotizacionBorradorController::class, 'eliminarBorrador']);
        });

        Route::group(['prefix' => 'cobranzas'], function () {
            Route::post('documentosvencidoscliente', [CobranzasController::class, 'documentosvencidoscliente']);
            Route::post('estadodecuentacliente', [CobranzasController::class, 'estadodecuentacliente']);
            Route::post('estadodecuentaclientetuval', [CobranzasController::class, 'estadodecuentaclientetuval']);
            Route::post('registrarcobro', [CobranzasController::class, 'registrarcobro']);
            Route::post('cobrosregistrados', [CobranzasController::class, 'cobrosregistrados']);
            Route::post('depositosporaprobar', [CobranzasController::class, 'depositosporaprobar']);
            Route::post('aprobardeposito', [CobranzasController::class, 'aprobardeposito']);
            Route::post('rechazardeposito', [CobranzasController::class, 'rechazardeposito']);
            Route::get('getcajausuario/{puntodeventa}/{USERID}', [CobranzasController::class, 'getcajausuario']);

            Route::get('imprimircobro/{id}', [CobranzasController::class, 'imprimircobro']);
            Route::get('imprimircierredecaja/{fecha}/{caja}', [CobranzasController::class, 'imprimircierredecaja']);
            Route::get('imprimircierredecajausuario/{fecha}/{caja}', [CobranzasController::class, 'imprimircierredecajausuario']);
            Route::get('resumencaja/{fecha}/{caja}/{USERID}', [CobranzasController::class, 'resumencaja']);
            Route::post('consultaretencion', [CobranzasController::class, 'consultaretencion']);
            Route::post('consultacheque', [CobranzasController::class, 'consultacheque']);
            Route::post('consultadeposito', [CobranzasController::class, 'consultadeposito']);

            Route::get('estadodecuentaclientetuval/{ruc}', [CobranzasController::class, 'estadodecuentaclientetuval']);
            Route::get('estadodecuentaclientetuvalexcel/{ruc}', [CobranzasController::class, 'estadodecuentaclientetuvalexcel']);
            Route::get('estadocaja/{fechaActual}/{USERID}', [CobranzasController::class, 'estadocaja']);
            Route::get('estadocaja/{USERID}', [CobranzasController::class, 'estadocaja']);
            Route::get('abrircaja/{puntodeventa}/{USERID}', [CobranzasController::class, 'abrircaja']);
            Route::get('cerrarcaja/{puntodeventa}/{USERID}', [CobranzasController::class, 'cerrarcaja']);
            Route::post('consultacierrecajausuario', [CobranzasController::class, 'consultacierrecajausuario']);
            Route::post('consultacobroscliente', [CobranzasController::class, 'consultacobroscliente']);



            //nuevos procesos

            Route::post('lista-punto-venta', [CobranzasController::class, 'listaPvCajasUsuario']);
            Route::post('lista-cajas', [CobranzasController::class, 'listarCajasPorUsuarioPuntoVenta']);
            Route::post('consultas/cierre-caja', [CobranzasController::class, 'consultarCierreCajaUsuario']);
            Route::post('pdf/cierre-caja-usuario', [CobranzasController::class, 'imprimirPdfConsultaCierreCaja']);
            Route::post('pdf/cierre-caja', [CobranzasController::class, 'imprimirPdfCierreCaja']);


            //documentos aplicacion
            Route::post('actualizar-aplicaciones-pendientes', [CobranzasController::class, 'actualizarListaDocumentosAplicacion']);

            //validacion de aplicacion de pagos a faturas
            Route::post('validacion-pago-facturas', [CobranzasController::class, 'obtenerInformacionPagosFacturas']);

            //desaplicaciones
            Route::prefix('desaplicaciones')->group(function () {
                Route::post('lista', [CobranzasController::class, 'listaCobrosAplicados']);
                Route::post('detalle', [CobranzasController::class, 'detalleCobrosAplicados']);
                Route::post('procesar', [CobranzasController::class, 'procesarDesaplicaciones']);
            });


            Route::prefix('depositos-historicos')->group(function () {
                Route::post('lista', [CobranzasController::class, 'listaHistoricaDepositos']);
            });

            //apis de cobros de facturas
            Route::prefix('cobros-facturas')->group(function () {
                Route::post('estado-caja', [CobranzasController::class, 'obtenerEstadoCajaUsuario']);
                Route::post('aperturar-caja', [CobranzasController::class, 'aperturarCajaUsuario']);

                Route::post('imprimir-estado-cuenta-pdf', [CobranzasController::class, 'imprimirPdfEstadoCuentaCliente']);
                Route::post('imprimir-estado-cuenta-excel', [CobranzasController::class, 'imprimirExcelEstadoCuentaCliente']);
                Route::post('facturas-cliente', [CobranzasController::class, 'obtenerFacturasEstadoCuentaCliente']);
                Route::post('aplicaciones-pendientes', [CobranzasController::class, 'obtenerListaDeDocumentosPendientesAplicacion']);
                Route::get('obtener-bancos', [CobranzasController::class, 'obtenerListaBancos']);
                Route::post('verificar-comprobante', [CobranzasController::class, 'consultarExistenciaRetencion']);
            });

            Route::prefix('ordenes-despacho')->group(function () {
                Route::post('lista', [DespachosController::class, 'listaConsultaOrdenesDespacho']);
                Route::post('imprimir-pdf', [DespachosController::class, 'imprimirOrderDespachoPdf']);
            });
        });


        Route::group(['prefix' => 'admin'], function () {
            Route::post('lista-logs', [AdminController::class, 'ListaLogs']);
            Route::post('app/versions/list', [AppController::class, 'index']);
            Route::post('app/versions', [AppController::class, 'store']);
            Route::patch('app/versions/{id}/active', [AppController::class, 'active']);
            Route::delete('app/versions/{id}', [AppController::class, 'destroy']);
        });


        Route::prefix('gestion')->group(function () {
            Route::post('lista-sucursales', [GestionSucursalesController::class, 'listaSucursales']);
            Route::post('sucursal/detalle', [GestionSucursalesController::class, 'obtenerDetalleSucursal']);
            Route::post('sucursal/actualizar-estado', [GestionSucursalesController::class, 'actualizarEstadoSucursal']);
        });
    });
});




Route::group(['prefix' => 'consultas-externas'], function () {
    Route::post('reservados', [ConsultasController::class, 'consultarItemsReservados']);
});

// para la sincronizacion global de articulo 1 por registro
Route::group(['prefix' => 'inventario'], function () {
    Route::prefix('articulos')->group(function () {
        Route::post('sincronizar', [ArticuloController::class, 'sincronizarDesdeApi']);
    });
});

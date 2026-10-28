<?php

use App\Http\Controllers\Admin\Perfiles\ProfileController;
use App\Http\Controllers\Admin\Usuarios\UserController;
use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Inventario\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Integraciones\Sincronizacion\SincronizacionController;
use App\Http\Controllers\Ventas\Pedidos\PedidosController;
use App\Http\Controllers\Cobranzas\CajasController;
use App\Http\Controllers\Cobranzas\DepositosController;
use App\Http\Controllers\Cobranzas\AplicacionesController;
use App\Http\Controllers\Cobranzas\EstadoCuentaController;
use App\Http\Controllers\Cobranzas\CobrosController;
use App\Http\Controllers\Ventas\Cotizaciones\CotizacionesController;
use App\Http\Controllers\Ventas\Consultas\ConsultasController;
use App\Http\Controllers\Compras\OrdenesCompra\OrdenDeCompraController;
use App\Http\Controllers\Inventario\Despachos\DespachosController;
use App\Http\Controllers\Devoluciones\DevolucionesController;
use App\Http\Controllers\Inventario\GuiasRemision\GuiasDeRemisionController;
use App\Http\Controllers\Inventario\Reposiciones\ReposicionController;
use App\Http\Controllers\Inventario\Transferencias\TransferenciasController;
use App\Http\Controllers\Gestion\Sucursales\GestionSucursalesController;
use App\Http\Controllers\Test\TestController;
use App\Http\Controllers\Admin\Notificaciones\UserNotificationController;
use App\Http\Controllers\Inventario\Articulos\ArticuloController;
use App\Http\Controllers\Ventas\Aprobaciones\AprobacionesController;
use App\Http\Controllers\Ventas\Clientes\ClienteController;
use App\Http\Controllers\Compras\Proveedores\ProveedoresController;
use App\Http\Controllers\Inventario\Transporte\FleteExternoController;
use App\Http\Controllers\Ventas\Reservas\ReservasController;
use Illuminate\Support\Facades\Mail;

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

Route::get('/cobranzas/enviarcobros', [CobrosController::class, 'enviarCobrosLote']);
Route::get('/cotizaciones/checkvalidezcotizaciones', [CotizacionesController::class, 'checkvalidezcotizaciones']);
Route::get('/cobranzas/consultacobrosanulados', [CobrosController::class, 'consultacobrosanulados']);
Route::get('/facturarsiguientemescron', [PedidosController::class, 'facturarsiguientemescron']);
Route::get('/facturarsiguientemes', [PedidosController::class, 'facturarsiguientemes']);
Route::get('/sincronizacion/updateperfilesusuarios', [SincronizacionController::class, 'updateperfilesusuarios']);
Route::get('/sincronizacion/llenausuariosgp', [SincronizacionController::class, 'llenausuariosgp']);
Route::get('/despachos/checkdespachosweb/{id}', [DespachosController::class, 'checkdespachosweb']);
Route::get('pedidos/volver-facturar-test/{id}', [PedidosController::class, 'volveraFacuturarTest']);
Route::get('pedidos/volver-facturar-prueba/{id}', [PedidosController::class, 'volverAFacturarPrueba']);
Route::get('/procesardevolucionestest', [DevolucionesController::class, 'procesardevolucionestest']);
Route::get('/checkdespachadoscompletos', [DespachosController::class, 'checkdespachadoscompletos']);
Route::get('/sincronizacion/testcliente', [SincronizacionController::class, 'testcliente']);
Route::get('/sincronizacion/testchucknorris', [SincronizacionController::class, 'testchucknorris']);
Route::get('/bodegas/getreposicion/{fecha}/{documento?}', [ReposicionController::class, 'getreposicion']);
Route::get('/consultastockarticulopuntodeventa/{articulo}/{puntodeventa}', [ConsultasController::class, 'consultastockarticulopuntodeventa']);
Route::get('/pedidos/facturarfaltante', [PedidosController::class, 'facturarfaltante']);
Route::get('/despachos/checkdespachadoscompletos', [DespachosController::class, 'checkdespachadoscompletos']);
Route::get('/transferencias/checkpedidostraslados', [TransferenciasController::class, 'checkpedidostraslados']);
Route::get('/bodegas/getreposicion', [ReposicionController::class, 'getreposicion']);
Route::get('/lista-ordenes-compra-recepcion', [OrdenDeCompraController::class, 'getViewPurchaseOrder']);
Route::get('/lista-ordenes-despachadas-pendientes', [OrdenDeCompraController::class, 'getOrdesDispatchPending']);
Route::get('/pedidos/lista-pedidos-sin-coordenadas', [PedidosController::class, 'getListPedidosSinCoordenadas']);
Route::get('/enviar-cotizaciones-recordatorio-sql', [CotizacionesController::class, 'sendAllQuotesRecorderSQLServer']);

/** asignar la nota de credito devolucion  */
Route::get('/devolucion/obtener-nota-credito-devolucion/{id}', [DevolucionesController::class, 'restaurarNotaCreditoDevolucion']);




Route::prefix('pedidos')->group(function () {
    Route::get('facturar-pedido-test/{codPedido}', [PedidosController::class, 'facturarpedidoTest']);
    Route::get('generar-orden-despacho/{id}', [PedidosController::class, 'generarOrdenDeDespacho']);
    Route::get('recalcular-pedido/{id}', [PedidosController::class, 'recalcularPedidoTest']);
    Route::get('actualizar-pedido-y-recalcular/{id}', [PedidosController::class, 'actualizarPedidoYRecalcular']);
    Route::get('actualizar-datos-pedidos', [PedidosController::class, 'actualizarOrdenCompraYNotaCliente']);
    Route::get('listar-pedidos-reservas', [PedidosController::class, 'listarPedidosReservas']);
    Route::get('cancelar-orden-despacho/{id}', [DespachosController::class, 'cancelarOrdenDespacho']);
    Route::get('validar-despachos-pedidos', [PedidosController::class, 'validacionExistenciaGuiasEnPedidos']);
    Route::get('imprimirpedidoxml/{id}', [PedidosController::class, 'imprimirpedidoxml']);
    Route::get('crear-traslado/{pedido}', [PedidosController::class, 'crearTrasladoPedidos']);
    // Route::get('sendfacturasgp', [PedidosController::class, 'sendfacturasgp']);
    Route::get('recalcularpedido/{id}', [PedidosController::class, 'recalcularpedido']);
    Route::get('cancelar-enviando', [PedidosController::class, 'cancelarPedidoEstadoEnviando']);
});


Route::prefix('cotizaciones')->group(function () {
    Route::get('reserva/recalcular-cotizacion/{id}', [CotizacionesController::class, 'recalcularCotizacionReserva']);
    Route::get('llenar-cotizacion', [CotizacionesController::class, 'llenarcotizacion']);
    Route::get('verificar-stock-actual-reserva', [ReservasController::class, 'verificarStockCotizacionActual']);

    Route::get('obtener-segmentos', [CotizacionesController::class, 'obtenerListaSegmentos']);
    Route::get('obtener-subsegmentos', [CotizacionesController::class, 'obtenerListaSubSegmentos']);
    Route::get('listado-cotizaciones-novedades', [CotizacionesController::class, 'consultQuotationsNews']);
    Route::get('lista-items-alternativos', [CotizacionesController::class, 'getItemsAlternatives']);
    Route::get('vercotizaciones', [CotizacionesController::class, 'vercotizaciones']);

    Route::get('obtener-lista-precio-defecto', [CotizacionesController::class, 'obtenerListaPrecioDefectoPuntoVenta']);
    Route::get('lista-facturas-historicas', [CotizacionesController::class, 'obtenerFacturasHistoricasCotizacion']);
});

Route::prefix('bodegas')->group(function () {
    Route::get('transferencias/checkpedidostraslados', [TransferenciasController::class, 'checkpedidostraslados']);
    Route::get('guiasderemision/checkguiasremisiones', [GuiasDeRemisionController::class, 'checkguiaremision']);
    Route::get('guiasderemision/verificar-ajuste-guia-despacho/{id}', [GuiasDeRemisionController::class, 'verificarYAsignarAjusteGuiaDespacho']);
    Route::get('guiasderemision/crear-ajuste-guia-remision/{id}', [GuiasDeRemisionController::class, 'creacionAjusteGpGuiaRemision']);
    Route::get('guiasderemision/reserva-stock-pedidos', [GuiasDeRemisionController::class, 'reservarStock']);
    Route::get('guiasderemision/obtener-detalle-guia-despacho/{id}', [GuiasDeRemisionController::class, 'obtenerInfoGUiaRemisionAjustePedido']);
    Route::get('guiasderemision/despahco-pedido-por-guia/{id}', [GuiasDeRemisionController::class, 'procesarPedidoDespachoPorGuia']);
    Route::get('guiasderemision/lista-guia-despachos', [GuiasDeRemisionController::class, 'listaGuiaDespachoConAjuste']);
    Route::get('guiasderemision/lista-guia-reposicion-transferencia', [GuiasDeRemisionController::class, 'listaGuiaTransferenciaReposicionConAjuste']);

    Route::get('transferencia-ventas/dashboard', [TransferenciasController::class, 'dashboardTransferenciaVentas']);
    Route::get('transferencia-ventas/bodega/cabecera', [TransferenciasController::class, 'obtenerCabeceraDetalleTransferenciaPorBodega']);
    Route::get('transferencia-ventas/bodega/lista', [TransferenciasController::class, 'obtenerListaDetalleTransferenciaPorBodega']);
    Route::get('transferencia-ventas/bodega/lista-guias-pendientes', [TransferenciasController::class, 'listaGuiaRemisionPendientes']);





    //dupicacion guia despacho
    Route::get('guiasderemision/despacho/replicar/{id}', [GuiasDeRemisionController::class, 'duplicarGuiaSoloPapeles']);
});




Route::prefix('consultas')->group(function () {
    Route::get('stock-transito', [ConsultasController::class, 'consultStockInTransit']);
    Route::get('stock-transito-transferencia-venta', [ConsultasController::class, 'consultarStockTransitoTransferenciaVenta']);
    Route::get('stock-transito-transferencia-reservas', [ConsultasController::class, 'consultarStockTransitoReservas']);
    Route::get('stock-transito-consolidado', [ConsultasController::class, 'consultaTransitoConsolidado']);
    Route::get('buscararticulo-web', [ConsultasController::class, 'buscarArticuloWeb']);
    Route::get('articulo/item-l', [ConsultasController::class, 'buscarArticuloL']);

    Route::get('reservados', [ConsultasController::class, 'reservados']);
    Route::get('reservasweb/{item?}', [ConsultasController::class, 'reservasweb']);
    Route::get('items_transferencia_venta', [ConsultasController::class, 'items_transferencia_venta']);
    Route::get('items_transferencia_reposicion', [ConsultasController::class, 'items_transferencia_reposicion']);
    Route::get('transferencias_pendientes_ingreso', [ConsultasController::class, 'transferencias_pendientes_ingreso']);
    Route::get('reposiciones_pendientes', [ConsultasController::class, 'reposiciones_pendientes']);
    Route::get('pendientes_despachar', [ConsultasController::class, 'pendientes_despachar']);
    Route::get('transferenciaspendientes', [ConsultasController::class, 'transferenciaspendientes']);
    Route::get('buscarcode/{artCode}', [PedidosController::class, 'buscarIteml']);
    Route::get('territorios', [ConsultasController::class, 'obtenerListaTerritorio']);
    Route::get('provincias', [ConsultasController::class, 'obtenerListaProvincias']);
    Route::get('ciudades', [ConsultasController::class, 'obtenerListaCiudadesPorProvincia']);
    Route::get('stock-exposicion', [ConsultasController::class, 'consultaStockExposicion']);
});

Route::group(['prefix' => 'reservas-web'], function () {
    Route::get('transferencias-web/traslados-por-reserva-dashboard/{ptovta}', [ReservasController::class, 'TransferenciasPorReservaDashboard']);
    Route::get('transferencias-web/cabecera-traslados-por-reserva-bodega', [ReservasController::class, 'obtenerDetalleCabeceraTransferenciasReservaPorBodega']);
    Route::get('transferencias-web/lista-traslados-por-reserva-bodega', [ReservasController::class, 'ListaTransferenciasReservaPorBodega']);

    Route::get('enviar-guia-gp/{id}', [GuiasDeRemisionController::class, 'enviarGuiaRemisionReservaAGp']);
    Route::get('transferencias-web/lista-guias-remision-pendientes', [ReservasController::class, 'listaGuiaRemisionReservasPendientes']);

    Route::get('generar-pdf', [GuiasDeRemisionController::class, 'generarPdfGuiaRemisionReserva']);
    Route::get('despachar-guia-reserva', [GuiasDeRemisionController::class, 'despacharGuiaRemisionReserva']);

    Route::get('transferencias-web/lista-transferencias-por-recibir', [ReservasController::class, 'listaTransferenciasReservasPorRecibir']);
    Route::get('transferencias-web/detalle-transferencias-por-recibir', [ReservasController::class, 'detalleTransferenciaReservasPorRecibir']);
    Route::get('transferencias-web/imprimir-ord-almacenamiento', [ReservasController::class, 'imprimirOrdAlmacenamiento']);
    Route::get('transferencias-web/recibir-items-guia-reserva', [ReservasController::class, 'recibirItemsGuiaReserva']);
    Route::get('cancelar-reserva-web', [ReservasController::class, 'cancelarReservaCotizacion']);


    Route::get('despachos/reservas-enviadas', [DespachosController::class, 'listaGuiaReservasEnviadas']);
    Route::get('despachos/reservas-recibidas', [DespachosController::class, 'listaGuiaReservasRecibidas']);
});

Route::prefix('aprobaciones-web')->group(function () {
    Route::get('lista-cotizaciones-pendientes', [AprobacionesController::class, 'listaCotizacionesPendientesPorAprobar']);
    Route::get('lista-pedidos-pendientes', [AprobacionesController::class, 'listaPedidosPendientesPorAprobar']);
    Route::get('verificar-stock-reserva', [ReservasController::class, 'consultarReservaAprobarRechazar']);
});

Route::prefix('devoluciones')->group(function () {
    Route::get('lista-devoluciones-por-recibir', [DevolucionesController::class, 'listaDevolucionesPorRecibir']);
    Route::get('detalle-devolucion-por-recibir', [DevolucionesController::class, 'detalleDevolucionPorRecibir']);
    Route::get('generar-nota-credito-errores/{id}', [DevolucionesController::class, 'generarNotaCreditoDevolucionesFallidas']);
    Route::get('generar-ajuste-devolucion/{id}', [DevolucionesController::class, 'generarAjusteDevolucionesFallidas']);
    Route::get('detalle-solicitud-devolucion', [DevolucionesController::class, 'detallepedidodevolucion']);



});


Route::get('notifications-web/all', [UserNotificationController::class, 'index']);
Route::get('notifications-web/mark-as-read', [UserNotificationController::class, 'marcarNotificacion']);
Route::get('notifications-web/mark-all-as-read', [UserNotificationController::class, 'marcarNotificacion']);


Route::get('consultas-web/buscar-clientes', [ConsultasController::class, 'buscarClientes']);
Route::get('consultas-web/cliente/cupo-disponible/{ruc}', [ConsultasController::class, 'obtenerCupoDisponibleCliente']);


Route::get('user-web/consulta/obtener-bodegas', [AuthController::class, 'obtenerBodegasPorPuntoDeVentaUsuario']);
Route::get('user-web/consulta/obtener-condiciones-pago', [AuthController::class, 'obtenerCondicionesDePago']);
Route::get('user-web/consulta/obtener-metodos-pago', [AuthController::class, 'obtenerMetodosDePago']);

Route::get('cotizaciones-web/recuperar-cotizacion-v2', [CotizacionesController::class, 'recuperarCotizacionV2']);
Route::get('cotizaciones-web/volvrer-a-pedir-cotizacion-v2', [CotizacionesController::class, 'volverAPedirCotizacionV2']);
Route::get('cotizacion-pedido-web/copiar-cotizacion-pedido', [PedidosController::class, 'copiarCotizacionOPedido']);

Route::get('pedidos-web/voler-a-pedir-pedido-v2', [PedidosController::class, 'volverAPedirPedido']);


Route::prefix('dashboard')->group(function () {
    Route::get('ordenes-despacho-pendientes/{ptovta}', [DashboardController::class, 'listadoDespachosPendientes']);
    Route::get('contador-items-servicios/{ptovta}', [DashboardController::class, 'contadorItemsServiciosDespachos']);
});





Route::group(['prefix' => 'cobranzas'], function () {
    Route::get('lista-punto-venta', [CajasController::class, 'listaPvCajasUsuario']);
    Route::get('lista-cajas', [CajasController::class, 'listarCajasPorUsuarioPuntoVenta']);
    Route::get('consulta-cierre-caja', [CajasController::class, 'consultarCierreCajaUsuario']);
    Route::get('pdf/cierre-caja-usuario', [CajasController::class, 'imprimirPdfConsultaCierreCaja']);
    Route::get('pdf/cierre-caja', [CajasController::class, 'imprimirPdfCierreCaja']);

    Route::get('validacion-pago-facturas', [AplicacionesController::class, 'obtenerInformacionPagosFacturas']);
    Route::get('lista-aplicaciones', [AplicacionesController::class, 'listaCobrosAplicados']);
    Route::get('detalle-aplicaciones', [AplicacionesController::class, 'detalleCobrosAplicados']);




    //cobros facturas
    Route::prefix('cobros-facturas')->group(function () {
        Route::get('estado-caja', [CajasController::class, 'obtenerEstadoCajaUsuario']);
        Route::get('aperturar-caja', [CajasController::class, 'aperturarCajaUsuario']);
        Route::get('imprimir-estado-cuenta-pdf', [EstadoCuentaController::class, 'imprimirPdfEstadoCuentaCliente']);
        Route::get('imprimir-estado-cuenta-excel', [EstadoCuentaController::class, 'imprimirExcelEstadoCuentaCliente']);
        Route::get('facturas-cliente', [EstadoCuentaController::class, 'obtenerFacturasEstadoCuentaCliente']);
        Route::get('aplicaciones-pendientes', [AplicacionesController::class, 'obtenerListaDeDocumentosPendientesAplicacion']);
        Route::get('obtener-bancos', [CobrosController::class, 'obtenerListaBancos']);
        Route::get('verificar-comprobante', [CobrosController::class, 'consultarExistenciaRetencion']);
        Route::get('verificar-comprobante-deposito', [CobrosController::class, 'consultarExistenciaDeposito']);
        Route::get('verificar-cheque', [CobrosController::class, 'consultarExistenciaCheque']);
        Route::get('imprimir-cobro', [CobrosController::class, 'imprirCobroPdf']);
        Route::get('registrar-cobro', [CobrosController::class, 'registrarCobroV1']);
        Route::get('enviar-cobro', [CobrosController::class, 'enviarCobroGp']);
        Route::get('validar-cobros', [AplicacionesController::class, 'obtenerInformacionPagosFacturas']);

    });


    Route::prefix('cierre-cajas')->group(function () {
        Route::get('cerrar', [CajasController::class, 'cerrarCajaV1']);
        Route::get('resumen', [CajasController::class, 'resumenCerrarCaja']);
    });
});

Route::prefix('gestion')->group(function () {
    Route::get('lista-sucursales', [GestionSucursalesController::class, 'listaSucursales']);
    Route::get('sucursal/detalle', [GestionSucursalesController::class, 'obtenerDetalleSucursal']);
});


Route::prefix('proveedores')->group(function () {
    Route::get('lista-fletes-externos-por-proveedores', [FleteExternoController::class, 'listaFletesExternosPorProveedorPuntoVenta']);
});


Route::get('guia-remision-web/imprimirguia/{id}', [GuiasDeRemisionController::class, 'imprimirguia']);
Route::get('pedidos-web/facturar-existente', [PedidosController::class, 'facturarexistente']);


Route::get('notificacion-prueba', [TestController::class, 'enviarNotificacion']);

Route::get('perfiles-web', [UserController::class, 'obtenerListaPerfilesWeb']);
Route::get('perfiles-app', [UserController::class, 'obtenerListaPerfilesApp']);
Route::get('detalle-usuario', [UserController::class, 'obtenerDetalleUsuario']);


Route::group(['prefix' => 'perfiles-pruebas'], function () {
    Route::get('lista', [ProfileController::class, 'lista']);
});

Route::group(['prefix' => 'inventario-web'], function () {
    Route::prefix('articulos')->group(function () {
        Route::get('configuracion-nivel', [ArticuloController::class, 'obtenerConfiguracionColorSegmento']);
        Route::get('configuracion-segmentacion', [ArticuloController::class, 'obtenerConfiguracionClasificacionSegmento']);
        Route::get('lista-alternativas', [ArticuloController::class, 'obtenerListaItemsFiltroSegmentacion']);
        Route::get('item-logistico', [ArticuloController::class, 'obtenerArticuloLogistico']);
         Route::get('item-l', [ArticuloController::class, 'buscarArticuloL']);

        });
});


Route::prefix('despachos')->group(function () {
    Route::get('imprimir-pdf', [DespachosController::class, 'imprimirOrderDespachoPdf']);
});


Route::get('/test-mail', function () {
    Mail::raw('¡Hola! Este es un correo de prueba enviado desde Laravel 9 tras la actualización.', function ($message) {
        // Reemplaza esto con tu correo real
        $message->to('sistemas6@tuvalsa.com')
            ->subject('Prueba de configuración de correo - Laravel 9');
    });

    return '¡Comando ejecutado! Revisa tu bandeja de entrada.';
});


Route::get('clientes/crear', [ClienteController::class, 'guardarCliente']);

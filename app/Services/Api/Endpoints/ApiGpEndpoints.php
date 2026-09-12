<?php

namespace App\Services\Api\Endpoints;

class ApiGpEndpoints
{

    // ---
    // ✅ 1. Endpoints de Autenticación y Generales
    // ---
    public const GENERAL_VALIDAR_USUARIO = 'api/validar-usuario';
    public const GENERAL_CONSULTAR_CORREO_USUARIO = 'api/buscar-correo-usuario';
    public const GENERAL_CONSULTA_TRANSFERENCIA_CABECERA = 'api/consultar-cabecera-transferencia';
    public const GENERAL_CONSULTAR_TRANSFERENCIA_DETALLE = 'api/consultar-detalle-transferencia';
    public const GENERAL_CONSULTAR_LISTA_PRECIO_ARTICULO = 'api/consultar-lista-precio-articulos';
    public const GENERAL_CONSULTA_LISTA_SEGMENTACION_ARTICULO_ABC = 'api/consultar-lista-segmentacion-articulo-abc';

    // ---
    // 📦 2. Endpoints de Inventario y Stock ( Bodega )
    // ---

    public const INVENTARIO_CONSULTAR_STOCK_BODEGA = 'api/consultar-stock-bodega';
    public const INVENTARIO_CONSULTAR_STOCK_BODEGA_ASYNC = 'api/consultar-stock-bodega-lotes';
    public const INVENTARIO_CONSULTAR_ARTICULO_COMPARTIMENTO = 'api/consultar-stock-articulo-bodega-compartimento';
    public const INVENTARIO_TRANSFERENCIA_COMPARTIMENTO = 'api/transferencia-compartimento';
    public const INVENTARIO_CONSULTAR_ITEM_COMPARTIMENTO = 'api/devoluciones/consulta-item-compartimento-bin';

    public const INVENTARIO_CONSULTA_ITEM_EXPOSICION = 'api/consultas/items-exposiciones';

    // ---
    // ⬅️ 3. Endpoints de Devoluciones y Notas de Crédito
    // ---
    public const DEVOLUCIONES_GENERAR_NOTA_CREDITO = 'api/devoluciones/generar-nota-credito';
    public const DEVOLUCIONES_RESPUESTA_NOTA_CREDITO = 'api/devoluciones/respuesta-nota-credito';
    public const DEVOLUCIONES_GENERAR_DEVOLUCION_VENTA = 'api/devoluciones/generar-devolucion-venta';
    public const DEVOLUCIONES_CONSULTAR_GRUPO_ECONOMICO_CLIENTE = 'api/devoluciones/consultar-grupo-economico-cliente';

    // ---
    // 💵 4. Endpoints de Cobros y Cartera
    // ---
    public const COBROS_CONSULTAR_CUPO_DISPONIBLE = 'api/cobros/consultar-cupo-disponible-cliente';
    public const COBROS_CONSULTAR_DOCUMENTOS_VENCIDOS = 'api/cobros/consultar-documentos-vencidos-cliente';
    public const COBROS_CONSULTAR_DOCUMENTOS_SIN_SOPORTE = 'api/cobros/consultar-documentos-sin-soporte-cliente';
    public const COBROS_CONSULTAR_LISTA_VENCIDOS = 'api/cobros/consultar-lista-documentos-vencidos-cliente';
    public const COBROS_CONSULTAR_LISTA_SIN_SOPORTE = 'api/cobros/consultar-lista-documentos-sin-soporte-cliente';

    public const COBROS_CONSULTA_DIAS_FERIADOS = 'api/cobros/consultar-dias-feriado';
    public const COBROS_CONSULTA_APLICACIONES_PENDIENTES = 'api/cobros/consultar-documentos-pendientes-aplicacion';
    public const COBROS_CONSULTA_DETALLE_ESTADO_CUENTA_CLIENTE = 'api/cobros/consultar-detalle-estado-cuenta-cliente';

    public const COBROS_CONSULTA_DOCUMENTOS_COBROS_APLICADOS = 'api/cobros/consulta-documentos-cobros-aplicados';
    public const COBROS_CONSULTA_APLICACIONES_PENDIENTES_POR_DOCUMENTO = 'api/cobros/consulta-aplicaciones-pendientes-por-documento';
    public const COBROS_CONSULTA_RECIBOS_CHEQUES = 'api/cobros/consulta-recibos-cheques';
    public const COBROS_CONSULTA_ESTADO_CUENTA_CLIENTE = 'api/cobros/consultar-estado-cuenta-cliente';
    public const COBROS_CONSULTA_ESTADO_CUENTA_CLIENTE_RESUMEN = 'api/cobros/consultar-estado-cuenta-cliente-resumen';

    // apis para el registro de metodos de pago
    public const COBROS_FACTURAS_REGISTRAR_EFECTIVO     ="api/cobranzas/cobros-facturas/registrar-efectivo";
    public const COBROS_FACTURAS_REGISTRAR_APLICACION   = 'api/cobranzas/cobros-facturas/registrar-aplicacion';
    public const COBROS_FACTURAS_REGISTRAR_RETENCION    = 'api/cobranzas/cobros-facturas/registrar-retencion';
    public const COBROS_FACTURAS_REGISTRAR_CHEQUE       = 'api/cobranzas/cobros-facturas/registrar-cheque';



    // ---
    // 📄 5. Endpoints de Transacciones ( Recibos, Facturación )
    // ---

    public const TRANSACCIONES_VALIDAR_RECIBO_ITEM_L = 'api/transacciones/validar-generacion-documento-recibo-compra-l';
    public const TRANSACCIONES_ELIMINAR_RECIBO_ITEM_L = 'api/transacciones/eliminar-documento-recibo-compra-l';
    public const TRANSACCIONES_GUARDAR_RECIBO_ITEM_L = 'api/transacciones/guardar-documento-recibo-compra-l';
    public const TRANSACCIONES_GENERAR_RECIBO_ITEM_L = 'api/transacciones/generar-documento-recibo-compra-l';
    public const TRANSACCIONES_RESPUESTA_RECIBO_ITEM_L = 'api/transacciones/respuesta-documento-recibo-compra-l';
    public const TRANSACCIONES_ESTADO_RECEPCION_ITEM_L = 'api/transacciones/estado-recepcion-recibo-compra-l';
    public const TRANSACCIONES_GUARDAR_FACTURA_VTA = 'api/transacciones/guardar-documento-factura-vta';
    public const TRANSACCIONES_RESPUESTA_FACTURA_VTA = 'api/transacciones/respuesta-documento-factura-vta';
    public const TRANSACCIONES_ELIMINAR_FACTURA_VTA = 'api/transacciones/eliminar-documento-factura-vta';


    // ---
    // 👥 6. Endpoints de Cliente
    // ---
    public const CLIENTE_OBTENER_TODOS_SEGMENTOS = 'api/cliente/obtener-segmentos';
    public const CLIENTE_OBTENER_SUBSEGMENTOS_SEGMENTO = 'api/cliente/obtener-subsegmento-segmento';
    public const CLIENTE_OBTENER_TIPO_VENDEDOR = 'api/cliente/obtener-tipo-vendedor';
    public const CLIENTE_OBTENER_SEGMENTO = 'api/cliente/obtener-segmento-cliente';
    public const CLIENTE_OBTENER_TIPO_VENDEDOR_DETALLE = 'api/cliente/obtener-tipo-venededor-detalle';

    public const CLIENTE_GUARDAR_MODIFICAR = 'api/cliente/crear-modificar-cliente';
    public const CLIENTE_GUARDAR_SUBSEGMENTO_RUC = 'api/cliente/crear-registro-subsegmento-ruc';
    public const CLIENTE_GUARDAR_NUEVA_DIRECCION = 'api/cliente/crear-nueva-direccion';
    public const CLIENTE_CREAR_MODIFICAR_ESTADO_SUCURSAL = 'api/cliente/actualizar-o-crear-registro-sucursal-cliente';

    // ---
    // 📜 7. Endpoints de Guías de Remisión ( General y Movimientos )
    // ---
    // General
    public const GUIA_REMISION_CREAR = 'api/inventario/crear-guia-remision';
    public const GUIA_REMISION_EXISTE = 'api/inventario/validar-existencia-guia-remision';
    public const GUIA_OBTENER_CLAVE_ACCESO = 'api/inventario/guia-remision-obtener-claveacceso';
    // Movimientos
    public const GUIA_REPOSICION_MOVIMIENTO_GENERAR = 'api/inventario/guardar-generar-movimiento-reposicion';
    public const GUIA_DESPACHO_MOVIMIENTO_GENERAR = 'api/inventario/guardar-generar-movimiento-despacho';
    public const GUIA_RESERVA_MOVIMIENTO_GENERAR = 'api/inventario/reservas/guardar-generar-movimiento/transferencia';
    public const GUIA_RESERVA_RECIBIR_ITEMS = 'api/inventario/reservas/guardar-generar-movimiento/recibir';

    public const GUIA_TRFV_MOVIMIENTOS_GENERAR = 'api/inventario/transferencia-ventas/enviar/guardar-generar-movimiento';
    public const GUIA_TRFV_RECIBIR_ITEMS = 'api/inventario/transferencia-ventas/recibir/guardar-generar-movimiento';
    public const GUIA_REPOSICION_RECIBIR_ITEMS = 'api/inventario/reposiciones/recibir/guardar-generar-movimiento';

    // Validaciones
    public const GUIA_DESPACHO_CHECK_AJUSTE = 'api/inventario/validar-existencia-guia-despacho';
    public const GUIA_TRANSFERENCIA_CHECK_AJUSTE = 'api/inventario/validar-existencia-guia-transferencia';
    public const GUIA_REPOSICION_CHECK_AJUSTE = 'api/inventario/validar-existencia-guia-reposicion';
    public const GUIA_RESERVA_CHECK_AJUSTE = 'api/inventario/reservas/validar-existencia-guia';
    public const GUIA_TRFV_RECIBIDO_CHECK_AJUSTE = 'api/inventario/guia-remision/validar-existencia';

    // ---
    // 🔔 8. Endpoints de Reservas y Notificaciones
    // ---
    public const RESERVAS_OBTENER_NOTIFICACIONES_LOCAL = 'api/reservas/obtener-notificaciones-local';

    // ---
    // 🏷️ 9. Endpoints de Cotizaciones
    // ---
    public const COTIZACIONES_GUARDAR_RECORDATORIO = 'api/cotizaciones/guardar-cotizacion-recordatorio';
    public const COTIZACIONES_LISTA_ITEMS_ALTERNATIVOS = 'api/cotizaciones/lista-items-alternativos';
    public const COTIZACIONES_LISTA_PROYECTOS_VENTA = 'api/cotizaciones/lista-proyectos-venta';
    public const COTIZACION_OBTENER_REGISTRO_PROYECTO_VENTA = 'api/cotizaciones/proyecto-venta';


    // ---
    // 🔄 9. Endpoints de Sincronizaciones
    // ---
    public const SYNC_CLIENTES = 'api/sync/clientes';
    public const SYNC_CLIENTES_SUCURSALES = 'api/sync/clientes-sucursales';
    public const SYNC_CLIENTES_COORDENADAS = 'api/sync/clientes-coordenadas';



    // ---
    // 🏷️ 10. Endpoints para actualizaciones masivas
    // ---
    public const UPDATE_BATCH_GET_ORDERS_HEADER = 'api/facturas-cabecera-pendientes';
    public const UPDATE_BATCH_GET_ORDERS_DETAIL = 'api/facturas-detalles-pendientes';


    public const UPDATE_BATCH_UPDATE_ORDERS = 'api/update-headers';
    public const UPDATE_BATCH_UPDATE_ORDERS_DETAIL = 'api/update-details';
    public const UPDATE_BATCH_UPDATE_QUOTE_STATUS = 'api/update-quotes-status';





    // ---
    // 🔄 11. Endpoints de  articulos
    // ---

    public const ARTICULOS_CONSULTAR_LISTA_TRAMPAS_VAPOR = 'api/articulos/seleccion-trampas-items';
    public const ARTICULOS_OBTENER_CLASIFICACION    = 'api/articulos/obtener-clasificacion';
    public const ARTICULOS_OBTENER_CONFIGURACION_SEGMENTO = 'api/articulos/configuracion-segmentacion';
}

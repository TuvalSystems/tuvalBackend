<?php

namespace App\Services\Api\Endpoints;

class ApiGpEndpoints
{

    // ---
    // ✅ 1. Endpoints de Autenticación y Generales
    // ---
    public const GENERAL_VALIDAR_USUARIO = 'api/validar-usuario';
    public const GENERAL_CONSULTA_TRANSFERENCIA_CABECERA = 'api/consultar-cabecera-transferencia';
    public const GENERAL_CONSULTAR_TRANSFERENCIA_DETALLE = 'api/consultar-detalle-transferencia';
    public const GENERAL_CONSULTAR_SUCURSALES_CLIENTE = 'api/consultar-sucursales-clientes';
    public const GENERAL_CONSULTAR_COORDENADAS = 'api/consultar-listado-coordenadas';
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
    public const COBROS_CONSULTA_COBROS_SIN_APLICAR = 'api/cobros/consultar-cobros-sin-aplicar';
    public const COBROS_CONSULTA_DETALLE_ESTADO_CUENTA_CLIENTE = 'api/cobros/consultar-detalle-estado-cuenta-cliente';


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
    // ---
    // 📜 7. Endpoints de Guías de Remisión ( General y Movimientos )
    // ---
    // General
    public const GUIA_REMISION_CREAR = 'api/inventario/crear-guia-remision';
    public const GUIA_REMISION_EXISTE = 'api/inventario/validar-existencia-guia-remision';
    public const GUIA_OBTENER_CLAVE_ACCESO = 'api/inventario/guia-remision-obtener-claveacceso';
    // Movimientos
    public const GUIA_TRFV_MOVIMIENTOS_GENERAR = 'api/inventario/guardar-generar-movimiento-transferencia-venta';
    public const GUIA_REPOSICION_MOVIMIENTO_GENERAR = 'api/inventario/guardar-generar-movimiento-reposicion';
    public const GUIA_DESPACHO_MOVIMIENTO_GENERAR = 'api/inventario/guardar-generar-movimiento-despacho';
    public const GUIA_RESERVA_MOVIMIENTO_GENERAR = 'api/inventario/reservas/guardar-generar-movimiento/transferencia';
    public const GUIA_RESERVA_RECIBIR_ITEMS = 'api/inventario/reservas/guardar-generar-movimiento/recibir';
    // Validaciones
    public const GUIA_DESPACHO_CHECK_AJUSTE = 'api/inventario/validar-existencia-guia-despacho';
    public const GUIA_TRANSFERENCIA_CHECK_AJUSTE = 'api/inventario/validar-existencia-guia-transferencia';
    public const GUIA_REPOSICION_CHECK_AJUSTE = 'api/inventario/validar-existencia-guia-reposicion';
    public const GUIA_RESERVA_CHECK_AJUSTE = 'api/inventario/reservas/validar-existencia-guia';
    public const GUIA_RESERVA_RECIBIDO_CHECK_AJUSTE = 'api/inventario/reservas/validar-existencia-ajuste-recibir';

    // ---
    // 🔔 8. Endpoints de Reservas y Notificaciones
    // ---
    public const RESERVAS_OBTENER_NOTIFICACIONES_LOCAL = 'api/reservas/obtener-notificaciones-local';

    // ---
    // 🏷️ 9. Endpoints de Cotizaciones
    // ---
    public const COTIZACIONES_GUARDAR_RECORDATORIO = 'api/cotizaciones/guardar-cotizacion-recordatorio';
    public const COTIZACIONES_LISTA_ITEMS_ALTERNATIVOS = 'api/cotizaciones/lista-items-alternativos';
}

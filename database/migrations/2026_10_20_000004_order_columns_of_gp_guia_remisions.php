<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->reorder([
            // Identificacion y estado de la guia.
            'id', 'GUI_REM_CODIGO', 'tipo', 'estado',
            // Identificadores de bodegas inmediatamente despues del estado.
            'bodega_origen_id', 'bodega_destino_id',
            'bodega_origen', 'bodega_destino', 'GUI_REM_BODEGA',
            // Numeracion y documentos comerciales.
            'GUI_REM_GUIA_REMISION', 'GUI_REM_ESTABLECIMIENTO',
            'GUI_REM_PTO_EMISION', 'GUI_REM_SECUENCIAL', 'pedido', 'orden_compra',
            // Documento de sustento.
            'GUI_REM_CODI_DOCU_SUSTENTO', 'GUI_REM_NUME_DOCU_SUSTENTO',
            'GUI_REM_NUME_AUTO_DOCU_SUSTENTO', 'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO',
            // Traslado y recorrido.
            'GUI_REM_MOTIVO_TRASLADO', 'GUI_REM_DIRECCION_PARTIDA',
            'GUI_REM_FECHA_INICIO', 'GUI_REM_FECHA_FIN', 'GUI_REM_RUTA',
            // Destinatario.
            'sucursal_destinatario_id', 'GUI_REM_IDENTIFICACION_DESTINATARIO',
            'GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'GUI_REM_DIRECCION_DESTINATARIO',
            // Transporte y paqueteria.
            'tipo_despacho_id', 'proveedor_despacho_id', 'chofer_transportista_id',
            'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA',
            'GUI_REM_IDENTIFICACION_TRANSPORTISTA', 'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'GUI_REM_PLACA', 'requiere_orden_compra', 'numero_orden_compra_gp',
            'monto_orden_compra',
            // Observaciones y control interno.
            'GUI_REM_OBSERVACION', 'rebajado', 'recibidos', 'procesando',
            'idempotency_key', 'user_id',
        ]);
    }

    public function down(): void
    {
        $this->reorder([
            'id', 'GUI_REM_CODIGO', 'tipo', 'estado',
            'bodega_origen_id', 'bodega_destino_id', 'bodega_origen', 'bodega_destino',
            'pedido', 'GUI_REM_BODEGA', 'GUI_REM_GUIA_REMISION',
            'GUI_REM_ESTABLECIMIENTO', 'GUI_REM_PTO_EMISION', 'GUI_REM_SECUENCIAL',
            'GUI_REM_DIRECCION_PARTIDA', 'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'GUI_REM_IDENTIFICACION_TRANSPORTISTA', 'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA',
            'GUI_REM_FECHA_INICIO', 'GUI_REM_FECHA_FIN', 'GUI_REM_PLACA', 'GUI_REM_OBSERVACION',
            'GUI_REM_DIRECCION_DESTINATARIO', 'sucursal_destinatario_id',
            'GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'GUI_REM_IDENTIFICACION_DESTINATARIO',
            'GUI_REM_MOTIVO_TRASLADO', 'GUI_REM_RUTA', 'GUI_REM_CODI_DOCU_SUSTENTO',
            'GUI_REM_NUME_DOCU_SUSTENTO', 'GUI_REM_NUME_AUTO_DOCU_SUSTENTO',
            'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO', 'rebajado', 'recibidos',
            'idempotency_key', 'orden_compra', 'tipo_despacho_id', 'proveedor_despacho_id',
            'chofer_transportista_id', 'numero_orden_compra_gp', 'monto_orden_compra',
            'requiere_orden_compra', 'user_id', 'procesando',
        ]);
    }

    private function reorder(array $order): void
    {
        // Reutilizamos las definiciones reales para conservar tipos, valores por
        // defecto, nulabilidad, comentarios y AUTO_INCREMENT.
        $table = (array) DB::selectOne('SHOW CREATE TABLE `gp_guia_remisions`');
        $ddl = $table['Create Table'];
        preg_match_all('/^\s*`([^`]+)`\s+(.+)$/m', $ddl, $matches, PREG_SET_ORDER);
        $definitions = [];
        foreach ($matches as $match) {
            $definitions[$match[1]] = rtrim(trim($match[2]), ',');
        }

        if ($definitions === []) {
            throw new RuntimeException('No se pudieron leer las columnas de gp_guia_remisions.');
        }

        // Cualquier columna adicional se conserva antes de las fechas.
        $order = array_values(array_unique(array_merge(
            $order,
            array_diff(array_keys($definitions), ['created_at', 'updated_at']),
            ['created_at', 'updated_at']
        )));

        $changes = [];
        $previous = null;
        foreach ($order as $column) {
            if (!isset($definitions[$column])) {
                continue;
            }
            $quoted = '`' . str_replace('`', '``', $column) . '`';
            $position = $previous === null ? ' FIRST' : ' AFTER ' . $previous;
            $changes[] = 'MODIFY COLUMN ' . $quoted . ' ' . $definitions[$column] . $position;
            $previous = $quoted;
        }

        DB::statement('ALTER TABLE `gp_guia_remisions` ' . implode(', ', $changes));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterOcgdDetalleProveedorFkSetNull extends Migration
{
    public function up(): void
    {
        // 1) Si existe, dropear la FK actual (sea cual sea su nombre)
        $fkName = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'gp_orden_de_compra_generado_detalles')
            ->where('COLUMN_NAME', 'detalle_proveedores_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($fkName) {
            Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        // 2) Hacer la columna nullable
        Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('detalle_proveedores_id')->nullable()->change();
        });

        // 3) Crear FK con ON DELETE SET NULL
        Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) {
            $table->foreign('detalle_proveedores_id')
                ->references('id')->on('gp_pedido_detalle_proveedores')
                ->nullOnDelete(); // onDelete('set null')
        });
    }

    public function down(): void
    {
        // 1) Dropear la FK (cualquiera que exista ahora)
        $fkName = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'gp_orden_de_compra_generado_detalles')
            ->where('COLUMN_NAME', 'detalle_proveedores_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($fkName) {
            Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        // 2) Volver NOT NULL
        Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('detalle_proveedores_id')->nullable(false)->change();
        });

        // 3) Restaurar FK con CASCADE (si así estaba antes)
        Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) {
            $table->foreign('detalle_proveedores_id')
                ->references('id')->on('gp_pedido_detalle_proveedores')
                ->cascadeOnDelete();
        });
    }
}

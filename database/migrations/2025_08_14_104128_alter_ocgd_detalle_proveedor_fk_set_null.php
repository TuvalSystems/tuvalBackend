<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterOcgdDetalleProveedorFkSetNull extends Migration
{
     public function up(): void
    {
        $table   = 'gp_orden_de_compra_generado_detalles';
        $column  = 'detalle_proveedores_id';  // <-- tu columna (PLURAL)
        $fkName  = 'fk_ocgd_det_prov';        // nombre corto para la FK (<=64)
        $idxName = 'idx_ocgd_det_prov';       // nombre corto para el índice

        // 0) (Opcional) Confirma que la columna exista; si no, créala
        if (!Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable()->index();
            });
        }

        // 1) Asegurar que la columna sea NULLABLE y tipo correcto
        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->unsignedBigInteger($column)->nullable()->change();
        });

        // 2) SANEAR DATOS: poner en NULL los valores huérfanos (evita error al crear FK)
        // (MySQL)
        DB::statement("
            UPDATE {$table} d
            LEFT JOIN gp_pedido_detalle_proveedores p
                   ON p.id = d.{$column}
            SET d.{$column} = NULL
            WHERE d.{$column} IS NOT NULL
              AND p.id IS NULL
        ");

        // 3) Crear índice corto si no existe (MySQL requiere índice para FK)
        $idxExists = DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $idxName)
            ->exists();

        if (!$idxExists) {
            Schema::table($table, function (Blueprint $t) use ($column, $idxName) {
                $t->index($column, $idxName);
            });
        }

        // 4) Si ya hubiera alguna FK previa sobre la columna, la quitamos (por si quedó con otro nombre)
        $existingFk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($existingFk) {
            Schema::table($table, function (Blueprint $t) use ($existingFk) {
                $t->dropForeign($existingFk);
            });
        }

        // 5) Crear la FK con nombre corto y ON DELETE SET NULL
        Schema::table($table, function (Blueprint $t) use ($column, $fkName) {
            $t->foreign($column, $fkName)
              ->references('id')
              ->on('gp_pedido_detalle_proveedores')
              ->nullOnDelete(); // ON DELETE SET NULL
        });
    }

    public function down(): void
    {
        $table   = 'gp_orden_de_compra_generado_detalles';
        $column  = 'detalle_proveedores_id';
        $fkName  = 'fk_ocgd_det_prov';
        $idxName = 'idx_ocgd_det_prov';

        // Quitar nuestra FK (si existe)
        $hasFk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('CONSTRAINT_NAME', $fkName)
            ->exists();

        if ($hasFk) {
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                $t->dropForeign($fkName);
            });
        }

        // (Opcional) dejar la columna NOT NULL de nuevo
        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->unsignedBigInteger($column)->nullable(false)->change();
        });

        // (Opcional) quitar el índice que creamos
        $idxExists = DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $idxName)
            ->exists();

        if ($idxExists) {
            Schema::table($table, function (Blueprint $t) use ($idxName) {
                $t->dropIndex($idxName);
            });
        }
    }
}

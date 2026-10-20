<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['gp_guia_remision_reservas', 'gp_guia_remisions'] as $tableName) {
            foreach (['origen', 'destino'] as $side) {
                $column = 'bodega_' . $side . '_id';
                if (!Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column, $side) {
                        $table->foreignId($column)->nullable()
                            ->after($side === 'origen' ? 'estado' : 'bodega_origen_id')
                            ->constrained('gp_bodegas')->nullOnDelete();
                    });
                }
            }

            foreach (['origen', 'destino'] as $side) {
                $query = DB::table($tableName);

                if ($tableName === 'gp_guia_remisions' && $side === 'destino') {
                    $query->where('tipo', '<>', 'despacho');
                }

                $warehouse = DB::table('gp_bodegas')
                    ->select('id')
                    ->whereColumn('gp_bodegas.BOD_BODEGA', $tableName . '.bodega_' . $side)
                    ->limit(1);

                $query->update([
                    'bodega_' . $side . '_id' => DB::raw('(' . $warehouse->toSql() . ')'),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['gp_guia_remision_reservas', 'gp_guia_remisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('bodega_origen_id');
                $table->dropConstrainedForeignId('bodega_destino_id');
            });
        }
    }
};

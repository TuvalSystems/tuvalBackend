<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Completar únicamente la llave interna faltante a partir del código histórico.
        DB::table('gp_cotizacions')->whereNull('pv_id')->update([
            'pv_id' => DB::raw('(SELECT id FROM gp_punto_ventas WHERE gp_punto_ventas.PTO_VTA_ID = gp_cotizacions.PTO_VTA_ID)'),
        ]);

        $inconsistent = DB::table('gp_cotizacions as q')
            ->leftJoin('gp_punto_ventas as p', 'p.PTO_VTA_ID', '=', 'q.PTO_VTA_ID')
            ->where(function ($query) {
                $query->whereNull('p.id')->orWhereNull('q.pv_id')->orWhereColumn('q.pv_id', '<>', 'p.id');
            })->exists();
        if ($inconsistent) {
            throw new RuntimeException('Existen cotizaciones cuyo pv_id no coincide con PTO_VTA_ID. Revisar antes de activar los índices del listado.');
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            // Índices no únicos: no cambian los valores permitidos ni las reglas comerciales.
            // El segundo cubre los filtros del conteo, sin leer cada cabecera completa.
            DB::statement('ALTER TABLE gp_cotizacions
                ADD INDEX idx_cot_list_pv_fecha (pv_id, created_at, id),
                ADD INDEX idx_cot_list_pv_estado (pv_id, estado, created_at, id, prospecto_id, cliente_id, user_id, cotizacion, total),
                ADD INDEX idx_cot_list_pv_usuario (pv_id, user_id, created_at, id),
                ALGORITHM=INPLACE, LOCK=NONE');
            return;
        }

        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->index(['pv_id', 'created_at', 'id'], 'idx_cot_list_pv_fecha');
            $table->index(['pv_id', 'estado', 'created_at', 'id', 'prospecto_id', 'cliente_id', 'user_id', 'cotizacion', 'total'], 'idx_cot_list_pv_estado');
            $table->index(['pv_id', 'user_id', 'created_at', 'id'], 'idx_cot_list_pv_usuario');
        });
    }

    public function down(): void
    {
        // La llave histórica completada se conserva: no se vuelve a introducir un nulo.
        // InnoDB puede sustituir el índice de la FK por uno compuesto al crearlo.
        if (!Schema::hasIndex('gp_cotizacions', 'gp_cotizacions_pv_id_foreign')) {
            Schema::table('gp_cotizacions', fn (Blueprint $table) => $table->index('pv_id', 'gp_cotizacions_pv_id_foreign'));
        }
        foreach (['idx_cot_list_pv_fecha', 'idx_cot_list_pv_estado', 'idx_cot_list_pv_usuario'] as $index) {
            if (Schema::hasIndex('gp_cotizacions', $index)) {
                Schema::table('gp_cotizacions', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }
    }
};

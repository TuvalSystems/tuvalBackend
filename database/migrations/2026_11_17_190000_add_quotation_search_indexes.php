<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            // Conservar LIKE sobre las columnas originales y su collation.
            // El índice cubre la búsqueda; no necesita leer las cabeceras completas.
            DB::statement('ALTER TABLE gp_cotizacions
                ADD INDEX idx_cot_list_prospectos (pv_id, cliente_id, created_at, id, prospecto_id, estado, user_id),
                ADD INDEX idx_cot_list_busqueda (pv_id, created_at, id, cotizacion, CLI_RAZON_SOCIAL, CLI_RUC, USERID, COND_ID, estado, prospecto_id, cliente_id, user_id, total),
                ALGORITHM=INPLACE, LOCK=NONE');
            return;
        }

        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->index(['pv_id', 'cliente_id', 'created_at', 'id', 'prospecto_id', 'estado', 'user_id'], 'idx_cot_list_prospectos');
            $table->index(['pv_id', 'created_at', 'id', 'cotizacion', 'CLI_RAZON_SOCIAL', 'CLI_RUC', 'USERID', 'COND_ID', 'estado', 'prospecto_id', 'cliente_id', 'user_id', 'total'], 'idx_cot_list_busqueda');
        });
    }

    public function down(): void
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dropIndex('idx_cot_list_prospectos');
            $table->dropIndex('idx_cot_list_busqueda');
        });
    }
};

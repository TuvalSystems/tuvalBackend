<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ia_operaciones');
        Schema::dropIfExists('ia_conversaciones');

        if (Schema::hasColumn('ia_configuracion', 'max_turnos')) {
            Schema::table('ia_configuracion', function (Blueprint $table) {
                $table->dropColumn('max_turnos');
            });
        }
        if (Schema::hasColumn('ia_consumos', 'conversacion_id')) {
            if (Schema::hasIndex('ia_consumos', 'ia_consumos_conversacion_id_index')) {
                Schema::table('ia_consumos', function (Blueprint $table) {
                    $table->dropIndex('ia_consumos_conversacion_id_index');
                });
            }
            Schema::table('ia_consumos', function (Blueprint $table) {
                $table->dropColumn('conversacion_id');
            });
        }
    }

    public function down(): void
    {
        // Los mensajes eliminados no se pueden reconstruir. El histórico de tokens se conserva.
    }
};

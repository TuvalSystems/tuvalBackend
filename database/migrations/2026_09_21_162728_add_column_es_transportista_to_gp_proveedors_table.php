<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gp_proveedors', function (Blueprint $table) {
            $table->boolean('es_transportista')->default(0)->after('PROV_ESTADO');
            $table->foreignId('usuario_modifica_id')->nullable()->after('es_transportista')
                ->comment('Usuario que modifica el registro')
                ->constrained('gp_usuarios')
                ->nullOnDelete();
            $table->dateTime('fecha_modificacion')->nullable()->after('usuario_modifica_id');
            $table->foreignId('fle_usuario_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('fle_fecha')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_proveedors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fle_usuario_id');
            $table->dropConstrainedForeignId('usuario_modifica_id');
            $table->dropColumn(['es_transportista', 'fecha_modificacion', 'fle_fecha']);
        });
    }
};

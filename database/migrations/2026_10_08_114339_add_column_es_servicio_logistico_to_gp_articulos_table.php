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
        Schema::table('gp_articulos', function (Blueprint $table) {
            $table->boolean('es_servicio_logistico')->default(0)->after('combinado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_articulos', function (Blueprint $table) {
            $table->dropColumn(['es_servicio_logistico']);
        });
    }
};

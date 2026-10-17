<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    public function up(): void
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->boolean('es_servicio_logistico')->default(0)->after('modificador');
        });

        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->boolean('es_servicio_logistico')->default(0)->after('modificador');
        });

        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->boolean('es_servicio_logistico')->default(0)->after('modificador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn(['es_servicio_logistico']);
        });
        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn(['es_servicio_logistico']);
        });
        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->dropColumn(['es_servicio_logistico']);
        });
    }
};

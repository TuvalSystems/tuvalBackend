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
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->boolean('facturar_ahora')
                ->after('factura_siguiente_mes')->default(false);
        });

        Schema::table('hi_cotizacion', function (Blueprint $table) {
            $table->boolean('facturar_ahora')
                ->after('factura_siguiente_mes')->default(false);
        });

        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->boolean('facturar_ahora')
                ->after('factura_siguiente_mes')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dropColumn(['facturar_ahora']);
        });
        Schema::table('hi_cotizacion', function (Blueprint $table) {
            $table->dropColumn(['facturar_ahora']);
        });
        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->dropColumn(['facturar_ahora']);
        });
    }
};

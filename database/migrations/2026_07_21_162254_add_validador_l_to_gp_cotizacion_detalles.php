<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->boolean('validador_l')->default(0)->after('valor_original_l');
        });

        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->boolean('validador_l')->default(0)->after('valor_original_l');
        });

        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->boolean('validador_l')->default(0)->after('valor_original_l');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn('validador_l');
        });

        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn('validador_l');
        });

        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->dropColumn('validador_l');
        });
    }
};

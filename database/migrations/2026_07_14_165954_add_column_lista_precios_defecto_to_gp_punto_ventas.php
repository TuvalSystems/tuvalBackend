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
        Schema::table('gp_punto_ventas', function (Blueprint $table) {
            $table->string('lista_precios_defecto',50)->nullable()->after('pto_vta_max_dias_autorizacion_reserva');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_punto_ventas', function (Blueprint $table) {
            $table->dropColumn('lista_precios_defecto');
        });
    }
};

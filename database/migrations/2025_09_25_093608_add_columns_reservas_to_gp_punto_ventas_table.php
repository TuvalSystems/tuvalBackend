<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsReservasToGpPuntoVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_punto_ventas', function (Blueprint $table) {
              $table->unsignedTinyInteger('pto_vta_max_dias_reserva')
                ->default(0)
                ->after('PTO_VTA_DIAS_MAXIMO_PARA_ANULACION')
                ->comment('Días máximos para solicitud de reserva de ventas');
            $table->unsignedTinyInteger('pto_vta_max_dias_autorizacion_reserva')
                ->default(0)
                ->after('pto_vta_max_dias_reserva')
                ->comment('Días máximos para autorización de reserva de ventas');
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
            $table->dropColumn([
                'pto_vta_max_dias_reserva',
                'pto_vta_max_dias_autorizacion_reserva',
            ]);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFechaHoraAprobacionComercialCrediticiaToGpPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->dateTime('fecha_hora_aprobacion_comercial')->nullable()->after('aprobador_comercial_id');
            $table->dateTime('fecha_hora_aprobacion_crediticia')->nullable()->after('aprobador_crediticio_id');
        });
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dateTime('fecha_hora_aprobacion_comercial')->nullable()->after('aprobador_comercial_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->dropColumn(['fecha_hora_aprobacion_comercial', 'fecha_hora_aprobacion_crediticia']);
        });

        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dropColumn('fecha_hora_aprobacion_comercial');
        });
    }
}

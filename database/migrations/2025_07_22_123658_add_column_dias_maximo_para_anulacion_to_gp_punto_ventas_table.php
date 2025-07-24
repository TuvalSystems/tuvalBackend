<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnDiasMaximoParaAnulacionToGpPuntoVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_punto_ventas', function (Blueprint $table) {
            $table->integer('PTO_VTA_DIAS_MAXIMO_PARA_ANULACION')->default(0)->after('PTO_VTA_DIA_VALIDEZ_COTIZACION')->comment('Días máximos para anulación de ventas');
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
            $table->dropColumn('PTO_VTA_DIAS_MAXIMO_PARA_ANULACION');
        });
    }
}

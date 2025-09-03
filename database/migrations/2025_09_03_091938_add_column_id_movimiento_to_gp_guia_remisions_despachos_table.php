<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdMovimientoToGpGuiaRemisionsDespachosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_guia_remision_despachos', function (Blueprint $table) {
            $table->bigInteger('id_movimiento')->unsigned()->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_guia_remision_despachos', function (Blueprint $table) {
            $table->dropColumn('id_movimiento');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDespachadoParcialToGpDevolucionesDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_devoluciones_detalles', function (Blueprint $table) {
             $table->boolean('despachado_parcial')->default(0)->after('compartimiento')->comment('Indica si el ítem fue despachado parcialmente');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_devoluciones_detalles', function (Blueprint $table) {
             $table->dropColumn('despachado_parcial');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCheckAlternativoGpDevolucionesDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('gp_devoluciones_detalles', function (Blueprint $table) {
           $table->boolean('CHECK_ALTERNATIVO')->default(0)->after('ART_DESCRIPCION_ORIGINAL');

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
            $table->dropColumn('CHECK_ALTERNATIVO');
        });
    }
}

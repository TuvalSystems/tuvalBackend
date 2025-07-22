<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAnulacionHabilitadaGpDevolucionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_devoluciones', function (Blueprint $table) {
           $table->boolean('anulacion_habilitada')->default(0)->after('permite_anulacion');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->dropColumn('anulacion_habilitada');
        });
    }
}

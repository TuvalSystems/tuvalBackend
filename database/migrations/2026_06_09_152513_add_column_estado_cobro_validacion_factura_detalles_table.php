<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnEstadoCobroValidacionFacturaDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cobro_validacion_factura_detalles', function (Blueprint $table) {
            $table->string('estado')->after('monto')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cobro_validacion_factura_detalles', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
}

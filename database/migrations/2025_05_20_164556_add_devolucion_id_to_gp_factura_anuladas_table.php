<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDevolucionIdToGpFacturaAnuladasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_factura_anuladas', function (Blueprint $table) {
            $table->integer('devolucion_id')->nullable()->after ('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_factura_anuladas', function (Blueprint $table) {
            $table->dropColumn('devolucion_id');
        });
    }
}

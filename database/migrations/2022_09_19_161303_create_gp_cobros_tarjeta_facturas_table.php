<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosTarjetaFacturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_tarjeta_facturas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->string('id_factura', 30);
            $table->decimal('monto', 11, 2);
            $table->string('observacion', 200)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cobros_tarjeta_facturas', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_tarjeta_facturas0')->references('id')->on('gp_cobros')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_tarjeta_facturas');
    }
}

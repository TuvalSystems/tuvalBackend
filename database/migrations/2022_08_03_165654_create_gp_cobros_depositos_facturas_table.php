<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosDepositosFacturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros_depositos_facturas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cobro_id')->unsigned();
            $table->bigInteger('deposito_id')->unsigned();
            $table->string('id_factura', 30);
            $table->decimal('monto', 11, 2);
            $table->string('observacion', 200)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cobros_depositos_facturas', function (Blueprint $table) {
            $table->foreign('cobro_id', 'gp_cobros_depositos_facturas_fk0')->references('id')->on('gp_cobros')->onDelete('cascade');
            $table->foreign('deposito_id', 'gp_cobros_depositos_facturas_fk1')->references('id')->on('gp_cobros_depositos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros_depositos_facturas');
    }
}

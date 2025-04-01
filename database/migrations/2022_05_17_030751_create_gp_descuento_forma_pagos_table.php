<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpDescuentoFormaPagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('gp_descuento_forma_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('DESC_FORMA_PAGO_CODIGO', 10)->unique();
            $table->string('DESC_FORMA_PAGO_CONDICION_PAGO', 21)->nullable();
            $table->integer('DESC_FORMA_PAGO_DIAS_CREDITO')->nullable();
            $table->string('DESC_FORMA_PAGO_EMISOR', 30)->nullable();
            $table->string('DESC_FORMA_PAGO_TIPO_CREDITO', 300)->nullable();
            $table->decimal('DESC_FORMA_PAGO_PORCENTAJE_REBAJAR', 11, 2)->nullable();
            $table->string('DESC_FORMA_PAGO_SRI', 300)->nullable();
            $table->string('DESC_FORMA_PAGO_TIPO_COBRO', 50)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_descuento_forma_pagos', function (Blueprint $table) {
            $table->foreign('DESC_FORMA_PAGO_CONDICION_PAGO', 'gp_descuento_forma_pagos_fk1')->references('COND_ID')->on('gp_condicion_pagos')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_descuento_forma_pagos');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCondicionPagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_condicion_pagos', function (Blueprint $table) {
            $table->id();
            $table->integer('COND_DIAS');
            $table->string('COND_ID', 21)->unique();
            $table->string('COND_TIPO_DESCUENTO', 15);
            $table->decimal('COND_VALOR',11,2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_condicion_pagos');
    }
}

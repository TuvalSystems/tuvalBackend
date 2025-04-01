<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpBodegasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_bodegas', function (Blueprint $table) {
            $table->id();
            $table->string('BOD_BODEGA', 20)->unique();
            $table->string('BOD_DESCRIPCION', 50)->nullable();
            $table->string('BOD_ESTADO', 20);
            $table->boolean('PERMITE_DESPACHO')->nullable();
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
        Schema::dropIfExists('gp_bodegas');
    }
}

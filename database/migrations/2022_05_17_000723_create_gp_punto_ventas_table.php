<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpPuntoVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_punto_ventas', function (Blueprint $table) {
            $table->id();
            $table->string('PTO_VTA_ID', 10)->unique();
            $table->string('PTO_VTA_DESCRIPCION', 65)->nullable();
            $table->string('PTO_VTA_DIRECCION_1', 61)->nullable();
            $table->string('PTO_VTA_DIRECCION_2', 61)->nullable();
            $table->string('PTO_VTA_ESTADO', 10)->nullable();
            $table->string('PTO_VTA_CIUDAD', 35)->nullable();
            $table->string('PTO_VTA_PROVINCIA', 30)->nullable();
            $table->string('PTO_VTA_PAIS', 30)->nullable();
            $table->string('PTO_VTA_FONO_1', 21)->nullable();
            $table->string('PTO_VTA_FONO_2', 21)->nullable();
            $table->string('PTO_VTA_FONO_4', 21)->nullable();
            $table->integer('PTO_VTA_DIAS_DEVOL_SIN_AUT')->nullable();
            $table->integer('PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO')->nullable();
            $table->integer('PTO_VTA_DIA_VALIDEZ_COTIZACION')->nullable();
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
        Schema::dropIfExists('gp_punto_ventas');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpRelacionPuntoVentaBodegasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_relacion_punto_venta_bodegas', function (Blueprint $table) {
            $table->id();
            $table->string('PTO_VTA_BOD_BODEGA', 20);
            $table->string('PTO_VTA_BOD_PUNTO_VENTA', 10);
            $table->timestamps();
        });
        Schema::table('gp_relacion_punto_venta_bodegas', function (Blueprint $table) {
            $table->foreign('PTO_VTA_BOD_BODEGA', 'gp_relacion_punto_venta_bodegas_fk1')->references('BOD_BODEGA')->on('gp_bodegas');
            $table->foreign('PTO_VTA_BOD_PUNTO_VENTA', 'gp_relacion_punto_venta_bodegas_fk2')->references('PTO_VTA_ID')->on('gp_punto_ventas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_relacion_punto_venta_bodegas');
    }
}

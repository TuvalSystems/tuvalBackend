<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpRelacionPuntoVentaCajasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_relacion_punto_venta_cajas', function (Blueprint $table) {
            $table->id();
            $table->string('PTO_VTA_CAJA_CAJA', 20);
            $table->string('PTO_VTA_CAJA_PUNTO_VENTA', 15);
            $table->timestamps();
        });
        Schema::table('gp_relacion_punto_venta_cajas', function (Blueprint $table) {
            $table->foreign('PTO_VTA_CAJA_PUNTO_VENTA', 'gp_relacion_punto_venta_cajas_fk1')->references('PTO_VTA_ID')->on('gp_punto_ventas')
                    ->onDelete('no action')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_relacion_punto_venta_cajas');
    }
}

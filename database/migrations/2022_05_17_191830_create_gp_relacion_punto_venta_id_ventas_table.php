<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpRelacionPuntoVentaIdVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_relacion_punto_venta_id_ventas', function (Blueprint $table) {
            $table->id();
            $table->string('PTO_VTA_ID_FACTURA_NC', 15);
            $table->string('PTO_VTA_ID_PUNTO_VENTA', 10);
            $table->timestamps();
        });
        Schema::table('gp_relacion_punto_venta_id_ventas', function (Blueprint $table) {
            $table->foreign('PTO_VTA_ID_PUNTO_VENTA', 'gp_relacion_punto_venta_id_ventas_fk1')->references('PTO_VTA_ID')->on('gp_punto_ventas')
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
        Schema::dropIfExists('gp_relacion_punto_venta_id_ventas');
    }
}

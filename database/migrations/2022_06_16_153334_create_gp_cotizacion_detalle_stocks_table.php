<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCotizacionDetalleStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cotizacion_detalle_stocks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cotizacion_id')->unsigned();
            $table->bigInteger('detalle_id')->unsigned();
            $table->string('STO_ART_BODEGA', 20);
            $table->decimal('STO_ART_STOCK_DISPONIBLE', 11, 2)->nullable();
            $table->decimal('seleccionado', 11, 2)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_cotizacion_detalle_stocks', function (Blueprint $table) {
            $table->foreign('cotizacion_id', 'gp_cotizacion_detalle_stocks_fk1')->references('id')->on('gp_cotizacions')->onDelete('cascade');
            $table->foreign('detalle_id', 'gp_cotizacion_detalle_stocks_fk2')->references('id')->on('gp_cotizacion_detalles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cotizacion_detalle_stocks');
    }
}

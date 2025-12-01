<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableGpCotizacionDetalleStocksLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cotizacion_detalle_stocks_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_id');
            $table->unsignedBigInteger('user_id');
            $table->string('tipo', 100);
            $table->string('cantidad', 100);
            $table->timestamps();
            $table->foreign('stock_id')->references('id')->on('gp_cotizacion_detalle_stocks');
            $table->foreign('user_id')->references('id')->on('gp_usuarios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cotizacion_detalle_stocks_logs');
    }
}

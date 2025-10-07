<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCotizacionReservaDetallesLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cotizacion_reserva_detalles_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ct_reserva_id')->nullable();
            $table->unsignedBigInteger('stock_id')->nullable();
            $table->string('ART_CODIGO', 40);
            $table->string('STO_ART_BODEGA', 40);
            $table->decimal('STO_ART_CANTIDAD', 18, 2);
            $table->timestamps();

            $table->foreign('ct_reserva_id')->references('id')->on('gp_cotizacion_reserva_logs');
            $table->foreign('stock_id')->references('id')->on('gp_cotizacion_detalle_stocks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacion_reserva_detalles_logs', function (Blueprint $table) {
            $table->dropColumn(['cotizacion_reserva_id', 'stock_id', 'ART_CODIGO', 'STO_ART_BODEGA', 'STO_ART_CANTIDAD']);
        });
    }
}

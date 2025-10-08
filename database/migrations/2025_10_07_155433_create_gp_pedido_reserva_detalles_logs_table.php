<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpPedidoReservaDetallesLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_pedido_reserva_detalles_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_reserva_id');
            $table->unsignedBigInteger('stock_id')->nullable();
            $table->string('art_code', 40);
            $table->string('bodega', 40);
            $table->decimal('cantidad', 18, 2);
            $table->foreign('pedido_reserva_id')->references('id')->on('gp_pedido_reserva_logs');
            $table->foreign('stock_id')->references('id')->on('gp_cotizacion_detalle_stocks');
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

         Schema::table('gp_pedido_reserva_detalles_logs', function (Blueprint $table) {
            $table->dropColumn(['pedido_reserva_id', 'stock_id', 'ART_CODIGO', 'STO_ART_BODEGA', 'STO_ART_CANTIDAD']);
        });
    }
}

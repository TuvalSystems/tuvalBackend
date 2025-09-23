<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableGpPedidosReservaLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_pedidos_reserva_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_origen_id')->nullable();
            $table->unsignedBigInteger('pedido_destino_id')->nullable();
            $table->unsignedBigInteger('user_id');

            $table->string('STO_BODEGA', 40);
            $table->string('ART_CODIGO', 40);
            $table->string('STO_ART_BODEGA', 40);
            $table->decimal('STO_ART_CANTIDAD', 18, 2);


            $table->foreign('cotizacion_origen_id')->references('id')->on('gp_cotizacions');
            $table->foreign('pedido_destino_id')->references('id')->on('gp_pedidos');
            $table->foreign('user_id')->references('id')->on('gp_usuarios');

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
        Schema::dropIfExists('gp_pedidos_reserva_logs');
    }
}

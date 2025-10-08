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
        Schema::create('gp_pedido_reserva_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reserva_id')->nullable();
            $table->unsignedBigInteger('cotizacion_origen_id')->nullable();
            $table->unsignedBigInteger('pedido_destino_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('bodega', 40)->nullable();

            $table->foreign('cotizacion_origen_id')->references('id')->on('gp_cotizacions');
            $table->foreign('pedido_destino_id')->references('id')->on('gp_pedidos');
            $table->foreign('user_id')->references('id')->on('gp_usuarios');
            $table->foreign('reserva_id')->references('id')->on('gp_reservas');
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

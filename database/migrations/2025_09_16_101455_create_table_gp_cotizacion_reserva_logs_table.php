<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableGpCotizacionReservaLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cotizacion_reserva_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reserva_id')->nullable();
            $table->unsignedBigInteger('cotizacion_origen_id')->nullable();
            $table->unsignedBigInteger('cotizacion_destino_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('STO_BODEGA', 40);
            $table->timestamps();

            $table->foreign('cotizacion_origen_id')->references('id')->on('gp_cotizacions');
            $table->foreign('cotizacion_destino_id')->references('id')->on('gp_cotizacions');
            $table->foreign('user_id')->references('id')->on('gp_usuarios');
            $table->foreign('reserva_id')->references('id')->on('gp_reservas');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cotizacion_reserva_logs');
    }
}

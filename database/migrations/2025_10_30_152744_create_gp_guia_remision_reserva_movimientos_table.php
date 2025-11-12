<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionReservaMovimientosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_guia_remision_reserva_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guia_id')->nullable()->unsigned()->index();
            $table->string('tipo_movimiento', 4)->nullable();
            $table->bigInteger('id_movimiento')->unsigned()->nullable();
            $table->string('cotizacion', 20)->nullable();
            $table->string('codigo', 50);
            $table->foreign('guia_id')->references('id')->on('gp_guia_remision_reservas');
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
        Schema::dropIfExists('gp_guia_remision_reserva_movimientos');
    }
}

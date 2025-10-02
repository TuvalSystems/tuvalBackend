<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpReservasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_reservas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('tipo_reserva_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('tipo_traslado_reserva_id')->nullable()->unsigned()->index();

            $table->unsignedBigInteger('user_ingreso_reserva_id')->nullable()->unsigned()->index();
            $table->date('fe_solicitud_reserva')->nullable();
            $table->dateTime('fe_ingreso_solicitud_reserva')->nullable();
            $table->string('obs_solicitud_reserva', 400)->nullable();


            $table->unsignedBigInteger('user_autorizacion_reserva_id')->nullable()->unsigned()->index();
            $table->date('fe_autorizacion_reserva')->nullable();
            $table->dateTime('fe_ingreso_autorizacion_reserva')->nullable();
            $table->string('obs_autorizacion_reserva', 400)->nullable();
            $table->boolean('estado_autorizacion_reserva')->default(false);
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazada', 'cancelado', 'facturado', 'traslado'])
                ->nullable()
                ->default(null)
                ->index();
            $table->foreign('cotizacion_id')->references('id')->on('gp_cotizacions');
            $table->foreign('tipo_reserva_id')->references('id')->on('gp_tipo_reservas');
            $table->foreign('tipo_traslado_reserva_id')->references('id')->on('gp_reserva_tipo_traslado');
            $table->foreign('user_autorizacion_reserva_id')->references('id')->on('gp_usuarios');
            $table->foreign('user_ingreso_reserva_id')->references('id')->on('gp_usuarios');

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
        Schema::dropIfExists('gp_reservas');
    }
}

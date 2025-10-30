<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionReservaDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_guia_remision_reserva_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guia_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('traslado_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('cotizacion_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('detalle_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('stock_id')->nullable()->unsigned()->index();
            $table->string('art_code', 250)->nullable();
            $table->string('art_descripcion', 250)->nullable();
            $table->string('art_cod_tres_letras', 5)->nullable();
            $table->decimal('cantidad', 11, 2)->nullable()->default(0);
            $table->string('art_plan_umedida', 30)->nullable();
            $table->decimal('recibido', 11, 2)->nullable()->default(0);


            $table->foreign('guia_id')->references('id')->on('gp_guia_remision_reservas');
            $table->foreign('traslado_id')->references('id')->on('gp_traslados_reservas');
            $table->foreign('cotizacion_id')->references('id')->on('gp_cotizacions');
            $table->foreign('detalle_id')->references('id')->on('gp_cotizacion_detalles');
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
        Schema::dropIfExists('gp_guia_remision_reserva_detalles');
    }
}

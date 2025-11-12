<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTrasladosReservasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_traslados_reservas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reserva_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('cotizacion_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('detalle_id')->nullable()->unsigned()->index();
            $table->unsignedBigInteger('stock_id')->nullable()->unsigned()->index();
            $table->string('art_code', 40)->nullable()->index();
            $table->string('art_descripcion', 300)->nullable()->index();
            $table->string('art_cod_tres_letras', 40)->nullable()->index();
            $table->string('art_plan_umedida', 40)->nullable()->index();
            $table->decimal('art_peso', 11, 2)->nullable()->index();
            $table->decimal('cantidad', 11, 2)->nullable()->index();
            $table->string('bodega_origen', 60)->nullable()->index();
            $table->string('bodega_destino', 60)->nullable()->index();
            $table->decimal('reservado', 11, 2)->nullable()->default(0)->index();
            $table->decimal('trasladado', 11, 2)->nullable()->default(0)->index();
            $table->boolean('completado')->default(0);
            $table->boolean('picking')->default(0);
            $table->timestamps();

            $table->foreign('reserva_id')->references('id')->on('gp_reservas');
            $table->foreign('cotizacion_id')->references('id')->on('gp_cotizacions');
            $table->foreign('detalle_id')->references('id')->on('gp_cotizacion_detalles');
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
        Schema::table('gp_traslados_reservas', function (Blueprint $table) {
            $table->dropColumn([
                'reserva_id',
                'cotizacion_id',
                'detalle_id',
                'stock_id',
                'art_code',
                'art_descripcion',
                'art_cod_tres_letras',
                'art_plan_umedida',
                'art_peso',
                'cantidad',
                'bodega_origen',
                'bodega_destino',
                'reservado',
                'trasladado',
                'completado',
                'picking',
            ]);
        });
    }
}

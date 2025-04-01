<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTrasladosVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_traslados_ventas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pedido_id')->unsigned();
            $table->bigInteger('detalle_id')->unsigned();
            $table->bigInteger('detalle_stocks_id')->unsigned();
            $table->string('ART_CODIGO', 30)->nullable();
            $table->string('ART_DESCRIPCION', 300)->nullable();
            $table->string('ART_CODIGO_TRES_LETRAS', 3)->nullable();
            $table->string('ART_PLAN_UMEDIDA', 30)->nullable();
            $table->decimal('ART_PESO', 11, 2)->nullable();
            $table->decimal('cantidad', 11, 2)->nullable();
            $table->string('bodega_origen', 20);
            $table->string('bodega_destino', 20);
            $table->decimal('reservado', 11, 2)->nullable();
            $table->decimal('trasladado', 11, 2)->nullable();
            $table->boolean('completado')->nullable();
            $table->boolean('picking')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_traslados_ventas', function (Blueprint $table) {
            $table->foreign('pedido_id', 'gp_traslados_ventas_fk0')->references('id')->on('gp_pedidos')->onDelete('cascade');
            $table->foreign('detalle_id', 'gp_traslados_ventas_fk1')->references('id')->on('gp_pedido_detalles')->onDelete('cascade');
            $table->foreign('detalle_stocks_id', 'gp_traslados_ventas_fk2')->references('id')->on('gp_pedido_detalle_stocks')->onDelete('cascade');
            $table->unique(['pedido_id', 'detalle_id', 'detalle_stocks_id'], 'gp_traslados_ventas_uid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_traslados_ventas');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpDevolucionesDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_devoluciones_detalles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('devolucion_id')->unsigned();
            $table->bigInteger('detalle_id')->unsigned();
            $table->string('ART_CODIGO', 30)->nullable();
            $table->string('ART_DESCRIPCION', 300)->nullable();
            $table->string('ART_DESCRIPCION_ORIGINAL', 300)->nullable();
            $table->string('ART_CODIGO_TRES_LETRAS', 3)->nullable();
            $table->integer('ART_DECIMAL_CANTIDAD')->nullable();
            $table->string('ART_PLAN_UMEDIDA', 30)->nullable();
            $table->string('ART_MARCA', 300)->nullable();
            $table->string('ART_MATERIAL', 300)->nullable();
            $table->decimal('ART_PESO', 11, 2)->nullable();
            $table->string('ART_TIPO_COMPRA', 5)->nullable();
            $table->decimal('ART_VALOR', 11, 2)->nullable();
            $table->decimal('ART_IVA', 11, 2)->nullable();
            $table->decimal('ART_VALOR_ORIGINAL', 11, 2)->nullable();
            $table->decimal('ART_DESCUENTO', 11, 4)->nullable();
            $table->decimal('ART_CANTIDAD', 11, 2)->nullable();
            $table->decimal('ART_STOCK', 11, 2)->nullable();
            $table->decimal('ART_IMP_VTA', 11, 2)->nullable();
            $table->decimal('ART_COSTO_PROMEDIO', 11, 2)->nullable();
            $table->decimal('descuento', 11, 2)->nullable();
            $table->decimal('descuento_original', 11, 2)->nullable();
            $table->decimal('modificador', 11, 2)->nullable();
            $table->decimal('seleccionado', 11, 2)->nullable();
            $table->decimal('devuelto', 11, 2)->nullable();
            $table->decimal('disponible', 11, 2)->nullable();
            $table->decimal('recibido', 11, 2)->nullable();
            $table->string('compartimiento', 50)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_devoluciones_detalles', function (Blueprint $table) {
            $table->foreign('devolucion_id', 'gp_devoluciones_detalles_fk1')->references('id')->on('gp_devoluciones')->onDelete('cascade');
            $table->foreign('detalle_id', 'gp_devoluciones_detalles_fk2')->references('id')->on('gp_pedido_detalles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_devoluciones_detalles');
    }
}

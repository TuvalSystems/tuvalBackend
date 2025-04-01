<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpOrdenDeCompraGeneradoDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_orden_de_compra_generado_detalles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('orden_generada_id')->unsigned();
            $table->bigInteger('detalle_proveedores_id')->unsigned();
            $table->string('ORDEN_COMPRA_DETALLE_BODEGA', 11);
            $table->string('ORDEN_COMPRA_DETALLE_ARTICULO', 30);
            $table->string('ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO', 300);
            $table->string('ORDEN_COMPRA_DETALLE_UMEDIDA', 11);
            $table->decimal('ORDEN_COMPRA_DETALLE_CANTIDAD', 11, 5);
            $table->decimal('recibido', 11, 2)->nullable();
            $table->decimal('ORDEN_COMPRA_DETALLE_COSTO_UNITARIO', 11, 2);
            $table->decimal('ORDEN_COMPRA_DETALLE_TOTAL', 11, 2);
            $table->decimal('ORDEN_COMPRA_DETALLE_IMPUESTO', 11, 2);
            $table->timestamps();
        });
        Schema::table('gp_orden_de_compra_generado_detalles', function (Blueprint $table) {
            $table->foreign('orden_generada_id', 'gp_orden_de_compra_generado_detalles_fk1')->references('id')->on('gp_orden_de_compra_generados')->onDelete('cascade');
            $table->foreign('detalle_proveedores_id', 'gp_orden_de_compra_generado_detalles_fk2')->references('id')->on('gp_pedido_detalle_proveedores')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_orden_de_compra_generado_detalles');
    }
}

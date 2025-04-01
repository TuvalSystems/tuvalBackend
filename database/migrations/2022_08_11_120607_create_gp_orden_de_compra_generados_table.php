<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpOrdenDeCompraGeneradosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_orden_de_compra_generados', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('orden_id')->unsigned();
            $table->bigInteger('detalle_id')->unsigned()->nullable();
            $table->bigInteger('detalle_proveedores_id')->unsigned()->nullable();
            $table->enum('estado', ['generado', 'recibido', 'eliminado'])->default('generado');
            $table->string('ORDEN_COMPRA_DOCUMENTO', 30);
            $table->date('ORDEN_COMPRA_FECHA_DOCUMENTO');
            $table->date('ORDEN_COMPRA_FECHA_ENTREGA');
            $table->string('ORDEN_COMPRA_ID_PROVEEDOR', 20);
            $table->string('ORDEN_COMPRA_DIRECCION', 120);
            $table->string('ORDEN_COMPRA_NOTA_DOCU', 300);
            $table->string('ORDEN_COMPRA_RECIBO', 300)->nullable();
            $table->string('USERID', 20)->nullable();
            $table->integer('recibidos')->nullable();
            $table->timestamps();
        });
        Schema::table('gp_orden_de_compra_generados', function (Blueprint $table) {
            $table->foreign('orden_id', 'gp_orden_de_compra_generados_fk1')->references('id')->on('gp_orden_de_compras')->onDelete('cascade');
//            $table->foreign('detalle_id', 'gp_orden_de_compra_generados_fk2')->references('id')->on('gp_pedido_detalles')->onDelete('no action');
//            $table->foreign('detalle_proveedores_id', 'gp_orden_de_compra_generados_fk3')->references('id')->on('gp_pedido_detalle_proveedores')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_orden_de_compra_generados');
    }
}

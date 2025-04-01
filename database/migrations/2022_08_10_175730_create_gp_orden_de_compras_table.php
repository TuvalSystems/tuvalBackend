<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpOrdenDeComprasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_orden_de_compras', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('detalle_id')->unsigned()->nullable();
            $table->bigInteger('detalle_proveedores_id')->unsigned()->nullable();
            $table->enum('estado', ['iniciado', 'enviado', 'generado'])->default('iniciado');
            $table->string('ORDEN_COMPRA_DOCU_ORIGEN', 20);
            $table->string('ORDEN_COMPRA_LINEA', 10);
            $table->string('DOC_GENERADO', 10)->nullable();
            $table->dateTime('ORDEN_COMPRA_FECHA_DOCUMENTO');
            $table->dateTime('ORDEN_COMPRA_FECHA_ENTREGA');
            $table->string('ORDEN_COMPRA_ID_CLIENTE', 30);
            $table->string('ORDEN_COMPRA_ID_PROVEEDOR', 30);
            $table->string('ORDEN_COMPRA_ID_DIRECCION', 300);
            $table->string('ORDEN_COMPRA_NOTA_DOCU', 300)->nullable();
            $table->string('ORDEN_COMPRA_BODEGA', 11);
            $table->string('ORDEN_COMPRA_ARTICULO', 30);
            $table->string('ORDEN_COMPRA_DESCRIPCION_ARTICULO', 300);
            $table->string('ORDEN_COMPRA_UMEDIDA', 11);
            $table->decimal('ORDEN_COMPRA_CANTIDAD', 11, 5);
            $table->decimal('ORDEN_COMPRA_COSTO_UNITARIO', 11, 2);
            $table->decimal('ORDEN_COMPRA_TOTAL', 11, 2);
            $table->decimal('ORDEN_COMPRA_IMPUESTO', 11, 2);
            $table->string('ORDEN_COMPRA_ID_USUARIO', 20);
            $table->string('ORDEN_COMPRA_MENSAJE', 500)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_orden_de_compras', function (Blueprint $table) {
            $table->foreign('ORDEN_COMPRA_DOCU_ORIGEN', 'gp_orden_de_compras_fk1')->references('pedido')->on('gp_pedidos')->onDelete('cascade');
//            $table->foreign('detalle_id', 'gp_orden_de_compras_fk2')->references('id')->on('gp_pedido_detalles')->onDelete('no action');
//            $table->foreign('detalle_proveedores_id', 'gp_orden_de_compras_fk3')->references('id')->on('gp_pedido_detalle_proveedores')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_orden_de_compras');
    }
}

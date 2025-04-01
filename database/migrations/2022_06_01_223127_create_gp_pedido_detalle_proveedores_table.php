<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpPedidoDetalleProveedoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_pedido_detalle_proveedores', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pedido_id')->unsigned();
            $table->bigInteger('detalle_id')->unsigned();
            $table->bigInteger('ID_PROVEEDOR');
            $table->string('DESCRIPCION_ARTICULO', 300);
            $table->date('FECHA_ENTREGA');
            $table->decimal('CANTIDAD', 11, 2);
            $table->decimal('COSTO_UNITARIO', 11, 2);
            $table->decimal('IMPUESTO', 11, 2);
            $table->decimal('TOTAL', 11, 2);
            $table->decimal('TOTALCONIMPUESTO', 11, 2);
            $table->string('NOTA', 300)->nullable();
            $table->decimal('MARGEN', 11, 2);
            $table->decimal('ingresado', 11,2)->nullable();
            $table->decimal('ejecutado', 11,2)->nullable();
            $table->enum('recibido', ['yes', 'no'])->default('no');
            $table->timestamps();
        });
        Schema::table('gp_pedido_detalle_proveedores', function (Blueprint $table) {
            $table->foreign('pedido_id', 'gp_pedido_detalle_proveedores_fk1')->references('id')->on('gp_pedidos')->onDelete('cascade');
            $table->foreign('detalle_id', 'gp_pedido_detalle_proveedores_fk2')->references('id')->on('gp_pedido_detalles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_pedido_detalle_proveedores');
    }
}

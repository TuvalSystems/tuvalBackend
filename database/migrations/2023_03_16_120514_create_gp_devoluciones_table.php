<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpDevolucionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_devoluciones', function (Blueprint $table) {
            $table->id();
            $table->string('devolucion', 20)->unique()->nullable();
            $table->string('DOC_GENERADO', 20)->unique()->nullable();
            $table->date('fecha_NC')->nullable();
            $table->enum('estado', ['solicitado', 'espera', 'procesado', 'aprobado', 'rechazado', 'recibido'])->default('solicitado');
            $table->bigInteger('pedido_id')->unsigned();
            $table->bigInteger('cliente_id')->unsigned()->nullable();
            $table->string('pedido', 20)->nullable();
            $table->string('factura', 20)->nullable();
            $table->boolean('volver_a_facturar')->nullable();
            $table->enum('tipo_devolucion', ['total', 'parcial'])->default('parcial');
            $table->boolean('despachado')->nullable();
            $table->bigInteger('cliente_volver_a_facturar_id')->unsigned()->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('USERID', 20)->nullable();
            $table->bigInteger('sucursal_id')->unsigned()->nullable();
            $table->bigInteger('sucursal_facturacion_id')->unsigned()->nullable();
            $table->string('motivo', 100)->nullable();
            $table->string('orden_de_compra', 100)->nullable();
            $table->enum('requiere_aprobacion', ['yes', 'no'])->default('no');
            $table->enum('aprobado', ['yes', 'no'])->default('no');
            $table->bigInteger('aprobador_id')->unsigned()->nullable();
            $table->string('aprobado_mensaje', 300)->nullable();
            $table->string('NC_VTA_MENSAJE', 300)->nullable();
            $table->boolean('devolucion_bodega')->nullable();
            $table->boolean('rebajado')->nullable();
            $table->boolean('procesando')->nullable();
            $table->integer('impuesto')->nullable();
            $table->decimal('subtotal', 11, 2);
            $table->decimal('descuento', 11, 2)->nullable();
            $table->decimal('subtotalmenosdescuento', 11, 2);
            $table->decimal('iva', 11, 2);
            $table->decimal('total', 11, 2);
            $table->string('observacion', 2000)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->foreign('pedido_id', 'gp_devoluciones_fk1')->references('id')->on('gp_pedidos')->onDelete('cascade');
            $table->foreign('cliente_volver_a_facturar_id', 'gp_devoluciones_fk2')->references('id')->on('gp_clientes')->onDelete('no action');
            $table->foreign('sucursal_id', 'gp_devoluciones_fk3')->references('id')->on('gp_cliente_sucursals')->onDelete('no action');
            $table->foreign('sucursal_facturacion_id', 'gp_devoluciones_fk4')->references('id')->on('gp_cliente_sucursals')->onDelete('no action');
            $table->foreign('cliente_id', 'gp_devoluciones_fk5')->references('id')->on('gp_clientes')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_devoluciones');
    }
}

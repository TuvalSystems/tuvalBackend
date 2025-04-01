<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpOrdenDespachosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_orden_despachos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pedido_id')->unique()->unsigned();
            $table->string('orden', 20)->unique()->nullable();
            $table->enum('estado', ['pendiente', 'liberada', 'despachada', 'cancelada'])->default('liberada');
            $table->string('PTO_VTA_ID', 10);
            $table->string('BOD_BODEGA', 20);
            $table->dateTime('autorizacion_fecha')->nullable();
            $table->dateTime('fecha_hora_liberacion')->nullable();
            $table->bigInteger('autorizacion_user_id')->unsigned()->nullable();
            $table->string('autorizacion_USERID', 20)->nullable();
            $table->string('autorizacion_comentario', 500)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_orden_despachos', function (Blueprint $table) {
            $table->foreign('pedido_id', 'gp_orden_despachos_fk0')->references('id')->on('gp_pedidos')->onDelete('cascade');
            $table->foreign('PTO_VTA_ID', 'gp_orden_despachos_fk1')->references('PTO_VTA_ID')->on('gp_punto_ventas')->onDelete('no action');
            $table->foreign('BOD_BODEGA', 'gp_orden_despachos_fk2')->references('BOD_BODEGA')->on('gp_bodegas')->onDelete('no action');
            $table->foreign('autorizacion_user_id', 'gp_orden_despachos_fk3')->references('id')->on('gp_usuarios')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_orden_despachos');
    }
}

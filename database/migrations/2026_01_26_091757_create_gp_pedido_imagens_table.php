<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpPedidoImagensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_pedido_imagenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_id');
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('tipo_mime')->nullable();
            $table->unsignedInteger('tamano')->nullable();
            $table->timestamps();

            $table->foreign('pedido_id')
                ->references('id')->on('gp_pedidos')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_pedido_imagenes');
    }
}

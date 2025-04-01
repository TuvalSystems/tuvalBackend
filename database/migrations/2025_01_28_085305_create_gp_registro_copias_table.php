<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpRegistroCopiasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_registro_copias', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pedido_origen')->unsigned()->nullable();
            $table->bigInteger('cotizacion_origen')->unsigned()->nullable();
            $table->bigInteger('cotizacion_destino')->unsigned()->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('USERID', 20)->nullable();
            $table->enum('tipo', ['copia', 'volver_a_pedir'])->default('copia');
            $table->timestamps();
        });
        Schema::table('gp_registro_copias', function (Blueprint $table) {
            $table->foreign('pedido_origen', 'gp_registro_copias_fk1')->references('id')->on('gp_pedidos')->onDelete('cascade');
            $table->foreign('cotizacion_origen', 'gp_registro_copias_fk2')->references('id')->on('gp_cotizacions')->onDelete('cascade');
            $table->foreign('cotizacion_destino', 'gp_registro_copias_fk3')->references('id')->on('gp_cotizacions')->onDelete('cascade');
            $table->foreign('user_id', 'gp_registro_copias_fk4')->references('id')->on('gp_usuarios')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_registro_copias');
    }
}

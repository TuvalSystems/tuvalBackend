<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCobrosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_cobros', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recibo', 15)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('cliente_id')->unsigned();
            $table->string('caja',15)->nullable();
            $table->dateTime('fecha');
            $table->enum('estado', ['iniciado', 'revisado', 'enviado'])->default('iniciado');
            $table->string('pedido', 20)->nullable();
            $table->string('referencia', 300)->nullable();
            $table->string('alerta', 1000)->nullable();
            $table->timestamps();
        });

        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->foreign('user_id', 'gp_cobros_fk0')->references('id')->on('gp_usuarios')->onDelete('no action');
            $table->foreign('cliente_id', 'gp_cobros_fk1')->references('id')->on('gp_clientes')->onDelete('no action');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_cobros');
    }
}

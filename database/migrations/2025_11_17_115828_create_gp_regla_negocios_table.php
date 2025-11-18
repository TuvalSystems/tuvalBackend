<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpReglaNegociosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_regla_negocios', function (Blueprint $t) {
            $t->id();
            $t->enum('perfil_aprobador', ['crediticia', 'comercial', 'ambos'])->default('crediticia');
            $t->string('nombre', 300)->nullable();
            $t->string('descripcion', 300)->nullable();
            $t->string('observacion1', 300)->nullable();
            $t->string('observacion2', 300)->nullable();
            $t->text('funcion')->nullable();
            $t->boolean('estado')->default(1);
            $t->dateTime('fecha_ingreso')->nullable();
            $t->unsignedBigInteger('user_ingreso_id')->nullable()->unsigned()->index();
            $t->dateTime('fecha_modificacion')->nullable();
            $t->unsignedBigInteger('user_modificacion_id')->nullable()->unsigned()->index();
            $t->foreign('user_ingreso_id')->references('id')->on('gp_usuarios');
            $t->foreign('user_modificacion_id')->references('id')->on('gp_usuarios');
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_regla_negocios');
    }
}

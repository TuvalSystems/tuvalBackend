<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200)->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('mensaje', 1000)->nullable();
            $table->enum('estado', ['nuevo', 'enviado', 'leido'])->default('nuevo');

            $table->timestamps();
        });
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->foreign('user_id', 'notificaciones  _fk0')->references('id')->on('gp_usuarios')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpRelacionUsuarioCajasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_relacion_usuario_cajas', function (Blueprint $table) {
            $table->id();
            $table->string('CAJ_USER_USUARIO',15);
            $table->string('CAJ_USER_CAJA',15);
            $table->timestamps();
        });
        Schema::table('gp_relacion_usuario_cajas', function (Blueprint $table) {
            $table->foreign('CAJ_USER_USUARIO', 'gp_relacion_usuario_cajas_fk1')->references('USERID')->on('gp_usuarios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_relacion_usuario_cajas');
    }
}

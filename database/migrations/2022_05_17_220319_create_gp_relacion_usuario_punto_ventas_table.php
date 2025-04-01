<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpRelacionUsuarioPuntoVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->id();
            $table->string('USER_PTO_VTA_PUNTO_VENTA', 10);
            $table->string('USER_PTO_VTA_USUARIO', 20);
            $table->timestamps();
        });
        Schema::table('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->foreign('USER_PTO_VTA_PUNTO_VENTA', 'gp_relacion_usuario_punto_ventas_fk1')->references('PTO_VTA_ID')->on('gp_punto_ventas');
            $table->foreign('USER_PTO_VTA_USUARIO', 'gp_relacion_usuario_punto_ventas_fk2')->references('USERID')->on('gp_usuarios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_relacion_usuario_punto_ventas');
    }
}

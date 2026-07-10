<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->boolean('base')->default(0)->after('USER_PTO_VTA_USUARIO');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->dropColumn('base');
        });
    }
};

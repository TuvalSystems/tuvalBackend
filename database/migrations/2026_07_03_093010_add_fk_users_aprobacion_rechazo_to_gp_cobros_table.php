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
        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->foreign('user_aprobador_id', 'fk_gp_usuarios_user_aprobador')->references('id')->on('gp_usuarios')->nullOnDelete();
            $table->foreign('user_rechazo_id', 'fk_gp_usuarios_user_rechazo')->references('id')->on('gp_usuarios')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cobros', function (Blueprint $table) {
              $table->dropForeign('fk_gp_usuarios_user_aprobador');
            $table->dropForeign('fk_gp_usuarios_user_rechazo');
        });
    }
};

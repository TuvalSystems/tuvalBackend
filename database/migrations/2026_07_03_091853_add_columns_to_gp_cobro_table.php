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
            $table->renameColumn('fecha_aprobacion_rechazo', 'fecha_aprobacion');
        });
         Schema::table('gp_cobros', function (Blueprint $table) {
            $table->dateTime('fecha_rechazo')->nullable();
            $table->unsignedBigInteger('user_rechazo_id')->nullable();
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
            $table->renameColumn('fecha_h', 'fecha');
            $table->dropColumn(['fecha_rechazo', 'user_rechazo_id']);

        });
    }
};

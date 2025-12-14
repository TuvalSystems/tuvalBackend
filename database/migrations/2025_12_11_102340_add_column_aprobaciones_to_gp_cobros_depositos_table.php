<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAprobacionesToGpCobrosDepositosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->dateTime('fecha_aprobacion')->nullable();
            $table->unsignedBigInteger('user_aprobador_id')->nullable();
        });
        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->dateTime('fecha_aprobacion_rechazo')->nullable();
            $table->unsignedBigInteger('user_aprobador_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            //
        });
    }
}

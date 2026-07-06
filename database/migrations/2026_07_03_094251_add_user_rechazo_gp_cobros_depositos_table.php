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
        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_rechazo_id')->nullable();
            $table->dateTime('fecha_rechazo')->nullable();
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
            $table->dropColumn('user_rechazo_id');
            $table->dropColumn('fecha_rechazo');
        });
    }
};

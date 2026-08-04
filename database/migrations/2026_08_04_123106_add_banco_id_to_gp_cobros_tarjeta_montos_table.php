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
        Schema::table('gp_cobros_tarjeta_montos', function (Blueprint $table) {
            $table->unsignedBigInteger('banco_id')->nullable()->after('cobro_id');
            $table->foreign('banco_id')->references('id')->on('gp_caja_bancos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cobros_tarjeta_montos', function (Blueprint $table) {
            $table->dropForeign(['banco_id']);
            $table->dropColumn('banco_id');
        });
    }
};

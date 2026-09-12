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
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->integer('proyecto')->nullable()->after('origen');
        });
         Schema::table('hi_cotizacion', function (Blueprint $table) {
            $table->integer('proyecto')->nullable()->after('origen');
        });
         Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->integer('proyecto')->nullable()->after('origen');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dropColumn(['proyecto']);
        });
         Schema::table('hi_cotizacion', function (Blueprint $table) {
            $table->dropColumn(['proyecto']);
        });
         Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->dropColumn(['proyecto']);
        });
    }
};

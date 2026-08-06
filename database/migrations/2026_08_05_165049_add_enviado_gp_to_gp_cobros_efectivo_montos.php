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
        // Schema::table('gp_cobros_efectivo_montos', function (Blueprint $table) {
        //     $table->boolean('enviado_gp')->default(0)->after('mensaje_ws');
        // });

         Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->boolean('enviado_gp')->default(0)->after('mensaje_ws');
        });



    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('gp_cobros_efectivo_montos', function (Blueprint $table) {
        //     $table->dropColumn(['enviado_gp']);
        // });

        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->dropColumn(['enviado_gp']);
        });
    }
};

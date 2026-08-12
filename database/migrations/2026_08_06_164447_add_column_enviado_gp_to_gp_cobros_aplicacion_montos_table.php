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


        if (! Schema::hasColumn('gp_cobros_aplicacion_montos', 'enviado_gp')) {
            Schema::table('gp_cobros_aplicacion_montos', function (Blueprint $table) {
                $table->boolean('enviado_gp')->default(0)->after('mensaje_ws');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        if (Schema::hasColumn('gp_cobros_aplicacion_montos', 'enviado_gp')) {
            Schema::table('gp_cobros_aplicacion_montos', function (Blueprint $table) {
                $table->dropColumn('enviado_gp');
            });
        }
    }
};

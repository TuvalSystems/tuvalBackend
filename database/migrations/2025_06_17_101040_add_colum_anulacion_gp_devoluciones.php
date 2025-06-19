<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumAnulacionGpDevoluciones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->boolean('permite_anulacion')->default(0)->after('procesando');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->dropColumn('permite_anulacion');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnSegmentosToGpClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_clientes', function (Blueprint $table) {
            $table->integer('CLI_SEGMENTO_ID')->after('CLI_FACTURA_POSTERIOR_AL_DESPACHO')->nullable();
            $table->integer('CLI_SUBSEGMENTO_ID')->after('CLI_SEGMENTO_ID')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_clientes', function (Blueprint $table) {
            $table->dropColumn('CLI_SEGMENTO_ID');
            $table->dropColumn('CLI_SUBSEGMENTO_ID');
        });
    }
}

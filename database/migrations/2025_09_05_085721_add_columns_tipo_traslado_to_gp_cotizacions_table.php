<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsTipoTrasladoToGpCotizacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
             $table->unsignedBigInteger('co_tipTrasResInv')->nullable()->unsigned()->after('co_TipSolResInv');
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
            $table->dropColumn('co_tipTrasResInv');
        });
    }
}

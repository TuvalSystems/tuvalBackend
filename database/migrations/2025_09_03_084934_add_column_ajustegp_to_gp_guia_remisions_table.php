<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAjustegpToGpGuiaRemisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->string('ajustegp', 350)->nullable()->after('procesando');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
             $table->dropColumn('ajustegp');
        });
    }
}

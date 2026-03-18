<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnZonaVendedorToGpVendedoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_vendedors', function (Blueprint $table) {
            $table->string('zona_vendedor')->nullable()->after('VEND_CORREO_ELECTRONICO');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_vendedors', function (Blueprint $table) {
            $table->dropColumn('zona_vendedor');
        });
    }
}

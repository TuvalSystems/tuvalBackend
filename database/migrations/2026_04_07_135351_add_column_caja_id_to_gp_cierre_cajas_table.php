<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddColumnCajaIdToGpCierreCajasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cierre_cajas', function (Blueprint $table) {
            $table->foreignId('caja_id')->after('id')->nullable()->constrained('gp_cajas')->onDelete('set null');
        });

        DB::statement("
            UPDATE gp_cierre_cajas AS gcc
            JOIN gp_cajas AS gc ON gcc.CAJA_CODIGO = gc.CAJA_CODIGO
            SET gcc.caja_id = gc.id
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cierre_cajas', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCajaIdToGpCobrosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->foreignId('caja_id')->after('cliente_id')->nullable()->constrained('gp_cajas')->onDelete('set null');
        });

        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->foreignId('banco_id')->after('cobro_id')->nullable()->constrained('gp_caja_bancos')->onDelete('set null');
        });

        Schema::table('gp_cobros_cheques', function (Blueprint $table) {
            $table->foreignId('banco_id')->after('cobro_id')->nullable()->constrained('gp_caja_bancos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });

        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->dropForeign(['banco_id']);
            $table->dropColumn('banco_id');
        });

        Schema::table('gp_cobros_cheques', function (Blueprint $table) {
            $table->dropForeign(['banco_id']);
            $table->dropColumn('banco_id');
        });
    }
}

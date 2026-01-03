<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnApprovalComercialQuoteToGpRegistroCopias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_registro_copias', function (Blueprint $table) {
            $table->boolean('destino_aprobado')->default(false)->after('tipo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_registro_copias', function (Blueprint $table) {
            $table->dropColumn('destino_aprobado');
        });
    }
}

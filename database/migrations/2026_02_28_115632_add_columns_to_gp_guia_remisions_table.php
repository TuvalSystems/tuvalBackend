<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToGpGuiaRemisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_guia_remision_despachos', function (Blueprint $table) {
            $table->string('tipo_movimiento', 4)->nullable()->after('guia_remision_id');
            $table->string('tipo', 4)->nullable()->after('tipo_movimiento')->comment('Ingreso o Egreso');
            $table->uuid('hash_interno')->nullable()->index()->after('pedido')->comment('UUID para trazabilidad con la API de bodega');
        });

        Schema::table('gp_guia_remision_transferencias', function (Blueprint $table) {
            $table->bigInteger('id_movimiento')->unsigned()->nullable()->after('guia_remision_id');
            $table->string('tipo_movimiento', 4)->nullable()->after('id_movimiento');
            $table->string('tipo', 4)->nullable()->after('tipo_movimiento')->comment('Ingreso o Egreso');
            $table->uuid('hash_interno')->nullable()->index()->after('pedido')->comment('UUID para trazabilidad con la API de bodega');
        });

        Schema::table('gp_guia_remision_reserva_movimientos', function (Blueprint $table) {
            $table->string('tipo', 4)->nullable()->after('tipo_movimiento')->comment('Ingreso o Egreso');
            $table->uuid('hash_interno')->nullable()->index()->after('codigo')->comment('UUID para trazabilidad con la API de bodega');
        });
    }


    public function down()
    {
        Schema::table('gp_guia_remision_despachos', function (Blueprint $table) {
            $table->dropColumn('tipo_movimiento');
            $table->dropColumn('tipo');
            $table->dropColumn('hash_interno');

        });
        Schema::table('gp_guia_remision_transferencias', function (Blueprint $table) {
            $table->dropColumn('id_movimiento');
            $table->dropColumn('tipo_movimiento');
            $table->dropColumn('tipo');
            $table->dropColumn('hash_interno');

        });
        Schema::table('gp_guia_remision_reserva_movimientos', function (Blueprint $table) {
            $table->dropColumn('tipo');
            $table->dropColumn('hash_interno');

        });
    }
}

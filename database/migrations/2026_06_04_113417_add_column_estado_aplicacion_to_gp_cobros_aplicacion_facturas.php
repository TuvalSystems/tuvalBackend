<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnEstadoAplicacionToGpCobrosAplicacionFacturas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cobros_aplicacion_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
        });
        Schema::table('gp_cobros_cheques_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
        });
        Schema::table('gp_cobros_depositos_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
        });
        Schema::table('gp_cobros_efectivo_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
        });
        Schema::table('gp_cobros_tarjeta_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
        });
        Schema::table('gp_cobros_retenciones', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('mensaje_ws_renta_srv');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cobros_aplicacion_facturas', function (Blueprint $table) {
            $table->dropColumn(['estado_aplicado']);
        });
        Schema::table('gp_cobros_cheques_facturas', function (Blueprint $table) {
            $table->dropColumn(['estado_aplicado']);
        });
        Schema::table('gp_cobros_depositos_facturas', function (Blueprint $table) {
            $table->dropColumn(['estado_aplicado']);
        });
        Schema::table('gp_cobros_efectivo_facturas', function (Blueprint $table) {
            $table->dropColumn(['estado_aplicado']);
        });
        Schema::table('gp_cobros_tarjeta_facturas', function (Blueprint $table) {
            $table->dropColumn(['estado_aplicado']);
        });
        Schema::table('gp_cobros_retenciones', function (Blueprint $table) {
            $table->dropColumn(['estado_aplicado']);
        });
    }
}

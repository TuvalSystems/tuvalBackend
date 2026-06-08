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
            $table->foreignId('user_desaplicacion_id')
                ->nullable()
                ->after('estado_aplicado')
                ->constrained('gp_usuarios')
                ->nullOnDelete();

            $table->dateTime('fecha_desaplicacion')
                ->nullable()
                ->after('user_desaplicacion_id');

            $table->text('observacion_desaplicacion')
                ->nullable()
                ->after('fecha_desaplicacion');
        });
        Schema::table('gp_cobros_cheques_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
            $table->foreignId('user_desaplicacion_id')
                ->nullable()
                ->after('estado_aplicado')
                ->constrained('gp_usuarios')
                ->nullOnDelete();

            $table->dateTime('fecha_desaplicacion')
                ->nullable()
                ->after('user_desaplicacion_id');

            $table->text('observacion_desaplicacion')
                ->nullable()
                ->after('fecha_desaplicacion');
        });
        Schema::table('gp_cobros_depositos_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
            $table->foreignId('user_desaplicacion_id')
                ->nullable()
                ->after('estado_aplicado')
                ->constrained('gp_usuarios')
                ->nullOnDelete();

            $table->dateTime('fecha_desaplicacion')
                ->nullable()
                ->after('user_desaplicacion_id');

            $table->text('observacion_desaplicacion')
                ->nullable()
                ->after('fecha_desaplicacion');
        });
        Schema::table('gp_cobros_efectivo_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
            $table->foreignId('user_desaplicacion_id')
                ->nullable()
                ->after('estado_aplicado')
                ->constrained('gp_usuarios')
                ->nullOnDelete();

            $table->dateTime('fecha_desaplicacion')
                ->nullable()
                ->after('user_desaplicacion_id');

            $table->text('observacion_desaplicacion')
                ->nullable()
                ->after('fecha_desaplicacion');
        });
        Schema::table('gp_cobros_tarjeta_facturas', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('observacion');
            $table->foreignId('user_desaplicacion_id')
                ->nullable()
                ->after('estado_aplicado')
                ->constrained('gp_usuarios')
                ->nullOnDelete();

            $table->dateTime('fecha_desaplicacion')
                ->nullable()
                ->after('user_desaplicacion_id');

            $table->text('observacion_desaplicacion')
                ->nullable()
                ->after('fecha_desaplicacion');
        });
        Schema::table('gp_cobros_retenciones', function (Blueprint $table) {
            $table->boolean('estado_aplicado')->default(1)->after('mensaje_ws_renta_srv');
            $table->foreignId('user_desaplicacion_id')
                ->nullable()
                ->after('estado_aplicado')
                ->constrained('gp_usuarios')
                ->nullOnDelete();

            $table->dateTime('fecha_desaplicacion')
                ->nullable()
                ->after('user_desaplicacion_id');

            $table->text('observacion_desaplicacion')
                ->nullable()
                ->after('fecha_desaplicacion');
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
            // $table->dropColumn(['estado_aplicado']);
            $table->dropColumn(['user_desaplicacion_id', 'fecha_desaplicacion', 'observacion_desaplicacion']);
        });
        Schema::table('gp_cobros_cheques_facturas', function (Blueprint $table) {
            // $table->dropColumn(['estado_aplicado']);
            $table->dropColumn(['user_desaplicacion_id', 'fecha_desaplicacion', 'observacion_desaplicacion']);
        });
        Schema::table('gp_cobros_depositos_facturas', function (Blueprint $table) {
            // $table->dropColumn(['estado_aplicado']);
            $table->dropColumn(['user_desaplicacion_id', 'fecha_desaplicacion', 'observacion_desaplicacion']);
        });
        Schema::table('gp_cobros_efectivo_facturas', function (Blueprint $table) {
            // $table->dropColumn(['estado_aplicado']);
            $table->dropColumn(['user_desaplicacion_id', 'fecha_desaplicacion', 'observacion_desaplicacion']);
        });
        Schema::table('gp_cobros_tarjeta_facturas', function (Blueprint $table) {
            // $table->dropColumn(['estado_aplicado']);
            $table->dropColumn(['user_desaplicacion_id', 'fecha_desaplicacion', 'observacion_desaplicacion']);
        });
        Schema::table('gp_cobros_retenciones', function (Blueprint $table) {
            // $table->dropColumn(['estado_aplicado']);
            $table->dropColumn(['user_desaplicacion_id', 'fecha_desaplicacion', 'observacion_desaplicacion']);
        });
    }
}

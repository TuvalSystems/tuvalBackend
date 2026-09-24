<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->string('cod_categoria')->after('modificador');
            $table->string('cod_linea')->after('cod_categoria');
            $table->string('cod_clase')->after('cod_linea');
            $table->string('resultado_configuracion_item_l')->after('cod_clase');
        });

        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->string('cod_categoria')->after('modificador');
            $table->string('cod_linea')->after('cod_categoria');
            $table->string('cod_clase')->after('cod_linea');
            $table->string('resultado_configuracion_item_l')->after('cod_clase');
        });

        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->string('cod_categoria')->after('cancelado');
            $table->string('cod_linea')->after('cod_categoria');
            $table->string('cod_clase')->after('cod_linea');
            $table->string('resultado_configuracion_item_l')->after('cod_clase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn(['cod_categoria', 'cod_linea', 'cod_clase', 'resultado_configuracion_item_l']);
        });
        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn(['cod_categoria', 'cod_linea', 'cod_clase', 'resultado_configuracion_item_l']);
        });
        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->dropColumn(['cod_categoria', 'cod_linea', 'cod_clase', 'resultado_configuracion_item_l']);
        });
    }
};

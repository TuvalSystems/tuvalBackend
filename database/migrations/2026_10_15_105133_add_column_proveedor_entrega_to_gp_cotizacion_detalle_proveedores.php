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
        Schema::table('gp_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->boolean('proveedor_entrega')->default(1)->after('NOTA');
        });

        Schema::table('hi_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->boolean('proveedor_entrega')->default(1)->after('NOTA');
        });
        Schema::table('gp_pedido_detalle_proveedores', function (Blueprint $table) {
            $table->boolean('proveedor_entrega')->default(1)->after('recibido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->dropColumn('proveedor_entrega');
        });

        Schema::table('hi_cotizacion_detalle_proveedores', function (Blueprint $table) {
            $table->dropColumn('proveedor_entrega');
        });
        Schema::table('gp_pedido_detalle_proveedores', function (Blueprint $table) {
            $table->dropColumn('proveedor_entrega');
        });
    }
};

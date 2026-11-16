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
        Schema::table('gp_guia_remisions', function (Blueprint $table) {

            $table->foreignId('sucursal_destinatario_id')
                ->after('GUI_REM_DIRECCION_DESTINATARIO')
                ->nullable()
                ->constrained('gp_cliente_sucursals')
                ->nullOnDelete();

            $table->foreignId('tipo_despacho_id')
                ->after('orden_compra')->nullable()
                ->constrained('tipo_despachos')
                ->nullOnDelete();

            $table->foreignId('fle_proveedor_id')
                ->after('tipo_despacho_id')->nullable()
                ->constrained('gp_proveedors')
                ->nullOnDelete();

            $table->foreignId('fle_externo_id')
                ->after('fle_proveedor_id')->nullable()
                ->constrained('flete_externos')
                ->nullOnDelete();

            $table->decimal('valor_orden_compra', 11, 2)
                ->after('fle_externo_id')
                ->nullable();
            $table->boolean('requiere_orden_compra')->default(false)
                ->after('valor_orden_compra');
            $table->boolean('oc_generada')->default(false)->after('requiere_orden_compra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_destinatario_id');
            $table->dropConstrainedForeignId('tipo_despacho_id');
            $table->dropConstrainedForeignId('fle_proveedor_id');
            $table->dropConstrainedForeignId('fle_externo_id');

            $table->dropColumn([
                'valor_orden_compra',
                'requiere_orden_compra',
                'oc_generada',
            ]);
        });
    }
};

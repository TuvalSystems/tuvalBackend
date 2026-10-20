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

            $table->foreignId('proveedor_despacho_id')
                ->after('tipo_despacho_id')->nullable()
                ->constrained('gp_proveedors')
                ->nullOnDelete();

            $table->foreignId('chofer_transportista_id')
                ->after('proveedor_despacho_id')->nullable()
                ->constrained('chofer_transportistas')
                ->nullOnDelete();
            $table->string('numero_orden_compra_gp')
                ->after('chofer_transportista_id')
                ->nullable();
            $table->decimal('monto_orden_compra', 11, 2)
                ->after('numero_orden_compra_gp')
                ->nullable();
            $table->boolean('requiere_orden_compra')->default(false)
                ->after('monto_orden_compra');
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
            $table->dropConstrainedForeignId('proveedor_despacho_id');
            $table->dropConstrainedForeignId('chofer_transportista_id');

            $table->dropColumn([
                'numero_orden_compra_gp',
                'monto_orden_compra',
                'requiere_orden_compra',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_remision_orden_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->nullable()->constrained('gp_pedidos')->restrictOnDelete();
            $table->foreignId('guia_remision_id')->nullable()->unique()->constrained('gp_guia_remisions')->restrictOnDelete();
            $table->foreignId('guia_reserva_id')->nullable()->unique()->constrained('gp_guia_remision_reservas')->restrictOnDelete();
            $table->string('tipo_guia', 30);
            $table->string('orden_gp')->nullable()->index();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->unsignedInteger('intentos')->default(0);
            $table->json('solicitud')->nullable();
            $table->json('respuesta')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->dateTime('fecha_intento')->nullable();
            $table->dateTime('fecha_generacion')->nullable();
            $table->dateTime('fecha_proximo_intento')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('gp_guia_remision_reservas', function (Blueprint $table) {
            $table->foreignId('tipo_despacho_id')->nullable()->constrained('tipo_despachos')->nullOnDelete();
            $table->foreignId('fle_proveedor_id')->nullable()->constrained('gp_proveedors')->nullOnDelete();
            $table->decimal('valor_orden_compra', 11, 2)->nullable();
            $table->boolean('requiere_orden_compra')->default(false);
            $table->boolean('oc_generada')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_remision_orden_compra');
        Schema::table('gp_guia_remision_reservas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_despacho_id');
        });
        Schema::table('gp_guia_remision_reservas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fle_proveedor_id');
        });
        Schema::table('gp_guia_remision_reservas', function (Blueprint $table) {
            $table->dropColumn(['valor_orden_compra', 'requiere_orden_compra', 'oc_generada']);
        });
    }
};

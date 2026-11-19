<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_facturacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_id');
            $table->boolean('directa')->default(false);
            $table->boolean('trasladado')->default(false);
            $table->boolean('check_demanda')->default(true);
            $table->unsignedInteger('version')->default(0);
            $table->string('estado', 20)->default('nueva');
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
            $table->unique(['pedido_id', 'directa', 'trasladado', 'check_demanda'], 'solicitud_facturacion_identidad');
        });
        Schema::create('envios_facturacion', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_id')->primary();
            $table->string('estado', 20);
            $table->string('factura')->nullable();
            $table->timestamps();
        });
        Schema::create('refacturaciones_devolucion', function (Blueprint $table) {
            $table->unsignedBigInteger('devolucion_id')->primary();
            $table->unsignedBigInteger('pedido_id')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('consumos_reserva_facturacion', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedBigInteger('stock_id');
            $table->unsignedInteger('cantidad')->default(0);
            $table->primary(['pedido_id', 'stock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_reserva_facturacion');
        Schema::dropIfExists('refacturaciones_devolucion');
        Schema::dropIfExists('envios_facturacion');
        Schema::dropIfExists('solicitudes_facturacion');
    }
};

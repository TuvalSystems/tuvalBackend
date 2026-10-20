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
        Schema::create('chofer_transportistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')
                ->constrained('gp_proveedors')
                ->restrictOnDelete();
            $table->string('nombre');
            $table->string('identificacion', 30);
            $table->foreignId('tipo_identificacion_id')
                ->nullable()
                ->constrained('tipo_identificacions')
                ->nullOnDelete();
            $table->string('placa');
            $table->boolean('estado')->default(1);
            $table->foreignId('usuario_ingreso_id')
                ->nullable()
                ->constrained('gp_usuarios')
                ->nullOnDelete();
            $table->dateTime('fecha_ingreso')->nullable();
            $table->foreignId('usuario_modifica_id')
                ->nullable()
                ->constrained('gp_usuarios')
                ->nullOnDelete();
            $table->dateTime('fecha_modificacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chofer_transportistas');
    }
};

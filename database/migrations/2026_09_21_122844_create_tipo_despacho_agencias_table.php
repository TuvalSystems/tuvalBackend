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
        Schema::create('tipo_despacho_punto_ventas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tipo_despacho_id')
                ->constrained('tipo_despachos')
                ->cascadeOnDelete();
            $table->foreignId('pv_id')
                ->constrained('gp_punto_ventas')
                ->cascadeOnDelete();

            $table->unique([
                'tipo_despacho_id',
                'pv_id',
            ]);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_despacho_punto_ventas');
    }
};

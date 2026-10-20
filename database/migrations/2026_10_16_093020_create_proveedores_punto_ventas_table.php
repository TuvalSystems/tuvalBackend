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
        Schema::create('proveedores_punto_ventas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prov_id')
                ->constrained('gp_proveedors')
                ->cascadeOnDelete();
            $table->foreignId('pv_id')
                ->constrained('gp_punto_ventas')
                ->cascadeOnDelete();

            $table->unique([
                'prov_id',
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
        Schema::dropIfExists('proveedores_punto_ventas');
    }
};

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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('codigo')->unique();
            $table->string('ruc', 13);
            $table->string('nombre', 80)->nullable();
            $table->string('direccion', 120)->nullable();
            $table->string('telefono', 60)->nullable();
            $table->smallInteger('resolucion_contribuyente_especial')->nullable();
            $table->decimal('tolerancia_doc_sin_soporte', 5, 2)->nullable();
            $table->string('ruc_proveedor_fact_electronica', 13);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};

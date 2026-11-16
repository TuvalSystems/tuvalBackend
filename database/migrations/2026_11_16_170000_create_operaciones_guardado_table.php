<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operaciones_guardado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('tipo', 40);
            $table->string('clave', 80);
            $table->char('huella', 64);
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->timestamps();
            $table->unique(['usuario_id', 'tipo', 'clave'], 'operaciones_guardado_identidad_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_guardado');
    }
};

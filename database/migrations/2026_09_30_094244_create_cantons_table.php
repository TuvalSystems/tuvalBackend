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
        Schema::create('cantons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')
                ->constrained('provincias')
                ->cascadeOnDelete();
            $table->string('codigo_inec')->nullable();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cantons');
    }
};

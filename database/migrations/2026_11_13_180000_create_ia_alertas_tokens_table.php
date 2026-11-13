<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_alertas_tokens', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('tipo', 30);
            $table->unsignedBigInteger('user_id');
            $table->date('periodo');
            $table->timestamp('created_at');
            $table->unique(['tipo', 'user_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_alertas_tokens');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ia_configuracion', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('id')->primary();
            $table->boolean('habilitado')->default(true);
            $table->unsignedInteger('limite_diario_usuario')->default(100000);
            $table->unsignedInteger('limite_mensual_usuario')->default(1000000);
            $table->unsignedInteger('limite_mensual_global')->default(10000000);
            $table->unsignedInteger('max_entrada')->default(20000);
            $table->unsignedInteger('max_salida')->default(6000);
            $table->unsignedBigInteger('actualizado_por')->nullable();
            $table->timestamps();
        });
        DB::table('ia_configuracion')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('ia_consumos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('operacion', 40);
            $table->string('modelo', 100);
            $table->string('response_id', 150)->nullable();
            $table->string('estado', 30);
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_salida')->nullable();
            $table->unsignedInteger('tokens_reservados');
            $table->unsignedInteger('tokens_contabilizados');
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_consumos');
        Schema::dropIfExists('ia_configuracion');
    }
};

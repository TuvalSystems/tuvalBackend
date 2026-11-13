<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_configuracion', function (Blueprint $table) {
            $table->unsignedInteger('limite_diario_global')->default(100000);
        });
        DB::table('ia_configuracion')->update(['limite_diario_global' => DB::raw('limite_diario_usuario')]);
        Schema::create('ia_presupuestos_perfil', function (Blueprint $table) {
            $table->unsignedBigInteger('perfil_id')->primary();
            $table->unsignedInteger('puntos_porcentaje');
            $table->timestamps();
        });
        Schema::table('ia_consumos', function (Blueprint $table) {
            $table->unsignedBigInteger('perfil_id')->nullable();
            $table->index(['perfil_id', 'created_at']);
        });
        // Para consumos anteriores solo se conoce el perfil actual del usuario.
        DB::table('ia_consumos')->update(['perfil_id' => DB::raw('(SELECT perfil_id FROM gp_usuarios WHERE gp_usuarios.id = ia_consumos.user_id)')]);
        Schema::table('ia_alertas_tokens', function (Blueprint $table) {
            $table->dropUnique(['tipo', 'user_id', 'periodo']);
            $table->unsignedBigInteger('perfil_id')->default(0);
            $table->unique(['tipo', 'user_id', 'perfil_id', 'periodo'], 'ia_alertas_cuota_unique');
        });
    }

    public function down(): void
    {
        // Conservar atribución histórica y alertas: esta transición no se revierte automáticamente.
    }
};

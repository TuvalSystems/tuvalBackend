<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditoriasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();

            // Usuario que realiza la acción
            $table->unsignedBigInteger('user_id')->nullable();

            // Campos polimórficos: guardan el tipo estable de Eloquent y el ID.
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            // Acción realizada (ej: UPDATE_STATUS)
            $table->string('action');

            // Valores en formato JSON (MySQL 5.7+ soporta JSON nativo)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Metadatos del entorno
            $table->text('url')->nullable();
            $table->string('ip_address', 45)->nullable(); // 45 para soportar IPv6
            $table->text('user_agent')->nullable();

            $table->timestamps(); // created_at y updated_at

            // Índices y Relaciones
            $table->foreign('user_id')->references('id')->on('gp_usuarios')->onDelete('set null');
            $table->index(['auditable_type', 'auditable_id']); // Para búsquedas rápidas de historial
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auditorias');
    }
}

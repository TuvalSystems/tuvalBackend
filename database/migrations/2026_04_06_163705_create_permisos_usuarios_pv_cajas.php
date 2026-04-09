<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermisosUsuariosPvCajas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permisos_usuarios_pv_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('gp_usuarios') // Tu tabla de usuarios
                ->cascadeOnDelete();

            $table->foreignId('pv_id')
                ->constrained('gp_punto_ventas') // Tu tabla de puntos de venta
                ->cascadeOnDelete();

            // Campo de control visual
            $table->boolean('estado')->default(1);
            $table->timestamps();

            // Índice único para evitar duplicados (Un usuario no puede tener 2 veces el mismo PV)
            $table->unique(['user_id', 'pv_id'], 'user_pv_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permisos_usuarios_pv_cajas');
    }
}

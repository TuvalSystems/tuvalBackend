<?php

use Illuminate\Database\Migrations\Migration;

// Se conserva el identificador registrado en pruebas.
// Las migraciones de origen ya crean el esquema definitivo de paqueteria.
return new class extends Migration
{
    public function up(): void
    {
        // Sin renombres, reordenamientos ni traslados intermedios.
    }

    public function down(): void
    {
        // La migracion de origen revierte los campos que crea.
    }
};

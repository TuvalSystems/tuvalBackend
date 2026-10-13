<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_configuraciones', function (Blueprint $table) {
            $table->id();

            // IDs locales: pueden resolverse después de recibir el registro de SQL Server.
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('pv_id')->nullable()->constrained('gp_punto_ventas')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('gp_clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('gp_vendedors')->nullOnDelete();

            // Identidad del registro de origen; tx_cli se conserva como texto.
            $table->string('codigo_empresa', 20)->comment('co_emp');
            $table->string('codigo_localidad', 20)->comment('co_loc');
            $table->string('ruc', 30)->comment('tx_cli');
            $table->string('codigo_vendedor', 20)->nullable()->comment('co_ven');
            $table->dateTime('fecha_ultima_modificacion', 3)->nullable()->comment('fe_ultmod');
            // Estado local: el job debe excluirlo de las columnas actualizadas por upsert.
            $table->boolean('estado')->default(1)->comment('1: activo, 0: inactivo; administrado en MySQL');

            $table->dateTime('sincronizado_at', 3)->nullable();
            $table->timestamps();

            // Una configuración por empresa, localidad y cliente del origen.
            $table->unique(['codigo_empresa', 'codigo_localidad', 'ruc'], 'clientes_config_origen_unique');
            $table->index('ruc');
            $table->index('fecha_ultima_modificacion', 'clientes_config_modificacion_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_configuraciones');
    }
};

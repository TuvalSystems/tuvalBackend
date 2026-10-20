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
        Schema::table('gp_clientes', function (Blueprint $table) {
            $table->foreignId('region_id')
                ->nullable()
                ->after('CLI_DIRECCION')
                ->constrained('regions')
                ->cascadeOnDelete();
            $table->foreignId('provincia_id')
                ->nullable()
                ->after('region_id')
                ->constrained('provincias')
                ->cascadeOnDelete();
            $table->foreignId('canton_id')
                ->nullable()
                ->after('provincia_id')
                ->constrained('cantons')
                ->cascadeOnDelete();
            $table->foreignId('tipo_identificacion_id')
                ->nullable()
                ->after('canton_id')
                ->constrained('tipo_identificacions')
                ->cascadeOnDelete();
            $table->boolean('enviado_gp')->default(0);
        });

        Schema::table('gp_cliente_sucursals', function (Blueprint $table) {
            $table->foreignId('region_id')
                ->nullable()
                ->after('CLI_SUC_REFERENCIA')
                ->constrained('regions')
                ->cascadeOnDelete();
            $table->foreignId('provincia_id')
                ->nullable()
                ->after('region_id')
                ->constrained('provincias')
                ->cascadeOnDelete();
            $table->foreignId('canton_id')
                ->nullable()
                ->after('provincia_id')
                ->constrained('cantons')
                ->cascadeOnDelete();
            $table->boolean('enviado_gp')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_identificacion_id');
            $table->dropConstrainedForeignId('canton_id');
            $table->dropConstrainedForeignId('provincia_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn('enviado_gp');
        });
        Schema::table('gp_cliente_sucursals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canton_id');
            $table->dropConstrainedForeignId('provincia_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn('enviado_gp');
        });
    }
};

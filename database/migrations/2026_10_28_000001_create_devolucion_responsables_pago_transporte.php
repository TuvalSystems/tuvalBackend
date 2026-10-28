<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_devolucion_responsables_pago_transporte', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descripcion', 100);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        foreach (['cliente' => 'CLIENTE', 'tuval' => 'TUVAL'] as $codigo => $descripcion) {
            DB::table('gp_devolucion_responsables_pago_transporte')->insert([
                'codigo' => $codigo, 'descripcion' => $descripcion,
                'estado' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->foreignId('responsable_pago_transporte_id')->nullable()
                ->constrained('gp_devolucion_responsables_pago_transporte')
                ->restrictOnDelete();
        });
        Schema::table('gp_devoluciones_detalles', function (Blueprint $table) {
            $table->boolean('es_servicio_logistico')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('gp_devoluciones_detalles', function (Blueprint $table) {
            $table->dropColumn('es_servicio_logistico');
        });
        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->dropForeign(['responsable_pago_transporte_id']);
            $table->dropColumn('responsable_pago_transporte_id');
        });
        Schema::dropIfExists('gp_devolucion_responsables_pago_transporte');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrampasVaporsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trampas_vapor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('gp_clientes')->nullOnDelete();
            $table->string('proceso', 200);
            $table->string('tipo_trampa', 100);
            $table->string('metodo_calculo', 100);
            $table->decimal('presion_trabajo_psig', 10, 2);
            $table->decimal('presion_retorno_psig', 10, 2);
            $table->decimal('presion_diferencial_psig', 10, 2);
            $table->string('fluido', 100);
            $table->decimal('volumen_m3', 10, 4);
            $table->decimal('densidad_kg_m3', 10, 2);
            $table->decimal('cp_kj_kg_c', 10, 2);
            $table->decimal('temperatura_inicial_c', 10, 2);
            $table->decimal('temperatura_final_c', 10, 2);
            $table->decimal('tiempo_calentamiento_min', 10, 2);
            $table->double('calor_requerido_kj');
            $table->decimal('hfg_kj_kg', 10, 2);
            $table->decimal('carga_conocida_kg_h', 10, 2)->nullable();
            $table->decimal('potencia_caldera_bhp', 10, 2)->nullable();
            $table->double('carga_estimada_kg');
            $table->decimal('factor_seguridad', 10, 2)->default(0);
            $table->double('carga_con_factor_seguridad_kg');
            $table->json('parametros_sp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trampas_vapor');
    }
}

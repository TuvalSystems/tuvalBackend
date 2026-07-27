<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuario_visibilidad_listas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usr_pr_id')->nullable()
                ->comment('Usuario principal al que se configura la visibilidad de listas')->constrained('gp_usuarios')->cascadeOnDelete();
            $table->foreignId('usr_ver_id')->nullable()
                ->comment('Usuario cuyos registros podrán ser visualizados')->constrained('gp_usuarios')->cascadeOnDelete();
            $table->foreignId('pv_id')->nullable()
                ->comment('Punto de venta donde visualizara los registros')->constrained('gp_punto_ventas')->cascadeOnDelete();
            $table->boolean('ver_cot')
                ->default(1)->comment('Permite visualizar cotizaciones');
            $table->boolean('ver_ped')
                ->default(1)->comment('Permite visualizar pedidos');
            $table->boolean('ver_dev')
                ->default(1)->comment('Permite visualizar devoluciones');
            $table->boolean('estado')->default(1);
            $table->timestamps();

            $table->unique(
                ['usr_pr_id', 'usr_ver_id', 'pv_id'],
                'uk_usr_visibilidad'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuario_visibilidad_listas');
    }
};

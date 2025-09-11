<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToGpArticulosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_articulos', function (Blueprint $table) {
            $table->boolean('disponible')->default(0);
            $table->timestamp('fecha_ultima_actividad')->nullable();
            $table->string('dias_transcurridos')->nullable();
            $table->string('dias_restantes')->nullable();
            $table->string('dias_enfriamiento')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_articulos', function (Blueprint $table) {
            $table->dropColumn('fecha_ultimo_uso');
            $table->dropColumn('disponible');
            $table->dropColumn('numero_dias_ultimo_uso');
        });
    }
}

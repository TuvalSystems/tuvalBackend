<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTrasladosReposicionCabecerasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_traslados_reposicion_cabeceras', function (Blueprint $table) {
            $table->id();
            $table->string('Documento', 20)->unique();
            $table->string('Bodega_Origen', 20);
            $table->string('Bodega_Destino', 20);
            $table->string('Codigo_Motivo', 20);
            $table->string('Motivo', 50)->nullable();
            $table->date('Fecha_Documento');
            $table->timestamps();
        });

        Schema::table('gp_traslados_reposicions', function (Blueprint $table) {
            $table->foreign('cabecera_id', 'gp_traslados_reposicions_fk0')->references('id')->on('gp_traslados_reposicion_cabeceras')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_traslados_reposicion_cabeceras');
    }
}

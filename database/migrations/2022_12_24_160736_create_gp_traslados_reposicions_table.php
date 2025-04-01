<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTrasladosReposicionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_traslados_reposicions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cabecera_id')->unsigned();
            $table->string('Documento', 20)->nullable();
            $table->string('Codigo_Motivo', 20)->nullable();
            $table->string('ART_CODIGO', 30)->nullable();
            $table->string('ART_DESCRIPCION', 300)->nullable();
            $table->string('ART_CODIGO_TRES_LETRAS', 3)->nullable();
            $table->string('ART_PLAN_UMEDIDA', 30)->nullable();
            $table->decimal('ART_PESO', 11, 2)->nullable();
            $table->decimal('cantidad', 11, 2)->nullable();
            $table->string('bodega_origen', 20);
            $table->string('bodega_destino', 20);
            $table->string('compartimiento', 20);
            $table->date('Fecha_Documento')->nullable();
            $table->decimal('reservado', 11, 2)->nullable();
            $table->decimal('trasladado', 11, 2)->nullable();
            $table->boolean('completado')->nullable();
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
        Schema::dropIfExists('gp_traslados_reposicions');
    }
}

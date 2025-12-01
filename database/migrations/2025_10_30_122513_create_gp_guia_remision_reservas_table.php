<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionReservasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_guia_remision_reservas', function (Blueprint $table) {
            $table->id();
            $table->string('clave_acceso', 250)->nullable();
            $table->string('numero_guia', 30)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->unsigned()->index();
            $table->enum('estado', ['pendiente', 'enviada', 'recibida', 'eliminada'])->default('pendiente');
            $table->string('bodega_origen', 30)->nullable();
            $table->string('bodega_destino', 30)->nullable();
            $table->string('guia_bodega', 50)->nullable();
            $table->string('establecimiento', 3)->nullable();
            $table->string('pto_emision', 3)->nullable();
            $table->string('secuencial', 9)->nullable();
            $table->string('direccion_partida', 200)->nullable();
            $table->string('razon_social_transportista', 300)->nullable();
            $table->string('identificacion_transportista', 30)->nullable();
            $table->enum('tipo_identificacion_transportista', ['04', '05', '06'])->default('04');
            $table->string('placa', 30)->nullable();
            $table->dateTime('fecha_salida')->nullable();
            $table->dateTime('fecha_llegada')->nullable();
            $table->string('observacion', 300)->nullable();
            $table->string('direccion_destinatario', 200)->nullable();
            $table->string('identificacion_destinatario', 30)->nullable();
            $table->string('razon_social_destinatario', 300)->nullable();
            $table->string('ruta', 150)->nullable();
            $table->string('cod_sustento', 50)->nullable();
            $table->string('doc_sustento', 50)->nullable();
            $table->string('num_auto_doc_sustento', 50)->nullable();
            $table->dateTime('fecha_emision_doc_sustento')->nullable();
            $table->boolean('rebajado')->nullable()->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreign('user_id')->references('id')->on('gp_usuarios');
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
        Schema::dropIfExists('gp_guia_remision_reservas');
    }
}

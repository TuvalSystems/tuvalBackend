<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTransportistasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_transportistas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre',300)->nullable();
            $table->string('tipo_identificacion',10)->nullable();
            $table->string('identificacion',20)->nullable();
            $table->string('tipo_licencia',20)->nullable();
            $table->string('direccion',300)->nullable();
            $table->string('telefono',20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_transportistas');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfiguracionNivelesArticulosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configuracion_niveles_articulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nivel')->unique();
            $table->string('color', 20)->default('#000000');
            $table->string('descripcion', 200)->nullable();
            $table->boolean('estado')->default(1);
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
        Schema::dropIfExists('configuracion_niveles_articulos');
    }
}

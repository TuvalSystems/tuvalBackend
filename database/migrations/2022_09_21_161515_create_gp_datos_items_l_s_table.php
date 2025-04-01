<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpDatosItemsLSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_datos_itemsl', function (Blueprint $table) {
            $table->id();
            $table->string('DATOS_ITEMS_L_CLASE', 20)->nullable();
            $table->string('DATOS_ITEMS_L_DESCRIPCION_CLASE', 50)->nullable();
            $table->integer('DATOS_ITEMS_L_DIA_MAXIMO')->nullable();
            $table->integer('DATOS_ITEMS_L_MARGEN')->nullable();
            $table->integer('DATOS_ITEMS_L_TIPO')->nullable();
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
        Schema::dropIfExists('gp_datos_itemsl');
    }
}

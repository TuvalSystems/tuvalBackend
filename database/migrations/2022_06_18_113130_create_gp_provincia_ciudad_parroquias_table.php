<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpProvinciaCiudadParroquiasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_provincia_ciudad_parroquias', function (Blueprint $table) {
            $table->id();
            $table->string('provincia', 40);
            $table->string('ciudad', 60);
            $table->string('parroquia', 120);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_provincia_ciudad_parroquias');
    }
}

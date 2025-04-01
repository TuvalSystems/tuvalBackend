<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpUnidadMedidasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_unidad_medidas', function (Blueprint $table) {
            $table->id();
            $table->string('UMEDIDA_PLAN', 19)->unique();
            $table->string('UMEDIDA_BASE', 9);
            $table->integer('UMEDIDA_CANTIDAD_DECIMAL');
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
        Schema::dropIfExists('gp_unidad_medidas');
    }
}

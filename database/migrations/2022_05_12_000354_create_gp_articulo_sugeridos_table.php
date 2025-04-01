<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpArticuloSugeridosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_articulo_sugeridos', function (Blueprint $table) {
            $table->id();
            $table->string('ART_CODIGO', 30);
            $table->integer('ART_ADI_CANTIDAD');
            $table->string('ART_ADI_SUGERIDO', 30);
            $table->timestamps();
        });
        Schema::table('gp_articulo_sugeridos', function (Blueprint $table) {
            $table->foreign('ART_CODIGO')->references('ART_CODIGO')->on('gp_articulos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_articulo_sugeridos');
    }
}

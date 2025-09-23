<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionDespachosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_guia_remision_despachos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('guia_remision_id')->unsigned();
            $table->string('codigo', 50);
            $table->string('pedido', 20)->nullable();
            $table->foreign('guia_remision_id')
                ->references('id')->on('gp_guia_remisions')
                ->onDelete('restrict')   // ← NO cascade
                ->onUpdate('cascade');
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
        Schema::dropIfExists('gp_guia_remision_despachos');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionTransferenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_guia_remision_transferencias', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('guia_remision_id')->unsigned();
            $table->string('codigo', 50);
            $table->string('pedido', 20)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_guia_remision_transferencias', function (Blueprint $table) {
            $table->foreign('guia_remision_id', 'gp_guia_remision_transferencias_fk0')->references('id')->on('gp_guia_remisions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_guia_remision_transferencias');
    }
}

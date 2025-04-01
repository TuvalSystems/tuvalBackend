<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpGuiaRemisionDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_guia_remision_detalles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('detalle_id')->unsigned();
            $table->bigInteger('guia_remision_id')->unsigned();
            $table->string('GUI_REM_CODI_INTERNO', 30);
            $table->string('GUI_REM_DESCRIPCION', 300);
            $table->string('GUI_REM_CODI_ADICIONAL', 3)->nullable();
            $table->decimal('GUI_REM_CANTIDAD', 11, 2);
            $table->string('GUI_REM_UNIDAD_MEDIDA', 30);
            $table->decimal('recibido', 11, 2)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_guia_remision_detalles', function (Blueprint $table) {
            $table->foreign('guia_remision_id', 'gp_guia_remision_detalles_fk0')->references('id')->on('gp_guia_remisions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_guia_remision_detalles');
    }
}

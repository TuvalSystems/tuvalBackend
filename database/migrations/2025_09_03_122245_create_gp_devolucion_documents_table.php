<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpDevolucionDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_devolucion_documents', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('devolucion_id')->unsigned();
            $table->string('devolucion', 50);
            $table->bigInteger('id_movimiento')->unsigned()->nullable();
            $table->string('documento_gp', 20)->nullable();
            $table->foreign('devolucion_id')
                ->references('id')->on('gp_devoluciones')
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
        Schema::dropIfExists('gp_devolucion_documents');
    }
}

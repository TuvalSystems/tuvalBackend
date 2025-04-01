<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpIngresosDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_ingresos_detalles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ingreso_id')->unsigned();
            $table->string('ART_CODIGO', 30)->nullable();
            $table->decimal('ingresado', 11, 2)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_ingresos_detalles', function (Blueprint $table) {
            $table->foreign('ingreso_id', 'gp_ingresos_detalles_fk0')->references('id')->on('gp_ingresos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_ingresos_detalles');
    }
}

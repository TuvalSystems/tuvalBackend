<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpIngresosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_ingresos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('USERID', 20)->nullable();
            $table->bigInteger('sucursal_id')->unsigned()->nullable();
            $table->enum('tipo', ['traslado_venta', 'traslado_reposicion', 'compra', 'devolucion'])->default('devolucion');
            $table->string('documento', 100)->nullable();
            $table->string('GUI_REM_GUIA_REMISION', 20)->nullable();
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
        Schema::dropIfExists('gp_ingresos');
    }
}

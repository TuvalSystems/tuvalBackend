<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpVendedorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_vendedors', function (Blueprint $table) {
            $table->id();
            $table->string('VEND_ID', 15)->unique();
            $table->string('VEND_NOMBRES', 20)->nullable();
            $table->string('VEND_APELLIDOS', 20)->nullable();
            $table->string('VEND_ID_EMPLEADO', 15)->nullable();
            $table->string('VEND_DIRECCION', 61)->nullable();
            $table->string('VEND_CIUDAD', 35)->nullable();
            $table->string('VEND_PROVINCIA', 29)->nullable();
            $table->string('VEND_TERRITORIO', 15)->nullable();
            $table->string('VEND_ESTADO', 10)->nullable();
            $table->string('VEND_CORREO_ELECTRONICO', 300)->nullable();
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
        Schema::dropIfExists('gp_vendedors');
    }
}

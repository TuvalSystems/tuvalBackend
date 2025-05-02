<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpDevolucionErroresJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_devolucion_errores_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('gp_devoluciones');
            $table->integer('criterio_id')->nullable();
            $table->string('mensaje')->nullable();
            $table->string('proceso')->nullable();
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
        Schema::dropIfExists('gp_devolucion_errores_jobs');
    }
}

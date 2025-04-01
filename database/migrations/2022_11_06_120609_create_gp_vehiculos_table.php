<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpVehiculosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gp_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200)->nullable();
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('placa', 10)->nullable();
            $table->bigInteger('transportista_id')->unsigned()->nullable();
            $table->timestamps();
        });
        Schema::table('gp_vehiculos', function (Blueprint $table) {
            $table->foreign('transportista_id', 'gp_vehiculos_fk0')->references('id')->on('gp_transportistas')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_vehiculos');
    }
}

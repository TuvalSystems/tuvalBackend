<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpClaseClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_clase_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('CLASS_CODIGO', 15)->unique();
            $table->string('CLASS_DESCRIPCION', 31)->nullable();
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
        Schema::dropIfExists('gp_clase_clientes');
    }
}

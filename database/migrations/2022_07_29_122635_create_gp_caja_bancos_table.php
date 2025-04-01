<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpCajaBancosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_caja_bancos', function (Blueprint $table) {
            $table->id();
            $table->string('CB_ID', 20)->unique();
            $table->string('CB_DESCRIPCION', 100);
            $table->string('CB_TIPO', 20);
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
        Schema::dropIfExists('gp_caja_bancos');
    }
}

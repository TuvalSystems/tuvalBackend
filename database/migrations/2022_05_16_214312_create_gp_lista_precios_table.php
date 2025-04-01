<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpListaPreciosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_lista_precios', function (Blueprint $table) {
            $table->id();
            $table->string('LST_PRC_ARTICULO', 30)->nullable();
            $table->string('LST_PRC_MONEDA', 15)->nullable();
            $table->string('LST_PRC_UMEDIDA', 9)->nullable();
            $table->string('LST_PRC_ID', 15)->nullable();
            $table->decimal('LST_PRC_VALOR', 11, 2)->nullable();
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
        Schema::dropIfExists('gp_lista_precios');
    }
}

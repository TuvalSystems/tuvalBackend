<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpTempCartStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_temp_cart_stocks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_tempcart')->unsigned();
            $table->string('STO_ART_BODEGA', 20);
            $table->decimal('STO_ART_STOCK_DISPONIBLE', 11, 2)->nullable();
            $table->decimal('seleccionado', 11, 2)->nullable();
            $table->timestamps();
        });
        Schema::table('gp_temp_cart_stocks', function (Blueprint $table) {
            $table->foreign('id_tempcart', 'gp_temp_cart_stocks_fk1')->references('id')->on('gp_temp_carts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_temp_cart_stocks');
    }
}

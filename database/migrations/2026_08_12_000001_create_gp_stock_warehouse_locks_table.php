<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpStockWarehouseLocksTable extends Migration
{
    public function up()
    {
        Schema::create('gp_stock_warehouse_locks', function (Blueprint $table) {
            $table->id();
            $table->string('ART_CODIGO', 60);
            $table->string('STO_ART_BODEGA', 20);
            $table->timestamps();

            $table->unique(
                ['ART_CODIGO', 'STO_ART_BODEGA'],
                'gp_stock_warehouse_locks_art_bodega_unique'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('gp_stock_warehouse_locks');
    }
}

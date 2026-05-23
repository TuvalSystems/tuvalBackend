<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnImportJobToGpPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_pedidos', function (Blueprint $table) {
             $table->unsignedTinyInteger('import_job')->default(0)->index();
        });
        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
              $table->unsignedTinyInteger('import_job')->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->dropColumn('import_job');
        });
        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->dropColumn('import_job');
        });
    }
}

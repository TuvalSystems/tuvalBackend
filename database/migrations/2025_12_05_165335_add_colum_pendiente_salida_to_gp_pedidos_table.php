<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumPendienteSalidaToGpPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->boolean('pendiente_salida')->default(false);
            $table->unsignedBigInteger('user_block_id')->nullable();
            $table->dateTime('fecha_bloqueo')->nullable();
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
            $table->dropColumn('pendiente_salida');
        });
    }
}

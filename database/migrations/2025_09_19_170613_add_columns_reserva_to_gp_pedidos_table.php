<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsReservaToGpPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_pedidos', function (Blueprint $table) {
             $table->enum('origen', ['cotizacion','reserva'])
                ->nullable()
                ->default(null)
                ->index();
            $table->dateTime('fe_facturacion_reserva')->nullable();
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
            $table->dropColumn(['origen', 'fe_facturacion_reserva']);
        });
    }
}

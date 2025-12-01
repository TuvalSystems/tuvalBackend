<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsUserRechazoToGpCotizacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacions', function (Blueprint $t) {
            $t->unsignedBigInteger('user_rechazo_id')->nullable()->unsigned()->index()->comment('Usuario que rechazo la cotizacion en estado de espera');
            $t->foreign('user_rechazo_id')->references('id')->on('gp_usuarios');
            $t->dateTime('fe_rechazo')->nullable();
        });

        Schema::table('gp_pedidos', function (Blueprint $t) {
            $t->unsignedBigInteger('user_rechazo_id')->nullable()->unsigned()->index()->comment('Usuario que rechazo el pedido en estado de espera')->after('fecha_hora_factura');
            $t->foreign('user_rechazo_id')->references('id')->on('gp_usuarios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            //
        });
    }
}

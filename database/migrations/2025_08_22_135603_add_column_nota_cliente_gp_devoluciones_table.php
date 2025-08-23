<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNotaClienteGpDevolucionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->string('nota_cliente', 350)->nullable()->after('orden_de_compra');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_devoluciones', function (Blueprint $table) {
            $table->dropColumn('nota_cliente');
        });
    }
}

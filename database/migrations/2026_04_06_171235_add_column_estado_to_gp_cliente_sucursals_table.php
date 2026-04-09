<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnEstadoToGpClienteSucursalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cliente_sucursals', function (Blueprint $table) {
            $table->boolean('estado')->default(1)->after('fecha_hora_creacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cliente_sucursals', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
}

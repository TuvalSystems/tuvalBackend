<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOrdenCompraToGpGuiaRemisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->string('orden_compra', 30)->nullable()->after('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->dropColumn('orden_compra');
        });
    }
}

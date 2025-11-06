<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIempotencyKeyToGpGuiaRemisionReservasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_guia_remision_reservas', function (Blueprint $t) {
            $t->string('idempotency_key', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_guia_remision_reservas', function (Blueprint $t) {
            $t->dropUnique(['idempotency_key']);
            $t->dropColumn('idempotency_key');
        });
    }
}

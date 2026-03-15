<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdempotencyKeyToGpGuiaRemisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('recibidos');
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
            $table->dropColumn('idempotency_key');
        });
    }
}

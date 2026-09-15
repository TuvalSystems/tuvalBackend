<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_condicion_pagos', function (Blueprint $table) {
            $table->boolean('es_cheque')->default(0)->after('COND_VALOR');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_condicion_pagos', function (Blueprint $table) {
            $table->dropColumn(['es_cheque']);
        });
    }
};

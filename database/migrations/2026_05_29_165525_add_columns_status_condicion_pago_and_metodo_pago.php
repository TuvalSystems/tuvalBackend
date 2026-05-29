<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsStatusCondicionPagoAndMetodoPago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_condicion_pagos', function (Blueprint $table) {
            $table->boolean('estado')->default(1)->after('COND_VALOR');
        });

        Schema::table('gp_descuento_forma_pagos', function (Blueprint $table) {
            $table->boolean('estado')->default(1)->after('DESC_FORMA_PAGO_TIPO_COBRO');
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
            $table->dropColumn('estado');
        });

        Schema::table('gp_descuento_forma_pagos', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
}

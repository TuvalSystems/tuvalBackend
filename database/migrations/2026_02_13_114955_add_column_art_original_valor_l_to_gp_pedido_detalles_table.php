<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnArtOriginalValorLToGpPedidoDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->decimal('valor_original_l', 11, 4)->default(0)->nullable()->after('ART_VALOR_ORIGINAL');
        });

        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->decimal('valor_original_l', 11, 4)->default(0)->nullable()->after('ART_VALOR_ORIGINAL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->dropColumn('valor_original_l');
        });

         Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->dropColumn('valor_original_l');
        });
    }
}

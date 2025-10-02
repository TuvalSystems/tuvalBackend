<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsCantidadesToGpCotizacionDetalleStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacion_detalle_stocks', function (Blueprint $table) {
            $table->decimal('cantidad_autorizada_reserva', 18, 2)->nullable()->default(0)->after('seleccionado');
            $table->decimal('cantidad_facturada', 18, 2)->nullable()->default(0)->after('cantidad_autorizada_reserva');
            $table->decimal('cantidad_cotizada', 18, 2)->nullable()->default(0)->after('cantidad_facturada');
            $table->decimal('cantidad_trasladada', 18, 2)->nullable()->default(0)->after('cantidad_cotizada');
            $table->decimal('cantidad_cancelada', 18, 2)->nullable()->default(0)->after('cantidad_trasladada');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacion_detalle_stocks', function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_cancelada',
                'cantidad_trasladada',
                'cantidad_cotizada',
                'cantidad_facturada',
                'cantidad_autorizada_reserva',
            ]);
        });
    }
}

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
            $table->unsignedDecimal('cantidad_reservada', 18, 2)
                ->default(0)
                ->comment('Cantidad reservada para cálculos de pendientes y disponibles');

            $table->decimal('cantidad_cotizada', 18, 2)->nullable()->default(0)->after('cantidad_facturada');
            $table->decimal('cantidad_traslado', 18, 2)->nullable()->default(0)->after('cantidad_cotizada');
            $table->decimal('cantidad_cancelada', 18, 2)->nullable()->default(0)->after('cantidad_traslado');
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
                'cantidad_traslado',
                'cantidad_cotizada',
                'cantidad_facturada',
                'cantidad_autorizada_reserva',
            ]);
        });
    }
}

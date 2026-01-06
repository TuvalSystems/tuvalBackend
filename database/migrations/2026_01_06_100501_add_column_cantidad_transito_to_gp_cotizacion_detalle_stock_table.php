<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCantidadTransitoToGpCotizacionDetalleStockTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacion_detalle_stocks', function (Blueprint $table) {
             $table->decimal('cantidad_transito', 18, 2)
                ->default(0)->after('cantidad_traslado')
                ->comment('Cantidad de transito que se almacena de forma temporal para la resta de disponible antes de que llegue a la bodega destino');
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
            $table->dropColumn('cantidad_transito');
        });
    }
}

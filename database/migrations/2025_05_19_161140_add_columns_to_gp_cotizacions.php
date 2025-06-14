<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToGpCotizacions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->boolean('facturado')->default(0)->after('estado');
            $table->foreignId('cotizacion_venta_motivo_id')->nullable()->constrained('gp_cotizacion_venta_motivos')->after('facturado');
            $table->boolean('recordar')->default(0)->after('cotizacion_venta_motivo_id');
            $table->date('fecha_recordatorio')->nullable()->after('recordar');
            $table->text('observacion')->nullable()->after('fecha_recordatorio');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dropColumn(['facturado', 'cotizacion_venta_motivo_id', 'recordar', 'fecha_recordatorio', 'observacion']);
        });
    }
}

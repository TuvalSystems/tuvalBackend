<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cotizacion_borradores', function (Blueprint $table) {
            $table->foreignId('pv_id')->after('user_id')->nullable()
                ->constrained('gp_punto_ventas')->nullOnDelete();
        });

        DB::table('cotizacion_borradores as borrador')
            ->join('gp_punto_ventas as pv', 'pv.PTO_VTA_ID', '=', 'borrador.punto_venta_id')
            ->update(['borrador.pv_id' => DB::raw('pv.id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_borradores', function (Blueprint $table) {
            $table->dropForeign(['pv_id']);
            $table->dropColumn('pv_id');
        });
    }
};

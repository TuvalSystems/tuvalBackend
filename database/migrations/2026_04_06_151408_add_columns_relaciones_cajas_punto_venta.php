<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddColumnsRelacionesCajasPuntoVenta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_relacion_punto_venta_cajas', function (Blueprint $table) {
            $table->foreignId('caja_id')->after('id')->nullable()->constrained('gp_cajas')->nullOnDelete();
            $table->foreignId('pv_id')->after('caja_id')->nullable()->constrained('gp_punto_ventas')->nullOnDelete();
            $table->boolean('estado')->default(1)->after('PTO_VTA_CAJA_PUNTO_VENTA');
        });

        DB::statement("
        UPDATE gp_relacion_punto_venta_cajas AS rel
        JOIN gp_cajas AS c ON rel.PTO_VTA_CAJA_CAJA = c.CAJA_CODIGO
        JOIN gp_punto_ventas AS pv ON rel.PTO_VTA_CAJA_PUNTO_VENTA = pv.PTO_VTA_ID
        SET rel.caja_id = c.id, rel.pv_id = pv.id
    ");
        Schema::table('gp_relacion_usuario_cajas', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('caja_id')->after('user_id')->nullable()->constrained('gp_cajas')->nullOnDelete();
            $table->boolean('estado')->default(1)->after('CAJ_USER_CAJA');
        });

        DB::statement("
        UPDATE gp_relacion_usuario_cajas AS rel
        JOIN gp_usuarios AS u ON rel.CAJ_USER_USUARIO = u.USERID
        JOIN gp_cajas AS c ON rel.CAJ_USER_CAJA = c.CAJA_CODIGO
        SET rel.user_id = u.id, rel.caja_id = c.id
    ");

        Schema::table('gp_cajas', function (Blueprint $table) {
            $table->boolean('estado')->default(1)->after('CAJA_DESCRIPCION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_relacion_punto_venta_cajas', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropForeign(['pv_id']);
            $table->dropColumn(['caja_id', 'pv_id']);
        });

        Schema::table('gp_relacion_usuario_cajas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['caja_id']);
            $table->dropColumn(['user_id', 'caja_id']);
        });

        Schema::table('gp_cajas', function (Blueprint $table) {
            $table->dropColumn(['estado']);
        });
    }
}

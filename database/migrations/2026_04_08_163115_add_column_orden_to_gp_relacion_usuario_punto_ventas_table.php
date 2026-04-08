<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOrdenToGpRelacionUsuarioPuntoVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->foreignId('pv_id')->after('user_id')->nullable()->constrained('gp_punto_ventas')->nullOnDelete();
            $table->integer('orden')->after('pv_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_relacion_usuario_punto_ventas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['pv_id']);
            $table->dropColumn(['user_id', 'pv_id']);
            $table->dropColumn('orden');
        });
    }
}

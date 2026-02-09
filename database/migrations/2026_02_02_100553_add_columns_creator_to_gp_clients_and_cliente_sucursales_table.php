<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsCreatorToGpClientsAndClienteSucursalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_clientes', function (Blueprint $table) {
             $table->unsignedBigInteger('user_creator_id')->nullable()->after('CLI_FACTURA_POSTERIOR_AL_DESPACHO');
             $table->dateTime('fecha_hora_creacion')->nullable()->after('user_creator_id');
              $table->foreign('user_creator_id')
                ->references('id')->on('gp_usuarios')
                ->onDelete('no action');
        });

         Schema::table('gp_cliente_sucursals', function (Blueprint $table) {
             $table->unsignedBigInteger('user_creator_id')->nullable()->after('CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA');
             $table->dateTime('fecha_hora_creacion')->nullable()->after('user_creator_id');
              $table->foreign('user_creator_id')
                ->references('id')->on('gp_usuarios')
                ->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_clientes', function (Blueprint $table) {
            $table->dropColumn(['user_creator_id', 'fecha_hora_creacion']);
        });
         Schema::table('gp_cliente_sucursals', function (Blueprint $table) {
            $table->dropColumn(['user_creator_id', 'fecha_hora_creacion']);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsUserAndTipoVendedorToGpVendedorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_vendedors', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('VEND_ID')
                ->constrained('gp_usuarios');

            $table->foreignId('tipo_vendedor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('tipo_vendedors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_vendedors', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['tipo_vendedor_id']);
            $table->dropColumn(['user_id', 'tipo_vendedor_id']);
        });
    }
}

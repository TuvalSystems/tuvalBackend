<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCodigosToGpArticulosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_articulos', function (Blueprint $table) {
            $table->string('cod_categoria')->nullable()->after('ART_MARCA');
            $table->string('cod_linea')->nullable()->after('cod_categoria');
            $table->string('cod_clase')->nullable()->after('cod_linea');
            // $table->unsignedTinyInteger('nivel')
            //     ->default(0)
            //     ->after('cod_clase');

            $table->index('cod_categoria', 'idx_gp_articulos_cod_categoria');
            $table->index('cod_linea', 'idx_gp_articulos_cod_linea');
            $table->index('cod_clase', 'idx_gp_articulos_cod_clase');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_articulos', function (Blueprint $table) {
            $table->dropIndex('idx_gp_articulos_cod_categoria');
            $table->dropIndex('idx_gp_articulos_cod_linea');
            $table->dropIndex('idx_gp_articulos_cod_clase');

            $table->dropColumn([
                'cod_categoria',
                'cod_linea',
                'cod_clase',
                'nivel',
            ]);
        });
    }
}

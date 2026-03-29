<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeZonaEnumToReglasAprobacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_regla_aprobacions', function (Blueprint $table) {
            $table->dropColumn(['tipo_vendedor', 'zona']);
            $table->foreignId('tipo_vendedor_id')
                ->nullable()
                ->after('user_autorizador_id')
                ->constrained('tipo_vendedors')
                ->onDelete('cascade');

            $table->foreignId('zona_id')
                ->nullable()
                ->after('tipo_vendedor_id')
                ->constrained('zonas')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_regla_aprobacions', function (Blueprint $table) {
            $table->dropForeign(['tipo_vendedor_id']);
            $table->dropForeign(['zona_id']);
            $table->dropColumn(['tipo_vendedor_id', 'zona_id']);

            // Revertir a los enums originales por si acaso
            $table->enum('tipo_vendedor', ['EXTERNO', 'MOSTRADOR', 'TECNICO', 'ALL'])->default('EXTERNO');
            $table->enum('zona', ['COSTA', 'SIERRA', 'ALL'])->default('COSTA');
        });
    }
}

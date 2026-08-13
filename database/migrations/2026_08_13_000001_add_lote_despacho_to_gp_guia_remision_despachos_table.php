<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLoteDespachoToGpGuiaRemisionDespachosTable extends Migration
{
    private const TABLE = 'gp_guia_remision_despachos';
    private const BATCH_UNIQUE = 'gp_grd_lote_despacho_unique';
    private const HASH_UNIQUE = 'gp_grd_hash_interno_unique';
    private const SEQUENCE_UNIQUE = 'gp_grd_secuencia_despacho_unique';

    public function up()
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->uuid('lote_despacho_id')
                ->nullable()
                ->after('hash_interno')
                ->comment('UUID del evento de despacho parcial');
            $table->unsignedInteger('secuencia_despacho')
                ->nullable()
                ->after('lote_despacho_id')
                ->comment('Secuencia del despacho parcial por guia/pedido/tipo');
        });

        $this->createUniqueIndexes();
    }

    public function down()
    {
        $this->dropUniqueIndexes();

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['lote_despacho_id', 'secuencia_despacho']);
        });
    }

    private function createUniqueIndexes(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unique('lote_despacho_id', self::BATCH_UNIQUE);
            $table->unique('hash_interno', self::HASH_UNIQUE);
            $table->unique(
                ['guia_remision_id', 'pedido', 'tipo_movimiento', 'tipo', 'secuencia_despacho'],
                self::SEQUENCE_UNIQUE
            );
        });
    }

    private function dropUniqueIndexes(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(self::BATCH_UNIQUE);
            $table->dropUnique(self::HASH_UNIQUE);
            $table->dropUnique(self::SEQUENCE_UNIQUE);
        });
    }
}

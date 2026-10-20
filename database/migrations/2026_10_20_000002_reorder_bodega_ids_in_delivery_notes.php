<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['gp_guia_remision_reservas', 'gp_guia_remisions'] as $tableName) {
            DB::statement("ALTER TABLE `{$tableName}`
                MODIFY COLUMN `bodega_origen_id` BIGINT UNSIGNED NULL AFTER `estado`,
                MODIFY COLUMN `bodega_destino_id` BIGINT UNSIGNED NULL AFTER `bodega_origen_id`");
        }
    }

    public function down(): void
    {
        // El orden anterior no afecta los datos ni las relaciones.
    }
};

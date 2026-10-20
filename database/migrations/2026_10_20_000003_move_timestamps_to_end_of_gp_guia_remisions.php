<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Buscamos la ultima columna de negocio para dejar las fechas al final.
        $columns = DB::select('SHOW COLUMNS FROM `gp_guia_remisions`');
        $lastColumn = null;
        foreach ($columns as $column) {
            if (!in_array($column->Field, ['created_at', 'updated_at'], true)) {
                $lastColumn = $column->Field;
            }
        }

        $this->moveTimestampsAfter($lastColumn);
    }

    public function down(): void
    {
        $this->moveTimestampsAfter('requiere_orden_compra');
    }

    private function moveTimestampsAfter(string $column): void
    {
        $column = str_replace('`', '``', $column);
        DB::statement("ALTER TABLE `gp_guia_remisions`
            MODIFY COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL AFTER `{$column}`,
            MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`");
    }
};

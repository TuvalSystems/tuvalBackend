<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mark records from the previous GP-backed flow as synchronized while
     * preserving newly created web branches without a GP identifier.
     */
    public function up(): void
    {
        DB::table('gp_clientes')
            ->where('created_at', '<', '2026-10-02 00:00:00')
            ->update(['enviado_gp' => true]);

        DB::table('gp_cliente_sucursals')
            ->whereNotNull('CLI_SUC_ID_DIRECCION')
            ->where('CLI_SUC_ID_DIRECCION', '<>', '')
            ->update(['enviado_gp' => true]);
    }

    /**
     * This data migration is intentionally irreversible because resetting all
     * rows to pending would enqueue records that already exist in GP.
     */
    public function down(): void
    {
        // No-op.
    }
};

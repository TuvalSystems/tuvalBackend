<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HEADER_FIELDS = [
        'monto_flete' => 'fle_valor',
        'proveedor_despacho_id' => 'fle_proveedor_id',
        'peso_flete' => 'fle_peso',
        'usuario_flete_id' => 'fle_usuario_id',
        'fecha_flete' => 'fle_fecha',
        'fecha_flete_autorizacion' => 'fle_fecha_aut',
        'usuario_flete_autorizador_id' => 'fle_usuario_aut_id',
    ];

    private const HEADER_TABLES = ['gp_cotizacions', 'gp_pedidos', 'hi_cotizacion'];
    private const DETAIL_TABLES = ['gp_cotizacion_detalles', 'gp_pedido_detalles', 'hi_cotizacion_detalles'];

    public function up(): void
    {
        foreach (self::HEADER_TABLES as $table) {
            $this->renameColumns($table, self::HEADER_FIELDS);
        }
        foreach (self::DETAIL_TABLES as $table) {
            $this->renameColumns($table, ['monto_flete' => 'fle_valor']);
        }

        // Los IDs y las referencias existentes se conservan al renombrar.
        Schema::rename('chofer_transportistas', 'flete_externos');
        $this->renameColumns('gp_guia_remisions', [
            'chofer_transportista_id' => 'flete_externo_id',
            'proveedor_despacho_id' => 'fle_proveedor_id',
            'monto_orden_compra' => 'valor_orden_compra',
        ]);

        Schema::table('gp_proveedors', function (Blueprint $table) {
            $table->foreignId('fle_usuario_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->dateTime('fle_fecha')->nullable();
        });

        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->boolean('oc_generada')->default(false);
        });

        // Solo un número de OC de GP permite afirmar que ya fue generada.
        DB::table('gp_guia_remisions')
            ->whereNotNull('numero_orden_compra_gp')
            ->whereRaw("TRIM(numero_orden_compra_gp) <> ''")
            ->update(['oc_generada' => true]);

        $this->renameMenuRoute(
            'sistemas-mantenimientos-lista-chofer-transportista',
            'sistemas-mantenimientos-lista-flete-externo',
            ['Choferes Transportistas', 'Chofer Transportista'],
            'Fletes Externos'
        );
    }

    public function down(): void
    {
        $this->renameMenuRoute(
            'sistemas-mantenimientos-lista-flete-externo',
            'sistemas-mantenimientos-lista-chofer-transportista',
            ['Fletes Externos'],
            'Choferes Transportistas'
        );

        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->dropColumn('oc_generada');
        });
        Schema::table('gp_proveedors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fle_usuario_id');
            $table->dropColumn('fle_fecha');
        });

        $this->renameColumns('gp_guia_remisions', [
            'flete_externo_id' => 'chofer_transportista_id',
            'fle_proveedor_id' => 'proveedor_despacho_id',
            'valor_orden_compra' => 'monto_orden_compra',
        ]);
        Schema::rename('flete_externos', 'chofer_transportistas');

        foreach (self::DETAIL_TABLES as $table) {
            $this->renameColumns($table, ['fle_valor' => 'monto_flete']);
        }
        foreach (self::HEADER_TABLES as $table) {
            $this->renameColumns($table, array_flip(self::HEADER_FIELDS));
        }
    }

    private function renameColumns(string $name, array $fields): void
    {
        foreach ($fields as $from => $to) {
            Schema::table($name, function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }

    private function renameMenuRoute(string $from, string $to, array $labels, string $label): void
    {
        DB::table('sections')->where('route_name', $from)->whereIn('name', $labels)->update(['name' => $label]);
        DB::table('sections')->where('route_name', $from)->update(['route_name' => $to]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->foreignId('pv_id')
                ->after('user_id')->nullable()
                ->constrained('gp_punto_ventas')
                ->nullOnDelete();
            $table->foreignId('vendedor_id')
                ->after('cliente_id')->nullable()
                ->constrained('gp_vendedors')
                ->nullOnDelete();
            $table->foreignId('tipo_despacho_id')
                ->after('pv_id')->nullable()
                ->constrained('tipo_despachos')
                ->nullOnDelete();
            $table->string('responsable_flete',)->nullable();
            $table->foreignId('proveedor_despacho_id')
                ->after('cliente_id')->nullable()
                ->constrained('gp_proveedors')
                ->nullOnDelete();
            $table->decimal('peso', 11, 2)->default(0)->after('modificador_global');
            $table->decimal('peso_flete', 11, 2)->default(0)->after('peso');
            $table->decimal('monto_flete', 11, 2)->default(0)->after('peso_flete');
        });

        Schema::table('gp_cotizacion_detalles', function (Blueprint $table) {
            $table->foreignId('art_id')
                ->after('cotizacion_id')
                ->nullable()
                ->constrained('gp_articulos')
                ->nullOnDelete();
            $table->decimal('monto_flete', 11, 2)->default(0)->after('validador_l');
            $table->decimal('precio_sin_flete', 11, 4)->default(0)->after('monto_flete');
            $table->string('lista_precio')->after('precio_sin_flete')->nullable();
        });
        Schema::table('hi_cotizacion', function (Blueprint $table) {
            $table->foreignId('pv_id')
                ->after('user_id')->nullable()
                ->constrained('gp_punto_ventas')
                ->nullOnDelete();
            $table->foreignId('vendedor_id')
                ->after('cliente_id')->nullable()
                ->constrained('gp_vendedors')
                ->nullOnDelete();
            $table->foreignId('tipo_despacho_id')
                ->after('pv_id')->nullable()
                ->constrained('tipo_despachos')
                ->nullOnDelete();
            $table->string('responsable_flete',)->nullable();
            $table->foreignId('proveedor_despacho_id')
                ->after('cliente_id')->nullable()
                ->constrained('gp_proveedors')
                ->nullOnDelete();
            $table->decimal('peso', 11, 2)->default(0)->after('total');
            $table->decimal('peso_flete', 11, 2)->default(0)->after('peso');
            $table->decimal('monto_flete', 11, 2)->default(0)->after('peso_flete');
        });
        Schema::table('hi_cotizacion_detalles', function (Blueprint $table) {
            $table->foreignId('art_id')
                ->after('hi_cot_id')
                ->nullable()
                ->constrained('gp_articulos')
                ->nullOnDelete();
            $table->decimal('monto_flete', 11, 2)->default(0)->after('validador_l');
            $table->decimal('precio_sin_flete', 11, 4)->default(0)->after('monto_flete');
            $table->string('lista_precio')->after('precio_sin_flete')->nullable();
        });

        Schema::table('gp_pedidos', function (Blueprint $table) {
            $table->foreignId('pv_id')
                ->after('user_id')->nullable()
                ->constrained('gp_punto_ventas')
                ->nullOnDelete();
            $table->foreignId('vendedor_id')
                ->after('cliente_id')->nullable()
                ->constrained('gp_vendedors')
                ->nullOnDelete();
            $table->foreignId('tipo_despacho_id')
                ->after('pv_id')->nullable()
                ->constrained('tipo_despachos')
                ->nullOnDelete();
            $table->string('responsable_flete',)->nullable();
            $table->foreignId('proveedor_despacho_id')
                ->after('cliente_id')->nullable()
                ->constrained('gp_proveedors')
                ->nullOnDelete();
            $table->decimal('peso', 11, 2)->default(0)->after('modificador_global');
            $table->decimal('peso_flete', 11, 2)->default(0)->after('peso');
            $table->decimal('monto_flete', 11, 2)->default(0)->after('peso_flete');
        });
        Schema::table('gp_pedido_detalles', function (Blueprint $table) {
            $table->foreignId('art_id')
                ->after('pedido_id')
                ->nullable()
                ->constrained('gp_articulos')
                ->nullOnDelete();
            $table->decimal('monto_flete', 11, 2)->default(0)->after('validador_l');
            $table->decimal('precio_sin_flete', 11, 4)->default(0)->after('monto_flete');
            $table->string('lista_precio')->after('precio_sin_flete')->nullable();
        });

        foreach (['gp_cotizacions', 'hi_cotizacion', 'gp_pedidos'] as $table) {
            $this->actualizarId($table, 'pv_id', 'PTO_VTA_ID', 'gp_punto_ventas', 'PTO_VTA_ID');
            $this->actualizarId($table, 'vendedor_id', 'VEND_ID', 'gp_vendedors', 'VEND_ID');
            $this->actualizarId($table, 'tipo_despacho_id', 'despacho', 'tipo_despachos', 'descripcion');
        }

        foreach (['gp_cotizacion_detalles', 'hi_cotizacion_detalles', 'gp_pedido_detalles'] as $table) {
            $this->actualizarId($table, 'art_id', 'ART_CODIGO', 'gp_articulos', 'ART_CODIGO');
        }
    }

    private function actualizarId(string $table, string $column, string $legacyColumn, string $catalog, string $catalogColumn): void
    {
        $query = DB::table($catalog)
            ->select('id')
            ->whereColumn("{$catalog}.{$catalogColumn}", "{$table}.{$legacyColumn}")
            ->orderBy('id')
            ->limit(1);

        // Sin coincidencia, la subconsulta devuelve NULL; no se crean IDs inexistentes.
        DB::table($table)->update([$column => DB::raw('('.$query->toSql().')')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['gp_pedido_detalles', 'hi_cotizacion_detalles', 'gp_cotizacion_detalles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('art_id');
                $table->dropColumn(['monto_flete', 'precio_sin_flete', 'lista_precio']);
            });
        }

        foreach (['gp_pedidos', 'hi_cotizacion', 'gp_cotizacions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('pv_id');
                $table->dropConstrainedForeignId('vendedor_id');
                $table->dropConstrainedForeignId('tipo_despacho_id');
                $table->dropConstrainedForeignId('proveedor_despacho_id');
                $table->dropColumn(['responsable_flete', 'peso', 'peso_flete', 'monto_flete']);
            });
        }
    }
};

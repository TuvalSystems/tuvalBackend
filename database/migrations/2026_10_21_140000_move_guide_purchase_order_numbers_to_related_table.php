<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('gp_guia_remisions', 'numero_orden_compra_gp')) {
            return;
        }

        // Trasladar todos los documentos antes de quitar la columna antigua.
        DB::table('gp_guia_remisions')->whereNotNull('numero_orden_compra_gp')
            ->whereRaw("TRIM(numero_orden_compra_gp) <> ''")
            ->chunkById(100, function ($guides) {
                foreach ($guides as $guide) {
                    DB::transaction(function () use ($guide) {
                        $number = trim($guide->numero_orden_compra_gp);
                        $query = DB::table('guia_remision_orden_compra')->where('guia_remision_id', $guide->id);
                        $record = $query->lockForUpdate()->first();
                        if ($record && trim((string) $record->orden_gp) !== '' && trim($record->orden_gp) !== $number) {
                            throw new RuntimeException("La guía {$guide->id} tiene números de OC distintos. No se eliminará la columna hasta conciliarlos.");
                        }
                        $values = [
                            'pedido_id' => $record?->pedido_id ?? DB::table('gp_pedidos')->where('pedido', $guide->pedido)->value('id'),
                            'tipo_guia' => $guide->tipo,
                            'orden_gp' => $number,
                            'estado' => 'generada',
                            'fecha_generacion' => $record?->fecha_generacion ?? $guide->updated_at ?? now(),
                            'updated_at' => now(),
                        ];
                        if ($record) {
                            $query->update($values);
                        } else {
                            DB::table('guia_remision_orden_compra')->insert($values + [
                                'guia_remision_id' => $guide->id,
                                'created_at' => $guide->created_at ?? now(),
                            ]);
                        }
                        DB::table('gp_guia_remisions')->where('id', $guide->id)->update(['oc_generada' => true]);
                    });
                }
            });

        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->dropColumn('numero_orden_compra_gp');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('gp_guia_remisions', 'numero_orden_compra_gp')) {
            Schema::table('gp_guia_remisions', function (Blueprint $table) {
                $table->string('numero_orden_compra_gp')->nullable();
            });
        }
        DB::table('guia_remision_orden_compra')->whereNotNull('guia_remision_id')
            ->whereNotNull('orden_gp')->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('gp_guia_remisions')->where('id', $record->guia_remision_id)
                        ->update(['numero_orden_compra_gp' => $record->orden_gp]);
                }
            });
    }
};

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoDespachoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposDespacho = [
            [
                'descripcion' => 'Retiro en bodega',
                'observacion' => 'Bodega de retiro y validaciones existentes.',
                'responsable_pago' => 'tuval',
                'usuario_ingreso_id' => 179,
                'fecha_ingreso' => '2026-09-21 10:48:10',
                'usuario_modifica_id' => 179,
                'fecha_modificacion' => '2026-09-21 13:34:34',
                'estado' => 1,
                'created_at' => '2026-09-21 10:48:10',
                'updated_at' => '2026-09-21 13:34:34',
            ],
            [
                'descripcion' => 'Despacho en ruta',
                'observacion' => 'Controles actuales de ruta o destino, si existen.',
                'responsable_pago' => 'tuval',
                'usuario_ingreso_id' => 179,
                'fecha_ingreso' => '2026-09-21 10:50:16',
                'usuario_modifica_id' => 179,
                'fecha_modificacion' => '2026-09-21 13:34:18',
                'estado' => 1,
                'created_at' => '2026-09-21 10:50:16',
                'updated_at' => '2026-09-21 13:34:18',
            ],
            [
                'descripcion' => 'Paquetería',
                'observacion' => 'Responsable del pago, botón Información logística e ítem de servicio logístico.',
                'responsable_pago' => 'cliente',
                'usuario_ingreso_id' => 179,
                'fecha_ingreso' => '2026-09-21 10:54:59',
                'usuario_modifica_id' => 179,
                'fecha_modificacion' => '2026-10-16 09:10:50',
                'estado' => 1,
                'created_at' => '2026-09-21 10:54:59',
                'updated_at' => '2026-10-16 09:10:50',
            ],
            [
                'descripcion' => 'Flete al cobro',
                'observacion' => 'Valor total del flete, método de distribución y botón Distribuir flete.',
                'responsable_pago' => 'cliente',
                'usuario_ingreso_id' => 179,
                'fecha_ingreso' => '2026-09-21 10:55:18',
                'usuario_modifica_id' => 179,
                'fecha_modificacion' => '2026-09-21 13:33:20',
                'estado' => 1,
                'created_at' => '2026-09-21 10:55:18',
                'updated_at' => '2026-09-21 13:36:01',
            ],
            [
                'descripcion' => 'Proveedor entrega',
                'observacion' => 'Proveedor, fecha estimada y observación, según el alcance aprobado.',
                'responsable_pago' => 'cliente',
                'usuario_ingreso_id' => 179,
                'fecha_ingreso' => '2026-09-21 10:55:31',
                'usuario_modifica_id' => 179,
                'fecha_modificacion' => '2026-10-22 09:32:45',
                'estado' => 1,
                'created_at' => '2026-09-21 10:55:31',
                'updated_at' => '2026-10-22 09:32:45',
            ],
        ];

        DB::transaction(function () use ($tiposDespacho): void {
            foreach ($tiposDespacho as $tipoDespacho) {
                DB::table('tipo_despachos')->updateOrInsert(
                    ['descripcion' => $tipoDespacho['descripcion']],
                    $tipoDespacho
                );
            }
        });
    }
}

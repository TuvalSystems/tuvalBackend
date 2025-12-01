<?php

namespace Database\Seeders;

use App\Models\aprobacion\GpReglaAprobacion;
use Illuminate\Database\Seeder;

class ReglaAprobacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        GpReglaAprobacion::insert([
            ['regla_negocio_id' => 1, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 2, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 3, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 4, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 5, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 6, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 7, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 8, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 9, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 10, 'user_autorizador_id' => 179, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],

            ['regla_negocio_id' => 1, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 2, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 3, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 4, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 5, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 6, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 7, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 8, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 9, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 10, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],

            ['regla_negocio_id' => 1, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 2, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 3, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 4, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 5, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 6, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 7, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 8, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 9, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 10, 'user_autorizador_id' => 93, 'descuento_min' => -0.05, 'descuento_max' => 0.015, 'tipo_vendedor' => 'v_externo', 'zona' => 'costa', 'created_at' => now(), 'updated_at' => now()],


            ['regla_negocio_id' => 1, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 2, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 3, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 4, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 5, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 6, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 7, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 8, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 9, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],
            ['regla_negocio_id' => 10, 'user_autorizador_id' => 163, 'descuento_min' => 0, 'descuento_max' => 0, 'tipo_vendedor' => 'all', 'zona' => 'all', 'created_at' => now(), 'updated_at' => now()],


        ]);
    }
}

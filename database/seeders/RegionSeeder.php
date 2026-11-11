<?php

namespace Database\Seeders;

use App\Models\Admin\Ubicaciones\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         Region::upsert(
            [
                [
                    'codigo' => 'COSTA',
                    'nombre' => 'COSTA',
                ],
                [
                    'codigo' => 'SIERRA',
                    'nombre' => 'SIERRA',
                ],
                [
                    'codigo' => 'ORIENTE',
                    'nombre' => 'ORIENTE',
                ],
                [
                    'codigo' => 'GALAPAGOS',
                    'nombre' => 'GALAPAGOS',
                ],
            ],
            ['codigo'],
            ['nombre']
        );
    }
}

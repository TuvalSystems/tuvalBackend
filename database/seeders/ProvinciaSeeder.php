<?php

namespace Database\Seeders;

use App\Models\Admin\Provincia;
use App\Models\Admin\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProvinciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regiones = Region::pluck('id', 'codigo');

        $provincias = [
            // COSTA
            ['codigo_inec' => '07', 'nombre' => 'EL ORO', 'region' => 'COSTA'],
            ['codigo_inec' => '08', 'nombre' => 'ESMERALDAS', 'region' => 'COSTA'],
            ['codigo_inec' => '09', 'nombre' => 'GUAYAS', 'region' => 'COSTA'],
            ['codigo_inec' => '12', 'nombre' => 'LOS RÍOS', 'region' => 'COSTA'],
            ['codigo_inec' => '13', 'nombre' => 'MANABÍ', 'region' => 'COSTA'],
            ['codigo_inec' => '23', 'nombre' => 'SANTO DOMINGO DE LOS TSÁCHILAS', 'region' => 'COSTA'],
            ['codigo_inec' => '24', 'nombre' => 'SANTA ELENA', 'region' => 'COSTA'],

            // SIERRA
            ['codigo_inec' => '01', 'nombre' => 'AZUAY', 'region' => 'SIERRA'],
            ['codigo_inec' => '02', 'nombre' => 'BOLÍVAR', 'region' => 'SIERRA'],
            ['codigo_inec' => '03', 'nombre' => 'CAÑAR', 'region' => 'SIERRA'],
            ['codigo_inec' => '04', 'nombre' => 'CARCHI', 'region' => 'SIERRA'],
            ['codigo_inec' => '05', 'nombre' => 'COTOPAXI', 'region' => 'SIERRA'],
            ['codigo_inec' => '06', 'nombre' => 'CHIMBORAZO', 'region' => 'SIERRA'],
            ['codigo_inec' => '10', 'nombre' => 'IMBABURA', 'region' => 'SIERRA'],
            ['codigo_inec' => '11', 'nombre' => 'LOJA', 'region' => 'SIERRA'],
            ['codigo_inec' => '17', 'nombre' => 'PICHINCHA', 'region' => 'SIERRA'],
            ['codigo_inec' => '18', 'nombre' => 'TUNGURAHUA', 'region' => 'SIERRA'],

            // AMAZONÍA
            ['codigo_inec' => '14', 'nombre' => 'MORONA SANTIAGO', 'region' => 'AMAZONIA'],
            ['codigo_inec' => '15', 'nombre' => 'NAPO', 'region' => 'AMAZONIA'],
            ['codigo_inec' => '16', 'nombre' => 'PASTAZA', 'region' => 'AMAZONIA'],
            ['codigo_inec' => '19', 'nombre' => 'ZAMORA CHINCHIPE', 'region' => 'AMAZONIA'],
            ['codigo_inec' => '21', 'nombre' => 'SUCUMBÍOS', 'region' => 'AMAZONIA'],
            ['codigo_inec' => '22', 'nombre' => 'ORELLANA', 'region' => 'AMAZONIA'],

            // INSULAR
            ['codigo_inec' => '20', 'nombre' => 'GALÁPAGOS', 'region' => 'INSULAR'],
        ];

        $data = array_map(
            fn (array $provincia) => [
                'region_id'   => $regiones[$provincia['region']],
                'codigo_inec' => $provincia['codigo_inec'],
                'nombre'      => $provincia['nombre'],
            ],
            $provincias
        );

        Provincia::upsert(
            $data,
            ['codigo_inec'],
            ['region_id', 'nombre']
        );
    }
}

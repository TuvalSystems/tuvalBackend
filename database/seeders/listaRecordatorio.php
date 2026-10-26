<?php

namespace Database\Seeders;

use App\Models\Ventas\Cotizaciones\GpCotizacionVentaMotivo;
use Illuminate\Database\Seeder;

class listaRecordatorio extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        GpCotizacionVentaMotivo::insert([
            ['descripcion' => 'CARTERA VENCIDA',                                'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'COMPRA OTRA SUCURSAL',                           'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'PRECIO',                                         'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'PROYECTO',                                       'recordatorio' => 1, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'SIN STOCK A NIVEL NACIONAL',                     'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'SIN STOCK EN LA SUCURSAL- SI HAY EN INMACONSA',  'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'COMPRA CON OTRA RAZÓN SOCIAL',                   'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'MATERIAL EN MAL ESTADO',                         'recordatorio' => 0, 'estado' => 1, 'created_at' => now(), 'updated_at' => now()],


        ])
       ;

    }
}

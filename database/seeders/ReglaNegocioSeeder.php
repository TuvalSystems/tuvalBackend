<?php

namespace Database\Seeders;

use App\Models\aprobacion\GpReglaNegocio;
use Illuminate\Database\Seeder;

class ReglaNegocioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        GpReglaNegocio::insert([
            // ['pefil_aprobador' => 'crediticia',  'nombre' => 'Forma de Pago', 'descripcion' => 'Forma de Pago', 'observacion1'=> '','observacion2'=> '','funcion'=> 'Función que verifica algún cambio en la forma de pago','estado' => 1, 'fecha_ingreso' => now(),'user_ingreso_id' =>179,'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'crediticia', 'nombre' => 'Cupo Cliente', 'descripcion' => 'Cupo Cliente', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Función que verifica la situación del cupo del cliente', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'crediticia', 'nombre' => 'Documentos Vencidos', 'descripcion' => 'Documentos Vencidos', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Función que verifica documentos Vencidos', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'crediticia', 'nombre' => 'Documentos Sin Soporte', 'descripcion' => 'Documentos Sin Soporte', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Función que verifica los documentos sin soporte', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],

            //comercial
            ['perfil_aprobador' => 'comercial', 'nombre' => 'Forma de Pago', 'descripcion' => 'Forma de Pago', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Forma de pago de cotizacion mayor que la del cliente', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],

            ['perfil_aprobador' => 'comercial', 'nombre' => 'Descuento Articulo', 'descripcion' => 'Descuento Articulo', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Cambio en el descuento del articulo', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'comercial', 'nombre' => 'Precio Articulo', 'descripcion' => 'Precio Articulo', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Cambio en el precio del articulo', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'comercial', 'nombre' => 'Item L', 'descripcion' => 'Item L', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Margen del producto L es menor al margen aprobado', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'comercial', 'nombre' => 'Descripcion Articulo', 'descripcion' => 'Descripcion Articulo', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Descripcion del producto es diferente a la original', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'comercial', 'nombre' => 'Volver a pedir', 'descripcion' => 'Volver a pedir(Cotización)', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Cotización desde Volver a Pedir', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
            ['perfil_aprobador' => 'comercial', 'nombre' => 'Volver a pedir', 'descripcion' => 'Volver a pedir(Pedido)', 'observacion1' => '', 'observacion2' => '', 'funcion' => 'Requiere aprobación comercial. La Cotización del pedido es desde Volver a Pedir estado Espera. Obligatorio aprobar.', 'estado' => 1, 'fecha_ingreso' => now(), 'user_ingreso_id' => 179, 'created_at' => now(), 'updated_at' => now()],
        ])
        ;
    }
}

<?php

namespace App\Traits\Devoluciones;

trait DevolucionesCriteriosTrait
{
    public function listReturnCriteria()
    {
        return   [
            //estado, tipo_devolucion, despachado, procesando, volver_a_facturar, devolucion_bodega, funciones->>>> metodo, generarnotadecredito, volverafacturar, generadevolucionventa
            ['id' => 1, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total',   'despachado' => 0,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => false, 'gpOrdenDespacho'  => true],
            ['id' => 2, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => false, 'gpOrdenDespacho'  => true],

            // pendiente - aprobados
            // ['id' => 3, 'estado' => 'aprobado',   'volver_a_facturar' => 0, 'tipo_devolucion' => 'total',   'despachado' => 0,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => false, 'gpOrdenDespacho'  => true],
            // ['id' => 4, 'estado' => 'aprobado',   'volver_a_facturar' => 1, 'tipo_devolucion' => 'total',   'despachado' => 0,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => true,  'generadevolucionventa'  => false, 'gpOrdenDespacho'  => true],
            // ['id' => 5, 'estado' => 'aprobado',   'volver_a_facturar' => 1, 'tipo_devolucion' => 'total',   'despachado' => 1,  'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true,   'volverafacturar'   => true,  'generadevolucionventa'  => false, 'gpOrdenDespacho'  => false],

            // ['id' => 6, 'estado' => 'aprobado',   'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => false, 'gpOrdenDespacho'  => false],
            // ['id' => 7, 'estado' => 'aprobado',   'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 0,  'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true,   'volverafacturar'   => true,  'generadevolucionventa'  => false, 'gpOrdenDespacho'  => true],
            // ['id' => 8, 'estado' => 'aprobado',   'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => true],
            // ['id' => 9, 'estado' => 'aprobado',   'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1,  'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',   'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => false, 'gpOrdenDespacho'  => true],

            //new
            ['id' => 3, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf',  'generarnotadecredito' => true,  'volverafacturar'   => false,  'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            ['id' => 4, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0,    'metodo' => 'procesardevoluciontotalsinrf',  'generarnotadecredito' => true,  'volverafacturar'   => false,  'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            ['id' => 5, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0,    'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true,   'volverafacturar'   => true, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            ['id' => 6, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true,   'volverafacturar'   => true, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],

            ['id' => 7, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0,    'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            ['id' => 8, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true,   'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            // ['id' => 8, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0,    'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true,   'volverafacturar'   => true, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            // ['id' => 9, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true,   'volverafacturar'   => true, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],


            //pendientes
            // ['id' => 11, 'estado' => 'procesado', 'volver_a_facturar' => 1,  'tipo_devolucion' => 'total',   'despachado' => 1, 'devolucion_bodega' => 0,    'metodo' =>                            null,  'generarnotadecredito' => false,  'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            // ['id' => 12, 'estado' => 'procesado', 'volver_a_facturar' => 1,  'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0,    'metodo' =>                            null,  'generarnotadecredito' => false,  'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            // ['id' => 13, 'estado' => 'procesado', 'volver_a_facturar' => 1,  'tipo_devolucion' => 'total',   'despachado' => 1, 'devolucion_bodega' => null, 'metodo' =>                            null,  'generarnotadecredito' => false,  'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            // ['id' => 14, 'estado' => 'procesado', 'volver_a_facturar' => 1,  'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' =>                            null,  'generarnotadecredito' => false,  'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => false],
            //    //new
            // ['id' => 15, 'estado' => 'procesado', 'volver_a_facturar' => 0,  'tipo_devolucion' => 'total',   'despachado' => 1,  'devolucion_bodega' => 1,    'metodo' =>  'procesardevolucionparcialconrf',  'generarnotadecredito' => true,  'volverafacturar'   => false, 'generadevolucionventa'  => true, 'gpOrdenDespacho'  => true],



        ];
    }
}

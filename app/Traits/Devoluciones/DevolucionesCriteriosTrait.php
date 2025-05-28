<?php

namespace App\Traits\Devoluciones;

trait DevolucionesCriteriosTrait
{
    public function listReturnCriteria()
    {
        return [
            //estado, tipo_devolucion, despachado, procesando, volver_a_facturar, devolucion_bodega, funciones->>>> metodo, generarnotadecredito, volverafacturar, generadevolucionventa
            ['id' => 1, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 2, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],


            ['id' => 3, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => false],
            ['id' => 4, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => false],

            ['id' => 5, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 6, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],

            ['id' => 7, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 8, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],

            ['id' => 9, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 10, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],


            ['id' => 11, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 12, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],


        ];
    }
}

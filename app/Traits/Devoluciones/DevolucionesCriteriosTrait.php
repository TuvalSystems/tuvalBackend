<?php

namespace App\Traits\Devoluciones;

trait DevolucionesCriteriosTrait
{
    public function listReturnCriteria()
    {
        return [
            //estado, tipo_devolucion, despachado, procesando, volver_a_facturar, devolucion_bodega, funciones->>>> metodo, generarnotadecredito, volverafacturar, generadevolucionventa
            ['id' => 1, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 2, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],


            //devoluciones recibidas parciales con volver a facturar con despacho
            ['id' => 3, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => false],
            ['id' => 4, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => false],

            //devoluciones recibidas totales con volver a facturar con despacho todos los materiales despachados de la factura
            ['id' => 5, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 6, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],

            //devoluciones recibidas totales sin volver a facturar con despacho
            ['id' => 7, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 8, 'estado' => 'recibido', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],

            //devoluciones recibidas parciales con volver a facturar todos los materiales despachados de la factura
            ['id' => 9, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 10, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],


            //devoluciones aprobadas totales con volve a facturar con despacho todos los materiales despachados de la factura
            ['id' => 11, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 12, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],


            //devoluciones aprobadas parciales con volver a facturar sin despachar todos los materiales de la factura
            ['id' => 13, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 14, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],

            //devoluciones aprobadas totales con volver a facturar sin despachar todos los materiales de la factura
            ['id' => 15, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 16, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],


            //devoluciones solicitadas totales sin volvera facturar, sin despacho pero con devolucion a bodega 0 y sin devolucion a bodega 1
            ['id' => 17, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 18, 'estado' => 'solicitado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],


            //devoluciones aprobadas parciales sin volvera facturar, sin despacho pero que requieren aprobación
            ['id' => 19, 'estado' => 'aprobado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],
            ['id' => 20, 'estado' => 'aprobado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],


            //devoluciones aprobadas totalales sin volvera facturar, sin despacho pero que requieren aprobación
            ['id' => 21, 'estado' => 'aprobado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 22, 'estado' => 'aprobado', 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],

             //devoluciones aprobadas parciales con volver a facturar con despacho pero que requieren aprobacion
            ['id' => 23, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 24, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],


             //devoluciones recibidas parciales con volver a facturar todos los materiales despachados de la factura -- se repite de id 13 y 14 pero con devolucion bodega 1
            ['id' => 25, 'estado' => 'recibido', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 1, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],

        ];
    }
}

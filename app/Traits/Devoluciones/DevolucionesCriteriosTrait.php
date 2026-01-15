<?php

namespace App\Traits\Devoluciones;

use App\Enums\devoluciones\DevolutionStatus;

trait DevolucionesCriteriosTrait
{
    public function listReturnCriteria()
    {
        return [
            //estado, tipo_devolucion, despachado, procesando, volver_a_facturar, devolucion_bodega, funciones->>>> metodo, generarnotadecredito, volverafacturar, generadevolucionventa
            ['id' => 1, 'estado' => DevolutionStatus::SOLICITADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 2, 'estado' => DevolutionStatus::SOLICITADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],


            //devoluciones recibidas parciales con volver a facturar con despacho
            ['id' => 3, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => false],
            ['id' => 4, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => false],

            //devoluciones recibidas totales con volver a facturar con despacho todos los materiales despachados de la factura
            ['id' => 5, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 6, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],

            //devoluciones recibidas totales sin volver a facturar con despacho
            ['id' => 7, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 8, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesarDevolucionesRecibirParcialesSinRf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],

            //devoluciones recibidas parciales con volver a facturar todos los materiales despachados de la factura
            ['id' => 9, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            ['id' => 10, 'estado' => DevolutionStatus::RECIBIDO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],


            //devoluciones aprobadas totales con volve a facturar con despacho todos los materiales despachados de la factura
            ['id' => 11, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 12, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],


            //devoluciones aprobadas parciales con volver a facturar sin despachar todos los materiales de la factura
            ['id' => 13, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 14, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],

            //devoluciones aprobadas totales con volver a facturar sin despachar todos los materiales de la factura
            ['id' => 15, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 16, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 1, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],


            //devoluciones solicitadas totales sin volvera facturar, sin despacho pero con devolucion a bodega 0 y sin devolucion a bodega 1
            ['id' => 17, 'estado' =>  DevolutionStatus::SOLICITADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 18, 'estado' =>  DevolutionStatus::SOLICITADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],


            //devoluciones aprobadas parciales sin volvera facturar, sin despacho pero que requieren aprobación
            ['id' => 19, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],
            ['id' => 20, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'parcial', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => false],


            //devoluciones aprobadas totalales sin volvera facturar, sin despacho pero que requieren aprobación
            ['id' => 21, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => 0, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],
            ['id' => 22, 'estado' => DevolutionStatus::APROBADO, 'volver_a_facturar' => 0, 'tipo_devolucion' => 'total', 'despachado' => 0, 'devolucion_bodega' => null, 'metodo' => 'procesardevolucionparcialconrf', 'generarnotadecredito' => true, 'volverafacturar' => false, 'generadevolucionventa' => false, 'gpOrdenDespacho' => true],

             //devoluciones aprobadas parciales con volver a facturar con despacho pero que requieren aprobacion
            // ['id' => 23, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => 0, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],
            // ['id' => 24, 'estado' => 'aprobado', 'volver_a_facturar' => 1, 'tipo_devolucion' => 'parcial', 'despachado' => 1, 'devolucion_bodega' => null, 'metodo' => 'procesardevoluciontotalsinrf', 'generarnotadecredito' => true, 'volverafacturar' => true, 'generadevolucionventa' => true, 'gpOrdenDespacho' => true],


        ];
    }
}

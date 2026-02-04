<?php

namespace App\Http\Controllers\bodegas;

use App\Http\Controllers\Controller;
use App\Models\GpTransportista;
use App\Models\GpVehiculo;
use Illuminate\Http\Request;

class TransportesController extends Controller
{
    public function transportistas(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $transportistas = GpTransportista::get();
        $vehiculos = GpVehiculo::get();

        return response([
            'success' => true,
            'transportistas' => $transportistas,
            'vehiculos' => $vehiculos,
        ]);
    }

    public function savetransportista(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        if(isset($data['id']) && $data['id'] != ''){
            $transportista = GpTransportista::where('id',$data['id'])->first();
        }else{
            $transportista = new GpTransportista();
        }
        $transportista->nombre = trim($data['nombre']);
        $transportista->tipo_identificacion = $data['tipo_identificacion'];
        $transportista->identificacion = trim($data['identificacion']);
        $transportista->tipo_licencia = $data['tipo_licencia'];
        $transportista->direccion = trim($data['direccion']);
        $transportista->telefono = trim($data['telefono']);

        if($transportista->save()){
            return response([
                'success' => true,
                'message' => 'Transportista guardado',
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }

    public function savevehiculo(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        if(isset($data['id']) && $data['id'] != ''){
            $vehiculo = GpVehiculo::where('id',$data['id'])->first();
        }else{
            $vehiculo = new GpVehiculo();
        }
        $vehiculo->nombre = trim($data['nombre']);
        $vehiculo->marca = trim($data['marca']);
        $vehiculo->modelo = trim($data['modelo']);
        $vehiculo->placa = trim($data['placa']);
        if(isset($data['transportista_id']))
            $vehiculo->transportista_id = $data['transportista_id'];
        if($vehiculo->save()){
            return response([
                'success' => true,
                'message' => 'Vehículo guardado',
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }

    public function deletetransportista($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        if(GpTransportista::where('id',$id)->delete()){
            return response([
                'success' => true,
                'message' => 'Transportista eliminado',
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al eliminar'
            ], 409);
        }
    }

    public function deletevehiculo($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        if(GpVehiculo::where('id',$id)->delete()){
            return response([
                'success' => true,
                'message' => 'Vehículo eliminado',
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al eliminar'
            ], 409);
        }
    }
}

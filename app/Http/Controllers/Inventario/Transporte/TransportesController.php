<?php

namespace App\Http\Controllers\Inventario\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Transporte\GpTransportista;
use App\Models\Inventario\Transporte\GpVehiculo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;

class TransportesController extends Controller
{
    public function transportistas(): Response|Application|ResponseFactory
    {
        $transportistas = GpTransportista::get();
        $vehiculos = GpVehiculo::get();

        return response([
            'success' => true,
            'transportistas' => $transportistas,
            'vehiculos' => $vehiculos,
        ]);
    }

    public function savetransportista(Request $request): Response|Application|ResponseFactory
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

    public function savevehiculo(Request $request): Response|Application|ResponseFactory
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

    public function deletetransportista($id): Response|Application|ResponseFactory
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

    public function deletevehiculo($id): Response|Application|ResponseFactory
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

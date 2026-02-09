<?php

namespace App\Services\Builders\Venta;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use Carbon\Carbon;

class ClientBuilder implements ClientBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function formPayloadNewAddress(array $data, GpCliente $cliente, $direccion, $email): array
    {
        return [
            'ruc'                   => $cliente->CLI_RUC,
            'contacto'              => data_get($data, 'contacto', ''),
            'direccionId'           => '',
            'direccion1'            => data_get($direccion, 0, ''),
            'direccion2'            => data_get($direccion, 1, ''),
            'ciudad'                => data_get($data, 'ciudad', ''),
            'provincia'             => data_get($data, 'provincia', ''),
            'pais'                  => 'Ec',
            'territorio'            => data_get($data, 'territorio', ''),
            'telefono1'             => data_get($data, 'telefono', ''),
            'telefono2'             => '',
            'telefono3'             => '',
            'telefono4'             => '',
            'correoSri'             => $email,
            'correoNotificacion'    => $email,
            'vendedor'              => $cliente->CLI_VENDEDOR,
            'puntoVenta'            =>data_get($data, 'puntoVenta', ''),
        ];
    }


    public function formPayloadNewSucursal(array $data, GpCliente $cliente, $identificador, $email, $direccion, GpUsuario   $user): GpClienteSucursal{
        $nw = new GpClienteSucursal();
         $nw->fill([
            'CLI_SUC_RUC'           => $cliente->CLI_RUC,
            'CLI_SUC_DESCRIPCION'   => $cliente->CLI_RAZON_SOCIAL,
            'CLI_SUC_ID_DIRECCION'  => $identificador,
            'CLI_SUC_DIRECCION'     => data_get($direccion, 0, ''),
            'CLI_SUC_CONTACTO'      => data_get($data, 'contacto', ''),
            'CLI_SUC_REFERENCIA'    => data_get($data, 'referencia', ''),
            'CLI_SUC_VENDEDOR'      => $cliente->CLI_VENDEDOR,
            'CLI_SUC_TERRITORIO'    => data_get($data, 'territorio', ''),
            'CLI_SUC_CIUDAD'        => data_get($data, 'ciudad', ''),
            'CLI_SUC_PROVINCIA'     => data_get($data, 'provincia', ''),
            'CLI_SUC_PAIS'          => 'Ecuador',
            'CLI_SUC_TELEFONO'      => data_get($data, 'telefono', ''),
            'CLI_SUC_CORREO_ELECTRONICO_SRI' => $email,
            'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA' => $email,
            'user_creator_id'       => $user->id,
            'fecha_hora_creacion'   => Carbon::now()->toDateTimeString(),
        ]);
        $nw->save();
        return $nw;
    }
}

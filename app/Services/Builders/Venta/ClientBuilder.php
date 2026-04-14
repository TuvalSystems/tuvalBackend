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
            'puntoVenta'            => data_get($data, 'puntoVenta', ''),
        ];
    }


    public function formPayloadNewSucursal(array $data, GpCliente $cliente, $identificador, $email, $direccion, GpUsuario   $user): GpClienteSucursal
    {
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


    /** builder para sincronizacion */
    public function formPayloadNewClientSync(array $data): array
    {
        return [
            'CLI_RAZON_SOCIAL'                              => trim(data_get($data, 'razonSocial', '')),
            'CLI_NOMBRE_CORTO'                              => trim(data_get($data, 'nombreCorto', '')),
            'CLI_CLASE'                                     => trim(data_get($data, 'idClase', '')),
            'CLI_PRIORIDAD'                                 => (int) data_get($data, 'prioridad', 0),
            'CLI_ID_DIRECCION'                              => trim(data_get($data, 'dirPrincipal', '')),
            'CLI_CONTACTO'                                  => trim(data_get($data, 'contacto', '')),
            'CLI_DIRECCION'                                 => trim(data_get($data, 'direccion', '')),
            'CLI_CIUDAD'                                    => trim(data_get($data, 'ciudad', '')),
            'CLI_PROVINCIA'                                 => trim(data_get($data, 'provincia', '')),
            'CLI_TERRITORIO'                                => trim(data_get($data, 'territorio', '')),
            'CLI_ESTADO'                                    => trim(data_get($data, 'estado', '')),
            'CLI_SUSPENDIDO'                                => trim(data_get($data, 'suspendido', '')),
            'CLI_TELEFONO_1'                                => trim(data_get($data, 'telefono1', '')),
            'CLI_TELEFONO_2'                                => trim(data_get($data, 'telefono2', '')),
            'CLI_TELEFONO_3'                                => trim(data_get($data, 'telefono3', '')),
            'CLI_TELEFONO_4'                                => trim(data_get($data, 'telefono4', '')),
            'CLI_FECHA_MODIFICACION'                        => trim(data_get($data, 'fechaModif', '')),
            'CLI_FECHA_CREACION'                            => trim(data_get($data, 'fechaCreacion', '')),
            'CLI_CORREO_ELECT_SRI'                          => trim(data_get($data, 'emailSri', '')),
            'CLI_CORREO_ELECT_NOTIFICACION_INTERNA'         => trim(data_get($data, 'emailInterno', '')),
            'CLI_IMPUESTO'                                  => (float)data_get($data, 'impVta', 0),
            'CLI_DIRECCION_ENVIO'                           => trim(data_get($data, 'dirEnvio', '')),
            'CLI_DIRECCION_FACTURA'                         => trim(data_get($data, 'dirFactura', '')),
            'CLI_VENDEDOR'                                  => trim(data_get($data, 'vendedor', '')),
            'CLI_DSTO_COMERCIAL'                            => (float)data_get($data, 'descComercial', 0),
            'CLI_CONDICION_PAGO'                            => trim(data_get($data, 'condPago', '')),
            'CLI_DIA_GRACIA_VTA_CREDITO'                    => (int)data_get($data, 'diaGraciaCredito', 0),
            'CLI_DIA_GRACIA_CHEQUE_A_FECHA'                 => (int)data_get($data, 'diaGraciaCheque', 0),
            'CLI_NIVEL_PRECIO'                              => trim(data_get($data, 'nivelPrecio', '')),
            'CLI_TIPO_CREDITO'                              => trim(data_get($data, 'tipoCredito', '')),
            'CLI_MONTO_CREDITO'                             => (float)data_get($data, 'montoCredito', 0),
            'CLI_GRUPO_ECONOMICO'                           => trim(data_get($data, 'grupoEconomico', '')),
            'CLI_GRUPO'                                     => trim(data_get($data, 'grupo', '')),
            'CLI_SUBGRUPO'                                  => trim(data_get($data, 'subGrupo', '')),
            'CLI_DIA_MAXIMO_MES_FACTURACION'                => (int)data_get($data, 'diaMaxMesFacturacion', 0),
            'CLI_FACTURA_POSTERIOR_AL_DESPACHO'             => (data_get($data, 'factPostAlDespacho') == 1) ? 'SI' : 'NO',
        ];
    }
}

<?php

namespace App\Services\Builders\Venta;

use App\Enums\Ventas\TaxRateEnum;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Traits\CleansPayloadData;
use Carbon\Carbon;

class ClientBuilder implements ClientBuilderInterface
{
    use CleansPayloadData;

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function formPayloadNewAddress(array $data, GpCliente $cliente, array $direccion, string $email): array
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


    public function formPayloadNewSucursal(array $data, GpCliente $cliente, ?string $identificador, string $email, array $direccion, GpUsuario $user): GpClienteSucursal
    {
        $nw = new GpClienteSucursal();
        $nw->fill([
            'cliente_id'                        => $cliente->id,
            'CLI_SUC_RUC'                       => $cliente->CLI_RUC,
            'CLI_SUC_DESCRIPCION'               => $cliente->CLI_RAZON_SOCIAL,
            'CLI_SUC_ID_DIRECCION'              => $identificador,
            'CLI_SUC_DIRECCION'                 => data_get($data, 'direccion', data_get($direccion, 0, '')),
            'CLI_SUC_CONTACTO'                  => data_get($data, 'contacto', ''),
            'CLI_SUC_REFERENCIA'                => data_get($data, 'referencia', ''),
            'CLI_SUC_VENDEDOR'                  => $cliente->CLI_VENDEDOR,
            'CLI_SUC_TERRITORIO'                => data_get($data, 'territorio', ''),
            'CLI_SUC_CIUDAD'                    => data_get($data, 'ciudad', ''),
            'CLI_SUC_PROVINCIA'                 => data_get($data, 'provincia', ''),
            'CLI_SUC_PAIS'                      => 'Ecuador',
            'CLI_SUC_TELEFONO'                  => data_get($data, 'telefono', ''),
            'CLI_SUC_CORREO_ELECTRONICO_SRI'    => $email,
            'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA' => $email,
            'user_creator_id'                   => $user->id,
            'fecha_hora_creacion'               => Carbon::now()->toDateTimeString(),
            'region_id'                         => data_get($data, 'region_id'),
            'provincia_id'                      => data_get($data, 'provincia_id'),
            'canton_id'                         => data_get($data, 'canton_id'),
        ]);
        return $nw;
    }


    /** builder para sincronizacion */
    public function formPayloadNewClientSync(array $data, ?int $regionId, ?int $provinciaId, ?int $cantonId): array
    {
        return [
            'CLI_RAZON_SOCIAL'                              => trim(data_get($data, 'razonSocial', '')),
            'CLI_NOMBRE_CORTO'                              => trim(data_get($data, 'nombreCorto', '')),
            'CLI_CLASE'                                     => trim(data_get($data, 'idClase', '')),
            'CLI_PRIORIDAD'                                 => (int) data_get($data, 'prioridad', 0),
            'CLI_ID_DIRECCION'                              => trim(data_get($data, 'dirPrincipal', '')),
            'CLI_CONTACTO'                                  => trim(data_get($data, 'contacto', "")),
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
            'CLI_FECHA_MODIFICACION'                        => Carbon::parse(data_get($data, 'fechaModif'))->format('Y-m-d'),
            'CLI_FECHA_CREACION'                            => Carbon::parse(data_get($data, 'fechaCreacion', ''))->format('Y-m-d'),
            'CLI_CORREO_ELECT_SRI'                          => trim(data_get($data, 'emailSri', '')),
            'CLI_CORREO_ELECT_NOTIFICACION_INTERNA'         => trim(data_get($data, 'emailInterno', '')),
            'CLI_IMPUESTO'                                  => (float) round(data_get($data, 'impVta', 0), 2),
            'CLI_DIRECCION_ENVIO'                           => trim(data_get($data, 'dirEnvio', '')),
            'CLI_DIRECCION_FACTURA'                         => trim(data_get($data, 'dirFactura', '')),
            'CLI_VENDEDOR'                                  => trim(data_get($data, 'vendedor', '')),
            'CLI_DSTO_COMERCIAL'                            => (float)data_get($data, 'descComercial', 0),
            'CLI_CONDICION_PAGO'                            => trim(data_get($data, 'condPago', '')),
            'CLI_DIA_GRACIA_VTA_CREDITO'                    => (int)data_get($data, 'diaGraciaCredito', 0),
            'CLI_DIA_GRACIA_CHEQUE_A_FECHA'                 => (int)data_get($data, 'diaGraciaCheque', 0),
            'CLI_NIVEL_PRECIO'                              => trim(data_get($data, 'nivelPrecio', '')),
            'CLI_TIPO_CREDITO'                              => trim(data_get($data, 'tipoCredito', '')),
            'CLI_MONTO_CREDITO'                             => (float) round(data_get($data, 'montoCredito', 0), 2),
            'CLI_GRUPO_ECONOMICO'                           => trim(data_get($data, 'grupoEconomico', '')),
            'CLI_GRUPO'                                     => trim(data_get($data, 'grupo', '')),
            'CLI_SUBGRUPO'                                  => trim(data_get($data, 'subGrupo', '')),
            'CLI_DIA_MAXIMO_MES_FACTURACION'                => (int)data_get($data, 'diaMaxMesFacturacion', 0),
            'CLI_FACTURA_POSTERIOR_AL_DESPACHO'             => (data_get($data, 'factPostAlDespacho') == 1) ? 'SI' : 'NO',
            'region_id'                                     => $regionId,
            'provincia_id'                                  => $provinciaId,
            'canton_id'                                     => $cantonId,
            'enviado_gp'                                    => true,
        ];
    }



    public function formPayloadClientBranchesSync(int $clienteId, string $ruc, string $descripcion, array $data, ?int $regionId, ?int $provinciaId, ?int $cantonId): array
    {
        return [
            'cliente_id'                                        => $clienteId,
            'CLI_SUC_RUC'                                       => $ruc,
            'CLI_SUC_DESCRIPCION'                               => trim($descripcion  ?? ''),
            'CLI_SUC_ID_DIRECCION'                              => trim(data_get($data, 'idDireccion', '')),
            'CLI_SUC_DIRECCION'                                 => trim(data_get($data, 'direccion', '')),
            'CLI_SUC_CONTACTO'                                  => trim(data_get($data, 'contacto', '')),
            'CLI_SUC_REFERENCIA'                                => trim(data_get($data, 'referencia', '')),
            'CLI_SUC_VENDEDOR'                                  => trim(data_get($data, 'vendedor', '')),
            'CLI_SUC_TERRITORIO'                                => trim(data_get($data, 'territorio', '')),
            'CLI_SUC_CIUDAD'                                    => trim(data_get($data, 'ciudad', '')),
            'CLI_SUC_PROVINCIA'                                 => trim(data_get($data, 'provincia', '')),
            'CLI_SUC_PAIS'                                      => trim(data_get($data, 'pais', '')),
            'CLI_SUC_TELEFONO'                                  => trim(data_get($data, 'telefono', '')),
            'CLI_SUC_CORREO_ELECTRONICO_SRI'                    => trim(data_get($data, 'correoSri', '')),
            'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA'   => trim(data_get($data, 'correoInterno', '')),
            'region_id'                                         => $regionId,
            'provincia_id'                                      => $provinciaId,
            'canton_id'                                         => $cantonId,
            'enviado_gp'                                        => true,
        ];
    }


    /** nuevo proceso de guardado de cliente y sucursales */
    public function formPayloadStoreClient(array $data, GpUsuario $user): GpCliente
    {
        $cli = new GpCliente();
        $cli->fill([
            'tipo_identificacion'                   => $this->clean($data, 'tipo_identificacion'),
            'tipo_identificacion_id'                => $this->clean($data, 'tipo_identificacion_id'),
            'CLI_RUC'                               => $this->clean($data, 'identificacion'),
            'CLI_RAZON_SOCIAL'                      => $this->clean($data, 'razon_social'),
            'CLI_NOMBRE_CORTO'                      => $this->clean($data, 'nombre_corto'),
            'CLI_CLASE'                             => 'LOCAL',
            'CLI_PRIORIDAD'                         => '1',
            'CLI_ID_DIRECCION'                      => 'PRINCIPAL',
            'CLI_CONTACTO'                          => $this->clean($data, 'contacto'),
            'CLI_DIRECCION'                         => $this->clean($data, 'direccionPrincipal.direccion'),
            'region_id'                             => $this->clean($data, 'direccionPrincipal.region_id'),
            'CLI_TERRITORIO'                        => $this->clean($data, 'direccionPrincipal.region'),
            'provincia_id'                          => $this->clean($data, 'direccionPrincipal.provincia_id'),
            'CLI_PROVINCIA'                         => $this->clean($data, 'direccionPrincipal.provincia'),
            'canton_id'                             => $this->clean($data, 'direccionPrincipal.canton_id'),
            'CLI_CIUDAD'                            => $this->clean($data, 'direccionPrincipal.canton'),
            'CLI_ESTADO'                            => 'ACTIVO',
            'CLI_SUSPENDIDO'                        => 'NO',
            'CLI_TELEFONO_1'                        => $this->clean($data, 'telefono1'),
            'CLI_TELEFONO_2'                        => $this->clean($data, 'telefono2'),
            'CLI_TELEFONO_3'                        => $this->clean($data, 'telefono3'),
            'CLI_TELEFONO_4'                        => $this->clean($data, 'telefono4'),
            'CLI_FECHA_MODIFICACION'                => now(),
            'CLI_FECHA_CREACION'                    => now(),
            'CLI_CORREO_ELECT_SRI'                  => $this->cleanLower($data, 'correo_sri'),
            'CLI_CORREO_ELECT_NOTIFICACION_INTERNA' => $this->cleanLower($data, 'correo_contacto'),
            'CLI_IMPUESTO'                          => TaxRateEnum::IVA_15->value,
            'CLI_DIRECCION_ENVIO'                   => 'PRINCIPAL',
            'CLI_DIRECCION_FACTURA'                 => 'PRINCIPAL',
            'CLI_VENDEDOR'                          => $this->clean($data, 'codigo_vendedor'),
            'CLI_DSTO_COMERCIAL'                    => 25,
            'CLI_CONDICION_PAGO'                    => 'CONTADO',
            'CLI_DIA_GRACIA_VTA_CREDITO'            => 0,
            'CLI_DIA_GRACIA_CHEQUE_A_FECHA'         => 0,
            'CLI_NIVEL_PRECIO'                      => $this->clean($data, 'lvlPrice'),
            'CLI_TIPO_CREDITO'                      => 'SIN CREDITO',
            'CLI_MONTO_CREDITO'                     => 0,
            'CLI_GRUPO_ECONOMICO'                   => '',
            'CLI_GRUPO'                             => $this->clean($data, 'grupo_nombre'),
            'CLI_DIA_MAXIMO_MES_FACTURACION'        => 0,
            'CLI_FACTURA_POSTERIOR_AL_DESPACHO'     => 'NO',
            'CLI_SEGMENTO_ID'                       => $this->cleanNullable($data, 'segmento_id'),
            'CLI_SUBSEGMENTO_ID'                    => $this->cleanNullable($data, 'subsegmento_id'),
            'user_creator_id'                       => $user->id,
            'fecha_hora_creacion'                   => now(),
        ]);
        return $cli;
    }

    public function formPayloadStoreAddressByClient(GpCliente $client, GpUsuario $user, array $data, ?string $identificador = null): GpClienteSucursal
    {
        $ad = new GpClienteSucursal();
        $ad->fill([
            'cliente_id'                                        => $client->id,
            'CLI_SUC_RUC'                                       => $client->CLI_RUC,
            'CLI_SUC_DESCRIPCION'                               => $client->CLI_RAZON_SOCIAL,
            'CLI_SUC_ID_DIRECCION'                              => $identificador,
            'CLI_SUC_DIRECCION'                                 => data_get($data, 'direccion'),
            'CLI_SUC_CONTACTO'                                  => data_get($client, 'CLI_CONTACTO', ''),
            'CLI_SUC_REFERENCIA'                                => data_get($client, 'CLI_CONTACTO', ''),
            'CLI_SUC_VENDEDOR'                                  => data_get($client, 'CLI_VENDEDOR'),
            'region_id'                                         => $this->clean($data, 'region_id'),
            'CLI_SUC_TERRITORIO'                                => data_get($data, 'region'),
            'provincia_id'                                      => data_get($data, 'provincia_id', ''),
            'CLI_SUC_PROVINCIA'                                 => data_get($data, 'provincia', ''),
            'canton_id'                                         => data_get($data, 'canton_id', ''),
            'CLI_SUC_CIUDAD'                                    => data_get($data, 'canton', ''),
            'CLI_SUC_PAIS'                                      => 'Ecuador',
            'CLI_SUC_TELEFONO'                                  => data_get($client, 'CLI_TELEFONO_1', ''),
            'CLI_SUC_CORREO_ELECTRONICO_SRI'                    => data_get($client, 'CLI_CORREO_ELECT_SRI', ''),
            'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA'   => data_get($client, 'CLI_CORREO_ELECT_SRI', ''),
            'user_creator_id'                                   => $user->id,
            'fecha_hora_creacion'                               => Carbon::now()->toDateTimeString(),
        ]);
        return $ad;
    }

    /** payloas para envio de cliente, proceso centralizado en ClientGpSenderService */

    public function formPayloadSenderClientGp(GpCliente $client): array
    {
        return [
            'ruc'                   => data_get($client, 'CLI_RUC'),
            'razonSocial'           => data_get($client, 'CLI_RAZON_SOCIAL'),
            'nombreCorto'           => data_get($client, 'CLI_NOMBRE_CORTO'),
            'clase'                 => data_get($client, 'CLI_CLASE'),
            'prioridad'             => data_get($client, 'CLI_PRIORIDAD'),
            'contacto'              => data_get($client, 'CLI_CONTACTO'),
            'direccion1'            => data_get($client, 'CLI_DIRECCION', ''),
            'direccion2'            => '',
            'ciudad'                => data_get($client, 'CLI_CIUDAD'),
            'provincia'             => data_get($client, 'CLI_PROVINCIA'),
            'pais'                  => 'Ec',
            'territorio'            => data_get($client, 'CLI_TERRITORIO'),
            'telefono1'             => data_get($client, 'CLI_TELEFONO_1'),
            'telefono2'             => data_get($client, 'CLI_TELEFONO_2'),
            'telefono3'             => data_get($client, 'CLI_TELEFONO_3'),
            'telefono4'             => data_get($client, 'CLI_TELEFONO_4'),
            'correoSri'             => data_get($client, 'CLI_CORREO_ELECT_SRI'),
            'correoNotificacion'    => data_get($client, 'CLI_CORREO_ELECT_NOTIFICACION_INTERNA'),
            'vendedor'              => data_get($client, 'CLI_VENDEDOR'),
            'grupo'                 => data_get($client, 'CLI_GRUPO', ''),
            'subGrupo'              => data_get($client, 'CLI_SUBGRUPO', ''),
            'descComercial'         => data_get($client, 'CLI_DSTO_COMERCIAL'),
            'condPago'              => data_get($client, 'CLI_CONDICION_PAGO'),
            'nivelPrecio'           => data_get($client, 'CLI_NIVEL_PRECIO'),
            'puntoVenta'            => '005', //por defecto se pasa un punto de venta
            'tipoId'                => data_get($client, 'tipo_identificacion'),
        ];
    }

    public function formPayloadSenderClientBranchToGp(GpClienteSucursal $branch): array
    {
        return [
            'ruc'                   => $branch->CLI_SUC_RUC,
            'contacto'              => $branch->CLI_SUC_CONTACTO,
            'direccionId'           => strtoupper(trim((string) $branch->CLI_SUC_ID_DIRECCION)),
            'direccion1'            => data_get($branch, 'CLI_SUC_DIRECCION', ''),
            'direccion2'            => '',
            'ciudad'                => $branch->CLI_SUC_CIUDAD,
            'provincia'             => $branch->CLI_SUC_PROVINCIA,
            'pais'                  => 'Ec',
            'territorio'            => $branch->CLI_SUC_TERRITORIO,
            'telefono1'             => $branch->CLI_SUC_TELEFONO,
            'telefono2'             => '',
            'telefono3'             => '',
            'telefono4'             => '',
            'correoSri'             => $branch->CLI_SUC_CORREO_ELECTRONICO_SRI,
            'correoNotificacion'    => $branch->CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA,
            'vendedor'              => $branch->CLI_VENDEDOR,
            'puntoVenta'            => '005', //este es un dato pro defecto, en el sp hay un proceso que reasigna el punto de venta
        ];
    }
}

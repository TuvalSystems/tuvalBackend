<?php

namespace App\Services\Builders\Bodega;

use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Services\Builders\Bodega\Contracts\ReservationDeliveryNoteParamsBuilderInterface;

class ReservationDeliveryNoteParamsBuilder implements ReservationDeliveryNoteParamsBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function buildReservationGuideHeader($payload, GpUsuario $user, array $datavehicle, string $secuencial, string $dirDestination, string $numGuide, string $bodGuide, string $dirOrigin): GpGuiaRemisionReserva
    {
        $guia = new GpGuiaRemisionReserva();
        $guia->fill([
            'numero_guia'                       => $numGuide,
            'user_id'                           => $user->id,
            'estado'                            => 'pendiente',
            'bodega_origen'                     => $payload['bod_origen'],
            'bodega_destino'                    => $payload['bod_destino'],
            'guia_bodega'                       => $bodGuide,
            'establecimiento'                   => $payload['pto_origen'],
            'pto_emision'                       => '001',
            'secuencial'                        => $secuencial,
            'direccion_partida'                 => $dirOrigin,
            'razon_social_transportista'        => $datavehicle['transportista']['nombre'],
            'identificacion_transportista'      => $datavehicle['transportista']['identificacion'],
            'tipo_identificacion_transportista' => $datavehicle['transportista']['tipo_identificacion'],
            'placa'                             => $datavehicle['vehiculo']['placa'],
            'fecha_salida'                      => $payload['fecha_salida'],
            'fecha_llegada'                     => $payload['fecha_llegada'],
            'observacion'                       => $payload['observacion'],
            'direccion_destinatario'            => $dirDestination,
            'identificacion_destinatario'       => '0990281866001',
            'razon_social_destinatario'         => 'TUVAL S.A.',
            'ruta'                              => $payload['ruta'],
            'fecha_emision_doc_sustento'        => '1900-01-01',
        ]);
        return $guia;
    }

    public function buildReservationGuideDetails($data, $guiaId): GpGuiaRemisionReservaDetalle
    {
        $d = new GpGuiaRemisionReservaDetalle();
        $d->fill([
            'guia_id'               => $guiaId,
            'traslado_id'           => $data['id'],
            'cotizacion_id'         => $data['cotizacion_id'],
            'detalle_id'            => $data['detalle_id'],
            'stock_id'              => $data['stock_id'],
            'art_code'              => $data['art_code'],
            'art_descripcion'       => $data['art_descripcion'],
            'art_cod_tres_letras'   => $data['art_cod_tres_letras'],
            'cantidad'              => $data['seleccionado'],
            'art_plan_umedida'      => $data['art_plan_umedida']
        ]);
        return $d;
    }
}

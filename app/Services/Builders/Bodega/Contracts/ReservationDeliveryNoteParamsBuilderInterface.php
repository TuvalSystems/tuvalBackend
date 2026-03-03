<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;

interface ReservationDeliveryNoteParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function buildReservationGuideHeader($payload, GpUsuario $user, array $datavehicle, string $secuencial, string $dirDestination, string $numGuide, string $bodGuide, string $dirOrigin): GpGuiaRemisionReserva;
    public function buildReservationGuideDetails($data, int $guiaId): GpGuiaRemisionReservaDetalle;

    //header to send GP Guide Remision

    public function buildReservationHeaderToGp($guia): array;
    public function buildReservationDestinationToGp($guia): array;
    public function builReservationDetailToGp(GpGuiaRemisionReserva $header, GpGuiaRemisionReservaDetalle $detail, int $indice): array;


    //functions to send GP Ajustment Reservation
    public function buildReservedDeliveryNotePayload(GpGuiaRemisionReserva $guia, string $documento, array $items, string $user, string $hashInterno): array;
     public function buildReservationGuideValidationPayload(array $params,  string $tipoMovimiento, string $tipo): array;
}

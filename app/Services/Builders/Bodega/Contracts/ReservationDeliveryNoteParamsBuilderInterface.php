<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\Compras\Recepciones\GpIngreso;
use App\Models\Compras\Recepciones\GpIngresosDetalle;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use App\Models\Ventas\Reservas\GpGuiaRemisionReservaDetalle;

interface ReservationDeliveryNoteParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function buildReservationGuideHeader(array $payload, GpUsuario $user, array $datavehicle, string $secuencial, string $dirDestination, string $numGuide, string $bodGuide, string $dirOrigin): GpGuiaRemisionReserva;
    public function buildReservationGuideDetails(array $data, int $guiaId): GpGuiaRemisionReservaDetalle;

    //header to send GP Guide Remision

    public function buildReservationHeaderToGp(GpGuiaRemisionReserva $guia): array;
    public function buildReservationDestinationToGp(GpGuiaRemisionReserva $guia): array;
    public function builReservationDetailToGp(GpGuiaRemisionReserva $header, GpGuiaRemisionReservaDetalle $detail, int $indice): array;


    //functions to send GP Ajustment Reservation
    public function buildReservedDeliveryNotePayload(GpGuiaRemisionReserva $guia, string $documento, array $items, string $user, string $hashInterno, ?string $numTranferencia): array;
    public function buildReservationGuideValidationPayload(array $params, string $tipoMovimiento, string $tipo): array;


    public function createRegisterHistortyHeader(GpGuiaRemisionReserva $guia, GpUsuario $user, string $quote): GpIngreso;
    public function createRegisterHistoryDetail(array $items, GpIngreso $ing): GpIngresosDetalle;
}

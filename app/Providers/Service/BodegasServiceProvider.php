<?php

namespace App\Providers\Service;

use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Bodegas\DispatchService;
use App\Services\Bodegas\DeliveryNoteService;
use App\Services\Bodegas\PurchaseReceiptService;
use App\Services\Bodegas\PurchaseReceiptLItemService;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Services\Bodegas\DeliveryNoteCommonService;
use App\Services\Bodegas\LogisticsOperationsService;
use App\Services\Bodegas\ReservationDeliveryNoteService;
use App\Services\Bodegas\TransferService;

class BodegasServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        DispatchServiceInterface::class => DispatchService::class,
        PurchaseReceiptServiceInterface::class => PurchaseReceiptService::class,
        PurchaseReceiptLItemServiceInterface::class => PurchaseReceiptLItemService::class,
        DeliveryNoteServiceInterface::class => DeliveryNoteService::class,
        LogisticsOperationsServiceInterface::class => LogisticsOperationsService::class,
        TransferServiceInterface::class => TransferService::class,
        ReservationDeliveryNoteServiceInterface::class => ReservationDeliveryNoteService::class,
        DeliveryNoteCommonServiceInterface::class => DeliveryNoteCommonService::class,
    ];


    // public function register()
    // {
    //     /** servicio dedicado a las ordenes de despacho */
    //     $this->app->singleton(DispatchServiceInterface::class, DispatchService::class);
    //     //aqui agrego las ordenes de compra recibidas para no crear otro provider

    //     $this->app->singleton(PurchaseReceiptServiceInterface::class, PurchaseReceiptService::class);

    //     /**Servicio para utilizar para los recibidos de Item L  */
    //     $this->app->singleton(PurchaseReceiptLItemServiceInterface::class, PurchaseReceiptLItemService::class);


    //     /** servicio dedicadoa las guias de remision  */
    //     $this->app->singleton(DeliveryNoteServiceInterface::class, DeliveryNoteService::class);


    //     $this->app->singleton(LogisticsOperationsServiceInterface::class, LogisticsOperationsService::class);


    //     /** servicio dedicado para transferencias controller */
    //     $this->app->singleton(TransferServiceInterface::class, TransferService::class);

    //     /** servicio dedicado para transferencias controller */
    //     $this->app->singleton(ReservationDeliveryNoteServiceInterface::class, ReservationDeliveryNoteService::class);


    //     /** servicio dedicado para la creacion de funciones globales para las guias de remision y reservas */
    //     $this->app->singleton(DeliveryNoteCommonServiceInterface::class, DeliveryNoteCommonService::class);


    // }


    /**
     * 3. EL MÉTODO MÁGICO
     * Obtiene los servicios que proporciona este proveedor.
     * Laravel consultará este array ANTES de cargar la clase.
     * Si el servicio solicitado no está aquí, no carga el provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }

}

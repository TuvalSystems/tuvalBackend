<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Bodegas\DispatchService;
use App\Services\Bodegas\DeliveryNoteService;
use App\Services\Bodegas\PurchaseReceiptService;
use App\Services\Bodegas\PurchaseReceiptLItemService;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;

class BodegasServiceProvider extends ServiceProvider
{

    public function register()
    {
        /** servicio dedicado a las ordenes de despacho */
        $this->app->singleton(
            DispatchServiceInterface::class,
            DispatchService::class
        );
        //aqui agrego las ordenes de compra recibidas para no crear otro provider

        $this->app->singleton(
            PurchaseReceiptServiceInterface::class,
            PurchaseReceiptService::class
        );

        /**Servicio para utilizar para los recibidos de Item L  */
        $this->app->bind(
            PurchaseReceiptLItemServiceInterface::class,
            PurchaseReceiptLItemService::class
        );


        /** servicio dedicadoa las guias de remision  */
        $this->app->bind(
            DeliveryNoteServiceInterface::class,
            DeliveryNoteService::class
        );
    }


    public function boot() {}
}

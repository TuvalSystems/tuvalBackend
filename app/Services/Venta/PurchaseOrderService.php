<?php

namespace App\Services\Venta;

use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService implements PurchaseOrderServiceInterface
{

    public function __construct()
    {

    }

    public function getViewPurchaseOrder()
    {
      return DB::table('tuvalapi.view_ordenes_compra')->get();
    }

    public function getOrdersDispatchPending (){
        return DB::table('tuvalapi.vw_OrdenesDespachoPendientesCxC')->get();
    }


}

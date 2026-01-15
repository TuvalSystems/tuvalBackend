<?php

namespace App\Services\Venta;

use App\Models\GpTempCart;
use App\Models\GpTempCartProveedores;
use App\Models\GpTempCartStock;
use App\Models\GpUsuario;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Venta\Contracts\QuotationCartServiceInterface;
use Illuminate\Support\Facades\File;

class QuotationCartService implements QuotationCartServiceInterface
{

    public function __construct(
        protected readonly QuotationBuilderInterface $builder,
        protected readonly QuotationRepositoryInterface $repository
    ) {
    }


    public function createTempItem(int $clientId, string $ptoVta, int $sucursalId, array $article, GpUsuario $user): GpTempCart
    {

        $claseArticulo = data_get($article, 'ART_CLASE_ARTICULO');
        if ($claseArticulo == 'VTA-SERV') {
            $article['ART_TIPO_COMPRA'] = 'SERV';
        }

        if ($claseArticulo == 'VTA-SERV-L') {
            $article['ART_TIPO_COMPRA'] = 'L';
        }
        $maxOrden = $this->repository->computedMaxOrder($clientId, $ptoVta, $sucursalId);
        $nuevoOrden = $maxOrden ? $maxOrden + 1 : 1;
        $cart = $this->builder->formPayloadAddCartTemp($clientId, $ptoVta, $sucursalId, $article, $user, $nuevoOrden);
        $cart->save();
        $stocks = data_get($article, 'stock', []);
        $arrSt = [];
        foreach ($stocks as $st) {
            $stockModel = $this->builder->formPayloadAddCartTempStock($st, $cart->id);
            $stockModel->save();
            $arrSt[] = $stockModel;
        }
        $cart->proveedores = [];
        $cart->stock = $arrSt;
        $cart->imagenes = $this->getImageArticle($cart->ART_CODIGO);

        //campos precargados para uso de front
        $cart->disabled_delete = false;

        return $cart;
    }

    public function getImageArticle($codArt)
    {
        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "images/productos/{$codArt}_{$i}.jpg";
            $pathAbsolute = public_path($path);
            if (File::exists($pathAbsolute)) {
                $images[] = asset($path);
            }
        }
        return $images;
    }

    public function destroyTempItem(int $tempId)
    {
        $cart = GpTempCart::find($tempId);
        $cart->delete();
    }

    // ==========================================
    // MICRO-ACTUALIZACIONES (PATCH)
    // ==========================================

    public function updateDescriptionItemTemp(int $tempId, string $description)
    {
        $cart = GpTempCart::find($tempId);
        $cart->ART_DESCRIPCION = trim(str_replace('  ', ' ', $description));
        $cart->save();
    }

    public function updateDescriptionAlternativeItemTemp(int $tempId, string $description)
    {
        $cart = GpTempCart::find($tempId);
        $cart->ART_DESCRIPCION = trim(str_replace('  ', ' ', $description));
        $cart->ART_DESCRIPCION_ORIGINAL = trim(str_replace('  ', ' ', $description));
        $cart->save();
    }



    public function updateCheckAlternativeTemp(int $tempId, bool $checkAlternative)
    {
        $cart = GpTempCart::find($tempId);
        $cart->CHECK_ALTERNATIVO = $checkAlternative;
        $cart->save();
    }

    public function updateQuantityTemp(int $tempId, float $quantity)
    {
        $cart = GpTempCart::find($tempId);
        $cart->ART_CANTIDAD = $quantity;
        $cart->save();
    }

    public function updatePriceTemp(int $tempId, float $price, float $modifyPrice)
    {
        $cart = GpTempCart::find($tempId);
        $cart->ART_VALOR = $price;
        $cart->modificador = $modifyPrice;
        $cart->save();
    }

    public function updateKgTemp(int $tempId, float $kg)
    {
        $cart = GpTempCart::find($tempId);
        $cart->ART_PESO = $kg;
        $cart->save();
    }


    public function updateStockTemp(int $tempId, int $qty)
    {
        $cart = GpTempCartStock::find($tempId);
        $cart->seleccionado = $qty;
        $cart->save();
    }

    public function updateDiscountTemp(int $tempId, float $discount)
    {
        $cart = GpTempCart::find($tempId);
        $cart->descuento = $discount;
        $cart->save();
    }



    public function updateModifyPriceTemp(int $tempId, float $modifyPrice)
    {
        $cart = GpTempCart::find($tempId);
        $cart->modificador = $modifyPrice;
        $cart->save();
    }


    //proceso de creacion de proveedor temporal


    public function saveProviderQuotationTemp(array $data)
    {
        if (empty($data['proveedores']) || !is_array($data['proveedores'])) {
            return;
        }
        $this->repository->deleteProviderRegisterTemp($data['id']);
        $this->saveProviderQuotationItemTemp($data['proveedores'], $data['id']);
        $this->repository->updateTempCartArtValor($data['id'], $data['ART_VALOR']);
    }



    public function saveProviderQuotationItemTemp($providers, $idTempCart)
    {
        foreach ($providers as $provider) {
            $prov = $this->builder->formPayloadAddCartTempProvider($provider, $idTempCart);
            $prov->save();
        }
    }


}

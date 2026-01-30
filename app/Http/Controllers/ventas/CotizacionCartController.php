<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\QuotationCartServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class CotizacionCartController extends Controller
{
    use JsonResponseTrait;
    public function __construct(
        protected readonly QuotationCartServiceInterface $quoteCart
    ) {
    }


    public function crearRegistroArticuloTemporal(Request $request): JsonResponse
    {
        $request->validate([
            'cliente_id' => 'required',
            'punto_venta_id' => 'required',
            'sucursal_id' => 'required',
            'articulo' => 'required|array'
        ]);
        try {
            $user = GpUsuario::find(Auth::id());
            $cart = DB::transaction(function () use ($request, $user) {
                return $this->quoteCart->createTempItem($request->cliente_id, $request->punto_venta_id, $request->sucursal_id, $request->articulo, $user);
            });
            return $this->successResponse('Item agregado correctamente', $cart, 201);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function eliminarRegistroTemp($id)
    {
        try {
            DB::transaction(fn() => $this->quoteCart->destroyTempItem($id));
            return $this->buildSuccessResponse('Item eliminado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    // ==========================================
    // MICRO-ACTUALIZACIONES (PATCH)
    // ==========================================

    public function actualizarDescripcionRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'descripcion' => 'required|string',
            ]);

            $this->quoteCart->updateDescriptionItemTemp($request->id, $request->descripcion);
            return $this->buildSuccessResponse('Descripcion actualizada correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }
    public function actualizarDescripcionAlternativaRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'descripcion' => 'required|string',
            ]);
            $this->quoteCart->updateDescriptionAlternativeItemTemp($request->id, $request->descripcion);
            return $this->buildSuccessResponse('Descripcion actualizada correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarCheckAlternativo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'CHECK_ALTERNATIVO' => 'required|boolean',
            ]);

            $this->quoteCart->updateCheckAlternativeTemp($request->id, $request->CHECK_ALTERNATIVO);
            return $this->buildSuccessResponse('Check alternativo actualizada correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarCantidadRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'cantidad' => 'required|numeric',
            ]);

            $this->quoteCart->updateQuantityTemp($request->id, $request->cantidad);
            return $this->buildSuccessResponse('Cantidad actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarPrecioRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'precio' => 'required|numeric',
                'modificador' => 'required|numeric',
            ]);

            $this->quoteCart->updatePriceTemp($request->id, $request->precio, $request->modificador);
            return $this->buildSuccessResponse('Precio actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarKgRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'peso' => 'required|numeric',
            ]);

            $this->quoteCart->updateKgTemp($request->id, $request->peso);
            return $this->buildSuccessResponse('Peso actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarStockRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'seleccionado' => 'required|numeric',
            ]);
            $this->quoteCart->updateStockTemp($request->id, $request->seleccionado);
            return $this->buildSuccessResponse('Stock actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function actualizarDescuentoRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'descuento' => 'required|numeric',
            ]);
            $this->quoteCart->updateDiscountTemp($request->id, $request->descuento);
            return $this->buildSuccessResponse('Descuento actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }
    public function actualizarModificadorRegistroTemp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required',
                'modificador' => 'required|numeric',
            ]);
            $this->quoteCart->updateModifyPriceTemp($request->id, $request->modificador);
            return $this->buildSuccessResponse('Precio actualizado correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function guardarProveedoresRegistroTemp(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required',
                'proveedores' => 'required|array',
                'precio_actual' => 'required|numeric',
            ]);
            DB::transaction(fn() => $this->quoteCart->saveProviderQuotationTemp($request->id, $request->proveedores, $request->precio_actual));
            return $this->buildSuccessResponse('Proveedores guardados correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function limpiarRegistroTemp(Request $request){
        try {
            $request->validate([
                'clienteId' => 'required|integer',
                'puntoVentaId' => 'required|string',
                'sucursalId' => 'required|integer',
            ]);
            DB::transaction(fn() => $this->quoteCart->clearCartTemp($request->clienteId, $request->puntoVentaId, $request->sucursalId, Auth::id()));
            return $this->buildSuccessResponse('Datos limpiados correctamente', 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

}

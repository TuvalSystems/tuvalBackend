<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use App\Services\Ventas\Cotizaciones\QuotationQueryService;
use Illuminate\Foundation\Http\FormRequest;

class ListarCotizacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pv_id' => 'required_without:puntodeventa_id|nullable|integer|min:1',
            'puntodeventa_id' => 'required_without:pv_id|nullable|string|max:20',
            'estado' => 'nullable|in:pendientes,caducado,prospectos,procesadas,rechazadas',
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d',
            'cotizacion_filtro' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:150',
            'page' => 'sometimes|integer|min:1',
            'perPage' => 'sometimes|integer|min:1|max:100',
            'sortField' => 'nullable|in:' . implode(',', QuotationQueryService::SORT_FIELDS),
            'sortOrder' => 'nullable|in:asc,desc',
        ];
    }
}

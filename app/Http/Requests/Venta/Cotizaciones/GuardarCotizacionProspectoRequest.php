<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class GuardarCotizacionProspectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Es un borrador de prospecto: no requiere cliente, vendedor ni reservas.
        return [
            'prospecto' => ['required', 'array'],
            'prospecto.CLI_RUC' => ['required', 'string', 'max:15'],
            'prospecto.CLI_RAZON_SOCIAL' => ['required', 'string', 'max:65'],
            'prospecto.CLI_TELEFONO_1' => ['nullable', 'string', 'max:21'],
            'prospecto.CLI_DIRECCION' => ['nullable', 'string', 'max:122'],
            'prospecto.CLI_CORREO_ELECT_SRI' => ['nullable', 'string', 'max:500'],
            'pv_id' => ['nullable', 'integer', 'exists:gp_punto_ventas,id'],
            'PTO_VTA_ID' => ['required_without:pv_id', 'nullable', 'string', 'exists:gp_punto_ventas,PTO_VTA_ID'],
            'bodega_facturacion' => ['required', 'string'],
            'tipo_despacho_id' => ['nullable', 'integer', 'exists:tipo_despachos,id'],
            'despacho' => ['nullable', 'in:Retiro en bodega,Despacho en ruta'],
            'DESC_FORMA_PAGO_CODIGO' => ['nullable', 'string', 'max:50'],
            'porcentage_descuentooriginal' => ['nullable', 'numeric', 'between:0,100'],
            'porcentage_descuento' => ['nullable', 'numeric', 'between:0,100'],
            'nota_interna' => ['nullable', 'string', 'max:300'],
            'nota_cliente' => ['nullable', 'string', 'max:300'],
            'orden_de_compra' => ['nullable', 'string', 'max:20'],
            'cotizacion' => ['required', 'array', 'min:1'],
            'cotizacion.*' => ['required', 'array'],
            'cotizacion.*.art_id' => ['required', 'integer', 'exists:gp_articulos,id'],
            'cotizacion.*.ART_CODIGO' => ['required', 'string'],
            'cotizacion.*.ART_DESCRIPCION' => ['required', 'string'],
            'cotizacion.*.ART_DESCRIPCION_ORIGINAL' => ['nullable', 'string', 'max:300'],
            'cotizacion.*.ART_CODIGO_TRES_LETRAS' => ['nullable', 'string', 'max:3'],
            'cotizacion.*.ART_PLAN_UMEDIDA' => ['nullable', 'string', 'max:30'],
            'cotizacion.*.ART_MARCA' => ['nullable', 'string', 'max:300'],
            'cotizacion.*.ART_MATERIAL' => ['nullable', 'string', 'max:300'],
            'cotizacion.*.ART_TIPO_COMPRA' => ['nullable', 'string', 'max:5'],
            'cotizacion.*.ART_CLASE_ARTICULO' => ['nullable', 'string', 'max:30'],
            'cotizacion.*.ART_CANTIDAD' => ['required', 'numeric', 'gt:0'],
            'cotizacion.*.ART_DECIMAL_CANTIDAD' => ['required', 'integer', 'between:0,6'],
            'cotizacion.*.ART_VALOR' => ['required', 'numeric', 'min:0'],
            'cotizacion.*.ART_VALOR_ORIGINAL' => ['nullable', 'numeric', 'min:0'],
            'cotizacion.*.ART_PESO' => ['nullable', 'numeric', 'min:0'],
            'cotizacion.*.ART_STOCK' => ['nullable', 'numeric'],
            'cotizacion.*.ART_COSTO_PROMEDIO' => ['nullable', 'numeric'],
            'cotizacion.*.ART_IMP_VTA' => ['required', 'numeric', 'between:0,100'],
            'cotizacion.*.descuento' => ['required', 'numeric', 'between:0,100'],
            'cotizacion.*.descuento_original' => ['nullable', 'numeric', 'between:0,100'],
            'cotizacion.*.modificador' => ['nullable', 'numeric'],
            'cotizacion.*.CHECK_ALTERNATIVO' => ['nullable', 'boolean'],
            'cotizacion.*.valor_original_l' => ['nullable', 'numeric'],
            'cotizacion.*.validador_l' => ['nullable', 'boolean'],
            'cotizacion.*.cod_categoria' => ['nullable', 'string'],
            'cotizacion.*.cod_linea' => ['nullable', 'string'],
            'cotizacion.*.cod_clase' => ['nullable', 'string'],
            'cotizacion.*.resultado_configuracion_item_l' => ['nullable', 'string'],
        ];
    }
}

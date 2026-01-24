<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class ValidateStoreQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }


    /**
     * Esto es CRÍTICO cuando usas FormData + JSON.stringify
     * Convierte el string 'items' a un array PHP real antes de validar.
     */
    public function prepareForValidation()
    {

        if ($this->has('items')) {
            $this->merge(['items' => json_decode($this->items, true)]);
        }
        // Conversión de booleanos que vienen como string "true"/"false" o "1"/"0"
        $this->merge([
            'factura_posterior_despacho' => filter_var($this->factura_posterior_despacho, FILTER_VALIDATE_BOOLEAN),
            'factura_a_demanda'          => filter_var($this->factura_a_demanda, FILTER_VALIDATE_BOOLEAN),
            'factura_siguiente_mes'      => filter_var($this->factura_siguiente_mes, FILTER_VALIDATE_BOOLEAN),
        ]);

        if ($this->registro_copia === 'false' || $this->registro_copia === 'null') {
            $this->merge(['registro_copia' => null]);
        }
        if ($this->cotizacion_id === 'false' || $this->cotizacion_id === 'null') {
            $this->merge(['cotizacion_id' => null]);
        }
         if ($this->fecha_entrega === 'false' || $this->fecha_entrega === 'null') {
            $this->merge(['fecha_entrega' => null]);
        }
    }
    public function rules()
    {
        return [
      // -- datos del cliente
            'cliente_id'                => ['required', 'integer', 'exists:gp_clientes,id'], // Corregido sintaxis array
            'ruc'                       => ['required', 'string', 'max:20'],
            'razon_social'              => ['required', 'string', 'max:255'],
            'correo_electronico_sri'    => ['required', 'string', 'email', 'max:255'],
            'vendedor_id'               => ['required', 'string'],
            'vendedor_nombres'          => ['nullable', 'string', 'max:255'],
            'vendedor_apellidos'        => ['nullable', 'string', 'max:255'],
            // -- logica de ubicacion y bodegas despacho
            'punto_venta_id'            => ['required', 'string'],
            'bodega_facturacion'        => ['required', 'string'],
            'tipo_entrega'              => ['required', 'in:retiro,despacho'],
            'fecha_entrega'             => ['required_if:tipo_entrega,despacho'],
            'direccion_facturacion_id'  => ['required'],
            'direccion_entrega_id'      => ['required_if:tipo_entrega,despacho'],

            // --- Booleanos (Facturación) ---
            'factura_posterior_despacho' => ['required', 'boolean'],
            'factura_a_demanda'          => ['required', 'boolean'],
            'factura_siguiente_mes'      => ['required', 'boolean'],

            'registro_copia'             => ['nullable', 'integer'],
            // -- pago
            'condicion_pago_id'          => ['required', 'string'],
            'condicion_pago_original_id' => ['required', 'string'],
            'forma_pago_id'              => ['required', 'string'],

            // -- notas y textos
            'nota_interna'              => ['nullable', 'string', 'max:1000'], // CORREGIDO: decía 'nota_internas' (plural)
            'nota_cliente'              => ['nullable', 'string', 'max:300'],
            'nota_operaciones'          => ['nullable', 'string', 'max:300'],
            'orden_de_compra'           => ['nullable', 'string', 'max:20'],

            // -- calculo
            'subtotal'                  => ['required', 'numeric', 'min:0'],
            'descuento'                 => ['nullable', 'numeric'],
            'iva'                       => ['required', 'numeric', 'min:0'],
            'total'                     => ['required', 'numeric', 'min:0'],
            'modificador_global'        => ['nullable', 'numeric'],

            'peso'                      => ['nullable', 'numeric'],
            'descuento_articulo'        => ['nullable', 'numeric'],
            'descuento_original'        => ['nullable', 'numeric'],
            // --- VALIDACIÓN DE ITEMS ---
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.ART_CODIGO'        => ['required'],
            'items.*.ART_CANTIDAD'      => ['required', 'numeric', 'min:0.00001'],
            'items.*.ART_VALOR'         => ['required', 'numeric', 'min:0'],
            'items.*.stock'             => ['nullable', 'array'],
            'items.*.proveedores'       => ['nullable', 'array'],
            // --- VALIDACIÓN DE IMÁGENES (AGREGADO) ---
            // Validamos 'imagenes' porque así lo llamaste en el formData.append
            'imagenes'                  => ['nullable', 'array'],
            'imagenes.*'                => ['file', 'image', 'mimes:jpeg,png,jpg', 'max:5120'], // Máx 5MB
        ];
    }


    public function attributes()
    {
       return [
            'cliente_id'             => 'cliente',
            'items'                  => 'artículos',
            'items.*.ART_CANTIDAD'   => 'cantidad del artículo',
            // 'direccion_entrega_id'   => 'dirección de entrega',
            // 'fecha_entrega'          => 'fecha de entrega',
            // 'nota_interna'           => 'nota interna',

            // Atributos para las imágenes
            'imagenes'               => 'imágenes',
            'imagenes.*'             => 'imagen',
        ];
    }
}

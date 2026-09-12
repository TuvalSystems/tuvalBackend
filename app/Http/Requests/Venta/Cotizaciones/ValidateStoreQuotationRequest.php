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

        $fieldsToNull = [
            'registro_copia',
            'cotizacion_id',
            'cotizacion_codigo',
            'fecha_entrega',
            'orden_de_compra',
            'nota_interna',
            'nota_cliente',
            'nota_operaciones',
            'forma_pago_id',
            'correo_electronico_sri'
        ];

        foreach ($fieldsToNull as $field) {
            $value = $this->$field;
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === 'false' || $value === 'null' || $value === '' || $value === 'undefined') {
                $this->merge([$field => null]);
            } else {
                // Aseguramos que el valor limpio se use en la validación
                $this->merge([$field => $value]);
            }
        }
    }
    public function rules()
    {
        return [
            'cotizacion_id'             => ['nullable', 'integer', 'exists:gp_cotizacions,id'],
            'cotizacion_codigo'         => ['nullable', 'string', 'max:50'],
            'mode'                      => ['required'],
            // -- datos del cliente
            'cliente_id'                => ['required', 'integer', 'exists:gp_clientes,id'], // Corregido sintaxis array
            'ruc'                       => ['required', 'string', 'max:20'],
            'razon_social'              => ['required', 'string', 'max:255'],
            'correo_electronico_sri' => [
                'nullable', // 1. Permite que sea null o vacío
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    // 2. Si el valor es nulo o vacío, no validamos nada (retornamos éxito)
                    $value = (string) $value;
                    if (trim($value) === '') {
                        return;
                    }

                    // Separamos por punto y coma (;) o coma (,)
                    $emails = preg_split('/[;]/', $value, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($emails as $email) {
                        $emailLimpio = trim($email);

                        // Validación estricta
                        if (!filter_var($emailLimpio, FILTER_VALIDATE_EMAIL)) {
                            $fail('El correo "' . $emailLimpio . '" no es válido. Asegúrese de separar múltiples correos usando SOLO punto y coma (;).');
                        }
                    }
                },
            ],
            'vendedor_id'                   => ['required', 'string'],
            'vendedor_nombres'              => ['nullable', 'string', 'max:255'],
            'vendedor_apellidos'            => ['nullable', 'string', 'max:255'],
            // -- logica de ubicacion y bodegas despacho
            'punto_venta_id'                => ['required', 'string'],
            'bodega_facturacion'            => ['required', 'string'],
            'tipo_entrega'                  => ['required', 'in:retiro,despacho'],
            'fecha_entrega'                 => ['required_if:tipo_entrega,despacho'],
            'direccion_facturacion_id'      => ['required'],
            'direccion_entrega_id'          => ['required_if:tipo_entrega,despacho'],

            // --- Booleanos (Facturación) ---
            'factura_posterior_despacho'    => ['required', 'boolean'],
            'factura_a_demanda'             => ['required', 'boolean'],
            'factura_siguiente_mes'         => ['required', 'boolean'],

            'registro_copia'                => ['nullable', 'integer'],
            // -- pago
            'condicion_pago_id'             => ['required', 'string'],
            'condicion_pago_original_id'    => ['required', 'string'],
            'forma_pago_id'                 => ['required', 'string'],

            // -- notas y textos
            'nota_interna'                  => ['nullable', 'string', 'max:1000'], // CORREGIDO: decía 'nota_internas' (plural)
            'nota_cliente'                  => ['nullable', 'string', 'max:300'],
            'nota_operaciones'              => ['nullable', 'string', 'max:300'],
            'orden_de_compra'               => ['nullable', 'string', 'max:20'],

            // -- calculo
            'subtotal'                      => ['required', 'numeric', 'min:0'],
            'descuento'                     => ['nullable', 'numeric'],
            'iva'                           => ['required', 'numeric', 'min:0'],
            'total'                         => ['required', 'numeric', 'min:0'],
            'modificador_global'            => ['nullable', 'numeric'],

            'peso'                          => ['nullable', 'numeric'],
            'descuento_articulo'            => ['nullable', 'numeric'],
            'descuento_original'            => ['nullable', 'numeric'],
            // --- VALIDACIÓN DE ITEMS -    --
            'items'                         => ['required', 'array', 'min:1'],

            'items.*.id'                    => ['nullable', 'integer'],
            'items.*.detalle_id'            => ['nullable', 'integer'],
            'items.*.cotizacion_id'         => ['nullable', 'integer', 'exists:gp_cotizacions,id'],
            'items.*.id_temp'               => ['nullable', 'string', 'max:100'],

            'items.*.ART_CODIGO'            => ['required'],
            'items.*.ART_CANTIDAD'          => ['required', 'numeric', 'min:0.00001'],
            'items.*.ART_VALOR'             => ['required', 'numeric', 'min:0'],
            'items.*.ART_VALOR_ORIGINAL'    => ['nullable', 'numeric'],
            'items.*.ART_DESCUENTO'         => ['nullable', 'numeric'],
            'items.*.ART_IVA'               => ['nullable', 'numeric'],
            'items.*.ART_PESO'              => ['nullable', 'numeric'],

            'items.*.ART_TIPO_COMPRA'           => ['nullable', 'string'],
            'items.*.ART_CODIGO_TRES_LETRAS'    => ['nullable', 'string'],
            'items.*.ART_DESCRIPCION'           => ['nullable', 'string'],
            'items.*.ART_DESCRIPCION_ORIGINAL'  => ['nullable', 'string'],
            'items.*.ART_PLAN_UMEDIDA'          => ['nullable', 'string'],
            'items.*.CHECK_ALTERNATIVO'         => ['required', 'boolean'],
            'items.*.ART_DECIMAL_CANTIDAD'      => ['nullable', 'numeric'],
            'items.*.ART_STOCK'                 => ['nullable', 'numeric'],
            'items.*.ART_COSTO_PROMEDIO'        => ['nullable', 'numeric'],

            'items.*.descuento'             => ['nullable', 'numeric'],
            'items.*.descuento_original'    => ['nullable', 'numeric'],
            'items.*.subtotal'              => ['nullable', 'numeric'],
            'items.*.modificador'           => ['nullable', 'numeric'],

            'items.*.cod_categoria'         => ['nullable', 'string'],
            'items.*.cod_linea'             => ['nullable', 'string'],
            'items.*.cod_clase'             => ['nullable', 'string'],
            'items.*.nivel'                 => ['nullable', 'integer'],

            'items.*.tipo'                      => ['nullable', 'string', 'max:50'],
            'items.*.ART_IMP_VTA'               => ['nullable', 'numeric'],
            'items.*.ART_MARCA'                 => ['nullable', 'string', 'max:100'],
            'items.*.ART_MATERIAL'              => ['nullable', 'string', 'max:100'],
            'items.*.valor_original_l'          => ['nullable', 'numeric'],
            'items.*.imagenes'                  => ['nullable', 'array'],
            'items.*.imagenes.*'                => ['nullable', 'string'],


            'items.*.stock'                 => ['nullable', 'array'],
            'items.*.proveedores'           => ['nullable', 'array'],

            // --- VALIDACIÓN DE IMÁGENES (AGREGADO) ---
            // Validamos 'imagenes' porque así lo llamaste en el formData.append
            'imagenes_nuevas'           => ['nullable', 'array'],
            'imagenes_nuevas.*'         => ['file', 'image', 'mimes:jpeg,png,jpg', 'max:5120'], // Máx 5MB


            'imagenes_existentes'       => ['nullable', 'array'],
            'imagenes_existentes.*'     => ['string'],
            'origen'                    => ['required', 'string'],

            //almacenamiento de pdfs
            'pdfs_nuevos'                => ['nullable', 'array'],
            'pdfs_nuevos.*'              => ['file', 'mimetypes:application/pdf,application/x-google-chrome-pdf,application/octet-stream', 'max:10120'],
            'pdfs_existentes'            => ['nullable', 'array'],
            'pdfs_existentes.*'          => ['string'],
            'proyecto'                   => ['nullable', 'integer']
        ];
    }


    public function attributes()
    {
        return [
            'cliente_id'            => 'cliente',
            'items'                 => 'artículos',
            'items.*.ART_CANTIDAD'  => 'cantidad del artículo',
            'imagenes_nuevas'       => 'nuevas imágenes',
            'imagenes_nuevas.*'     => 'nueva imagen',
            'cotizacion_id'         => 'ID de cotización',
        ];
    }
}

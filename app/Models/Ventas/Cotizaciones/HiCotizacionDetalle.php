<?php

namespace App\Models\Ventas\Cotizaciones;

use App\Models\GpCotizacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HiCotizacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'hi_cotizacion_detalles';

    protected $fillable = [
        'hi_cot_id',
        'cot_id',
        'det_id',
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_DESCRIPCION_ORIGINAL',
        'CHECK_ALTERNATIVO',
        'ART_CODIGO_TRES_LETRAS',
        'ART_DECIMAL_CANTIDAD',
        'ART_PLAN_UMEDIDA',
        'ART_MARCA',
        'ART_MATERIAL',
        'ART_PESO',
        'ART_TIPO_COMPRA',
        'ART_VALOR',
        'ART_IVA',
        'ART_VALOR_ORIGINAL',
        'valor_original_l',
        'validador_l',
        'ART_DESCUENTO',
        'ART_CANTIDAD',
        'ART_STOCK',
        'ART_IMP_VTA',
        'ART_COSTO_PROMEDIO',
        'descuento',
        'descuento_original',
        'modificador',
    ];

    protected $casts = [
        'hi_cot_id'            => 'integer',
        'cot_id'               => 'integer',
        'det_id'               => 'integer',
        'CHECK_ALTERNATIVO'    => 'boolean',
        'ART_DECIMAL_CANTIDAD' => 'integer',
        'ART_PESO'             => 'decimal:2',
        'ART_VALOR'            => 'decimal:4',
        'ART_IVA'              => 'decimal:2',
        'ART_VALOR_ORIGINAL'   => 'decimal:4',
        'valor_original_l'     => 'decimal:4',
        'ART_DESCUENTO'        => 'decimal:4',
        'ART_CANTIDAD'         => 'decimal:2',
        'ART_STOCK'            => 'decimal:2',
        'ART_IMP_VTA'          => 'decimal:2',
        'ART_COSTO_PROMEDIO'   => 'decimal:2',
        'descuento'            => 'decimal:2',
        'descuento_original'   => 'decimal:2',
        'validador_l'          => 'boolean'
    ];

    public function hiCotizacion()
    {
        return $this->belongsTo(HiCotizacion::class, 'hi_cot_id');
    }

    public function cotizacionOriginal()
    {
        return $this->belongsTo(GpCotizacion::class, 'cot_id');
    }
}

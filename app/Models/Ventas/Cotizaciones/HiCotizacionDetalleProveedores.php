<?php

namespace App\Models\Ventas\Cotizaciones;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HiCotizacionDetalleProveedores extends Model
{
    use HasFactory;

    protected $table = 'hi_cotizacion_detalle_proveedores';

    protected $fillable = [
        'hi_cot_id',
        'hi_cot_det_id',
        'cot_id',
        'det_id',
        'ID_PROVEEDOR',
        'DESCRIPCION_ARTICULO',
        'FECHA_ENTREGA',
        'CANTIDAD',
        'COSTO_UNITARIO',
        'IMPUESTO',
        'TOTAL',
        'TOTALCONIMPUESTO',
        'MARGEN',
        'NOTA',
        'proveedor_entrega'
    ];

    protected $casts = [
        'hi_cot_id'                 => 'integer',
        'hi_cot_det_id'             => 'integer',
        'cot_id'                    => 'integer',
        'det_id'                    => 'integer',
        'ID_PROVEEDOR'              => 'integer',
        'FECHA_ENTREGA'             => 'date',
        'CANTIDAD'                  => 'decimal:2',
        'COSTO_UNITARIO'            => 'decimal:2',
        'IMPUESTO'                  => 'decimal:2',
        'TOTAL'                     => 'decimal:2',
        'TOTALCONIMPUESTO'          => 'decimal:2',
        'MARGEN'                    => 'decimal:2',
    ];

    public function hiCotizacion()
    {
        return $this->belongsTo(HiCotizacion::class, 'hi_cot_id');
    }

    public function hiCotizacionDetalle()
    {
        return $this->belongsTo(HiCotizacionDetalle::class, 'hi_cot_det_id');
    }

    public function cotizacionOriginal()
    {
        return $this->belongsTo(GpCotizacion::class, 'cot_id');
    }

    public function detalleOriginal()
    {
        return $this->belongsTo(GpCotizacionDetalle::class, 'det_id');
    }
}

<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class GpDevolucionesDetalle extends Model
{
    use HasFactory;

    protected $table = 'gp_devoluciones_detalles';
    protected $appends = ['pending_received_devolution'];
    protected $fillable = [
        'devolucion_id',
        'detalle_id',
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
        'ART_DESCUENTO',
        'ART_CANTIDAD',
        'ART_STOCK',
        'ART_IMP_VTA',
        'ART_COSTO_PROMEDIO',
        'descuento',
        'descuento_original',
        'modificador',
        'seleccionado',
        'disponible',
        'recibido',
        'devuelto',
        'compartimiento',
        'USERID',
        'despachado_parcial'
    ];




    protected function getPendingReceivedDevolutionAttribute()
    {
        $select = (float) $this->seleccionado;
        $devuelto = (float) $this->devuelto;
        $resultado = max(0, $select - $devuelto);
        return number_format($resultado, 2, '.', '');
    }
}

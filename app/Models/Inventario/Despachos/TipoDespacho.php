<?php

namespace App\Models\Inventario\Despachos;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TipoDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'descripcion',
        'observacion',
        'usuario_ingreso_id',
        'fecha_ingreso',
        'usuario_modifica_id',
        'fecha_modificacion',
        'responsable_pago',
        'estado',
    ];

    protected $casts = [
        'descripcion'           => 'string',
        'observacion'           => 'string',
        'usario_ingreso_id'     => 'integer',
        'fecha_ingreso'         => 'datetime:Y-m-d H:i:s',
        'usuario_modifica_id'   => 'integer',
        'fecha_modificacion'    => 'datetime:Y-m-d H:i:s',
        'estado'                => 'boolean',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];



    public function puntosVenta(): BelongsToMany
    {
        return $this->belongsToMany(
            GpPuntoVenta::class,
            'tipo_despacho_punto_ventas',
            'tipo_despacho_id',
            'pv_id'
        )->withTimestamps();
    }
}

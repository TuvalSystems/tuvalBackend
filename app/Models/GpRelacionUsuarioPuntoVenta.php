<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionUsuarioPuntoVenta
 *
 * @property int $id
 * @property string $USER_PTO_VTA_PUNTO_VENTA
 * @property string $USER_PTO_VTA_USUARIO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GpUsuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereUSERPTOVTAPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereUSERPTOVTAUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereUpdatedAt($value)
 */
class GpRelacionUsuarioPuntoVenta extends Model
{
    use HasFactory;

    protected $fillable  = [
        'id',
        'user_id',
        'pv_id',
        'orden',
        'USER_PTO_VTA_PUNTO_VENTA',
        'USER_PTO_VTA_USUARIO'
    ];


    public function usuario()
    {
        return $this->belongsTo(GpUsuario::class, 'USER_PTO_VTA_USUARIO');
    }

    public function puntoVenta()
    {
        return $this->belongsTo(GpPuntoVenta::class, 'pv_id');
    }
}

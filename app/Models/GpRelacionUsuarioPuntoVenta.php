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
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereUSERPTOVTAPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereUSERPTOVTAUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioPuntoVenta whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpRelacionUsuarioPuntoVenta extends Model
{
    use HasFactory;


    public function usuario(){
        return $this->belongsTo(GpUsuario::class,'USER_PTO_VTA_USUARIO');
    }
}

<?php

namespace App\Models\Ventas\Aprobaciones;

use App\Models\Admin\Usuarios\GpUsuario;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Aprobaciones\GpReglaAprobacion
 *
 * @property int $id
 * @property int|null $regla_negocio_id
 * @property int|null $user_autorizador_id
 * @property int|null $tipo_vendedor_id
 * @property int|null $zona_id
 * @property string|null $descuento_min
 * @property string|null $descuento_max
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GpUsuario|null $usuarioAutorizador
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereDescuentoMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereDescuentoMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereReglaNegocioId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereTipoVendedorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereUserAutorizadorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaAprobacion whereZonaId($value)
 * @mixin \Eloquent
 */
class GpReglaAprobacion extends Model
{
    use HasFactory;
    protected $table = 'gp_regla_aprobacions';
    protected $fillable = [
        'regla_negocio_id',
        'descuento_min',
        'descuento_max',
        'tipo_vendedor',
        'zona',
        'user_autorizador_id'
    ];

    protected $casts = [
        'descuento_min' => 'decimal:4',
        'descuento_max' => 'decimal:4',
    ];


    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function usuarioAutorizador()
    {
        return $this->belongsTo(GpUsuario::class, 'user_autorizador_id');
    }
}

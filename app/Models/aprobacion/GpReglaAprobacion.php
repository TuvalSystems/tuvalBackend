<?php

namespace App\Models\aprobacion;

use App\Models\GpUsuario;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

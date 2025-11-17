<?php

namespace App\Models\aprobacion;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpReglaAprobacion extends Model
{
    use HasFactory;

     protected $fillable = [
        'regla_negocio_id',
        'descuento_min',
        'descuento_max',
        'tipo_vendedor',
        'zona',
        'user_autorizador_id'
    ];

    protected $casts = [
        'descuento_min' => 'float',
        'descuento_max' => 'float'
    ];


     protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}

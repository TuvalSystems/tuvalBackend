<?php

namespace App\Models\aprobacion;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpReglaNegocio extends Model
{
    use HasFactory;

    protected $casts = [
        'fecha_ingreso' => 'datetime:Y-m-d H:i:s',
    ];


    protected $fillable = [
        'perfil_aprobador',
        'nombre',
        'descripcion',
        'observacion1',
        'observacion2',
        'funcion',
        'estado',
        'fecha_ingreso',
        'user_ingreso_id',
        'fecha_modificacion',
        'user_modificacion_id',
    ];


    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

}

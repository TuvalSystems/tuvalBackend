<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpDevolucionDocument extends Model
{
    use HasFactory;

    protected $fillable =[
        'devolucion_id',
        'devolucion',
        'id_movimiento',
        'documento_gp',
    ];
}

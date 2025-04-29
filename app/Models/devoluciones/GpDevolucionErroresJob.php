<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpDevolucionErroresJob extends Model
{
    use HasFactory;

    protected $table = 'gp_devolucion_errores_jobs';
    protected $fillable = [
        'devolucion_id',
        'criterio_id',
        'proceso',
        'mensaje',
        'created_at',
        'updated_at'
    ];
}

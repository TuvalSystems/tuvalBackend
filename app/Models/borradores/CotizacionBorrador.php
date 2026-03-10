<?php

namespace App\Models\borradores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionBorrador extends Model
{
    use HasFactory;
    protected $table = 'cotizacion_borradores';

    protected $fillable = [
        'user_id',
        'draft_id',
        'punto_venta_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}

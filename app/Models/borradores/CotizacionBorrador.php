<?php

namespace App\Models\borradores;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class CotizacionBorrador extends Model
{
    use HasFactory, Prunable;
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


    /**
     * esto lo usamos como comando mode:prune que define el tiempo de vida de los borradores
     * se elimina pasando los 60 dias de inactividad,
     * definimos el tiempo en prueneable y declaramos el comando en el kernel
     */
    public function prunable(): Builder
    {
       return static::query()->where('updated_at', '<', now()->subDays(60));
    }
}

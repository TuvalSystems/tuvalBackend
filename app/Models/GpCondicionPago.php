<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCondicionPago
 *
 * @property int $id
 * @property int $COND_DIAS
 * @property string $COND_ID
 * @property string $COND_TIPO_DESCUENTO
 * @property string $COND_VALOR
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereCONDDIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereCONDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereCONDTIPODESCUENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereCONDVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCondicionPago whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCondicionPago extends Model
{
    use HasFactory;

    protected $fillable = [
        'COND_DIAS',
        'COND_ID',
        'COND_TIPO_DESCUENTO',
        'COND_VALOR',
        'es_cheque',
        'estado',
    ];

    protected $casts = [
        'es_cheque' => 'boolean'
    ];
}

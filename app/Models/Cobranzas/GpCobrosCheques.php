<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * App\Models\Cobranzas\GpCobrosCheques
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $banco
 * @property string $cuenta
 * @property string $cheque
 * @property string $monto
 * @property string|null $comentario
 * @property string $fecha_emision
 * @property string $fecha_vencimiento
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereBanco($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereCheque($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereComentario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereCuenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereFechaEmision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereFechaVencimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosCheques whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosCheques extends Model
{
    use HasFactory;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpMotivosDevolucion
 *
 * @property int $id
 * @property string|null $motivo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion whereMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpMotivosDevolucion whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpMotivosDevolucion extends Model
{
    use HasFactory;
}

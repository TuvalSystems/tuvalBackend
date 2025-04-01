<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpUnidadMedida
 *
 * @property int $id
 * @property string $UMEDIDA_PLAN
 * @property string $UMEDIDA_BASE
 * @property int $UMEDIDA_CANTIDAD_DECIMAL
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida whereUMEDIDABASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida whereUMEDIDACANTIDADDECIMAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida whereUMEDIDAPLAN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUnidadMedida whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpUnidadMedida extends Model
{
    use HasFactory;
}

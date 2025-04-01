<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpVehiculo
 *
 * @property int $id
 * @property string|null $nombre
 * @property string|null $marca
 * @property string|null $modelo
 * @property string|null $placa
 * @property int|null $transportista_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereMarca($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereModelo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo wherePlaca($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVehiculo whereTransportistaId($value)
 */
class GpVehiculo extends Model
{
    use HasFactory;
}

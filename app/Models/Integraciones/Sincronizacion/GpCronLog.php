<?php

namespace App\Models\Integraciones\Sincronizacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Integraciones\Sincronizacion\GpCronLog
 *
 * @property int $id
 * @property string $metodo
 * @property string $fecha
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $data
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog whereMetodo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCronLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCronLog extends Model
{
    use HasFactory;

    protected $table ='gp_cron_logs';
    protected $fillable = ['metodo','fecha','data'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpTransportista
 *
 * @property int $id
 * @property string|null $nombre
 * @property string|null $tipo_identificacion
 * @property string|null $identificacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereIdentificacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereTipoIdentificacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $tipo_licencia
 * @property string|null $direccion
 * @property string|null $telefono
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereDireccion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereTelefono($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereTipoLicencia($value)
 * @property int|null $vehiculo_id
 * @method static \Illuminate\Database\Eloquent\Builder|GpTransportista whereVehiculoId($value)
 */
class GpTransportista extends Model
{
    use HasFactory;
}

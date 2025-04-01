<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpProspecto
 *
 * @property int $id
 * @property string|null $CLI_RUC
 * @property string|null $CLI_RAZON_SOCIAL
 * @property string|null $CLI_DIRECCION
 * @property string|null $CLI_CORREO_ELECT_SRI
 * @property string|null $CLI_TELEFONO_1
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereCLICORREOELECTSRI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereCLIDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereCLIRAZONSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereCLIRUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereCLITELEFONO1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProspecto whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpProspecto extends Model
{
    use HasFactory;
}

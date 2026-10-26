<?php

namespace App\Models\Admin\Ubicaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Admin\Ubicaciones\GpPais
 *
 * @property int $id
 * @property string $PAIS_CODIGO
 * @property string $PAIS_DESCRIPCION
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais wherePAISCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais wherePAISDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPais whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpPais extends Model
{
    use HasFactory;
}

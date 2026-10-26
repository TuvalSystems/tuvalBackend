<?php

namespace App\Models\Admin\Ubicaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Admin\Ubicaciones\GpTerritorio
 *
 * @property int $id
 * @property string $TERRITORIO_CODIGO
 * @property string $TERRITORIO_DESCRIPCION
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio whereTERRITORIOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio whereTERRITORIODESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTerritorio whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTerritorio extends Model
{
    use HasFactory;
}

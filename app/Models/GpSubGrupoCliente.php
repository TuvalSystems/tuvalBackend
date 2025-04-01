<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpSubGrupoCliente
 *
 * @property int $id
 * @property string $SUBGRUPO_CODIGO
 * @property string|null $SUBGRUPO_DESCRIPCION
 * @property string|null $GRUPO_CODIGO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente whereGRUPOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente whereSUBGRUPOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente whereSUBGRUPODESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpSubGrupoCliente whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpSubGrupoCliente extends Model
{
    use HasFactory;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpGrupoCliente
 *
 * @property int $id
 * @property string $GRUPO_CODIGO
 * @property string|null $GRUPO_DESCRIPCION
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente whereGRUPOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente whereGRUPODESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGrupoCliente whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpGrupoCliente extends Model
{
    use HasFactory;
}

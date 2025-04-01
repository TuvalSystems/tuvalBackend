<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpClaseCliente
 *
 * @property int $id
 * @property string $CLASS_CODIGO
 * @property string|null $CLASS_DESCRIPCION
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente whereCLASSCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente whereCLASSDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClaseCliente whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpClaseCliente extends Model
{
    use HasFactory;
}

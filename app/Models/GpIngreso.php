<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpIngreso
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $USERID
 * @property string $tipo
 * @property string|null $documento
 * @property string|null $GUI_REM_GUIA_REMISION
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereGUIREMCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereSucursalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereUserId($value)
 * @mixin \Eloquent
 */
class GpIngreso extends Model
{
    use HasFactory;
}

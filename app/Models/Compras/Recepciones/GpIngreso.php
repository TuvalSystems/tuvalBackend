<?php

namespace App\Models\Compras\Recepciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Compras\Recepciones\GpIngreso
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $USERID
 * @property int|null $sucursal_id
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
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngreso whereGUIREMGUIAREMISION($value)
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

    protected $fillable = [
        'user_id',
        'USERID',
        'tipo',
        'documento',
        'GUI_REM_GUIA_REMISION',
    ];
}

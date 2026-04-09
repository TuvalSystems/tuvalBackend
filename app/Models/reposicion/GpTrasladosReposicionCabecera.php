<?php

namespace App\Models\reposicion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\reposicion\GpTrasladosReposicionCabecera
 *
 * @property int $id
 * @property string $Documento
 * @property string $Bodega_Origen
 * @property string $Bodega_Destino
 * @property string $Codigo_Motivo
 * @property string|null $Motivo
 * @property string $Fecha_Documento
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereCodigoMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereFechaDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTrasladosReposicionCabecera extends Model
{
    use HasFactory;

    protected $fillable = [
        'Documento',
        'Bodega_Origen',
        'Bodega_Destino',
        'Codigo_Motivo',
        'Fecha_Documento',
        'Motivo',
    ];
}

<?php

namespace App\Models\reposicion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\reposicion\GpTrasladosReposicion
 *
 * @property int $id
 * @property int $cabecera_id
 * @property string|null $Documento
 * @property string|null $Codigo_Motivo
 * @property string|null $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property string|null $ART_PLAN_UMEDIDA
 * @property string|null $ART_PESO
 * @property string $cantidad
 * @property string $bodega_origen
 * @property string $bodega_destino
 * @property string $compartimiento
 * @property string|null $Fecha_Documento
 * @property string $reservado
 * @property string $trasladado
 * @property int|null $completado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $picking
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereCabeceraId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereCodigoMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereCompartimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereCompletado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereFechaDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion wherePicking($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereReservado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereTrasladado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicion whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTrasladosReposicion extends Model
{
    use HasFactory;

    protected $fillable = [
        'cabecera_id',
        'Documento',
        'Codigo_Motivo',
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_CODIGO_TRES_LETRAS',
        'ART_PLAN_UMEDIDA',
        'ART_PESO',
        'cantidad',
        'bodega_origen',
        'bodega_destino',
        'compartimiento',
        'Fecha_Documento',
        'reservado',
        'trasladado',
        'completado',
        'picking'
    ];
}

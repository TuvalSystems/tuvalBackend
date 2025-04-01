<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpDatosItemsL
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $DATOS_ITEMS_L_CLASE
 * @property string|null $DATOS_ITEMS_L_DESCRIPCION_CLASE
 * @property int|null $DATOS_ITEMS_L_DIA_MAXIMO
 * @property int|null $DATOS_ITEMS_L_MARGEN
 * @property int|null $DATOS_ITEMS_L_TIPO
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereDATOSITEMSLCLASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereDATOSITEMSLDESCRIPCIONCLASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereDATOSITEMSLDIAMAXIMO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereDATOSITEMSLMARGEN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDatosItemsL whereDATOSITEMSLTIPO($value)
 */
class GpDatosItemsL extends Model
{
    use HasFactory;

    protected $table = 'gp_datos_itemsl';
}

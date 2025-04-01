<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpDescuentoFormaPago
 *
 * @property int $id
 * @property string $DESC_FORMA_PAGO_CODIGO
 * @property string|null $DESC_FORMA_PAGO_CONDICION_PAGO
 * @property int|null $DESC_FORMA_PAGO_DIAS_CREDITO
 * @property string|null $DESC_FORMA_PAGO_EMISOR
 * @property string|null $DESC_FORMA_PAGO_TIPO_CREDITO
 * @property string|null $DESC_FORMA_PAGO_PORCENTAJE_REBAJAR
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOCONDICIONPAGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGODIASCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOEMISOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOPORCENTAJEREBAJAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOTIPOCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $DESC_FORMA_PAGO_SRI
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOSRI($value)
 * @property string|null $DESC_FORMA_PAGO_TIPO_COBRO
 * @method static \Illuminate\Database\Eloquent\Builder|GpDescuentoFormaPago whereDESCFORMAPAGOTIPOCOBRO($value)
 */
class GpDescuentoFormaPago extends Model
{
    use HasFactory;
}

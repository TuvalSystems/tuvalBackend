<?php

namespace App\Models\Cobranzas;

use App\Models\GpCajaBancos;
use App\Models\GpUsuario;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Cobranzas\GpCobrosDeposito
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $estado
 * @property string|null $tipo
 * @property string|null $banco
 * @property string|null $comprobante
 * @property string|null $fecha
 * @property string $monto
 * @property string|null $codigo_ws
 * @property string|null $mensaje_ws
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fecha_aprobacion
 * @property int|null $user_aprobador_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cobranzas\GpCobrosDepositosFacturas> $depositosFactura
 * @property-read int|null $depositos_factura_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereBanco($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereCodigoWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereComprobante($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereFechaAprobacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereMensajeWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereUserAprobadorId($value)
 * @mixin \Eloquent
 */
class GpCobrosDeposito extends Model
{
    use HasFactory;

    protected $table = 'gp_cobros_depositos';

    protected $fillable = [
        'cobro_id',
        'estado',
        'tipo',
        'banco',
        'comprobante',
        'fecha',
        'monto',
        'codigo_ws',
        'mensaje_ws',
        'fecha_aprobacion_rechazo',
        'user_aprobador_rechazo_id',
    ];
    protected $cast = [
        'created_at'                    => 'datetime:Y-m-d H:i:s',
        'updated_at'                    => 'datetime:Y-m-d H:i:s',
        'fecha'                         => 'datetime:Y-m-d H:i:s',
        'fecha_aprobacion_rechazo'      => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public function depositosFactura()
    {
        return $this->hasMany(GpCobrosDepositosFacturas::class, 'cobro_id', 'cobro_id');
    }

    public function usuarioAutorizador()
    {
        return $this->belongsTo(GpUsuario::class, 'user_aprobador_rechazo_id', 'id');
    }

    public function bancoDeposito()
    {
        return $this->belongsTo(GpCajaBancos::class, 'banco_id', 'id');
    }


    /**relacion para depositos - facturas cobranzas */

    public function facturas()
    {
        return $this->hasMany(GpCobrosDepositosFacturas::class, 'deposito_id', 'id');
    }
}

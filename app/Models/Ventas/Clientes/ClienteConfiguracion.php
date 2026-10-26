<?php

namespace App\Models\Ventas\Clientes;

use App\Models\Admin\Empresas\Empresas;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Ventas\Vendedores\GpVendedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteConfiguracion extends Model
{
    use HasFactory;
    protected $table = 'clientes_configuraciones';

    protected $fillable = [
        'empresa_id',
        'codigo_empresa',
        'pv_id',
        'codigo_localidad',
        'cliente_id',
        'ruc',
        'vendedor_id',
        'codigo_vendedor',
        'fecha_ultima_modificacion',
        'estado',
        'sincronizado_at',
    ];

    protected $casts = [
        'empresa_id'                => 'integer',
        'pv_id'                     => 'integer',
        'cliente_id'                => 'integer',
        'vendedor_id'               => 'integer',
        'ruc'                       => 'string',
        'estado'                    => 'boolean',
        'fecha_ultima_modificacion' => 'datetime',
        'sincronizado_at'           => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresas::class, 'empresa_id');
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(GpPuntoVenta::class, 'pv_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(GpCliente::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(GpVendedor::class, 'vendedor_id');
    }
}

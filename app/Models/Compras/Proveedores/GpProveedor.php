<?php

namespace App\Models\Compras\Proveedores;

use App\Models\Ventas\Pedidos\GpPedidoDetalleProveedores;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpProveedor extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'PROV_RUC',
        'PROV_RAZON_SOCIAL',
        'PROV_CLASE',
        'PROV_ID_DIRECCION',
        'PROV_CONTACTO',
        'PROV_DIRECCION_1',
        'PROV_DIRECCION_2',
        'PROV_DIRECCION_3',
        'PROV_CIUDAD',
        'PROV_PROVINCIA',
        'PROV_PAIS',
        'PROV_TELEFONO_1',
        'PROV_TELEFONO_2',
        'PROV_TELEFONO_3',
        'PROV_TELEFONO_4',
        'PROV_CONDICION_PAGO',
        'PROV_DSTO_COMERCIAL',
        'PROV_FECHA_MODIFICACION',
        'PROV_FECHA_CREACION',
        'PROV_CORREO_ELECT',
        'PROV_IMPUESTO',
        'PROV_ESTADO',
        'es_transportista',
        'fle_usuario_id',
        'fle_fecha',
        'usuario_modifica_id',
        'fecha_modificacion'
    ];

    protected $casts = [
        'es_transportista'      => 'boolean',
        'fle_usuario_id'        => 'integer',
        'fle_fecha'             => 'datetime:Y-m-d H:i:s',
        'usuario_modifica_id'   => 'integer',
        'fecha_modificacion'    => 'datetime:Y-m-d H:i:s',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];

    public function detalleProveedores()
    {
        return $this->hasMany(GpPedidoDetalleProveedores::class, 'ID_PROVEEDOR', 'id');
    }


    public function fletesExternos(): HasMany
    {
        return $this->hasMany(FleteExterno::class, 'proveedor_id');
    }

    public function puntosVenta(): BelongsToMany
    {
        return $this->belongsToMany(
            GpPuntoVenta::class,
            'proveedores_punto_ventas',
            'prov_id',
            'pv_id'
        )->withTimestamps();
    }
}

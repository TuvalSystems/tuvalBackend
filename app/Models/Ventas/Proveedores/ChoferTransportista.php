<?php

namespace App\Models\Ventas\Proveedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChoferTransportista extends Model
{
    use HasFactory;

    protected $fillable = [
        'proveedor_id',
        'nombre',
        'identificacion',
        'tipo_identificacion_id',
        'placa',
        'estado',
        'usuario_ingreso_id',
        'fecha_ingreso',
        'usuario_modifica_id',
        'fecha_modificacion'
    ];

    protected $casts = [
        'proveedor_id'        => 'integer',
        'tipo_identificacion' => 'integer',
        'estado'              => 'boolean',
        'usuario_ingreso_id'  => 'integer',
        'fecha_ingreso'       => 'datetime:Y-m-d H:i:s',
        'usuario_modifica_id' => 'datetime:Y-m-d H:i:s',
        'created_at'          => 'datetime:Y-m-d H:i:s',
        'updated_at'          => 'datetime:Y-m-d H:i:s',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(GpProveedor::class, 'proveedor_id');
    }
}

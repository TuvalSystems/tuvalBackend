<?php

namespace App\Models\Compras\Proveedores;

use App\Models\Ventas\Clientes\TipoIdentificacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleteExterno extends Model
{
    use HasFactory;

    protected $table = 'flete_externos';
    protected $fillable = [
        'proveedor_id',
        'nombre',
        'identificacion',
        'tipo_identificacion_id',
        'placa',
        'estado',
        'usuario_ingreso_id',
        'usuario_modifica_id',
    ];

    protected $casts = [
        'proveedor_id'          => 'integer',
        'tipo_identificacion_id' => 'integer',
        'estado'                => 'boolean',
        'usuario_ingreso_id'    => 'integer',
        'usuario_modifica_id'   => 'integer',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(GpProveedor::class, 'proveedor_id');
    }

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }


}

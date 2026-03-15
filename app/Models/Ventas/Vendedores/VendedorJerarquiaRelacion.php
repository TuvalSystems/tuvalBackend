<?php

namespace App\Models\Ventas\Vendedores;

use App\Models\Ventas\TipoVendedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendedorJerarquiaRelacion extends Model
{
    use HasFactory;

    protected $table = 'vendedor_jerarquia_relaciones';

    protected $casts = [
        'estado'        => 'boolean',
        'nivel'         => 'integer',
        'modified_at'   => 'datetime',
    ];
    protected $fillable = [
        'vendedor_jerarquia_id',
        'tipo_vendedor_padre_id',
        'tipo_vendedor_hijo_id',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at'
    ];


    public function jerarquia()
    {
        return $this->belongsTo(VendedorJerarquia::class, 'vendedor_jerarquia_id');
    }

    public function tipoVendedorPadre()
    {
        return $this->belongsTo(TipoVendedor::class, 'tipo_vendedor_padre_id');
    }

    public function tipoVendedorHijo()
    {
        return $this->belongsTo(TipoVendedor::class, 'tipo_vendedor_hijo_id');
    }
}

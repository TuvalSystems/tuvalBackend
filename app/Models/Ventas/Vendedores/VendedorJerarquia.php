<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendedorJerarquia extends Model
{
    use HasFactory;

    protected $table = 'vendedor_jerarquias';


    protected $casts = [
        'estado'        => 'boolean',
        'nivel'         => 'integer',
        'modified_at'   => 'datetime',
    ];
    protected $fillable = [
        'vendedor_id',
        'nombre',
        'descripcion',
        'nivel',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];


    public function vendedor()
    {
        return $this->belongsTo(GpVendedor::class, 'vendedor_id');
    }

    public function relaciones()
    {
        return $this->hasMany(VendedorJerarquiaRelacion::class, 'vendedor_jerarquia_id');
    }
}

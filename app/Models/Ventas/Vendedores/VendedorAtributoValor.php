<?php

namespace App\Models\Ventas\Vendedores;

use App\Models\Ventas\Vendedores\GpVendedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendedorAtributoValor extends Model
{
    use HasFactory;

    protected $table = 'vendedor_atributo_valores';

    protected $fillable = [
        'vendedor_id',
        'atributo_id',
        'opcion_id',
        'valor_string',
        'valor_integer',
        'valor_decimal',
        'valor_boolean',
        'valor_date',
    ];

    protected $casts = [
        'valor_boolean' => 'boolean',
        'valor_date'    => 'date',
        'valor_decimal' => 'decimal:4',
    ];

    public function vendedor()
    {
        return $this->belongsTo(GpVendedor::class, 'vendedor_id');
    }

    public function atributo()
    {
        return $this->belongsTo(VendedorAtributo::class, 'atributo_id');
    }

    public function opcion()
    {
        return $this->belongsTo(VendedorAtributoOpcion::class, 'opcion_id');
    }
}

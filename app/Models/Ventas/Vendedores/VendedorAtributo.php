<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\Ventas\Vendedores\SellerAttributeTypeEnum;

class VendedorAtributo extends Model
{
    use HasFactory;

    protected $table = 'vendedor_atributos';

    protected $casts = [
        'tipo_dato' => SellerAttributeTypeEnum::class,
    ];
    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_dato',
        'es_multiple',
        'es_requerido',
        'estado',
        'descripcion',
    ];


    public function opciones()
    {
        return $this->hasMany(VendedorAtributoOpcion::class, 'atributo_id');
    }

    public function valores()
    {
        return $this->hasMany(VendedorAtributoValor::class, 'atributo_id');
    }
}

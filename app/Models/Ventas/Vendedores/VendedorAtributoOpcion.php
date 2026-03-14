<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendedorAtributoOpcion extends Model
{
    use HasFactory;

    protected $table = 'vendedor_atributo_opciones';

    protected $fillable = [
        'atributo_id',
        'codigo',
        'nombre',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];

    public function atributo()
    {
        return $this->belongsTo(VendedorAtributo::class, 'atributo_id');
    }

    public function valores()
    {
        return $this->hasMany(VendedorAtributoValor::class, 'opcion_id');
    }
}

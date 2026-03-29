<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    use HasFactory;

    protected $table = 'zonas';
    protected $fillable =  [
        'descripcion',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];

    public function vendedores()
    {
        return $this->belongsToMany(GpVendedor::class, 'vendedor_zona', 'zona_id', 'vendedor_id');
    }
}

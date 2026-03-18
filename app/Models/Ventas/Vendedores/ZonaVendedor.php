<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZonaVendedor extends Model
{
    use HasFactory;

    protected $table = 'zona_vendedor';

    protected $fillable =  [
        'descripcion',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];

    public function vendedores()
    {
        return $this->hasMany(GpVendedor::class, 'zona_vendedor_id');
    }
}

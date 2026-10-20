<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parroquia extends Model
{
    use HasFactory;


    protected $table = 'parroquias';
    protected $fillable = [
        'canton_id',
        'codigo_inec',
        'nombre',
        'tipo'
    ];
    protected $casts = [
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];
}

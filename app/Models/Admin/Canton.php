<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Canton extends Model
{
    use HasFactory;

    protected $table = 'cantons';
    protected $fillable = [
        'provincia_id',
        'codigo_inec',
        'nombre',
    ];
    protected $casts = [
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];
}

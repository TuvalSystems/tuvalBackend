<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpTempGuiasDespachoBlock extends Model
{
    use HasFactory;

    protected $table = 'gp_temp_guias_despacho_blocks';

    protected $fillable = [
        'guia_id',
        'pedido',
        'user_id'
    ];
}

<?php

namespace App\Models\Gestion;

use App\Models\Admin\Usuarios\GpUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    use HasFactory;

    protected $table = 'auditorias';

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
    ];


    public function user()
    {
        return $this->belongsTo(GpUsuario::class, 'user_id');
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}

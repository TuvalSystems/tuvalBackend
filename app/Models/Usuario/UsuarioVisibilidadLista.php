<?php

namespace App\Models\Usuario;

use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioVisibilidadLista extends Model
{
    use HasFactory;

    protected $table = 'usuario_visibilidad_listas';

    protected $fillable = [
        'usr_pr_id',
        'usr_ver_id',
        'pv_id',
        'ver_cot',
        'ver_ped',
        'ver_dev',
        'estado'
    ];

    protected $casts = [
        'usr_pr_id'     => 'integer',
        'usr_ver_id'    => 'integer',
        'pv_id'         => 'integer',
        'ver_cot'       => 'boolean',
        'ver_ped'       => 'boolean',
        'ver_dev'       => 'boolean',
        'estado'        => 'boolean',
    ];


    public function usuarioPrincipal(): BelongsTo
    {
        return $this->belongsTo(GpUsuario::class, 'usr_pr_id');
    }

    public function usuarioVisible(): BelongsTo
    {
        return $this->belongsTo(GpUsuario::class, 'usr_ver_id');
    }
}

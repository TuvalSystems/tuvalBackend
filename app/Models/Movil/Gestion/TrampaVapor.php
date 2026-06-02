<?php

namespace App\Models\Movil\Gestion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrampaVapor extends Model
{
    use HasFactory;
    protected $table = 'trampas_vapor';

    protected $casts = [
        'parametros_sp' => 'array'
    ];
    protected $fillable = [
        'user_id',
        'cliente_id',
        'proceso',
        'tipo_trampa',
        'metodo_calculo',
        'presion_trabajo_psig',
        'presion_retorno_psig',
        'presion_diferencial_psig',
        'fluido',
        'volumen_m3',
        'densidad_kg_m3',
        'cp_kj_kg_c',
        'temperatura_inicial_c',
        'temperatura_final_c',
        'tiempo_calentamiento_min',
        'calor_requerido_kj',
        'hfg_kj_kg',
        'carga_conocida_kg_h',
        'potencia_caldera_bhp',
        'carga_estimada_kg',
        'factor_seguridad',
        'carga_con_factor_seguridad_kg',
        'parametros_sp'
    ];
}

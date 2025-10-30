<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpGuiaRemisionReserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'clave_acceso',
        'numero_guia',
        'user_id',
        'estado',
        'bodega_origen',
        'bodega_destino',
        'guia_bodega',
        'establecimiento',
        'pto_emision',
        'secuencial',
        'direccion_partida',
        'razon_social_transporte',
        'identificacion_transporte',
        'tipo_identificacion_transporte',
        'placa',
        'fecha_inicio',
        'fecha_fin',
        'observacion',
        'direccion_destinatario',
        'identificacion_destinatario',
        'razon_social_destinatario',
        'ruta',
        'cod_sustento',
        'doc_sustento',
        'num_auto_doc_sustento',
        'fecha_emision_doc_sustento',
        'rebajado',
    ];


    public function detalleGuiaReservas()
    {
        return $this->hasMany(GpGuiaRemisionReservaDetalle::class, 'guia_remision_reserva_id', 'id');
    }
}

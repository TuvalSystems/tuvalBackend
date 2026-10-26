<?php

namespace App\Models\Cobranzas\Cobros;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobroValidacionFactura extends Model
{
    use HasFactory;

      protected $table = 'cobro_validacion_facturas';

    protected $fillable = [
        'grupo_validacion',
        'cliente_id',
        'cliente_ruc',
        'user_id',
        'tipo_ingreso',
        'factura',
        'fecha_vencimiento',
        'monto_original_factura',
        'saldo_cliente',
        'valor_a_aplicar',
        'sum_payments_gp',
        'sum_pending_web',
        'sum_devolutions',
        'total_apply',
        'saldo_factura',
        'saldo_base',
        'saldo_disponible',
        'total_pending_not_accounted',
        'excess_value',
        'price_recommend',
        'has_excess',
        'has_double_apply',
        'validation_type',
        'validation_message',
        'double_apply_doc',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto_original_factura' => 'decimal:2',
        'saldo_cliente' => 'decimal:2',
        'valor_a_aplicar' => 'decimal:2',
        'sum_payments_gp' => 'decimal:2',
        'sum_pending_web' => 'decimal:2',
        'sum_devolutions' => 'decimal:2',
        'total_apply' => 'decimal:2',
        'saldo_factura' => 'decimal:2',
        'saldo_base' => 'decimal:2',
        'saldo_disponible' => 'decimal:2',
        'total_pending_not_accounted' => 'decimal:2',
        'excess_value' => 'decimal:2',
        'price_recommend' => 'decimal:2',
        'has_excess' => 'boolean',
        'has_double_apply' => 'boolean',
    ];

    public function detalles()
    {
        return $this->hasMany(CobroValidacionFacturaDetalle::class, 'validacion_factura_id');
    }
}

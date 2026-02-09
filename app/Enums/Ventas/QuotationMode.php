<?php

namespace App\Enums\Ventas;

enum QuotationMode: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case COPY_QUOTATION = 'copiar_cotizacion';
    case COPY_ORDER = 'copiar_pedido';
    case REORDER = 'volver_a_pedir';
    case RESTORE_QUOTE = 'restore_quote';


    public static function fromType(string $type): self
    {
        return match($type) {
            'cotizacion' => self::COPY_QUOTATION,
            'pedido' => self::COPY_ORDER,
            default      => throw new \InvalidArgumentException("Tipo de copia no válido: $type"),
        };
    }
}

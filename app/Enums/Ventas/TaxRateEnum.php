<?php
namespace App\Enums\Ventas;

enum TaxRateEnum: int
{
    case IVA_15 = 15;
    case IVA_12 = 12;


    public function factor(): float
    {
        return 1 + ($this->value / 100);
    }

    public function calculateSubtotal(float $total): float
    {
        return round($total / $this->factor(), 2);
    }

}


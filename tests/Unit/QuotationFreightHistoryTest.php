<?php

namespace Tests\Unit;

use App\Models\GpCotizacion;
use App\Services\Builders\Venta\QuotationBuilder;
use PHPUnit\Framework\TestCase;

class QuotationFreightHistoryTest extends TestCase
{
    public function test_history_preserves_freight_audit_fields(): void
    {
        $fields = [
            'fle_usuario_id' => 12,
            'fle_fecha' => '2026-10-15 09:00:00',
            'fle_usuario_aut_id' => 34,
            'fle_fecha_aut' => '2026-10-15 10:00:00',
        ];
        $quote = new GpCotizacion($fields);
        $history = (new QuotationBuilder())->formPayloadHistoryHeaderQuote($quote, 1);

        foreach ($fields as $field => $value) {
            $this->assertSame($value, $history->getAttribute($field), $field);
        }
    }

    public function test_history_accepts_quotes_without_freight_audit_data(): void
    {
        $history = (new QuotationBuilder())->formPayloadHistoryHeaderQuote(new GpCotizacion(), 1);

        foreach (['fle_usuario_id', 'fle_fecha', 'fle_usuario_aut_id', 'fle_fecha_aut'] as $field) {
            $this->assertArrayHasKey($field, $history->getAttributes());
            $this->assertNull($history->getAttribute($field));
        }
    }
}

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
            'usuario_flete_id' => 12,
            'fecha_flete' => '2026-10-15 09:00:00',
            'usuario_flete_autorizador_id' => 34,
            'fecha_flete_autorizacion' => '2026-10-15 10:00:00',
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

        foreach (['usuario_flete_id', 'fecha_flete', 'usuario_flete_autorizador_id', 'fecha_flete_autorizacion'] as $field) {
            $this->assertArrayHasKey($field, $history->getAttributes());
            $this->assertNull($history->getAttribute($field));
        }
    }
}

<?php

namespace Tests\Feature;

use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatsappServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp' => [
            'token' => 'test-token',
            'phone_number_id' => '123456',
            'api_version' => 'v25.0',
            'order_ready_template' => 'pedido_retiro_prueba',
            'order_ready_language' => 'es_EC',
        ]]);
        Http::preventStrayRequests();
    }

    public function test_sends_configured_template_and_language_with_ordered_parameters(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.test']],
        ])]);

        $result = (new WhatsappService())->sendOrderReadyNotification(
            '593999999999', ' María ', ' PED-12345 ', ' Principal '
        );

        $this->assertSame('wamid.test', $result['messages'][0]['id']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v25.0/123456/messages'
                && $request['template']['name'] === 'pedido_retiro_prueba'
                && $request['template']['language']['code'] === 'es_EC'
                && $request['template']['components'] === [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'María'],
                        ['type' => 'text', 'text' => 'PED-12345'],
                        ['type' => 'text', 'text' => 'Principal'],
                    ],
                ]];
        });
        Http::assertSentCount(1);
    }

    public function test_missing_translation_returns_actionable_error(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['code' => 132001, 'message' => 'Template name does not exist in the translation'],
        ], 404)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("plantilla 'pedido_retiro_prueba' en el idioma 'es_EC'");

        (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal');
    }

    public function test_missing_language_fails_before_sending(): void
    {
        config(['services.whatsapp.order_ready_language' => '']);
        Http::fake();

        try {
            (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal');
            $this->fail('Expected missing configuration to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('WHATSAPP_ORDER_READY_LANGUAGE', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_other_api_errors_are_not_reported_as_missing_templates(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['code' => 190, 'message' => 'Invalid token'],
        ], 401)]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal');
    }

    public function test_text_mode_sends_body_without_template_configuration(): void
    {
        config([
            'services.whatsapp.order_ready_type' => 'text',
            'services.whatsapp.order_ready_template' => null,
            'services.whatsapp.order_ready_language' => null,
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.text']]])]);

        (new WhatsappService())->sendOrderReadyNotification('+593999999999', ' María ', '123', 'Principal');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['recipient_type'] === 'individual'
                && $request['to'] === '593999999999'
                && $request['type'] === 'text'
                && !isset($request['template'])
                && $request['text']['preview_url'] === false
                && $request['text']['body'] === "📦 *¡Tu pedido está listo para retirar!*\n\n"
                    . "Hola María, ya puedes recoger tu pedido.\n\n"
                    . "*Número de pedido:* 123\n"
                    . "*Bodega de retiro:* Principal\n\n"
                    . "Presenta tu número de pedido al momento del retiro.\n\n"
                    . 'Gracias por confiar en *Tuval*.';
        });
        Http::assertSentCount(1);
    }

    public function test_template_uses_default_image_and_allows_per_message_override(): void
    {
        config(['services.whatsapp.order_ready_image_url' => 'https://example.com/default.jpg']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.image']]])]);

        foreach ([null, 'https://example.com/order.png'] as $imageUrl) {
            (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal', $imageUrl);
            $expected = $imageUrl ?? 'https://example.com/default.jpg';
            Http::assertSent(function ($request) use ($expected) {
                $components = $request['template']['components'];
                return $request['type'] === 'template'
                    && $components[0] === [
                        'type' => 'header',
                        'parameters' => [['type' => 'image', 'image' => ['link' => $expected]]],
                    ]
                    && $components[1]['type'] === 'body'
                    && count($components[1]['parameters']) === 3;
            });
        }
        Http::assertSentCount(2);
    }

    public function test_invalid_image_fails_before_sending(): void
    {
        Http::fake();
        foreach ([['template', 'file:///image.jpg'], ['text', 'invalid']] as [$type, $imageUrl]) {
            config(['services.whatsapp.order_ready_type' => $type]);
            try {
                (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal', $imageUrl);
                $this->fail('Expected invalid image configuration to fail.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_text_mode_sends_default_image_with_caption(): void
    {
        config([
            'services.whatsapp.order_ready_type' => 'text',
            'services.whatsapp.order_ready_image_url' => 'https://example.com/default.jpg',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.image']]])]);
        (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal');
        Http::assertSent(fn ($request) => $request['type'] === 'image'
            && $request['image']['link'] === 'https://example.com/default.jpg'
            && str_contains($request['image']['caption'], '*Número de pedido:* 123')
            && !isset($request['text']) && !isset($request['template']));
        Http::assertSentCount(1);
    }

    public function test_pdf_replaces_image_in_text_and_template_modes(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.pdf']]])]);
        config(['services.whatsapp.order_ready_image_url' => 'https://example.com/default.jpg']);
        foreach (['text', 'template'] as $mode) {
            config(['services.whatsapp.order_ready_type' => $mode]);
            (new WhatsappService())->sendOrderReadyNotification(
                '593999999999', 'María', '123', 'Principal',
                'https://example.com/image.jpg', 'https://example.com/download?file=123', 'prueba.pdf'
            );
            Http::assertSent(function ($request) use ($mode) {
                if ($mode === 'text') {
                    return $request['type'] === 'document'
                        && $request['document']['link'] === 'https://example.com/download?file=123'
                        && $request['document']['filename'] === 'prueba.pdf'
                        && str_contains($request['document']['caption'], '*Número de pedido:* 123')
                        && !isset($request['image']) && !isset($request['text']);
                }
                return $request['type'] === 'template'
                    && $request['template']['components'][0] === [
                        'type' => 'header',
                        'parameters' => [[
                            'type' => 'document',
                            'document' => ['link' => 'https://example.com/download?file=123', 'filename' => 'prueba.pdf'],
                        ]],
                    ]
                    && $request['template']['components'][1]['type'] === 'body';
            });
        }
        Http::assertSentCount(2);
    }

    public function test_configured_pdf_is_sent_without_request_pdf_url(): void
    {
        config([
            'services.whatsapp.order_ready_type' => 'text',
            'services.whatsapp.order_ready_pdf_url' => 'https://example.com/default.pdf',
            'services.whatsapp.order_ready_image_url' => 'https://example.com/default.jpg',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response([])]);
        (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal');
        Http::assertSent(fn ($request) => $request['type'] === 'document'
            && $request['document']['link'] === 'https://example.com/default.pdf'
            && !isset($request['image']));
        Http::assertSentCount(1);
    }

    public function test_pdf_uses_default_filename(): void
    {
        config(['services.whatsapp.order_ready_type' => 'text']);
        Http::fake(['graph.facebook.com/*' => Http::response([])]);
        (new WhatsappService())->sendOrderReadyNotification(
            '593999999999', 'María', '123', 'Principal', null, 'https://example.com/test.pdf'
        );
        Http::assertSent(fn ($request) => $request['document']['filename'] === 'documento.pdf');
    }

    public function test_invalid_pdf_url_and_oversized_caption_do_not_send(): void
    {
        config(['services.whatsapp.order_ready_type' => 'text']);
        Http::fake();
        foreach ([['María', 'file:///test.pdf'], [str_repeat('a', 1025), 'https://example.com/test.pdf']] as [$name, $url]) {
            try {
                (new WhatsappService())->sendOrderReadyNotification('593999999999', $name, '123', 'Principal', null, $url);
                $this->fail('Expected invalid PDF request to fail.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_closed_window_reports_requirement_without_retrying(): void
    {
        config(['services.whatsapp.order_ready_type' => 'text']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131047]], 400)]);

        try {
            (new WhatsappService())->sendOrderReadyNotification('593999999999', 'María', '123', 'Principal');
            $this->fail('Expected closed window to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ventana de atención de 24 horas', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }
}

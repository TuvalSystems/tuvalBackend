<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Contracts\WhatsappServiceInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class WhatsappService implements WhatsappServiceInterface
{
    public function sendOrderReadyNotification(
        string $phone,
        string $customerName,
        string $orderNumber,
        string $branchName,
        ?string $imageUrl = null,
        ?string $pdfUrl = null,
        ?string $pdfFilename = null
    ) {
        $phone = preg_replace('/^\+/', '', trim($phone));
        if (!preg_match('/^[1-9][0-9]{7,14}$/', $phone)) {
            throw new InvalidArgumentException(
                'El teléfono debe incluir el código de país, sin + ni espacios.'
            );
        }

        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.api_version');
        $messageType = config('services.whatsapp.order_ready_type', 'template');
        $templateName = trim((string) config('services.whatsapp.order_ready_template'));
        $templateLanguage = trim((string) config('services.whatsapp.order_ready_language'));


        if (!in_array($messageType, ['text', 'template'], true)) {
            throw new RuntimeException('WHATSAPP_ORDER_READY_TYPE debe ser text o template.');
        }

        $pdfUrl = trim((string) ($pdfUrl ?? config('services.whatsapp.order_ready_pdf_url')));
        $pdfFilename = trim((string) $pdfFilename) ?: 'documento.pdf';
        if ($pdfUrl !== '' && (!filter_var($pdfUrl, FILTER_VALIDATE_URL)
            || !in_array(strtolower((string) parse_url($pdfUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw new InvalidArgumentException('El PDF debe tener una URL pública HTTP o HTTPS.');
        }
        $imageUrl = $pdfUrl !== '' ? '' : trim((string) ($imageUrl ?? config('services.whatsapp.order_ready_image_url')));
        if ($imageUrl !== '') {
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw new InvalidArgumentException('La imagen debe tener una URL pública HTTP o HTTPS.');
            }
        }

        if (!$token || !$phoneNumberId || !$version
            || ($messageType === 'template' && (!$templateName || !$templateLanguage))) {
            throw new RuntimeException(
                'La configuración de WhatsApp está incompleta. Revisa WHATSAPP_TOKEN, '
                . 'WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_API_VERSION, '
                . 'WHATSAPP_ORDER_READY_TEMPLATE y WHATSAPP_ORDER_READY_LANGUAGE.'
            );
        }
        $parameters = [];

        foreach ([$customerName, $orderNumber, $branchName] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(
                    'Los datos del mensaje no pueden estar vacíos.'
                );
            }

            $parameters[] = [
                'type' => 'text',
                'text' => trim($value),
            ];
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => $messageType,
        ];

        if ($messageType === 'text') {
            $payload['text'] = [
                'preview_url' => false,
                'body' => "📦 *¡Tu pedido está listo para retirar!*\n\n"
                    . "Hola {$parameters[0]['text']}, ya puedes recoger tu pedido.\n\n"
                    . "*Número de pedido:* {$parameters[1]['text']}\n"
                    . "*Bodega de retiro:* {$parameters[2]['text']}\n\n"
                    . "Presenta tu número de pedido al momento del retiro.\n\n"
                    . 'Gracias por confiar en *Tuval*.',
            ];
            if ($pdfUrl !== '' || $imageUrl !== '') {
                if (mb_strlen($payload['text']['body']) > 1024) {
                    throw new InvalidArgumentException('El pie del archivo no puede superar 1024 caracteres.');
                }
                $mediaType = $pdfUrl !== '' ? 'document' : 'image';
                $payload['type'] = $mediaType;
                $payload[$mediaType] = ['link' => $pdfUrl ?: $imageUrl, 'caption' => $payload['text']['body']];
                if ($mediaType === 'document') {
                    $payload[$mediaType]['filename'] = $pdfFilename;
                }
                unset($payload['text']);
            }
        } else {
            $payload['template'] = [
                    'name' => $templateName,
                    'language' => [
                        'code' => $templateLanguage,
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => $parameters,
                        ],
                    ],
                ];
            if ($pdfUrl !== '' || $imageUrl !== '') {
                $mediaType = $pdfUrl !== '' ? 'document' : 'image';
                $media = ['link' => $pdfUrl ?: $imageUrl];
                if ($mediaType === 'document') {
                    $media['filename'] = $pdfFilename;
                }
                array_unshift($payload['template']['components'], [
                    'type' => 'header',
                    'parameters' => [[
                        'type' => $mediaType,
                        $mediaType => $media,
                    ]],
                ]);
            }
        }

        $response = Http::withToken($token)
            ->asJson()
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->post($url, $payload);

        if ($response->failed() && (int) $response->json('error.code') === 131047) {
            throw new RuntimeException(
                'WhatsApp (131047): la ventana de atención de 24 horas está cerrada. '
                . 'El cliente debe escribir al número de WhatsApp para enviar texto libre, '
                . 'o debes usar WHATSAPP_ORDER_READY_TYPE=template con una plantilla aprobada.'
            );
        }

        if ($response->failed() && (int) $response->json('error.code') === 132001) {
            throw new RuntimeException(
                "WhatsApp (132001): no se encontró la plantilla '{$templateName}' "
                . "en el idioma '{$templateLanguage}'. Verifica en WhatsApp Manager que exista, "
                . 'esté aprobada y pertenezca a la cuenta asociada a WHATSAPP_PHONE_NUMBER_ID. '
                . 'Configura el nombre y el código de idioma exactos en '
                . 'WHATSAPP_ORDER_READY_TEMPLATE y WHATSAPP_ORDER_READY_LANGUAGE.'
            );
        }

        $response->throw();

        return $response->json();
    }
}

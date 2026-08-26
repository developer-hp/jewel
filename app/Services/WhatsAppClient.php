<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The WhatsApp Cloud API, as much of it as this app uses: one template message.
 *
 * This is the only outbound HTTP call in the codebase, which is worth knowing for
 * two reasons — there is no house style to copy, and any test that touches a code
 * path reaching here must Http::fake() or it will try to call Meta for real.
 *
 * Nothing here throws for an ordinary rejection. A wrong template name or a
 * recipient who has opted out is an answer, not a crash, and the order it belongs to
 * is already saved. Only a transient failure throws, so the job's retries have
 * something worth retrying.
 */
class WhatsAppClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token'))
            && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Send one approved template.
     *
     * @param  array<int, string>  $header  the header placeholders, or [] for none
     * @param  array<int, string>  $body  the body placeholders, in order
     * @param  array{link: string, filename: string}|null  $document  a PDF header
     * @return bool whether Meta accepted it
     *
     * @throws WhatsAppTransientException when the failure is worth retrying
     */
    public function sendTemplate(
        string $to,
        string $template,
        string $language,
        array $header,
        array $body,
        ?array $document = null,
    ): bool {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp: no credentials configured; nothing sent.');

            return false;
        }

        $payload = static::payload($to, $template, $language, $header, $body, $document);

        try {
            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout((int) config('services.whatsapp.timeout', 10))
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $e) {
            // Never reached Meta at all — worth another go.
            throw new WhatsAppTransientException('Could not reach the WhatsApp Cloud API: '.$e->getMessage(), 0, $e);
        }

        if ($response->serverError()) {
            throw new WhatsAppTransientException(
                'The WhatsApp Cloud API returned '.$response->status().'.'
            );
        }

        if ($response->failed()) {
            // Meta's error object carries a code and a human message; the recipient
            // is reduced to its last four digits, and the token never appears.
            Log::warning('WhatsApp: Meta rejected the message.', [
                'status' => $response->status(),
                'error' => $response->json('error'),
                'template' => $template,
                'to' => '…'.substr($to, -4),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Exactly what is posted to Meta.
     *
     * Public so the tests assert against the same builder the client uses, rather
     * than a second copy of the shape that can drift from it.
     *
     * @param  array<int, string>  $header
     * @param  array<int, string>  $body
     * @param  array{link: string, filename: string}|null  $document
     * @return array<string, mixed>
     */
    public static function payload(
        string $to,
        string $template,
        string $language,
        array $header,
        array $body,
        ?array $document = null,
    ): array {
        $components = [];

        // A document header and a text header are alternatives, not both: Meta
        // allows one header component and its type is fixed by the template.
        if ($document !== null) {
            $components[] = ['type' => 'header', 'parameters' => [[
                'type' => 'document',
                // Meta fetches this itself, so it has to be reachable from the
                // public internet — a local hostname will simply fail.
                'document' => ['link' => $document['link'], 'filename' => $document['filename']],
            ]]];
        } elseif ($header !== []) {
            // Omitted entirely when the template has no header, rather than sent empty.
            $components[] = ['type' => 'header', 'parameters' => static::parameters($header)];
        }

        if ($body !== []) {
            $components[] = ['type' => 'body', 'parameters' => static::parameters($body)];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            // Digits only. A leading plus is rejected.
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                // Meta treats "en" and "en_US" as different templates; this has to
                // match the approved one exactly.
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];
    }

    /**
     * Placeholders are positional — array order is {{1}}, {{2}}, and so on. There is
     * no named-parameter mode in use here.
     *
     * @param  array<int, string>  $values
     * @return array<int, array<string, string>>
     */
    private static function parameters(array $values): array
    {
        return array_map(
            fn (string $value) => ['type' => 'text', 'text' => $value],
            array_values($values),
        );
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.whatsapp.base_url'), '/')
            .'/'.config('services.whatsapp.api_version')
            .'/'.config('services.whatsapp.phone_number_id')
            .'/messages';
    }
}

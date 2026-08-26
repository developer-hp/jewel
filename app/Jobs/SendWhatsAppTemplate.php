<?php

namespace App\Jobs;

use App\Services\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One WhatsApp template message.
 *
 * It carries plain strings and deliberately does not use SerializesModels. A queued
 * job that serialises a model re-fetches it when it runs, so an order edited or
 * deleted in the meantime would either send the wrong figures or blow up with
 * ModelNotFoundException. What was true when the order was taken is what the
 * customer is told.
 *
 * Retries only bite on WhatsAppTransientException — the client swallows an ordinary
 * rejection, because a wrong template name fails identically three times.
 */
class SendWhatsAppTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** Long enough for a Meta blip to pass, short enough to still be news. */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $body
     * @param  array{link: string, filename: string}|null  $document
     */
    public function __construct(
        public readonly string $to,
        public readonly string $template,
        public readonly string $language,
        public readonly array $header,
        public readonly array $body,
        public readonly ?array $document = null,
    ) {}

    public function handle(WhatsAppClient $client): void
    {
        $client->sendTemplate(
            $this->to,
            $this->template,
            $this->language,
            $this->header,
            $this->body,
            $this->document,
        );
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('WhatsApp: the send job gave up.', [
            'template' => $this->template,
            'to' => '…'.substr($this->to, -4),
            'message' => $e?->getMessage(),
        ]);
    }
}

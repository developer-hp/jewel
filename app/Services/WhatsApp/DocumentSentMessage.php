<?php

namespace App\Services\WhatsApp;

/**
 * What fills the file_message template's placeholders.
 *
 * Its header is the PDF itself rather than text, so header() is empty here — the
 * document is passed separately, straight to the client, because Meta's shape for a
 * document parameter is nothing like a text one.
 */
class DocumentSentMessage implements TemplateMessage
{
    use TemplateParameters;

    public function __construct(
        private readonly string $customerName,
        private readonly string $describedAs,
    ) {}

    /**
     * @return array<int, string>
     */
    public function header(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function body(): array
    {
        return [
            $this->text($this->customerName, 'Customer'),
            // "Your ___ is ready" — a ledger report, an invoice, whatever was typed.
            $this->text($this->describedAs, 'document'),
        ];
    }
}

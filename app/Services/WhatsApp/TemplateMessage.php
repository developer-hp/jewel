<?php

namespace App\Services\WhatsApp;

/**
 * What every message builder supplies: the placeholder values, in the order Meta
 * numbers them.
 *
 * The values themselves are the only thing that differs between events — deciding
 * whether to send, normalising the number and queueing the job are all shared, in
 * WhatsAppNotifier.
 */
interface TemplateMessage
{
    /**
     * The header placeholders, or an empty list for a template without a header.
     *
     * @return array<int, string>
     */
    public function header(): array;

    /**
     * The body placeholders, in order.
     *
     * @return array<int, string>
     */
    public function body(): array;
}

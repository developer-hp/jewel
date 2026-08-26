<?php

namespace App\Support;

/**
 * Every moment the app can send a WhatsApp message, and what each one's template
 * has to accept.
 *
 * This enum is the source of truth for which templates exist. The whatsapp_templates
 * table only records what the shop registered with Meta for each case — the name,
 * the language, and whether it is switched on — so a case with no row yet is simply
 * one nobody has set up.
 *
 * Adding an event is a case here, a message builder under App\Services\WhatsApp, and
 * a call to the notifier. No schema change.
 */
enum WhatsAppEvent: string
{
    case OrderCreated = 'order_created';

    case RepairCreated = 'repair_created';

    case DocumentSent = 'document_sent';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated => 'Order created',
            self::RepairCreated => 'Repair taken in',
            self::DocumentSent => 'Document sent',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OrderCreated => 'Sent to the customer as soon as an order is taken.',
            self::RepairCreated => 'Sent to the customer as soon as a repair is booked in.',
            self::DocumentSent => 'Sent by hand from Send Document, with a PDF attached.',
        };
    }

    /**
     * The header placeholders in order, or an empty list for a template with no
     * header. Shown on the settings screen so the template registered with Meta can
     * be made to match.
     *
     * @return array<int, string>
     */
    public function headerParameters(): array
    {
        return match ($this) {
            self::OrderCreated, self::RepairCreated => ['App name'],
            // A document header, not a text one — the PDF itself.
            self::DocumentSent => ['The PDF'],
        };
    }

    /**
     * The body placeholders, in the order Meta numbers them {{1}}, {{2}}, ….
     *
     * @return array<int, string>
     */
    public function bodyParameters(): array
    {
        return match ($this) {
            self::OrderCreated => ['Customer name', 'Order number', 'Delivery date', 'Sales person'],
            // A repair carries several sales people rather than one, so the last
            // slot is the list of them.
            self::RepairCreated => ['Customer name', 'Repair number', 'Delivery date', 'Sales person'],
            self::DocumentSent => ['Customer name', 'What the document is'],
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}

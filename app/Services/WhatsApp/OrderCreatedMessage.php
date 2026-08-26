<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\OrderForm;

/**
 * What fills the customerorder template's placeholders.
 *
 * One class per event, so the second event adds a file rather than a branch. The
 * order here is the order Meta numbers them in — see WhatsAppEvent::bodyParameters(),
 * which describes the same list for the settings screen and must be kept in step.
 */
class OrderCreatedMessage implements TemplateMessage
{
    use TemplateParameters;

    public function __construct(private readonly OrderForm $form) {}

    /**
     * @return array<int, string>
     */
    public function header(): array
    {
        return [
            // The shop's own name, as the customer knows it.
            $this->text(AppSetting::current()->app_name, 'Jewel'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function body(): array
    {
        return [
            // "Hello -" would be worse than a generic greeting.
            $this->text($this->form->customer_name, 'Customer'),
            $this->text($this->form->reference()),
            // d-m-Y is what the listing and the printed form show, so the customer
            // reads the same date the counter does.
            $this->text($this->form->delivery_date?->format('d-m-Y')),
            // Denormalised onto the order at save time, so this survives the sales
            // person being deleted and costs no query.
            $this->text($this->form->sales_person_name),
        ];
    }
}

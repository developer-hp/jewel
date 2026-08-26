<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\RepairForm;
use App\Models\RepairFormSalesPerson;

/**
 * What fills the customerrepair template's placeholders.
 *
 * The same four values as the order confirmation, read off a repair form — which
 * differs in one way that matters: a repair is booked against several sales people
 * through a pivot, where an order snapshots a single name onto the row.
 */
class RepairCreatedMessage implements TemplateMessage
{
    use TemplateParameters;

    public function __construct(private readonly RepairForm $form) {}

    /**
     * @return array<int, string>
     */
    public function header(): array
    {
        return [
            $this->text(AppSetting::current()->app_name, 'Jewel'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function body(): array
    {
        return [
            $this->text($this->form->customer_name, 'Customer'),
            $this->text($this->form->reference()),
            $this->text($this->form->delivery_date?->format('d-m-Y')),
            $this->text($this->salesPeople()),
        ];
    }

    /**
     * Every name on the form, in the order they were entered. The pivot snapshots
     * each name at save time, so this survives a sales person being deleted.
     */
    private function salesPeople(): string
    {
        return $this->form->salesPersons
            ->map(fn (RepairFormSalesPerson $person) => trim((string) $person->name))
            ->filter()
            ->implode(', ');
    }
}

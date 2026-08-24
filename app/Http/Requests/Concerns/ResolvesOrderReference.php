<?php

namespace App\Http\Requests\Concerns;

use App\Models\OgEstimate;

/**
 * The Order Number select posts one value — "in", "out" or "order:12" — which has to
 * become the two columns behind it. Shared by the OG estimate and the voucher, since
 * the control is the same on both.
 */
trait ResolvesOrderReference
{
    /**
     * Rules for the single select. Required, because a document has to say what it
     * is against; the split below then guarantees only one column is filled.
     *
     * @return array<int, mixed>
     */
    protected function orderReferenceRules(): array
    {
        return [
            'required', 'string',
            function (string $attribute, mixed $value, callable $fail) {
                $split = OgEstimate::splitOrderReference(is_string($value) ? $value : null);

                if ($split['direction'] === null && $split['order_form_id'] === null) {
                    $fail('Choose IN, OUT or an order number.');
                }
            },
        ];
    }

    /**
     * Fold the select back into `direction` and `order_form_id` before validation, so
     * the two can never both arrive filled.
     */
    protected function mergeOrderReference(): void
    {
        $this->merge(OgEstimate::splitOrderReference($this->input('order_reference')));
    }
}

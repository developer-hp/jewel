<?php

namespace App\Http\Requests;

use App\Models\InternalStock;
use App\Models\InternalStockEntry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InternalStockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('entry') ? 'internal_stock_entry.edit' : 'internal_stock_entry.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_stock_id' => ['required', 'exists:internal_stocks,id'],
            'type' => ['required', Rule::in(array_keys(InternalStockEntry::TYPES))],
            'weight' => ['required', 'numeric', 'min:0.001', 'max:9999999.999', $this->withinBalance()],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * An Out may not take more than the pot holds.
     *
     * Checked against the balance with this entry's own contribution removed, so
     * editing an existing Out does not find itself in the way — otherwise raising
     * one from 100 to 120 would be measured against a balance that still has the
     * old 100 taken off it.
     */
    private function withinBalance(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($this->input('type') !== InternalStockEntry::TYPE_OUT) {
                return;
            }

            $stock = InternalStock::find($this->input('internal_stock_id'));

            if (! $stock) {
                return;
            }

            $available = $stock->balance();
            $editing = $this->route('entry');

            if ($editing && $editing->internal_stock_id === $stock->id) {
                $available -= $editing->signedWeight();
            }

            if (round((float) $value, 3) > round($available, 3)) {
                $fail(sprintf(
                    '%s holds %s GM. Taking out %s would overdraw it.',
                    $stock->name,
                    rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.'),
                ));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['internal_stock_id' => 'internal stock'];
    }
}

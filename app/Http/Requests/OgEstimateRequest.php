<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesOrderReference;
use Illuminate\Foundation\Http\FormRequest;

class OgEstimateRequest extends FormRequest
{
    use ResolvesOrderReference;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('og_estimate') ? 'og_estimate.edit' : 'og_estimate.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estimate_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:150'],
            'contact_no' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'sales_person_id' => ['required', 'exists:sales_persons,id'],

            'order_reference' => $this->orderReferenceRules(),
            'direction' => ['nullable', 'in:in,out'],
            'order_form_id' => ['nullable', 'exists:order_forms,id'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.sort_order' => ['required', 'integer', 'min:0'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.gross_weight' => ['required', 'numeric', 'min:0', 'max:999999.999'],
            'lines.*.net_weight' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            // A purity, so it cannot exceed a whole.
            'lines.*.touch_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one piece to the estimate.',
            'lines.*.touch_percent.max' => 'Purity is a percentage — it cannot be more than 100.',
            'lines.*.net_weight.min' => 'A line needs a net weight.',
        ];
    }

    /**
     * Blank rows are dropped, the way every other grid in this app already behaves.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeOrderReference();

        $lines = collect($this->input('lines', []))
            ->reject(fn ($row) => blank($row['description'] ?? null) && blank($row['net_weight'] ?? null))
            ->values()
            ->map(fn ($row, $i) => [
                'sort_order' => $i,
                'description' => $row['description'] ?? null,
                'gross_weight' => ($row['gross_weight'] ?? null) ?: 0,
                'net_weight' => ($row['net_weight'] ?? null) ?: 0,
                'touch_percent' => ($row['touch_percent'] ?? null) ?: 0,
                'rate' => ($row['rate'] ?? null) ?: 0,
            ])
            ->all();

        $this->merge(['lines' => $lines]);
    }
}

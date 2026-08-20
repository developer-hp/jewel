<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HallmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('hallmark') ? 'hallmark.edit' : 'hallmark.create');
    }

    /**
     * Note what is absent: lot_no, total pieces and total cost. The number is issued
     * by the model and the totals are derived from the lines.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hallmark_date' => ['required', 'date'],
            'cost_per_piece' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'gross_weight' => ['required', 'numeric', 'min:0', 'max:9999999.999'],

            'photo' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
            'remove_photo' => ['boolean'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_group_id' => ['required', 'exists:item_groups,id'],
            'lines.*.description' => ['required', 'string', 'max:150'],
            'lines.*.purity_id' => ['required', 'exists:purities,id'],
            'lines.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.pcs_per_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one line before saving.',
            'lines.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_photo' => $this->boolean('remove_photo'),
            'hallmark_date' => $this->input('hallmark_date') ?: today()->toDateString(),
            // Drop rows the user added but never filled in — a line with no item group
            // or no quantity is not a line, exactly as on the lot and stone repeaters.
            'lines' => collect($this->input('lines', []))
                ->filter(fn ($line) => is_array($line)
                    && filled($line['item_group_id'] ?? null)
                    && (int) ($line['quantity'] ?? 0) > 0)
                ->values()
                ->map(fn (array $line, int $index) => [
                    'item_group_id' => $line['item_group_id'],
                    'description' => trim((string) ($line['description'] ?? '')),
                    'purity_id' => $line['purity_id'] ?? null,
                    'supplier_id' => ($line['supplier_id'] ?? null) ?: null,
                    'quantity' => $line['quantity'],
                    'pcs_per_quantity' => ($line['pcs_per_quantity'] ?? null) ?: 1,
                    'sort_order' => $index,
                ])
                ->all(),
        ]);
    }
}

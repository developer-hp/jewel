<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ItemLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('lot') ? 'item_lot.edit' : 'item_lot.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lot_date' => ['required', 'date'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'metal_type_id' => ['nullable', 'exists:metal_types,id'],
            'purity_id' => ['nullable', 'exists:purities,id'],
            'making_charge_id' => ['nullable', 'exists:making_charges,id'],

            'total_gross_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'total_net_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'photo' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
            'remove_photo' => ['boolean'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_group_id' => ['required', 'exists:item_groups,id'],
            'lines.*.pieces' => ['required', 'integer', 'min:0', 'max:100000'],
            'lines.*.tags' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lines = collect($this->input('lines', []));

            // One line per group: the schema enforces it, but a duplicate should read
            // as a form error rather than a database exception.
            $groups = $lines->pluck('item_group_id')->filter();

            if ($groups->count() !== $groups->unique()->count()) {
                $validator->errors()->add('lines', 'Each item group may appear only once on a lot.');
            }

            $lot = $this->route('lot');

            if (! $lot) {
                return;
            }

            // Tags cannot be cut below what has already been entered against them.
            foreach ($lines as $index => $line) {
                $used = Item::where('item_lot_id', $lot->id)
                    ->where('item_group_id', $line['item_group_id'] ?? 0)
                    ->count();

                if ($used > (int) ($line['tags'] ?? 0)) {
                    $validator->errors()->add(
                        "lines.{$index}.tags",
                        "{$used} item(s) already exist for this group, so tags cannot be below {$used}."
                    );
                }
            }

            // A group already carrying items cannot simply be dropped from the lot.
            $removed = Item::where('item_lot_id', $lot->id)
                ->whereNotIn('item_group_id', $groups->all())
                ->distinct()
                ->pluck('item_group_id');

            if ($removed->isNotEmpty()) {
                $validator->errors()->add('lines', 'A group with items already entered cannot be removed from the lot.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_photo' => $this->boolean('remove_photo'),
            'lot_date' => $this->input('lot_date') ?: today()->toDateString(),
            // Drop blank repeater rows the user added but never filled in.
            'lines' => collect($this->input('lines', []))
                ->filter(fn ($line) => is_array($line) && filled($line['item_group_id'] ?? null))
                ->values()
                ->all(),
        ]);
    }
}

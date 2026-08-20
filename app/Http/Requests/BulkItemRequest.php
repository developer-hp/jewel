<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Models\ItemLot;
use App\Models\Purity;
use App\Models\StoneMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * The queued rows from the lot entry screen.
 *
 * Every row carries its own metal, purity and making charge — the header strip on
 * the screen only seeds the defaults — plus any stone and diamond lines. Errors are
 * keyed per row so a bad line can be pointed at, rather than the whole batch
 * failing anonymously.
 */
class BulkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('item.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Supplier is a batch-level fact: a lot comes from one karigar.
            'supplier_id' => ['nullable', 'exists:suppliers,id'],

            'rows' => ['required', 'array', 'min:1'],
            'rows.*.item_group_id' => ['required', 'exists:item_groups,id'],
            'rows.*.metal_type_id' => ['required', 'exists:metal_types,id'],
            'rows.*.purity_id' => ['required', 'exists:purities,id'],
            'rows.*.making_charge_id' => ['nullable', 'exists:making_charges,id'],
            'rows.*.name' => ['required', 'string', 'max:150'],
            'rows.*.huid' => ['nullable', 'string', 'max:20'],
            'rows.*.gross_weight' => ['required', 'numeric', 'gt:0', 'max:99999.999'],
            'rows.*.other_deduction' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],

            'rows.*.stones' => ['array'],
            'rows.*.stones.*.stone_master_id' => ['required', 'exists:stone_masters,id'],
            'rows.*.stones.*.pieces' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rows.*.stones.*.weight_carat' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'rows.*.stones.*.weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'rows.*.stones.*.rate' => ['nullable', 'numeric', 'min:0'],
            'rows.*.stones.*.deduct_from_gross' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Add at least one row before saving.',
            'rows.*.gross_weight.gt' => 'Gross weight must be more than zero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = collect($this->input('rows', []));

            $this->checkRows($validator, $rows);
            $this->checkQuota($validator, $rows);
        });
    }

    /**
     * Per-row checks that need more than a field rule.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function checkRows(Validator $validator, $rows): void
    {
        // One lookup for the batch rather than one per stone line.
        $masters = StoneMaster::whereIn(
            'id',
            $rows->pluck('stones')->filter()->flatten(1)->pluck('stone_master_id')->filter()->unique()
        )->get()->keyBy('id');

        foreach ($rows as $index => $row) {
            // The purity dropdown is filtered client-side; re-check server-side so a
            // stale or tampered row cannot pair 22K gold with the Silver metal type.
            if (filled($row['purity_id'] ?? null) && filled($row['metal_type_id'] ?? null)) {
                $belongs = Purity::whereKey($row['purity_id'])
                    ->where('metal_type_id', $row['metal_type_id'])
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add("rows.{$index}.purity_id", 'This purity does not belong to the selected metal type.');
                }
            }

            $gross = (float) ($row['gross_weight'] ?? 0);

            if ($gross <= 0) {
                continue;
            }

            // Net has to survive the stone and diamond deductions too. Doing it here
            // rather than leaning on ItemCalculator::recalculate(), which raises one
            // error keyed to gross_weight and cannot say which row is at fault.
            $deducted = collect($row['stones'] ?? [])
                ->filter(fn ($stone) => (bool) ($stone['deduct_from_gross'] ?? false))
                ->sum(fn ($stone) => $this->stoneGrams($stone));

            $less = (float) ($row['other_deduction'] ?? 0);
            $net = $gross - $deducted - $less;

            if ($net > 0) {
                continue;
            }

            // Point at whichever field is actually at fault: the manual deduction on
            // its own, or the combination once stones are counted.
            $field = $less >= $gross ? 'other_deduction' : 'gross_weight';

            $validator->errors()->add(
                "rows.{$index}.{$field}",
                sprintf('Net weight would be %s g — deductions exceed the gross weight.', number_format($net, 3))
            );
        }
    }

    /**
     * Grams a stone line contributes, mirroring ItemCalculator: carat is the stored
     * unit, so a grams-only entry converts back through it.
     *
     * @param  array<string, mixed>  $stone
     */
    private function stoneGrams(array $stone): float
    {
        $carat = (float) ($stone['weight_carat'] ?? 0);

        if ($carat <= 0) {
            $carat = ((float) ($stone['weight_grams'] ?? 0)) / Item::CARAT_TO_GRAM;
        }

        return round($carat * Item::CARAT_TO_GRAM, 4);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function checkQuota(Validator $validator, $rows): void
    {
        /** @var ItemLot|null $lot */
        $lot = $this->route('lot');

        if (! $lot) {
            return;
        }

        $remaining = $lot->remainingByGroup();

        foreach ($rows->groupBy('item_group_id') as $groupId => $groupRows) {
            if (! array_key_exists((int) $groupId, $remaining)) {
                $validator->errors()->add('rows', 'A row refers to an item group that is not part of this lot.');

                continue;
            }

            if ($groupRows->count() > $remaining[(int) $groupId]) {
                $name = $lot->lines->firstWhere('item_group_id', (int) $groupId)?->itemGroup?->name ?? 'group';

                $validator->errors()->add(
                    'rows',
                    "Only {$remaining[(int) $groupId]} tag(s) remain for {$name}, but {$groupRows->count()} row(s) were submitted."
                );
            }
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rows' => collect($this->input('rows', []))
                ->filter(fn ($row) => is_array($row) && filled($row['item_group_id'] ?? null))
                ->map(fn (array $row) => [
                    'item_group_id' => $row['item_group_id'],
                    'metal_type_id' => $row['metal_type_id'] ?? null,
                    'purity_id' => $row['purity_id'] ?? null,
                    // `??` before `?:` — the optional keys are absent, not empty, when
                    // the row did not carry them.
                    'making_charge_id' => ($row['making_charge_id'] ?? null) ?: null,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'huid' => filled($row['huid'] ?? null) ? strtoupper(trim($row['huid'])) : null,
                    'gross_weight' => $row['gross_weight'] ?? null,
                    'other_deduction' => ($row['other_deduction'] ?? null) ?: 0,
                    'stones' => $this->normalisedStones($row['stones'] ?? []),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Drop stone lines with no master chosen, exactly as the single item form does.
     *
     * @param  mixed  $stones
     * @return array<int, array<string, mixed>>
     */
    private function normalisedStones($stones): array
    {
        return collect(is_array($stones) ? $stones : [])
            ->filter(fn ($stone) => is_array($stone) && filled($stone['stone_master_id'] ?? null))
            ->map(fn (array $stone) => [
                'stone_master_id' => $stone['stone_master_id'],
                'pieces' => $stone['pieces'] ?? 0,
                'weight_carat' => $stone['weight_carat'] ?? 0,
                'weight_grams' => $stone['weight_grams'] ?? 0,
                'rate' => $stone['rate'] ?? null,
                'deduct_from_gross' => (bool) ($stone['deduct_from_gross'] ?? false),
            ])
            ->values()
            ->all();
    }
}

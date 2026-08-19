<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStone;
use App\Models\StoneMaster;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Every derived number on an item is produced here, server-side, from the raw
 * inputs (gross weight, other deduction, and the stone/diamond rows). Nothing
 * derived is ever accepted from a request.
 */
class ItemCalculator
{
    /**
     * Build the stone/diamond row attributes from submitted input, snapshotting
     * the unit and rate off the master so a later master edit cannot retroactively
     * change what this item is worth.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, StoneMaster>  $masters  keyed by id
     * @return array<int, array<string, mixed>>
     */
    public function buildStoneRows(array $rows, $masters): array
    {
        $built = [];

        foreach ($rows as $row) {
            $master = $masters->get((int) ($row['stone_master_id'] ?? 0));

            if (! $master) {
                continue;
            }

            $pieces = (int) ($row['pieces'] ?? 0);

            // Weight may be entered in either unit. Carat is the stored source of
            // truth, so a grams-only entry is converted back before anything else.
            $carat = round((float) ($row['weight_carat'] ?? 0), 3);
            $submittedGrams = (float) ($row['weight_grams'] ?? 0);

            if ($carat <= 0 && $submittedGrams > 0) {
                $carat = round($submittedGrams / Item::CARAT_TO_GRAM, 3);
            }

            $grams = round($carat * Item::CARAT_TO_GRAM, 4);

            // The rate may be overridden per row; fall back to the master's default.
            $rate = array_key_exists('rate', $row) && $row['rate'] !== null && $row['rate'] !== ''
                ? (float) $row['rate']
                : (float) $master->default_rate;

            $built[] = [
                'stone_master_id' => $master->id,
                'kind' => $master->kind,
                'pieces' => $pieces,
                'weight_carat' => $carat,
                'weight_grams' => $grams,
                'rate_unit' => $master->rate_unit,
                'rate' => round($rate, 2),
                'amount' => $this->lineAmount($master->rate_unit, $rate, $pieces, $carat, $grams),
                'deduct_from_gross' => (bool) ($row['deduct_from_gross'] ?? false),
            ];
        }

        return $built;
    }

    /**
     * What one stone/diamond row is worth, per its rate unit.
     */
    public function lineAmount(string $rateUnit, float $rate, int $pieces, float $carat, float $grams): float
    {
        return round(match ($rateUnit) {
            'piece' => $rate * $pieces,
            'carat' => $rate * $carat,
            'gram' => $rate * $grams,
            'fixed' => $rate,
            default => 0.0,
        }, 2);
    }

    /**
     * Recompute the item's deduction and net weight columns from its saved rows.
     *
     * Call inside the store/update transaction, after the child rows are synced.
     *
     * @throws ValidationException when the deductions swallow the whole piece
     */
    public function recalculate(Item $item): void
    {
        $rows = $item->itemStones()->get();

        $item->stone_weight_grams = $this->deductibleGrams($rows, StoneMaster::KIND_STONE);
        $item->diamond_weight_grams = $this->deductibleGrams($rows, StoneMaster::KIND_DIAMOND);

        $net = round(
            (float) $item->gross_weight
                - (float) $item->stone_weight_grams
                - (float) $item->diamond_weight_grams
                - (float) $item->other_deduction,
            3
        );

        if ($net <= 0) {
            // Over-deduction is the most common data-entry slip on a jadtar piece.
            throw ValidationException::withMessages([
                'gross_weight' => sprintf(
                    'Net weight would be %s g. Stone, diamond and other deductions (%s g) must be less than the gross weight.',
                    number_format($net, 3),
                    number_format(
                        (float) $item->stone_weight_grams
                            + (float) $item->diamond_weight_grams
                            + (float) $item->other_deduction,
                        3
                    ),
                ),
            ]);
        }

        $item->net_weight = $net;
        $item->save();
    }

    /**
     * @param  Collection<int, ItemStone>  $rows
     */
    private function deductibleGrams($rows, string $kind): float
    {
        return round(
            (float) $rows->where('kind', $kind)
                ->where('deduct_from_gross', true)
                ->sum('weight_grams'),
            3
        );
    }
}

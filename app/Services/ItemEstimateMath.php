<?php

namespace App\Services;

use App\Models\ItemEstimateLine;
use Illuminate\Support\Collection;

/**
 * What a jadtar estimate is worth.
 *
 * Its own service rather than methods on the model, for the same reason the OG
 * estimate has one: the screen computes these in JavaScript as the clerk types, the
 * PDF computes them again at print, and the tests check them a third time. One
 * statement of the rule keeps those three from drifting.
 *
 * Stone amounts are not restated here — they come from
 * ItemCalculator::lineAmount(), which already knows how each rate unit is read.
 */
class ItemEstimateMath
{
    /** Cash is settled to the nearest ten at the counter. */
    private const ROUNDING = 10;

    /**
     * Gross less whatever the stones and diamonds account for.
     */
    public function netWeight(ItemEstimateLine $line): float
    {
        return round((float) $line->gross_weight - $this->stoneWeight($line), 3);
    }

    public function stoneWeight(ItemEstimateLine $line): float
    {
        return round((float) $line->stones->sum('weight_grams'), 3);
    }

    /**
     * The jadtar column: what the stones and diamonds come to.
     */
    public function jadtar(ItemEstimateLine $line): float
    {
        return round((float) $line->stones->sum('amount'), 2);
    }

    /**
     * The metal. Rate is per ten grams, so 104.320 g at 132920 is 1,386,621.
     */
    public function metalValue(ItemEstimateLine $line): float
    {
        return round($this->netWeight($line) * (float) $line->rate / 10, 2);
    }

    /**
     * Labour, read through the type beside the amount.
     */
    public function labour(ItemEstimateLine $line): float
    {
        $amount = (float) $line->labour_amount;

        return round(match ($line->labour_type) {
            ItemEstimateLine::LABOUR_PERCENTAGE => $this->metalValue($line) * $amount / 100,
            ItemEstimateLine::LABOUR_FIXED => $amount,
            default => $this->netWeight($line) * $amount,
        }, 2);
    }

    /**
     * Everything that is neither metal nor labour: the stones and diamonds plus the
     * other charges.
     *
     * This is what the breakdown under the item on the printed form adds up to. Other
     * charges belong in that list — leaving them out was what stopped the printed
     * column reconciling with the line total.
     */
    public function charges(ItemEstimateLine $line): float
    {
        return round($this->jadtar($line) + (float) $line->oc_amount, 2);
    }

    public function lineTotal(ItemEstimateLine $line): float
    {
        return round(
            $this->metalValue($line) + $this->jadtar($line) + $this->labour($line) + (float) $line->oc_amount,
            2
        );
    }

    /**
     * The grid footer. Note the figure under RATE is the metal *value*, not a sum of
     * rates — adding rates together would mean nothing.
     *
     * @param  Collection<int, ItemEstimateLine>  $lines
     * @return object{gross: float, net: float, metal: float, jadtar: float, charges: float, labour: float, oc: float, total: float}
     */
    public function totals(Collection $lines): object
    {
        $sum = fn (callable $fn) => round($lines->sum($fn), 2);

        return (object) [
            'gross' => round((float) $lines->sum('gross_weight'), 3),
            'net' => round($lines->sum(fn (ItemEstimateLine $l) => $this->netWeight($l)), 3),
            'metal' => $sum(fn (ItemEstimateLine $l) => $this->metalValue($l)),
            'jadtar' => $sum(fn (ItemEstimateLine $l) => $this->jadtar($l)),
            'charges' => $sum(fn (ItemEstimateLine $l) => $this->charges($l)),
            'labour' => $sum(fn (ItemEstimateLine $l) => $this->labour($l)),
            'oc' => $sum(fn (ItemEstimateLine $l) => (float) $l->oc_amount),
            'total' => $sum(fn (ItemEstimateLine $l) => $this->lineTotal($l)),
        ];
    }

    /**
     * The printed box.
     *
     * The round-off lands on the **final** figure, after tax — so GST is charged on the
     * true amount and only the grand total is brought to a round ten.
     *
     * Each figure is rounded to the rupee for display and the round-off is the gap
     * between their sum and the rounded grand total, which means the printed column
     * always adds up exactly rather than hiding paise behind the scenes.
     *
     * @param  Collection<int, ItemEstimateLine>  $lines
     * @return object{amount: float, gst: float, round_off: float, total: float}
     */
    public function summary(Collection $lines, bool $gstEnabled, float $gstPercent): object
    {
        $amount = round($this->totals($lines)->total);
        $gst = $gstEnabled ? round($amount * $gstPercent / 100) : 0.0;

        $total = round(($amount + $gst) / self::ROUNDING) * self::ROUNDING;

        return (object) [
            'amount' => $amount,
            'gst' => $gst,
            'round_off' => round($total - $amount - $gst, 2),
            'total' => $total,
        ];
    }
}
